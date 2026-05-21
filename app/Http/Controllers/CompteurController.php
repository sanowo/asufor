<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompteurController extends Controller
{
    /**
     * Afficher la page de gestion des compteurs
     */
    public function index()
    {
        $quartiers = DB::table('quartier')->orderBy('NOM')->get();

        return Inertia::render('Compteurs/Index', [
            'quartiers' => $quartiers
        ]);
    }

    /**
     * Liste des compteurs (DataTable server-side)
     */
    public function list(Request $request)
    {
        $query = DB::table('compteur as co')
            ->leftJoin('client as c', 'co.ID_CLIENT', '=', 'c.ID_CLIENT')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->select(
                'co.ID_COMPTEUR',
                'co.NUM_COMPTEUR',
                'co.DATE_START',
                'co.ACTIF',
                'co.ID_CLIENT',
                'c.NUM_CLIENT',
                DB::raw('CONCAT(c.PRENOM, " ", c.NOM) as CLIENT'),
                'q.NOM as QUARTIER'
            );

        // Filtres
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('co.NUM_COMPTEUR', 'LIKE', "%{$search}%")
                  ->orWhere('c.NUM_CLIENT', 'LIKE', "%{$search}%")
                  ->orWhere('c.NOM', 'LIKE', "%{$search}%")
                  ->orWhere('c.PRENOM', 'LIKE', "%{$search}%");
            });
        }

        if ($request->quartier && $request->quartier !== '*') {
            $query->where('c.ID_QUARTIER', $request->quartier);
        }

        if ($request->status !== null && $request->status !== '*') {
            $query->where('co.ACTIF', $request->status);
        }

        $query->orderBy('co.DATE_START', 'DESC');

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 50;

        $total = $query->count();
        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data
        ]);
    }

    /**
     * Créer un nouveau compteur
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_client' => 'required|integer|exists:client,ID_CLIENT',
            'num_compteur' => 'required|string|max:50',
            'date_start' => 'required|date',
            'actif' => 'required|boolean'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier que ce numéro n'existe pas déjà pour ce client
            $exists = DB::table('compteur')
                ->where('ID_CLIENT', $validated['id_client'])
                ->where('NUM_COMPTEUR', trim($validated['num_compteur']))
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => ['num_compteur' => 'Ce numéro de compteur est déjà utilisé pour ce client']
                ], 422);
            }

            // Insérer le compteur
            $idCompteur = DB::table('compteur')->insertGetId([
                'ID_CLIENT' => $validated['id_client'],
                'NUM_COMPTEUR' => trim($validated['num_compteur']),
                'DATE_START' => $validated['date_start'],
                'ACTIF' => $validated['actif']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compteur enregistré avec succès',
                'id_compteur' => $idCompteur
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mettre à jour un compteur
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'num_compteur' => 'required|string|max:50',
            'actif' => 'required|boolean'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier que le compteur existe
            $compteur = DB::table('compteur')
                ->where('ID_COMPTEUR', $id)
                ->first();

            if (!$compteur) {
                return response()->json([
                    'errors' => ['general' => 'Compteur non trouvé']
                ], 404);
            }

            // Mettre à jour le compteur
            DB::table('compteur')
                ->where('ID_COMPTEUR', $id)
                ->update([
                    'NUM_COMPTEUR' => trim($validated['num_compteur']),
                    'ACTIF' => $validated['actif']
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compteur modifié avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un compteur
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            // Vérifier que le compteur existe
            $compteur = DB::table('compteur')
                ->where('ID_COMPTEUR', $id)
                ->first();

            if (!$compteur) {
                return response()->json([
                    'errors' => ['general' => 'Compteur non trouvé']
                ], 404);
            }

            // Vérifier qu'il n'y a pas de relevés associés
            $hasReleves = DB::table('index_v2')
                ->where('ID_COMPTEUR', $id)
                ->exists();

            if ($hasReleves) {
                return response()->json([
                    'errors' => ['general' => 'Impossible de supprimer ce compteur car il a des relevés associés']
                ], 422);
            }

            // Supprimer le compteur
            DB::table('compteur')
                ->where('ID_COMPTEUR', $id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compteur supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Obtenir les compteurs d'un client
     */
    public function getByClient($numClient)
    {
        $compteurs = DB::table('compteur as co')
            ->join('client as c', 'co.ID_CLIENT', '=', 'c.ID_CLIENT')
            ->where('c.NUM_CLIENT', $numClient)
            ->select(
                'co.ID_COMPTEUR',
                'co.NUM_COMPTEUR',
                'co.DATE_START',
                'co.ACTIF',
                'co.ID_CLIENT',
                'c.NUM_CLIENT',
                DB::raw('CONCAT(c.PRENOM, " ", c.NOM) as CLIENT')
            )
            ->orderBy('co.DATE_START', 'DESC')
            ->get();

        return response()->json($compteurs);
    }
}
