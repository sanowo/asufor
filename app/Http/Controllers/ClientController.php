<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index()
    {
        $quartiers = DB::table('quartier')->get();
        $usages = DB::table('typeusage')->get();
        return Inertia::render('Clients/Index', ['quartiers' => $quartiers, 'usages' => $usages]);
    }

    public function list(Request $request)
    {
        $query = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM',
                DB::raw('(SELECT COUNT(*) FROM compteur WHERE ID_CLIENT = c.ID_CLIENT) as NB_COMPTEURS'),
                DB::raw('(SELECT SUM(IMPAYE) FROM facture_v2 WHERE NUM_CLIENT = c.NUM_CLIENT) as TOTAL_IMPAYE'));

        // Apply view filter
        $view = $request->input('view', 'all');
        switch ($view) {
            case 'actifs':
                $query->where('c.STATUT', 1);
                break;
            case 'suspendus':
                $query->where('c.STATUT', 0);
                break;
            case 'retard':
                // Clients with unpaid invoices past due date
                $query->whereExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('facture_v2 as f')
                      ->whereColumn('f.NUM_CLIENT', 'c.NUM_CLIENT')
                      ->where('f.IMPAYE', '>', 0)
                      ->where('f.DATEECH', '<', DB::raw('CURDATE()'));
                });
                break;
            case 'social':
                // Clients with social usage type (typically ID_USAGE = 3 for social)
                $query->whereExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('typeusage as u2')
                      ->whereColumn('u2.ID_USAGE', 'c.USED')
                      ->where('u2.NOM', 'like', '%social%');
                });
                break;
            case 'inactifs':
                // Clients without subscriptions or meters
                $query->where(function($q) {
                    $q->whereNull('c.ABONNEMENT')
                      ->orWhere('c.ABONNEMENT', 0);
                })->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('compteur')
                      ->whereColumn('compteur.ID_CLIENT', 'c.ID_CLIENT')
                      ->where('compteur.ACTIF', 1);
                });
                break;
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('c.NUM_CLIENT', 'like', "%$search%")
                  ->orWhere('c.NOM', 'like', "%$search%")
                  ->orWhere('c.PRENOM', 'like', "%$search%")
                  ->orWhere('c.TELEPHONE', 'like', "%$search%");
            });
        }

        if ($request->filled('quartier') && $request->quartier !== '*') {
            $query->where('c.ID_QUARTIER', $request->quartier);
        }

        if ($request->filled('usage') && $request->usage !== '*') {
            $query->where('c.USED', $request->usage);
        }

        if ($request->filled('statut') && $request->statut !== '*') {
            $query->where('c.STATUT', $request->statut);
        }

        $total = $query->count();
        $clients = $query->orderBy('c.NOM', 'asc')->skip($request->input('start', 0))->take($request->input('length', 50))->get();

        $metaQuery = DB::table('client as c');
        if ($request->filled('search')) {
            $search = $request->search;
            $metaQuery->where(function($q) use ($search) {
                $q->where('c.NUM_CLIENT', 'like', "%$search%")
                  ->orWhere('c.NOM', 'like', "%$search%")
                  ->orWhere('c.PRENOM', 'like', "%$search%");
            });
        }

        $meta = ['count' => $metaQuery->count(), 'actifs' => (clone $metaQuery)->where('c.STATUT', 1)->count(), 'suspendus' => (clone $metaQuery)->where('c.STATUT', 0)->count()];

        return response()->json(['data' => ['result' => $clients, 'meta' => $meta], 'recordsTotal' => $total, 'recordsFiltered' => $total]);
    }

    public function show($num_client)
    {
        $client = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM')
            ->where('c.NUM_CLIENT', $num_client)->first();

        if (!$client) return response()->json(['error' => 'Client non trouvé'], 404);

        $compteurs = DB::table('compteur as co')
            ->where('co.ID_CLIENT', $client->ID_CLIENT)
            ->select(
                'co.*',
                DB::raw('(SELECT r.RELEVE FROM releve r WHERE r.ID_COMPTEUR = co.ID_COMPTEUR ORDER BY r.DATE_INDEX DESC LIMIT 1) as LAST_RELEVE'),
                DB::raw('(SELECT r.DATE_INDEX FROM releve r WHERE r.ID_COMPTEUR = co.ID_COMPTEUR ORDER BY r.DATE_INDEX DESC LIMIT 1) as LAST_RELEVE_DATE')
            )
            ->get();
        $factures = DB::table('facture_v2')->where('NUM_CLIENT', $num_client)->where('IMPAYE', '>', 0)->orderBy('DATEFACTURE', 'desc')->limit(10)->get();
        $prets = DB::table('pret')->where('NUM_CLIENT', $num_client)->where('ACTIF', 1)->get();

        return response()->json(['client' => $client, 'compteurs' => $compteurs, 'factures' => $factures, 'prets' => $prets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['num_client' => 'required|string|unique:client,NUM_CLIENT', 'nom' => 'required|string|max:255', 'prenom' => 'required|string|max:255', 'telephone' => 'nullable|string|max:20', 'id_quartier' => 'required|integer', 'used' => 'required|integer', 'abonnement' => 'nullable|numeric', 'statut' => 'required|integer|in:0,1']);

        DB::beginTransaction();
        try {
            DB::table('client')->insert(['NUM_CLIENT' => $validated['num_client'], 'NOM' => strtoupper($validated['nom']), 'PRENOM' => ucfirst($validated['prenom']), 'TELEPHONE' => $validated['telephone'] ?? null, 'ID_QUARTIER' => $validated['id_quartier'], 'USED' => $validated['used'], 'ABONNEMENT' => $validated['abonnement'] ?? 0, 'STATUT' => $validated['statut'], 'DATE_INSCRIPTION' => now()]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Client créé avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    public function update(Request $request, $num_client)
    {
        $validated = $request->validate(['nom' => 'required|string|max:255', 'prenom' => 'required|string|max:255', 'telephone' => 'nullable|string|max:20', 'id_quartier' => 'required|integer', 'used' => 'required|integer', 'abonnement' => 'nullable|numeric', 'statut' => 'required|integer|in:0,1']);

        DB::beginTransaction();
        try {
            DB::table('client')->where('NUM_CLIENT', $num_client)->update(['NOM' => strtoupper($validated['nom']), 'PRENOM' => ucfirst($validated['prenom']), 'TELEPHONE' => $validated['telephone'] ?? null, 'ID_QUARTIER' => $validated['id_quartier'], 'USED' => $validated['used'], 'ABONNEMENT' => $validated['abonnement'] ?? 0, 'STATUT' => $validated['statut']]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Client mis à jour avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    public function destroy($num_client)
    {
        $client = DB::table('client')->where('NUM_CLIENT', $num_client)->first();
        if (!$client) return response()->json(['errors' => ['general' => 'Client non trouvé']], 404);

        $hasFactures  = DB::table('facture_v2')->where('NUM_CLIENT', $num_client)->exists();
        $hasCompteurs = DB::table('compteur')->where('ID_CLIENT', $client->ID_CLIENT)->exists();

        if ($hasFactures || $hasCompteurs) {
            return response()->json(['errors' => ['general' => 'Impossible de supprimer: le client a des factures ou des compteurs']], 400);
        }

        DB::table('client')->where('NUM_CLIENT', $num_client)->delete();
        return response()->json(['success' => true, 'message' => 'Client supprimé avec succès']);
    }

    public function getPrets($num_client)
    {
        $client = DB::table('client')->where('NUM_CLIENT', $num_client)->first();
        if (!$client) return response()->json(['error' => 'Client non trouvé'], 404);

        $prets = DB::table('pret')->where('NUM_CLIENT', $num_client)->where('ACTIF', 1)
            ->select('ID_PRET', 'DATE_PRET', 'MONTANT_PRET', 'MONTANT_TRANCHE', DB::raw('PAYER as RECU'), DB::raw('IMPAYER as RESTANT'))->get();

        return response()->json(['NUM_CLIENT' => $client->NUM_CLIENT, 'PRENOM' => $client->PRENOM, 'NOM' => $client->NOM, 'prets' => $prets]);
    }
}
