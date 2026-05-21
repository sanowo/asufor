<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class PaiementService
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }
    /**
     * ALGORITHME WATERFALL DE PAIEMENT
     * CRITIQUE: Ordre STRICT - Eau → Prêts → Frais coupure
     *
     * @param string $numero_facture
     * @param int $montant_recu
     * @param array $prets_included IDs des facture_pret à payer
     * @param bool $pay_frais_coupure
     * @return array Résultat du paiement
     */
    public function applyPaymentWaterfall(
        string $numero_facture,
        int $montant_recu,
        array $prets_included = [],
        bool $pay_frais_coupure = false
    ): array {

        DB::beginTransaction();

        try {
            $montant_restant = $montant_recu;
            $details = [];

            // PHASE 1: PAYER FACTURES EAU (chronologique)
            $result_eau = $this->payWaterBills($numero_facture, $montant_restant);
            $montant_restant = $result_eau['montant_restant'];
            $details = array_merge($details, $result_eau['details']);

            // PHASE 2: PAYER PRÊTS (si argent restant et prêts sélectionnés)
            if ($montant_restant > 0 && count($prets_included) > 0) {
                $result_prets = $this->payLoans($prets_included, $montant_restant);
                $montant_restant = $result_prets['montant_restant'];
                $details = array_merge($details, $result_prets['details']);
            }

            // PHASE 3: PAYER FRAIS DE COUPURE (si applicable et argent restant)
            if ($pay_frais_coupure && $montant_restant >= 2000) {
                $result_frais = $this->payDisconnectionFee($numero_facture, $montant_restant);
                $montant_restant = $result_frais['montant_restant'];
                $details = array_merge($details, $result_frais['details']);
            }

            // PHASE 4: VÉRIFIER AUTO-RÉACTIVATION CLIENT
            $facture = DB::table('facture_v2')
                ->where('NUMERO_FACTURE', $numero_facture)
                ->first();

            if ($facture) {
                $this->checkAutoReactivation($facture->NUM_CLIENT);
            }

            DB::commit();

            return [
                'success' => true,
                'montant_utilise' => $montant_recu - $montant_restant,
                'montant_restant' => $montant_restant,
                'details' => $details
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * PHASE 1: Paiement factures eau (chronologique)
     */
    private function payWaterBills(string $numero_facture, int $montant_restant): array
    {
        $details = [];

        $releves = DB::table('facture_v2')
            ->where('NUMERO_FACTURE', $numero_facture)
            ->orderBy('ID_FACTURE', 'ASC')
            ->get();

        foreach ($releves as $releve) {
            if ($montant_restant > 0) {
                $releve_impaye = intval($releve->IMPAYE);
                $releve_paying = min($montant_restant, $releve_impaye);
                $montant_restant -= $releve_paying;

                $old_regle = intval($releve->REGLE);
                $new_recu = intval($releve->RECU) + $releve_paying;
                $new_impaye = intval($releve->IMPAYE) - $releve_paying;

                // IMPORTANT: Vérifier si une réduction est appliquée
                // Si oui, comparer RECU avec MONTANT_APRES_REDUCTION au lieu de TOTAL
                $reduction = DB::table('facture_reduction')
                    ->where('NUM_FACTURE', $numero_facture)
                    ->first();

                if ($reduction) {
                    // Avec réduction : réglé si RECU >= MONTANT_APRES_REDUCTION
                    $is_regle = ($new_recu >= $reduction->MONTANT_APRES_REDUCTION) ? 1 : 0;
                } else {
                    // Sans réduction : réglé si IMPAYE == 0
                    $is_regle = ($new_impaye == 0) ? 1 : 0;
                }

                DB::table('facture_v2')
                    ->where('ID_FACTURE', $releve->ID_FACTURE)
                    ->update([
                        'RECU' => $new_recu,
                        'IMPAYE' => $new_impaye,
                        'REGLE' => $is_regle
                    ]);

                // ANALYTICS: Mettre à jour les stats si le statut REGLE a changé
                $this->analyticsService->handleFacturePaid(
                    $releve->DATEFACTURE,
                    $releve_paying,
                    $old_regle == 1,
                    $is_regle == 1
                );

                $details[] = [
                    'type' => 'PAIEMENT_FACTURE',
                    'type_id' => 13,
                    'target' => $numero_facture,
                    'montant' => $releve_paying,
                    'id_facture' => $releve->ID_FACTURE
                ];
            }
        }

        return [
            'montant_restant' => $montant_restant,
            'details' => $details
        ];
    }

    /**
     * PHASE 2: Paiement prêts (chronologique)
     */
    private function payLoans(array $prets_included, int $montant_restant): array
    {
        $details = [];

        $prets = DB::table('facture_pret')
            ->whereIn('ID_FACTURE', $prets_included)
            ->orderBy('ID_FACTURE', 'ASC')
            ->get();

        foreach ($prets as $pret) {
            if ($montant_restant > 0) {
                $pret_impaye = intval($pret->IMPAYE);
                $pret_paying = min($montant_restant, $pret_impaye);
                $montant_restant -= $pret_paying;

                $new_recu = intval($pret->RECU) + $pret_paying;
                $new_impaye = intval($pret->IMPAYE) - $pret_paying;
                $is_regle = ($new_impaye == 0) ? 1 : 0;

                // Update facture_pret
                DB::table('facture_pret')
                    ->where('ID_FACTURE', $pret->ID_FACTURE)
                    ->update([
                        'RECU' => $new_recu,
                        'IMPAYE' => $new_impaye,
                        'REGLE' => $is_regle
                    ]);

                // Update pret master
                DB::table('pret')
                    ->where('ID_PRET', $pret->ID_PRET)
                    ->update([
                        'PAYER' => DB::raw('PAYER + ' . $pret_paying),
                        'IMPAYER' => DB::raw('IMPAYER - ' . $pret_paying)
                    ]);

                // Vérifier si prêt totalement remboursé
                $pret_updated = DB::table('pret')
                    ->where('ID_PRET', $pret->ID_PRET)
                    ->first();

                if ($pret_updated && $pret_updated->IMPAYER == 0) {
                    DB::table('pret')
                        ->where('ID_PRET', $pret->ID_PRET)
                        ->update(['ACTIF' => 0]);
                }

                $details[] = [
                    'type' => 'REMBOURSEMENT_PRET',
                    'type_id' => 14,
                    'target' => $pret->NUMERO_FACTURE,
                    'montant' => $pret_paying,
                    'id_pret' => $pret->ID_PRET
                ];
            }
        }

        return [
            'montant_restant' => $montant_restant,
            'details' => $details
        ];
    }

    /**
     * PHASE 3: Paiement frais de coupure (2000 FCFA fixe)
     */
    private function payDisconnectionFee(string $numero_facture, int $montant_restant): array
    {
        $details = [];

        if ($montant_restant >= 2000) {
            $montant_restant -= 2000;

            // Marquer le bon de coupure comme payé dans facture_v2
            DB::table('facture_v2')
                ->where('NUMERO_FACTURE', $numero_facture)
                ->where('BONCOUPURE', 1)
                ->update([
                    'BONCOUPURE' => 0, // 0 = payé, 1 = impayé
                    'DATEBONCOUPURE' => now()
                ]);

            $details[] = [
                'type' => 'FRAIS_COUPURE',
                'type_id' => 23,
                'target' => $numero_facture,
                'montant' => 2000
            ];
        }

        return [
            'montant_restant' => $montant_restant,
            'details' => $details
        ];
    }

    /**
     * PHASE 4: Vérifier auto-réactivation client
     * CRITIQUE: Client réactivé SEULEMENT si TOUTES factures payées
     *
     * NOTE RÉDUCTIONS: On utilise REGLE au lieu de IMPAYE car avec les réductions,
     * IMPAYE peut être > 0 même si la facture est réglée
     */
    private function checkAutoReactivation(string $num_client): void
    {
        $client = DB::table('client')
            ->where('NUM_CLIENT', $num_client)
            ->first();

        // STATUT: 0 = actif, 1 = suspendu
        if ($client && $client->STATUT == 1) {
            // Compter les factures non réglées au lieu de sommer IMPAYE
            $factures_non_reglees = DB::table('facture_v2')
                ->where('NUM_CLIENT', $num_client)
                ->where('REGLE', 0)
                ->count();

            // Réactiver seulement si TOUTES les factures sont réglées
            if ($factures_non_reglees == 0) {
                DB::table('client')
                    ->where('NUM_CLIENT', $num_client)
                    ->update(['STATUT' => 0]);
            }
        }
    }

    /**
     * Créer l'opération de paiement
     */
    public function createPaymentOperation(
        string $numero_facture,
        int $montant_recu,
        array $details,
        int $user_id
    ): int {

        $operation_id = DB::table('operation')->insertGetId([
            'ID_TYPEOPERATION' => 13, // PAIEMENT_FACTURE
            'ID_STRUCTURE' => 11, // CAISSE_ID
            'ID_OP_TARGET' => $numero_facture,
            'DATE_OPERATION' => now(),
            'DATE_LOG' => now(),
            'MONTANT' => $montant_recu,
            'ID_USER' => $user_id,
            'STATUS' => 'ATTENTE',
            'ID_USER_STATUS' => $user_id
        ]);

        // Créer les détails d'opération
        foreach ($details as $detail) {
            DB::table('operation_detail')->insert([
                'ID_OP_PARENT' => $operation_id,
                'ID_TYPEOPERATION' => $detail['type_id'],
                'ID_OP_TARGET' => $detail['target'],
                'MONTANT' => $detail['montant'],
                'DATE_LOG' => now()
            ]);
        }

        return $operation_id;
    }

    /**
     * Confirmer une opération
     */
    public function confirmOperation(int $operation_id, int $user_id): bool
    {
        // Récupérer l'opération avant modification
        $operation = DB::table('operation')
            ->where('ID_OPERATION', $operation_id)
            ->where('STATUS', '!=', 'ANNULE')
            ->first();

        if (!$operation) {
            return false;
        }

        $oldStatus = $operation->STATUS;

        $updated = DB::table('operation')
            ->where('ID_OPERATION', $operation_id)
            ->where('STATUS', '!=', 'ANNULE')
            ->update([
                'STATUS' => 'CONFIRM',
                'ID_USER_STATUS' => $user_id
            ]) > 0;

        // ANALYTICS: Mettre à jour les stats si le statut a changé
        if ($updated && $oldStatus !== 'CONFIRM') {
            $this->analyticsService->handleOperationStatusChanged(
                $operation->DATE_OPERATION,
                $operation->ID_TYPEOPERATION,
                $operation->MONTANT,
                $oldStatus,
                'CONFIRM'
            );
        }

        return $updated;
    }
}
