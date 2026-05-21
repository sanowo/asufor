<?php

namespace App\Services;

class ConsommationService
{
    /**
     * Calcule la consommation d'eau
     * CRITIQUE: CONSOMMATION = NOUVEL_INDEX - ANCIEN_INDEX
     */
    public function calculateConsommation(int $ancien_index, int $nouvel_index): int
    {
        return intval($nouvel_index) - intval($ancien_index);
    }

    /**
     * Calcule le coût de la consommation
     * CRITIQUE: COUT = CONSOMMATION × TARIF
     */
    public function calculateCost(int $consommation, int $tarif): int
    {
        return intval($consommation) * intval($tarif);
    }

    /**
     * Applique la logique client (Normal, Borne Fontaine, Social)
     * CRITIQUE: Gestion des types de clients
     *
     * @return array ['total', 'recu', 'impaye', 'regle']
     */
    public function applyClientTypeLogic(int $cout_releve, int $client_usage_type): array
    {
        $is_bf = ($client_usage_type == 2); // Borne Fontaine
        $is_social = ($client_usage_type == 7); // Social

        if ($is_social) {
            // Client social = eau gratuite
            return [
                'total' => 0,
                'recu' => 0,
                'impaye' => 0,
                'regle' => 1
            ];
        }

        if ($is_bf) {
            // Borne Fontaine: 20% avance, 80% crédit
            $avance = intval($cout_releve * 0.2);
            $credit = intval($cout_releve * 0.8);

            return [
                'total' => $cout_releve,
                'recu' => $avance,
                'impaye' => $credit,
                'regle' => 0
            ];
        }

        // Client normal
        return [
            'total' => $cout_releve,
            'recu' => 0,
            'impaye' => $cout_releve,
            'regle' => 0
        ];
    }

    /**
     * Valide que la date est J-1 ou J-2 du mois
     * CRITIQUE: Validation date relevé
     */
    public function validateReleveDate(string $date): array
    {
        $maxday = date('t', strtotime($date)); // Dernier jour du mois
        $curday = date('d', strtotime($date)); // Jour actuel

        if ($curday != $maxday - 1 && $curday != $maxday - 2) {
            $prevday = $maxday - 1;
            $prevvday = $maxday - 2;
            $month = date('F', strtotime($date));

            return [
                'valid' => false,
                'error' => "Seuls le $prevvday et le $prevday sont autorisés pour le mois de $month"
            ];
        }

        return ['valid' => true];
    }
}
