<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    /**
     * Print single or multiple factures
     */
    public function printFactures(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters = $request->input('filters', []);
            $factureNumbers = app(\App\Http\Controllers\FactureController::class)->getFacturesFromFilters($filters);
        } else {
            $validated = $request->validate([
                'facture_numbers' => 'required|array',
                'facture_numbers.*' => 'required|string'
            ]);
            $factureNumbers = $validated['facture_numbers'];
        }

        $factures = [];
        foreach ($factureNumbers as $numero) {
            $facture = $this->getFactureData($numero);
            if ($facture) {
                $factures[] = $facture;
            }
        }

        if (empty($factures)) {
            return response()->json(['error' => 'Aucune facture trouvée'], 404);
        }

        $parametres = $this->getParametres();

        $pdf = PDF::loadView('pdf.factures', [
            'factures' => $factures,
            'parametres' => $parametres
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('factures_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Print bon de coupure (disconnection notices)
     */
    public function printBonsCoupure(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters = $request->input('filters', []);
            $factureNumbers = app(\App\Http\Controllers\FactureController::class)->getFacturesFromFilters($filters);

            $factures = DB::table('facture_v2 as f')
                ->leftJoin('client as c', 'f.NUM_CLIENT', '=', 'c.NUM_CLIENT')
                ->leftJoin('quartier as q', 'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
                ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
                ->whereIn('f.NUMERO_FACTURE', $factureNumbers)
                ->where('f.REGLE', 0)
                ->select('f.*', 'c.NOM', 'c.PRENOM', 'c.TELEPHONE', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM')
                ->get();
        } else {
            $validated = $request->validate([
                'facture_numbers' => 'required|array',
                'facture_numbers.*' => 'required|string'
            ]);

            $factures = DB::table('facture_v2 as f')
                ->leftJoin('client as c', 'f.NUM_CLIENT', '=', 'c.NUM_CLIENT')
                ->leftJoin('quartier as q', 'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
                ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
                ->whereIn('f.NUMERO_FACTURE', $validated['facture_numbers'])
                ->where('f.REGLE', 0)
                ->select('f.*', 'c.NOM', 'c.PRENOM', 'c.TELEPHONE', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM')
                ->get();
        }

        if ($factures->isEmpty()) {
            return response()->json(['error' => 'Aucun bon de coupure trouvé'], 404);
        }

        $parametres = $this->getParametres();

        $pdf = PDF::loadView('pdf.bons-coupure', [
            'factures' => $factures,
            'parametres' => $parametres
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('bons_coupure_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Print fiche de relevé (meter reading forms)
     */
    public function printFicheReleve(Request $request)
    {
        set_time_limit(120);

        $validated = $request->validate([
            'quartier'     => 'required',
            'client_usage' => 'nullable|string',
            'client'       => 'nullable|string',
            'date_start'   => 'nullable|date',
            'date_end'     => 'nullable|date',
        ]);

        // Calculer la plage de dates : défaut = 30 derniers jours
        $dateEnd   = $validated['date_end']   ?? date('Y-m-d');
        $dateStart = $validated['date_start'] ?? date('Y-m-d', strtotime('-29 days'));

        // Dernier relevé par compteur dans la période sélectionnée
        $lastReleve = DB::table('releve as r')
            ->join(
                DB::raw("(SELECT ID_COMPTEUR, MAX(DATE_INDEX) as MAX_DATE
                          FROM releve
                          WHERE DATE_INDEX BETWEEN '{$dateStart}' AND '{$dateEnd}'
                          GROUP BY ID_COMPTEUR) as lr"),
                function ($join) {
                    $join->on('r.ID_COMPTEUR', '=', 'lr.ID_COMPTEUR')
                         ->on('r.DATE_INDEX', '=', 'lr.MAX_DATE');
                }
            )
            ->select('r.ID_COMPTEUR', 'r.RELEVE as INDEX_COMPTEUR', 'r.DATE_INDEX');

        $query = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->leftJoin('compteur as cpt', 'c.ID_CLIENT', '=', 'cpt.ID_CLIENT')
            ->leftJoinSub($lastReleve, 'lr', 'cpt.ID_COMPTEUR', '=', 'lr.ID_COMPTEUR')
            ->where('c.STATUT', 1)
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM',
                     'cpt.ID_COMPTEUR', 'cpt.NUM_COMPTEUR',
                     'lr.INDEX_COMPTEUR', 'lr.DATE_INDEX as DATE_DERNIER_RELEVE',
                     DB::raw('CONCAT(c.NOM, " ", c.PRENOM) as CLIENT'));

        if ($validated['quartier'] !== '*') {
            $query->where('c.ID_QUARTIER', $validated['quartier']);
        }

        if (!empty($validated['client_usage'])) {
            $query->where('c.USED', $validated['client_usage']);
        }

        if (!empty($validated['client'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('c.NUM_CLIENT', 'LIKE', '%' . $validated['client'] . '%')
                  ->orWhere('c.NOM', 'LIKE', '%' . $validated['client'] . '%')
                  ->orWhere('c.PRENOM', 'LIKE', '%' . $validated['client'] . '%');
            });
        }

        $clients = $query->orderBy('q.NOM')->orderBy('c.NOM')->get();

        if ($clients->isEmpty()) {
            return response()->json(['error' => 'Aucun client trouvé pour cette période et ces filtres'], 404);
        }

        $parametres = $this->getParametres();
        $quartier = null;

        if ($validated['quartier'] !== '*') {
            $quartier = DB::table('quartier')->where('ID_QUARTIER', $validated['quartier'])->first();
        }

        $pdf = PDF::loadView('pdf.fiche-releve', [
            'clients'    => $clients,
            'parametres' => $parametres,
            'quartier'   => $quartier,
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('fiche_releve_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Print caisse operations journal
     */
    public function printOperations(Request $request)
    {
        $validated = $request->validate([
            'date_start' => 'required|date',
            'date_end' => 'required|date',
            'status' => 'nullable|string',
            'type_operation' => 'nullable|string'
        ]);

        $query = DB::table('operation as o')
            ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
            ->leftJoin('utilisateur as u', 'o.ID_USER', '=', 'u.ID_USER')
            ->whereBetween('o.DATE_OPERATION', [$validated['date_start'], $validated['date_end']])
            ->select('o.*', 't.LIBELLE as TYPE_LABEL', 't.IS_REVENUE', 'u.NOM as USER_NAME');

        if (!empty($validated['status']) && $validated['status'] !== '*') {
            $query->where('o.STATUS', $validated['status']);
        }

        if (!empty($validated['type_operation']) && $validated['type_operation'] !== '*') {
            $query->where('o.ID_TYPEOPERATION', $validated['type_operation']);
        }

        $operations = $query->orderBy('o.DATE_OPERATION', 'desc')->get();

        if ($operations->isEmpty()) {
            return response()->json(['error' => 'Aucune opération trouvée'], 404);
        }

        // Calculate totals
        $total_credit = $operations->where('IS_REVENUE', 1)->sum('MONTANT');
        $total_debit = $operations->where('IS_REVENUE', 0)->sum('MONTANT');
        $solde = $total_credit - $total_debit;

        $parametres = $this->getParametres();

        $pdf = PDF::loadView('pdf.operations-caisse', [
            'operations' => $operations,
            'parametres' => $parametres,
            'date_start' => $validated['date_start'],
            'date_end' => $validated['date_end'],
            'total_credit' => $total_credit,
            'total_debit' => $total_debit,
            'solde' => $solde
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('operations_' . $validated['date_start'] . '_' . $validated['date_end'] . '.pdf');
    }

    /**
     * Print liste des clients suspendus
     */
    public function printClientsSuspendus(Request $request)
    {
        $validated = $request->validate([
            'quartier' => 'nullable|integer'
        ]);

        $query = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->where('c.STATUT', 0)
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_NOM',
                DB::raw('(SELECT COUNT(*) FROM compteur WHERE ID_CLIENT = c.ID_CLIENT) as NB_COMPTEURS'),
                DB::raw('(SELECT SUM(IMPAYE) FROM facture_v2 WHERE NUM_CLIENT = c.NUM_CLIENT) as TOTAL_IMPAYE'));

        if (isset($validated['quartier'])) {
            $query->where('c.ID_QUARTIER', $validated['quartier']);
        }

        $clients = $query->orderBy('q.NOM')->orderBy('c.NOM')->get();

        if ($clients->isEmpty()) {
            return response()->json(['error' => 'Aucun client suspendu trouvé'], 404);
        }

        $total_impaye = $clients->sum('TOTAL_IMPAYE');
        $total_compteurs = $clients->sum('NB_COMPTEURS');

        $parametres = $this->getParametres();

        $pdf = PDF::loadView('pdf.clients-suspendus', [
            'clients' => $clients,
            'parametres' => $parametres,
            'total_impaye' => $total_impaye,
            'total_compteurs' => $total_compteurs
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('clients_suspendus_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Print liste des factures (table format)
     */
    public function printFacturesList(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters = $request->input('filters', []);
            $factureNumbers = app(\App\Http\Controllers\FactureController::class)->getFacturesFromFilters($filters);
        } else {
            $validated = $request->validate([
                'facture_numbers' => 'required|array',
                'facture_numbers.*' => 'required|string'
            ]);
            $factureNumbers = $validated['facture_numbers'];
        }

        $factures = DB::table('facture_v2 as f')
            ->leftJoin('client as c', 'f.NUM_CLIENT', '=', 'c.NUM_CLIENT')
            ->leftJoin('quartier as q', 'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->whereIn('f.NUMERO_FACTURE', $factureNumbers)
            ->select('f.*',
                DB::raw("CONCAT(c.NOM, ' ', c.PRENOM) as CLIENT"),
                'q.NOM as QUARTIER',
                DB::raw('(SELECT COALESCE(SUM(TOTAL), 0) FROM facture_v2 WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) +
                        (SELECT COALESCE(SUM(TOTAL), 0) FROM facture_pret WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) as MONTANT_TOTAL'),
                DB::raw('(SELECT COALESCE(SUM(IMPAYE), 0) FROM facture_v2 WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) +
                        (SELECT COALESCE(SUM(IMPAYE), 0) FROM facture_pret WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) as RESTANT'))
            ->orderBy('f.DATEFACTURE', 'desc')
            ->get();

        if ($factures->isEmpty()) {
            return response()->json(['error' => 'Aucune facture trouvée'], 404);
        }

        $total = $factures->sum('MONTANT_TOTAL');
        $restant = $factures->sum('RESTANT');
        $encaisse = $total - $restant;

        $parametres = $this->getParametres();

        $pdf = PDF::loadView('pdf.factures-list', [
            'factures' => $factures,
            'parametres' => $parametres,
            'total' => $total,
            'encaisse' => $encaisse,
            'restant' => $restant
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('liste_factures_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Get full facture data with related information
     */
    private function getFactureData($numero_facture)
    {
        $facture = DB::table('facture_v2 as f')
            ->leftJoin('client as c', 'f.NUM_CLIENT', '=', 'c.NUM_CLIENT')
            ->leftJoin('quartier as q', 'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->leftJoin('releve as r', 'f.ID_RELEVE', '=', 'r.ID_INDEX')
            ->leftJoin('compteur as cpt', 'r.ID_COMPTEUR', '=', 'cpt.ID_COMPTEUR')
            ->where('f.NUMERO_FACTURE', $numero_facture)
            ->select('f.*',
                     'c.NOM', 'c.PRENOM', 'c.TELEPHONE', 'c.NUM_CLIENT',
                     'q.NOM as QUARTIER',
                     'u.NOM as USAGE_NOM',
                     'r.ANCIEN_INDEX',
                     'r.RELEVE as NOUVEL_INDEX',
                     DB::raw('(r.RELEVE - r.ANCIEN_INDEX) as CONSOMMATION'),
                     'cpt.NUM_COMPTEUR')
            ->first();

        if (!$facture) {
            return null;
        }

        // Get active loans for this client
        $prets = DB::table('pret')
            ->where('NUM_CLIENT', $facture->NUM_CLIENT)
            ->where('ACTIF', 1)
            ->where('IMPAYER', '>', 0)
            ->get();

        // Get reduction applied to this facture (if any)
        $reduction = DB::table('facture_reduction as fr')
            ->leftJoin('reduction as r', 'fr.ID_REDUCTION', '=', 'r.ID_REDUCTION')
            ->where('fr.NUM_FACTURE', $numero_facture)
            ->select('fr.*', 'r.LIBELLE as REDUCTION_LIBELLE', 'r.DESCRIPTION as REDUCTION_DESCRIPTION')
            ->first();

        return [
            'facture' => $facture,
            'prets' => $prets,
            'reduction' => $reduction
        ];
    }

    /**
     * Get system parameters (company info)
     */
    private function getParametres()
    {
        $params = DB::table('parametres')
            ->whereIn('TYPE', ['entreprise', 'adresse', 'telephone'])
            ->pluck('VALUE', 'TYPE')
            ->toArray();

        return [
            'entreprise' => $params['entreprise'] ?? 'ASUFOR',
            'adresse' => $params['adresse'] ?? '',
            'telephone' => $params['telephone'] ?? ''
        ];
    }
}
