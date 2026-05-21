<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FacturationService
{
    /**
     * Génère le numéro de facture
     * CRITIQUE: Format FACTddmmyyNUM_CLIENT
     */
    public function generateInvoiceNumber(string $date, string $num_client): string
    {
        $day = date('d', strtotime($date));
        $month = date('m', strtotime($date));
        $year = date('y', strtotime($date));

        return "FACT{$day}{$month}{$year}{$num_client}";
    }

    /**
     * Crée une facture pour un relevé
     * CRITIQUE: Génération automatique facture_v2
     */
    public function createInvoiceForReleve(array $releveData, array $clientData, int $tarif): array
    {
        $consommation = $releveData['consommation'];
        $cout_releve = $consommation * $tarif;

        $consommationService = new ConsommationService();
        $clientLogic = $consommationService->applyClientTypeLogic($cout_releve, $clientData['USED']);

        $num_facture = $this->generateInvoiceNumber($releveData['date'], $clientData['NUM_CLIENT']);

        return [
            'NUMERO_FACTURE' => $num_facture,
            'ID_CLIENT' => $clientData['ID_CLIENT'],
            'NUM_CLIENT' => $clientData['NUM_CLIENT'],
            'ID_RELEVE' => $releveData['id_releve'],
            'ID_QUARTIER' => $clientData['ID_QUARTIER'],
            'CONSOMMATION' => $consommation,
            'DATEFACTURE' => $releveData['date'],
            'DATEECH' => date('Y-m-d', strtotime($releveData['date'] . ' +30 days')),
            'TOTAL' => $clientLogic['total'],
            'RECU' => $clientLogic['recu'],
            'IMPAYE' => $clientLogic['impaye'],
            'REGLE' => $clientLogic['regle'],
            'BONCOUPURE' => 0,
            'REGLEMENT_TYPE' => 'PAIEMENT'
        ];
    }

    /**
     * Crée les factures de prêt mensuelles
     * CRITIQUE: Génération automatique facture_pret lors d'un relevé
     */
    public function createLoanInvoices(string $num_facture, array $client, string $date_facture): array
    {
        $factures_pret = [];

        // Récupérer les prêts actifs du client
        $prets = DB::table('pret')
            ->where('ID_CLIENT', $client['ID_CLIENT'])
            ->where('ACTIF', 1)
            ->where('IMPAYER', '>', 0)
            ->get();

        foreach ($prets as $pret) {
            // SKIP si facture_pret impayée existe déjà
            $impayeeExists = DB::table('facture_pret')
                ->where('ID_CLIENT', $client['ID_CLIENT'])
                ->where('ID_PRET', $pret->ID_PRET)
                ->where('REGLE', 0)
                ->exists();

            if ($impayeeExists) {
                continue;
            }

            // SKIP si facture_pret déjà générée pour cette période
            $periodeExists = DB::table('facture_pret')
                ->where('ID_CLIENT', $client['ID_CLIENT'])
                ->where('ID_PRET', $pret->ID_PRET)
                ->where('DATEFACTURE', $date_facture)
                ->exists();

            if ($periodeExists) {
                continue;
            }

            // Calcul mensualité
            $mensualite = intval($pret->MENSUALITE);
            $pret_restant = intval($pret->IMPAYER);
            $mensualite = min($mensualite, $pret_restant);

            $factures_pret[] = [
                'NUMERO_FACTURE' => $num_facture,
                'ID_CLIENT' => $client['ID_CLIENT'],
                'NUM_CLIENT' => $client['NUM_CLIENT'],
                'ID_PRET' => $pret->ID_PRET,
                'ID_QUARTIER' => $client['ID_QUARTIER'],
                'DATEFACTURE' => $date_facture,
                'DATEECH' => date('Y-m-d', strtotime($date_facture . ' +30 days')),
                'TOTAL' => $mensualite,
                'RECU' => 0,
                'IMPAYE' => $mensualite,
                'REGLE' => 0,
                'REGLEMENT_TYPE' => ''
            ];
        }

        return $factures_pret;
    }

    /**
     * Récupère les détails complets d'une facture
     */
    public function getFactureDetails(string $numero_facture): array
    {
        // Facture principale
        $original = DB::table('facture_v2')
            ->where('NUMERO_FACTURE', $numero_facture)
            ->first();

        if (!$original) {
            return [];
        }

        // Relevés associés
        $releves = DB::table('facture_v2 as f')
            ->leftJoin('releve as r', 'r.ID_INDEX', '=', 'f.ID_RELEVE')
            ->leftJoin('compteur as compt', 'r.ID_COMPTEUR', '=', 'compt.ID_COMPTEUR')
            ->where('f.NUMERO_FACTURE', $numero_facture)
            ->select('f.*', 'compt.NUM_COMPTEUR', 'r.ANCIEN_INDEX',
                     'r.ID_INDEX', 'r.RELEVE as NOUVEAU_INDEX', 'r.DATE_INDEX')
            ->get()
            ->toArray();

        // Prêts associés
        $prets = DB::table('facture_pret as fp')
            ->leftJoin('pret as p', 'fp.ID_PRET', '=', 'p.ID_PRET')
            ->where('fp.NUMERO_FACTURE', $numero_facture)
            ->select('fp.*', 'p.DATE as PRET_DATE', 'p.MONTANT as PRET_MONTANT',
                     'p.MOTIF', 'p.PAYER as PRET_PAYER', 'p.IMPAYER as PRET_IMPAYE')
            ->get()
            ->toArray();

        // Client
        $client = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->where('c.ID_CLIENT', $original->ID_CLIENT)
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_TYPE', 'u.TARIF as USAGE_TARIF')
            ->first();

        // Frais de coupure
        $frais_encaisse = DB::table('operation_detail')
            ->where('ID_OP_TARGET', $numero_facture)
            ->where('ID_TYPEOPERATION', 23) // FRAIS_COUPURE
            ->sum('MONTANT');

        $frais_to_pay = max(0, 2000 - intval($frais_encaisse));

        // Réduction appliquée (s'il y en a une)
        $reduction = DB::table('facture_reduction as fr')
            ->leftJoin('reduction as r', 'fr.ID_REDUCTION', '=', 'r.ID_REDUCTION')
            ->where('fr.NUM_FACTURE', $numero_facture)
            ->select('fr.*', 'r.LIBELLE as REDUCTION_LIBELLE', 'r.DESCRIPTION as REDUCTION_DESCRIPTION')
            ->first();

        return [
            'original' => $original,
            'releve' => $releves,
            'prets' => $prets,
            'client' => $client,
            'frais_to_pay' => $frais_to_pay,
            'frais_encaisse' => $frais_encaisse,
            'reduction' => $reduction // Ajouter les informations de réduction
        ];
    }
    /**
 * Charge les détails de plusieurs factures en UN minimum de requêtes.
 * Remplace les appels répétés à getFactureDetails() dans les boucles.
 *
 * @param  string[] $numeroFactures
 * @return array    Indexé par NUMERO_FACTURE, chaque entrée = même structure que getFactureDetails()
 */
public function getFactureDetailsBatch(array $numeroFactures): array
{
    if (empty($numeroFactures)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($numeroFactures), '?'));

    // -----------------------------------------------------------------------
    // 1. Factures principales (facture_v2)
    // -----------------------------------------------------------------------
    $originals = DB::table('facture_v2')
        ->whereIn('NUMERO_FACTURE', $numeroFactures)
        ->get()
        ->keyBy('NUMERO_FACTURE'); // indexé pour accès O(1)

    // -----------------------------------------------------------------------
    // 2. Relevés associés
    // -----------------------------------------------------------------------
    $releves = DB::table('facture_v2 as f')
        ->leftJoin('releve as r', 'r.ID_INDEX', '=', 'f.ID_RELEVE')
        ->leftJoin('compteur as compt', 'r.ID_COMPTEUR', '=', 'compt.ID_COMPTEUR')
        ->whereIn('f.NUMERO_FACTURE', $numeroFactures)
        ->select(
            'f.NUMERO_FACTURE',
            'f.*',
            'compt.NUM_COMPTEUR',
            'r.ANCIEN_INDEX',
            'r.ID_INDEX',
            'r.RELEVE as NOUVEAU_INDEX',
            'r.DATE_INDEX'
        )
        ->get()
        ->groupBy('NUMERO_FACTURE'); // [ 'FAC-001' => [releve1, releve2, ...] ]

    // -----------------------------------------------------------------------
    // 3. Prêts associés
    // -----------------------------------------------------------------------
    $prets = DB::table('facture_pret as fp')
        ->leftJoin('pret as p', 'fp.ID_PRET', '=', 'p.ID_PRET')
        ->whereIn('fp.NUMERO_FACTURE', $numeroFactures)
        ->select(
            'fp.NUMERO_FACTURE',
            'fp.*',
            'p.DATE as PRET_DATE',
            'p.MONTANT as PRET_MONTANT',
            'p.MOTIF',
            'p.PAYER as PRET_PAYER',
            'p.IMPAYER as PRET_IMPAYE'
        )
        ->get()
        ->groupBy('NUMERO_FACTURE');

    // -----------------------------------------------------------------------
    // 4. Clients (via ID_CLIENT des factures originales)
    //    On déduplique les ID_CLIENT car plusieurs factures peuvent
    //    appartenir au même client.
    // -----------------------------------------------------------------------
    $clientIds = $originals->pluck('ID_CLIENT')->unique()->filter()->values()->all();

    $clients = collect();
    if (!empty($clientIds)) {
        $clients = DB::table('client as c')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED', '=', 'u.ID_USAGE')
            ->whereIn('c.ID_CLIENT', $clientIds)
            ->select('c.*', 'q.NOM as QUARTIER', 'u.NOM as USAGE_TYPE', 'u.TARIF as USAGE_TARIF')
            ->get()
            ->keyBy('ID_CLIENT');
    }

    // -----------------------------------------------------------------------
    // 5. Frais de coupure (opération type 23) – agrégés par facture
    // -----------------------------------------------------------------------
    $fraisRows = DB::table('operation_detail')
        ->whereIn('ID_OP_TARGET', $numeroFactures)
        ->where('ID_TYPEOPERATION', 23)
        ->select('ID_OP_TARGET', DB::raw('SUM(MONTANT) as TOTAL_FRAIS'))
        ->groupBy('ID_OP_TARGET')
        ->get()
        ->keyBy('ID_OP_TARGET');

    // -----------------------------------------------------------------------
    // 6. Réductions – une par facture
    // -----------------------------------------------------------------------
    $reductions = DB::table('facture_reduction as fr')
        ->leftJoin('reduction as r', 'fr.ID_REDUCTION', '=', 'r.ID_REDUCTION')
        ->whereIn('fr.NUM_FACTURE', $numeroFactures)
        ->select('fr.*', 'r.LIBELLE as REDUCTION_LIBELLE', 'r.DESCRIPTION as REDUCTION_DESCRIPTION')
        ->get()
        ->keyBy('NUM_FACTURE');

    // -----------------------------------------------------------------------
    // 7. Assemblage – même structure que getFactureDetails()
    // -----------------------------------------------------------------------
    $result = [];

    foreach ($numeroFactures as $num) {
        $original = $originals->get($num);

        if (!$original) {
            $result[$num] = [];
            continue;
        }

        $fraisEncaisse = (float) ($fraisRows->get($num)->TOTAL_FRAIS ?? 0);
        $fraisToPay    = max(0, 2000 - intval($fraisEncaisse));

        $result[$num] = [
            'numero_facture' => $num, // clé pour le mapping dans le controller
            'original'       => $original,
            'releve'         => ($releves->get($num) ?? collect())->values()->toArray(),
            'prets'          => ($prets->get($num)   ?? collect())->values()->toArray(),
            'client'         => $clients->get($original->ID_CLIENT),
            'frais_to_pay'   => $fraisToPay,
            'frais_encaisse' => $fraisEncaisse,
            'reduction'      => $reductions->get($num),
        ];
    }

    return $result;
}
}
