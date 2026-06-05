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
        // releve.ID_CLIENT lié directement à client ; CONSOMMATION = RELEVE - ANCIEN_INDEX
        // TOTAL vient de facture_v2 via ID_RELEVE = ID_INDEX
        $releveStats = DB::table('releve as r')
            ->leftJoin('client as c', 'r.ID_CLIENT', '=', 'c.ID_CLIENT')
            ->leftJoin('facture_v2 as f', 'r.ID_INDEX', '=', 'f.ID_RELEVE')
            ->whereDate('r.DATE_INDEX', '>=', $dateStart)
            ->whereDate('r.DATE_INDEX', '<=', $dateEnd)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(r.RELEVE - r.ANCIEN_INDEX), 0) AS consommation, COALESCE(SUM(f.TOTAL), 0) AS total')
            ->first();

        // ── Factures (même UNION que FactureController::list) ────────────────
        // Filtre sur DATEFACTURE (nom réel de la colonne)
        $whereSQL = 'WHERE f.DATEFACTURE >= ? AND f.DATEFACTURE <= ?';
        $params   = [$dateStart, $dateEnd];

        $idsSubQuery = "SELECT f.NUMERO_FACTURE FROM facture_v2 AS f $whereSQL";

        $unionSQL = "
            SELECT
                NUMERO_FACTURE,
                SUM(TOTAL)              AS TOTAL,
                SUM(RECU)               AS TOTAL_RECU,
                IF(SUM(REGLE) = COUNT(*), 1, 0) AS REGLE,
                MAX(REGLEMENT_TYPE)     AS REGLEMENT_TYPE
            FROM (
                SELECT f.NUMERO_FACTURE, f.TOTAL, f.RECU, f.REGLE, f.REGLEMENT_TYPE
                FROM facture_v2 AS f
                $whereSQL

                UNION ALL

                SELECT fp.NUMERO_FACTURE, fp.TOTAL, fp.RECU, fp.REGLE, fp.REGLEMENT_TYPE
                FROM facture_pret AS fp
                WHERE fp.NUMERO_FACTURE IN ($idsSubQuery)
            ) AS u
            GROUP BY NUMERO_FACTURE
        ";

        // 4 ? au total : branche 1 (2) + idsSubQuery dans branche 2 (2)
        $unionParams = array_merge($params, $params);

        $factureStats = DB::selectOne("
            SELECT
                COUNT(*) AS count,
                COALESCE(SUM(TOTAL), 0) AS total,
                COALESCE(SUM(TOTAL_RECU), 0) AS total_recu,
                COALESCE(SUM(CASE WHEN REGLEMENT_TYPE = 'GRACIER'      THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_gracie,
                COUNT(CASE WHEN REGLEMENT_TYPE = 'GRACIER' THEN 1 END) AS nb_gracie,
                COALESCE(SUM(CASE WHEN REGLEMENT_TYPE = 'RECOUVREMENT' THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_recouvrement,
                COUNT(CASE WHEN REGLEMENT_TYPE = 'RECOUVREMENT' THEN 1 END) AS nb_recouvrement,
                COALESCE(SUM(CASE WHEN REGLE = 0 AND REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN TOTAL - TOTAL_RECU ELSE 0 END), 0) AS total_impaye,
                COUNT(CASE WHEN REGLE = 0 AND REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN 1 END) AS nb_impaye
            FROM ($unionSQL) AS agg
        ", $unionParams);

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
                'count'        => (int)   ($releveStats->count        ?? 0),
                'consommation' => (float) ($releveStats->consommation ?? 0),
                'total'        => (float) ($releveStats->total        ?? 0),
            ],
            'factures' => [
                'count'              => (int)   ($factureStats->count              ?? 0),
                'total'              => (float) ($factureStats->total              ?? 0),
                'total_recu'         => (float) ($factureStats->total_recu         ?? 0),
                'total_gracie'       => (float) ($factureStats->total_gracie       ?? 0),
                'nb_gracie'          => (int)   ($factureStats->nb_gracie          ?? 0),
                'total_recouvrement' => (float) ($factureStats->total_recouvrement ?? 0),
                'nb_recouvrement'    => (int)   ($factureStats->nb_recouvrement    ?? 0),
                'total_impaye'       => (float) ($factureStats->total_impaye       ?? 0),
                'nb_impaye'          => (int)   ($factureStats->nb_impaye          ?? 0),
            ],
            'caisse' => [
                'count'         => (int)   ($caisseStats->count         ?? 0),
                'total_credit'  => (float) ($caisseStats->total_credit  ?? 0),
                'total_debit'   => (float) ($caisseStats->total_debit   ?? 0),
                'total_attente' => (float) ($caisseStats->total_attente ?? 0),
                'solde'         => (float) (($caisseStats->total_credit ?? 0) - ($caisseStats->total_debit ?? 0)),
            ],
        ]);
    }
}
