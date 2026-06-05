<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAnalytics extends Command
{
    protected $signature = 'analytics:backfill {--truncate : Truncate analytics tables before backfilling}';
    protected $description = 'Seed analytics tables (releve_monthly, factures_monthly, caisse_monthly) from existing data';

    public function handle(): int
    {
        if ($this->option('truncate')) {
            $this->warn('Truncating analytics tables...');
            DB::table('releve_monthly')->truncate();
            DB::table('factures_monthly')->truncate();
            DB::table('caisse_monthly')->truncate();
        }

        $this->backfillReleve();
        $this->backfillFactures();
        $this->backfillCaisse();

        $this->info('Analytics backfill complete.');
        return 0;
    }

    private function backfillReleve(): void
    {
        $this->info('Backfilling releve_monthly...');

        $rows = DB::select("
            SELECT
                YEAR(DATE_INDEX) AS year,
                MONTH(DATE_INDEX) AS month,
                COUNT(*) AS releves_count,
                IFNULL(SUM(RELEVE - ANCIEN_INDEX), 0) AS consommation_total,
                IFNULL(AVG(RELEVE - ANCIEN_INDEX), 0) AS consommation_moyenne
            FROM releve
            WHERE DATE_INDEX IS NOT NULL
            GROUP BY YEAR(DATE_INDEX), MONTH(DATE_INDEX)
        ");

        foreach ($rows as $row) {
            DB::table('releve_monthly')->updateOrInsert(
                ['year' => $row->year, 'month' => $row->month],
                [
                    'releves_count'       => $row->releves_count,
                    'consommation_total'  => round($row->consommation_total, 2),
                    'consommation_moyenne' => round($row->consommation_moyenne, 2),
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ]
            );
        }

        $this->line("  → {$this->count('releve_monthly')} months updated.");
    }

    private function backfillFactures(): void
    {
        $this->info('Backfilling factures_monthly...');

        $rows = DB::select("
            SELECT
                YEAR(f.DATEFACTURE) AS year,
                MONTH(f.DATEFACTURE) AS month,
                COUNT(DISTINCT f.NUMERO_FACTURE) AS factures_count,
                SUM(CASE WHEN f.REGLE = 1 THEN 1 ELSE 0 END) AS factures_reglees,
                SUM(CASE WHEN f.REGLE = 0 THEN 1 ELSE 0 END) AS factures_impayees,
                IFNULL(SUM(f.TOTAL), 0) AS montant_total,
                IFNULL(SUM(f.RECU), 0) AS montant_regle,
                IFNULL(SUM(f.IMPAYE), 0) AS montant_impaye,
                COUNT(fr.NUM_FACTURE) AS factures_avec_reduction,
                IFNULL(SUM(fr.MONTANT_REDUCTION), 0) AS montant_reductions
            FROM facture_v2 f
            LEFT JOIN facture_reduction fr ON fr.NUM_FACTURE = f.NUMERO_FACTURE
            WHERE f.DATEFACTURE IS NOT NULL
            GROUP BY YEAR(f.DATEFACTURE), MONTH(f.DATEFACTURE)
        ");

        foreach ($rows as $row) {
            DB::table('factures_monthly')->updateOrInsert(
                ['year' => $row->year, 'month' => $row->month],
                [
                    'factures_count'          => $row->factures_count,
                    'factures_reglees'        => $row->factures_reglees,
                    'factures_impayees'       => $row->factures_impayees,
                    'montant_total'           => $row->montant_total,
                    'montant_regle'           => $row->montant_regle,
                    'montant_impaye'          => $row->montant_impaye,
                    'factures_avec_reduction' => $row->factures_avec_reduction,
                    'montant_reductions'      => $row->montant_reductions,
                    'updated_at'              => now(),
                    'created_at'              => now(),
                ]
            );
        }

        $this->line("  → {$this->count('factures_monthly')} months updated.");
    }

    private function backfillCaisse(): void
    {
        $this->info('Backfilling caisse_monthly...');

        $rows = DB::select("
            SELECT
                YEAR(o.DATE_OPERATION) AS year,
                MONTH(o.DATE_OPERATION) AS month,
                SUM(CASE WHEN o.ID_TYPEOPERATION = 13 THEN 1 ELSE 0 END) AS paiements_count,
                IFNULL(SUM(CASE WHEN o.ID_TYPEOPERATION = 13 THEN o.MONTANT ELSE 0 END), 0) AS paiements_total,
                SUM(CASE WHEN o.ID_TYPEOPERATION = 14 THEN 1 ELSE 0 END) AS remboursements_count,
                IFNULL(SUM(CASE WHEN o.ID_TYPEOPERATION = 14 THEN o.MONTANT ELSE 0 END), 0) AS remboursements_total,
                SUM(CASE WHEN o.ID_TYPEOPERATION = 23 THEN 1 ELSE 0 END) AS frais_coupure_count,
                IFNULL(SUM(CASE WHEN o.ID_TYPEOPERATION = 23 THEN o.MONTANT ELSE 0 END), 0) AS frais_coupure_total,
                SUM(CASE WHEN o.STATUS = 'CONFIRM' THEN 1 ELSE 0 END) AS operations_confirmees,
                SUM(CASE WHEN o.STATUS = 'ANNULE' THEN 1 ELSE 0 END) AS operations_annulees,
                IFNULL(SUM(CASE WHEN o.STATUS = 'CONFIRM' THEN o.MONTANT ELSE 0 END), 0) AS montant_confirme,
                IFNULL(SUM(CASE WHEN o.STATUS = 'ANNULE' THEN o.MONTANT ELSE 0 END), 0) AS montant_annule
            FROM operation o
            WHERE o.DATE_OPERATION IS NOT NULL
            AND o.ID_TYPEOPERATION IN (13, 14, 23)
            AND o.STATUS = 'CONFIRM'
            GROUP BY YEAR(o.DATE_OPERATION), MONTH(o.DATE_OPERATION)
        ");

        foreach ($rows as $row) {
            DB::table('caisse_monthly')->updateOrInsert(
                ['year' => $row->year, 'month' => $row->month],
                [
                    'paiements_count'       => $row->paiements_count,
                    'paiements_total'       => $row->paiements_total,
                    'remboursements_count'  => $row->remboursements_count,
                    'remboursements_total'  => $row->remboursements_total,
                    'frais_coupure_count'   => $row->frais_coupure_count,
                    'frais_coupure_total'   => $row->frais_coupure_total,
                    'operations_confirmees' => $row->operations_confirmees,
                    'operations_annulees'   => $row->operations_annulees,
                    'montant_confirme'      => $row->montant_confirme,
                    'montant_annule'        => $row->montant_annule,
                    'updated_at'            => now(),
                    'created_at'            => now(),
                ]
            );
        }

        $this->line("  → {$this->count('caisse_monthly')} months updated.");
    }

    private function count(string $table): int
    {
        return DB::table($table)->count();
    }
}
