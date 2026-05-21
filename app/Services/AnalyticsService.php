<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Service pour gérer les analytics mensuelles de manière incrémentale
 *
 * PRINCIPE:
 * - Chaque CRUD sur releve/facture/operation met à jour les analytics
 * - On ajuste les compteurs en temps réel (pas de recalcul complet)
 * - Création automatique de la ligne mensuelle si elle n'existe pas
 */
class AnalyticsService
{
    /**
     * ======================
     * RELEVE MONTHLY
     * ======================
     */

    /**
     * Créer ou mettre à jour les analytics lors de la création d'un relevé
     */
    public function handleReleveCreated($date, $consommation)
    {
        [$year, $month] = $this->extractYearMonth($date);

        DB::table('releve_monthly')
            ->updateOrInsert(
                ['year' => $year, 'month' => $month],
                [
                    'releves_count' => DB::raw('releves_count + 1'),
                    'consommation_total' => DB::raw('consommation_total + ' . $consommation),
                ]
            );

        // Recalculer la moyenne
        $this->recalculateReleveAverage($year, $month);
    }

    /**
     * Mettre à jour les analytics lors de la suppression d'un relevé
     */
    public function handleReleveDeleted($date, $consommation)
    {
        [$year, $month] = $this->extractYearMonth($date);

        DB::table('releve_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->update([
                'releves_count' => DB::raw('GREATEST(releves_count - 1, 0)'),
                'consommation_total' => DB::raw('GREATEST(consommation_total - ' . $consommation . ', 0)'),
            ]);

