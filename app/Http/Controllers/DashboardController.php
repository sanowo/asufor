<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalClients       = DB::table('client')->count();
        $clientsActifs      = DB::table('client')->where('STATUT', 0)->count();
        $clientsSuspendus   = DB::table('client')->where('STATUT', 1)->count();
        $totalCompteurs     = DB::table('compteur')->count();
        $compteursFonctionnels = DB::table('compteur')->where('ACTIF', 1)->count();
        $pretsActifs        = DB::table('pret')->where('ACTIF', 1)->count();
        $totalPretsImpaye   = DB::table('pret')->where('ACTIF', 1)->sum('IMPAYER');

        return Inertia::render('Dashboard', [
            'global_stats' => [
                'total_clients'          => $totalClients,
                'clients_actifs'         => $clientsActifs,
                'clients_suspendus'      => $clientsSuspendus,
                'total_compteurs'        => $totalCompteurs,
                'compteurs_fonctionnels' => $compteursFonctionnels,
                'prets_actifs'           => $pretsActifs,
                'total_prets_impaye'     => (float) $totalPretsImpaye,
            ],
        ]);
    }

    public function stats(Request $request)
    {
        $request->validate([
            'date_start' => 'nullable|date_format:Y-m-d',
            'date_end'   => 'nullable|date_format:Y-m-d',
        ]);

        $dateStart = $request->filled('date_start')
            ? $request->date_start
            : now()->subDays(29)->format('Y-m-d');

        $dateEnd = $request->filled('date_end')
            ? $request->date_end
            : now()->format('Y-m-d');

        // ── Relevés ──────────────────────────────────────────────────────────
        $releveStats = DB::table('releve as r')
            ->join('compteur as c', 'r.ID_COMPTEUR', '=', 'c.ID_COMPTEUR')
            ->join('client as cl', 'c.ID_CLIENT', '=', 'cl.ID_CLIENT')
            ->join('facture_v2 as f', 'r.ID_INDEX', '=', 'f.ID_RELEVE')
            ->whereDate('r.DATE_INDEX', '>=', $dateStart)
            ->whereDate('r.DATE_INDEX', '<=', $dateEnd)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(r.CONSOMMATION),0) AS consommation, COALESCE(SUM(f.TOTAL),0) AS total')
            ->first();

        // ── Factures (UNION facture_v2 + facture_pret) ───────────────────────
        $mainQuery = "
            SELECT
                u.NUMERO_FACTURE,
                SUM(u.TOTAL)            AS TOTAL,
                SUM(u.RECU)             AS TOTAL_RECU,
                MAX(u.REGLE)            AS REGLE,
                MAX(u.REGLEMENT_TYPE)   AS REGLEMENT_TYPE
            FROM (
                SELECT NUMERO_FACTURE, TOTAL, RECU, REGLE, REGLEMENT_TYPE
                FROM facture_v2
                WHERE DATE_ECHEANCE >= ? AND DATE_ECHEANCE <= ?

                UNION ALL

                SELECT fp.NUMERO_FACTURE, fp.TOTAL, fp.RECU, fp.REGLE, fp.REGLEMENT_TYPE
                FROM facture_pret fp
                INNER JOIN facture_v2 fv ON fp.NUMERO_FACTURE = fv.NUMERO_FACTURE
                WHERE fv.DATE_ECHEANCE >= ? AND fv.DATE_ECHEANCE <= ?
            ) u
            GROUP BY u.NUMERO_FACTURE
        ";

        $factureStats = DB::selectOne("
            SELECT
                COUNT(*)  AS count,
                COALESCE(SUM(TOTAL), 0) AS total,
                COALESCE(SUM(TOTAL_RECU), 0) AS total_recu,
                COALESCE(SUM(CASE WHEN REGLEMENT_TYPE = 'GRACIER'       THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_gracie,
                COUNT(CASE WHEN REGLEMENT_TYPE = 'GRACIER' THEN 1 END)                                         AS nb_gracie,
                COALESCE(SUM(CASE WHEN REGLEMENT_TYPE = 'RECOUVREMENT'  THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_recouvrement,
                COUNT(CASE WHEN REGLEMENT_TYPE = 'RECOUVREMENT' THEN 1 END)                                     AS nb_recouvrement,
                COALESCE(SUM(CASE WHEN REGLE = 0 AND REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_impaye,
                COUNT(CASE WHEN REGLE = 0 AND REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN 1 END)      AS nb_impaye
            FROM ($mainQuery) AS agg
        ", [$dateStart, $dateEnd, $dateStart, $dateEnd]);

        // ── Caisse ───────────────────────────────────────────────────────────
        $caisseStats = DB::table('operation as o')
            ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
            ->whereDate('o.DATE_OPERATION', '>=', $dateStart)
            ->whereDate('o.DATE_OPERATION', '<=', $dateEnd)
            ->selectRaw("
                COUNT(*) AS count,
                IFNULL(SUM(CASE WHEN t.EFFECT = '+' AND o.STATUS != 'ANNULE' THEN o.MONTANT ELSE 0 END), 0) AS total_credit,
                IFNULL(SUM(CASE WHEN t.EFFECT = '-' AND o.STATUS != 'ANNULE' THEN o.MONTANT ELSE 0 END), 0) AS total_debit,
                IFNULL(SUM(CASE WHEN o.STATUS = 'ATTENTE' THEN o.MONTANT ELSE 0 END), 0) AS total_attente
            ")
            ->first();

        return response()->json([
            'periode' => [
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
                'is_default' => !$request->filled('date_start') && !$request->filled('date_end'),
            ],
            'releves' => [
                'count'        => (int)   $releveStats->count,
                'consommation' => (float) $releveStats->consommation,
                'total'        => (float) $releveStats->total,
            ],
            'factures' => [
                'count'              => (int)   $factureStats->count,
                'total'              => (float) $factureStats->total,
                'total_recu'         => (float) $factureStats->total_recu,
                'total_gracie'       => (float) $factureStats->total_gracie,
                'nb_gracie'          => (int)   $factureStats->nb_gracie,
                'total_recouvrement' => (float) $factureStats->total_recouvrement,
                'nb_recouvrement'    => (int)   $factureStats->nb_recouvrement,
                'total_impaye'       => (float) $factureStats->total_impaye,
                'nb_impaye'          => (int)   $factureStats->nb_impaye,
            ],
            'caisse' => [
                'count'         => (int)   $caisseStats->count,
                'total_credit'  => (float) $caisseStats->total_credit,
                'total_debit'   => (float) $caisseStats->total_debit,
                'total_attente' => (float) $caisseStats->total_attente,
                'solde'         => (float) ($caisseStats->total_credit - $caisseStats->total_debit),
            ],
        ]);
    }
}
