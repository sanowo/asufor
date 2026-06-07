<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PretController extends Controller
{
    /**
     * Afficher la page de gestion des prêts
     */
    public function index()
    {
        return Inertia::render('Prets/Index');
    }

    /**
     * Liste des prêts (DataTable server-side)
     */
    public function list(Request $request)
    {
        $query = DB::table('pret as p')
            ->leftJoin('client as c', 'p.ID_CLIENT', '=', 'c.ID_CLIENT')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->select(
                'p.ID_PRET',
                'p.NUM_CLIENT',
                'p.MONTANT as MONTANT_PRET',
                'p.MOTIF',
                'p.DATE as DATE_PRET',
                'p.PAYER',
                'p.IMPAYER',
                'p.ACTIF',
                'p.TRANCHE as MONTANT_TRANCHE',
                'p.MENSUALITE',
                DB::raw('CONCAT(c.PRENOM, " ", c.NOM) as CLIENT'),
                'q.NOM as QUARTIER'
            );

        // Filtres
        if ($request->filled('id_pret')) {
            $query->where('p.ID_PRET', $request->id_pret);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('p.NUM_CLIENT', 'LIKE', "%{$search}%")
                  ->orWhere('c.NOM', 'LIKE', "%{$search}%")
                  ->orWhere('c.PRENOM', 'LIKE', "%{$search}%")
                  ->orWhere('p.MOTIF', 'LIKE', "%{$search}%");
            });
        }

        if ($request->status !== null && $request->status !== '*') {
            $query->where('p.ACTIF', $request->status);
        }

        if ($request->date_start) {
            $query->whereDate('p.DATE', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('p.DATE', '<=', $request->date_end);
        }

        $query->orderBy('p.DATE', 'DESC');

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 50;

        $total = $query->count();
        $data = $query->skip($start)->take($length)->get();

        // Calcul des statistiques
        $stats = DB::table('pret as p')
            ->leftJoin('client as c', 'p.ID_CLIENT', '=', 'c.ID_CLIENT');

        // Appliquer les mêmes filtres pour les stats
        if ($request->search) {
            $search = $request->search;
            $stats->where(function($q) use ($search) {
                $q->where('p.NUM_CLIENT', 'LIKE', "%{$search}%")
                  ->orWhere('c.NOM', 'LIKE', "%{$search}%")
                  ->orWhere('c.PRENOM', 'LIKE', "%{$search}%")
                  ->orWhere('p.MOTIF', 'LIKE', "%{$search}%");
            });
        }

        if ($request->status !== null && $request->status !== '*') {
            $stats->where('p.ACTIF', $request->status);
        }

        if ($request->date_start) {
            $stats->whereDate('p.DATE', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $stats->whereDate('p.DATE', '<=', $request->date_end);
        }

        $meta = $stats->selectRaw('
            COUNT(*) as count,
            SUM(p.MONTANT) as total_montant,
            SUM(p.PAYER) as total_paye,
            SUM(p.IMPAYER) as total_impaye
        ')->first();

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
            'meta' => $meta
        ]);
    }

    /**
     * Créer un nouveau prêt
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'num_client' => 'required|string|exists:client,NUM_CLIENT',
            'montant' => 'required|numeric|min:0',
            'motif' => 'required|string|max:255',
            'date_pret' => 'required|date',
            'tranche' => 'required|numeric|min:0',
            'mensualite' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();

        try {
            // Récupérer ID_CLIENT
            $client = DB::table('client')
                ->where('NUM_CLIENT', $validated['num_client'])
                ->first();

            if (!$client) {
                return response()->json([
                    'errors' => ['num_client' => 'Client non trouvé']
                ], 404);
            }

            // Insérer le prêt
            $idPret = DB::table('pret')->insertGetId([
                'ID_CLIENT' => $client->ID_CLIENT,
                'NUM_CLIENT' => $validated['num_client'],
                'MONTANT' => $validated['montant'],
                'MOTIF' => $validated['motif'],
                'DATE' => $validated['date_pret'],
                'PAYER' => 0,
                'IMPAYER' => $validated['montant'],
                'TRANCHE' => $validated['tranche'],
                'MENSUALITE' => $validated['mensualite'],
                'ACTIF' => 1
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt enregistré avec succès',
                'id_pret' => $idPret
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour un prêt
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
            'tranche' => 'required|numeric|min:0',
            'mensualite' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier que le prêt existe
            $pret = DB::table('pret')
                ->where('ID_PRET', $id)
                ->first();

            if (!$pret) {
                return response()->json([
                    'errors' => ['general' => 'Prêt non trouvé']
                ], 404);
            }

            // Recalculer IMPAYER en fonction du nouveau montant
            $nouveauImpaye = $validated['montant'] - $pret->PAYER;

            // Mettre à jour le prêt
            DB::table('pret')
                ->where('ID_PRET', $id)
                ->update([
                    'MONTANT' => $validated['montant'],
                    'TRANCHE' => $validated['tranche'],
                    'MENSUALITE' => $validated['mensualite'],
                    'IMPAYER' => $nouveauImpaye
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt modifié avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Suspendre un prêt (ACTIF = 0)
     */
    public function suspend($id)
    {
        DB::beginTransaction();

        try {
            // Vérifier que le prêt existe
            $pret = DB::table('pret')
                ->where('ID_PRET', $id)
                ->first();

            if (!$pret) {
                return response()->json([
                    'errors' => ['general' => 'Prêt non trouvé']
                ], 404);
            }

            // Suspendre le prêt
            DB::table('pret')
                ->where('ID_PRET', $id)
                ->update(['ACTIF' => 0]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt suspendu avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Réactiver un prêt (ACTIF = 1)
     */
    public function reactivate($id)
    {
        DB::beginTransaction();

        try {
            // Vérifier que le prêt existe
            $pret = DB::table('pret')
                ->where('ID_PRET', $id)
                ->first();

            if (!$pret) {
                return response()->json([
                    'errors' => ['general' => 'Prêt non trouvé']
                ], 404);
            }

            // Réactiver le prêt
            DB::table('pret')
                ->where('ID_PRET', $id)
                ->update(['ACTIF' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt réactivé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un prêt
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            // Vérifier que le prêt existe
            $pret = DB::table('pret')
                ->where('ID_PRET', $id)
                ->first();

            if (!$pret) {
                return response()->json([
                    'errors' => ['general' => 'Prêt non trouvé']
                ], 404);
            }

            // Vérifier qu'il n'y a pas eu de paiements
            if ($pret->PAYER > 0) {
                return response()->json([
                    'errors' => ['general' => 'Impossible de supprimer ce prêt car des paiements ont été effectués']
                ], 422);
            }

            // Supprimer le prêt
            DB::table('pret')
                ->where('ID_PRET', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prêt supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }
}