        // Recalculer la moyenne
        $this->recalculateReleveAverage($year, $month);
    }

    /**
     * Recalculer la consommation moyenne
     */
    private function recalculateReleveAverage($year, $month)
    {
        $stats = DB::table('releve_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($stats && $stats->releves_count > 0) {
            $average = $stats->consommation_total / $stats->releves_count;

            DB::table('releve_monthly')
                ->where('year', $year)
                ->where('month', $month)
                ->update(['consommation_moyenne' => round($average, 2)]);
        }
    }

    /**
     * ======================
     * FACTURES MONTHLY
     * ======================
     */

    /**
     * Créer ou mettre à jour les analytics lors de la création d'une facture
     */
    public function handleFactureCreated($date, $total, $recu, $impaye, $regle, $hasReduction = false, $montantReduction = 0)
    {
        [$year, $month] = $this->extractYearMonth($date);

        $data = [
            'factures_count' => DB::raw('factures_count + 1'),
            'montant_total' => DB::raw('montant_total + ' . $total),
            'montant_regle' => DB::raw('montant_regle + ' . $recu),
            'montant_impaye' => DB::raw('montant_impaye + ' . $impaye),
        ];

        if ($regle) {
            $data['factures_reglees'] = DB::raw('factures_reglees + 1');
        } else {
            $data['factures_impayees'] = DB::raw('factures_impayees + 1');
        }

        if ($hasReduction) {
            $data['factures_avec_reduction'] = DB::raw('factures_avec_reduction + 1');
            $data['montant_reductions'] = DB::raw('montant_reductions + ' . $montantReduction);
        }

        DB::table('factures_monthly')
            ->updateOrInsert(
                ['year' => $year, 'month' => $month],
                $data
            );
    }

    /**
     * Mettre à jour les analytics lors du paiement d'une facture
     *
     * @param string $date Date de facturation (pas date de paiement)
     * @param int $montantPaye Montant du paiement
     * @param bool $wasRegle État REGLE avant paiement
     * @param bool $isRegle État REGLE après paiement
     */
    public function handleFacturePaid($date, $montantPaye, $wasRegle, $isRegle)
    {
        [$year, $month] = $this->extractYearMonth($date);

        $data = [
            'montant_regle' => DB::raw('montant_regle + ' . $montantPaye),
            'montant_impaye' => DB::raw('GREATEST(montant_impaye - ' . $montantPaye . ', 0)'),
        ];

        // Si la facture passe de impayée à réglée
        if (!$wasRegle && $isRegle) {
            $data['factures_reglees'] = DB::raw('factures_reglees + 1');
            $data['factures_impayees'] = DB::raw('GREATEST(factures_impayees - 1, 0)');
        }

        DB::table('factures_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->update($data);
    }

    /**
     * Mettre à jour les analytics lors de la suppression d'une facture
     */
    public function handleFactureDeleted($date, $total, $recu, $impaye, $regle, $hasReduction = false, $montantReduction = 0)
    {
        [$year, $month] = $this->extractYearMonth($date);

        $data = [
            'factures_count' => DB::raw('GREATEST(factures_count - 1, 0)'),
            'montant_total' => DB::raw('GREATEST(montant_total - ' . $total . ', 0)'),
            'montant_regle' => DB::raw('GREATEST(montant_regle - ' . $recu . ', 0)'),
            'montant_impaye' => DB::raw('GREATEST(montant_impaye - ' . $impaye . ', 0)'),
        ];

        if ($regle) {
            $data['factures_reglees'] = DB::raw('GREATEST(factures_reglees - 1, 0)');
        } else {
            $data['factures_impayees'] = DB::raw('GREATEST(factures_impayees - 1, 0)');
        }

        if ($hasReduction) {
            $data['factures_avec_reduction'] = DB::raw('GREATEST(factures_avec_reduction - 1, 0)');
            $data['montant_reductions'] = DB::raw('GREATEST(montant_reductions - ' . $montantReduction . ', 0)');
        }

        DB::table('factures_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->update($data);
    }

    /**
     * ======================
     * CAISSE MONTHLY
     * ======================
     */

    /**
     * Créer ou mettre à jour les analytics lors de la création d'une opération
     */
    public function handleOperationCreated($date, $typeOperation, $montant, $status)
    {
        [$year, $month] = $this->extractYearMonth($date);

        $data = [];

        // Traiter selon le type d'opération
        switch ($typeOperation) {
            case 13: // PAIEMENT_FACTURE
                $data['paiements_count'] = DB::raw('paiements_count + 1');
                $data['paiements_total'] = DB::raw('paiements_total + ' . $montant);
                break;

            case 14: // REMBOURSEMENT_PRET
                $data['remboursements_count'] = DB::raw('remboursements_count + 1');
                $data['remboursements_total'] = DB::raw('remboursements_total + ' . $montant);
                break;

            case 23: // FRAIS_COUPURE
                $data['frais_coupure_count'] = DB::raw('frais_coupure_count + 1');
                $data['frais_coupure_total'] = DB::raw('frais_coupure_total + ' . $montant);
                break;
        }

        // Statut
        if ($status === 'CONFIRM') {
            $data['operations_confirmees'] = DB::raw('operations_confirmees + 1');
            $data['montant_confirme'] = DB::raw('montant_confirme + ' . $montant);
        } elseif ($status === 'ANNULE') {
            $data['operations_annulees'] = DB::raw('operations_annulees + 1');
            $data['montant_annule'] = DB::raw('montant_annule + ' . $montant);
        }

        if (!empty($data)) {
            DB::table('caisse_monthly')
                ->updateOrInsert(
                    ['year' => $year, 'month' => $month],
                    $data
                );
        }
    }

    /**
     * Mettre à jour les analytics lors du changement de statut d'une opération
     *
     * Exemple: ATTENTE -> CONFIRM ou CONFIRM -> ANNULE
     */
    public function handleOperationStatusChanged($date, $typeOperation, $montant, $oldStatus, $newStatus)
    {
        [$year, $month] = $this->extractYearMonth($date);

        $data = [];

        // Retirer de l'ancien statut
        if ($oldStatus === 'CONFIRM') {
            $data['operations_confirmees'] = DB::raw('GREATEST(operations_confirmees - 1, 0)');
            $data['montant_confirme'] = DB::raw('GREATEST(montant_confirme - ' . $montant . ', 0)');
        } elseif ($oldStatus === 'ANNULE') {
            $data['operations_annulees'] = DB::raw('GREATEST(operations_annulees - 1, 0)');
            $data['montant_annule'] = DB::raw('GREATEST(montant_annule - ' . $montant . ', 0)');
        }

        // Ajouter au nouveau statut
        if ($newStatus === 'CONFIRM') {
            $data['operations_confirmees'] = DB::raw('operations_confirmees + 1');
            $data['montant_confirme'] = DB::raw('montant_confirme + ' . $montant);
        } elseif ($newStatus === 'ANNULE') {
            $data['operations_annulees'] = DB::raw('operations_annulees + 1');
            $data['montant_annule'] = DB::raw('montant_annule + ' . $montant);
        }

        if (!empty($data)) {
            DB::table('caisse_monthly')
                ->where('year', $year)
                ->where('month', $month)
                ->update($data);
        }
    }

    /**
     * ======================
     * HELPERS
     * ======================
     */

    /**
     * Extraire année et mois d'une date
     */
    private function extractYearMonth($date)
    {
        $carbon = Carbon::parse($date);
        return [$carbon->year, $carbon->month];
    }

    /**
     * Obtenir les stats d'un mois donné pour les relevés
     */
    public function getReleveMonthly($year, $month)
    {
        return DB::table('releve_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Obtenir les stats d'un mois donné pour les factures
     */
    public function getFacturesMonthly($year, $month)
    {
        return DB::table('factures_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Obtenir les stats d'un mois donné pour la caisse
     */
    public function getCaisseMonthly($year, $month)
    {
        return DB::table('caisse_monthly')
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Obtenir les stats des N derniers mois
     */
    public function getLastNMonths($tableName, $n = 12)
    {
        $startDate = Carbon::now()->subMonths($n - 1)->startOfMonth();

        return DB::table($tableName)
            ->where(function ($query) use ($startDate) {
                $query->where('year', '>', $startDate->year)
                    ->orWhere(function ($q) use ($startDate) {
                        $q->where('year', $startDate->year)
                          ->where('month', '>=', $startDate->month);
                    });
            })
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC')
            ->get();
    }
}
