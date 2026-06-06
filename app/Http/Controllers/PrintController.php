<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    // ─────────────────────────────────────────────
    //  FACTURES (A4 portrait, coupon + ciseaux)
    // ─────────────────────────────────────────────
    public function printFactures(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters        = $request->input('filters', []);
            $factureNumbers = app(FactureController::class)->getFacturesFromFilters($filters);
        } else {
            $validated      = $request->validate([
                'facture_numbers'   => 'required|array',
                'facture_numbers.*' => 'required|string',
            ]);
            $factureNumbers = $validated['facture_numbers'];
        }

        $factures = [];
        foreach ($factureNumbers as $numero) {
            $data = $this->getFactureData($numero);
            if ($data) {
                $factures[] = $data;
            }
        }

        if (empty($factures)) {
            return response()->json(['error' => 'Aucune facture trouvée'], 404);
        }

        $pdf = PDF::loadView('pdf.factures', [
            'factures'   => $factures,
            'parametres' => $this->getParametres(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('factures_' . date('Y-m-d') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  BONS DE COUPURE (A4 portrait)
    // ─────────────────────────────────────────────
    public function printBonsCoupure(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters        = $request->input('filters', []);
            $factureNumbers = app(FactureController::class)->getFacturesFromFilters($filters);
        } else {
            $validated      = $request->validate([
                'facture_numbers'   => 'required|array',
                'facture_numbers.*' => 'required|string',
            ]);
            $factureNumbers = $validated['facture_numbers'];
        }

        // Factures impayées de base
        $facturesBase = DB::table('facture_v2 as f')
            ->leftJoin('client as c',    'f.NUM_CLIENT',  '=', 'c.NUM_CLIENT')
            ->leftJoin('quartier as q',  'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED',        '=', 'u.ID_USAGE')
            ->whereIn('f.NUMERO_FACTURE', $factureNumbers)
            ->where('f.REGLE', 0)
            ->select(
                'f.*',
                'c.NOM', 'c.PRENOM', 'c.TELEPHONE', 'c.NUM_CI',
                'q.NOM as QUARTIER',
                'u.NOM as USAGE_NOM',
                'u.TARIF as USAGE_TARIF'
            )
            ->get();

        if ($facturesBase->isEmpty()) {
            return response()->json(['error' => 'Aucun bon de coupure trouvé'], 404);
        }

        // Enrichir chaque facture avec ses prêts et arriérés
        $factures = $facturesBase->map(function ($facture) {
            $facture->prets    = $this->getPretsActifs($facture->NUM_CLIENT);
            $facture->arrieres = $this->getArrieres($facture->NUM_CLIENT, $facture->NUMERO_FACTURE);
            return $facture;
        });

        $pdf = PDF::loadView('pdf.bons-coupure', [
            'factures'   => $factures,
            'parametres' => $this->getParametres(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('bons_coupure_' . date('Y-m-d') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  FICHE DE RELEVÉ (A4 paysage)
    // ─────────────────────────────────────────────
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

        $dateEnd   = $validated['date_end']   ?? date('Y-m-d');
        $dateStart = $validated['date_start'] ?? date('Y-m-d', strtotime('-29 days'));

        // Dernier relevé par compteur dans la période
        $lastReleve = DB::table('releve as r')
            ->join(
                DB::raw("(SELECT ID_COMPTEUR, MAX(DATE_INDEX) as MAX_DATE
                          FROM releve
                          WHERE DATE_INDEX BETWEEN '{$dateStart}' AND '{$dateEnd}'
                          GROUP BY ID_COMPTEUR) as lr"),
                function ($join) {
                    $join->on('r.ID_COMPTEUR', '=', 'lr.ID_COMPTEUR')
                         ->on('r.DATE_INDEX',  '=', 'lr.MAX_DATE');
                }
            )
            ->select('r.ID_COMPTEUR', 'r.RELEVE as INDEX_COMPTEUR', 'r.DATE_INDEX');

        $query = DB::table('client as c')
            ->leftJoin('quartier as q',    'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u',   'c.USED',        '=', 'u.ID_USAGE')
            ->leftJoin('compteur as cpt',  'c.ID_CLIENT',   '=', 'cpt.ID_CLIENT')
            ->leftJoinSub($lastReleve, 'lr', 'cpt.ID_COMPTEUR', '=', 'lr.ID_COMPTEUR')
            ->where('c.STATUT', 1)
            ->select(
                'c.*',
                'q.NOM as QUARTIER',
                'u.NOM as USAGE_NOM',
                'cpt.ID_COMPTEUR',
                'cpt.NUM_COMPTEUR',
                'lr.INDEX_COMPTEUR',
                'lr.DATE_INDEX as DATE_DERNIER_RELEVE',
                DB::raw('CONCAT(c.NOM, " ", c.PRENOM) as CLIENT')
            );

        if ($validated['quartier'] !== '*') {
            $query->where('c.ID_QUARTIER', $validated['quartier']);
        }
        if (!empty($validated['client_usage'])) {
            $query->where('c.USED', $validated['client_usage']);
        }
        if (!empty($validated['client'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('c.NUM_CLIENT', 'LIKE', '%' . $validated['client'] . '%')
                  ->orWhere('c.NOM',      'LIKE', '%' . $validated['client'] . '%')
                  ->orWhere('c.PRENOM',   'LIKE', '%' . $validated['client'] . '%');
            });
        }

        $clients = $query->orderBy('q.NOM')->orderBy('c.NOM')->get();

        if ($clients->isEmpty()) {
            return response()->json(['error' => 'Aucun client trouvé pour ces filtres'], 404);
        }

        $quartier = null;
        if ($validated['quartier'] !== '*') {
            $quartier = DB::table('quartier')->where('ID_QUARTIER', $validated['quartier'])->first();
        }

        $pdf = PDF::loadView('pdf.fiche-releve', [
            'clients'    => $clients,
            'parametres' => $this->getParametres(),
            'quartier'   => $quartier,
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('fiche_releve_' . date('Y-m-d') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  TICKET DE CAISSE (format thermique 62mm)
    // ─────────────────────────────────────────────
    public function printTicket(Request $request)
    {
        $validated = $request->validate([
            'ids'  => 'required|string',
            'type' => 'required|string|in:facture,operation',
        ]);

        $ids  = explode(',', $validated['ids']);
        $type = $validated['type'];

        if ($type === 'facture') {
            $operations = DB::table('operation_detail as o')
                ->leftJoin('typeoperation as ty', 'o.ID_TYPEOPERATION', '=', 'ty.ID_TYPEOPERATION')
                ->whereIn('o.ID_OP', $ids)
                ->select('o.*', 'ty.LIBELLE as OPERATION')
                ->get();
        } else {
            $operations = DB::table('operation as o')
                ->leftJoin('typeoperation as ty', 'o.ID_TYPEOPERATION', '=', 'ty.ID_TYPEOPERATION')
                ->whereIn('o.ID_OPERATION', $ids)
                ->select('o.*', 'ty.LIBELLE as OPERATION')
                ->get();
        }

        if ($operations->isEmpty()) {
            return response()->json(['error' => 'Aucune opération trouvée'], 404);
        }

        $pdf = PDF::loadView('pdf.ticket', [
            'operations' => $operations,
            'parametres' => $this->getParametres(),
            'type'       => $type,
        ])->setPaper([0, 0, 175.75, 841.89], 'portrait'); // 62mm de large

        return $pdf->stream('ticket_' . date('YmdHis') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  LISTE GÉNÉRIQUE (clients / factures / relevés / prêts / opérations)
    // ─────────────────────────────────────────────
    public function printList(Request $request)
    {
        set_time_limit(120);

        $validated = $request->validate([
            'target'       => 'required|string|in:clients,factures,releves,prets,operations',
            'ids'          => 'nullable|array',
            'ids.*'        => 'string',
            'facture_type' => 'nullable|string',
            'subtarget'    => 'nullable|string',
            'format'       => 'nullable|string',
            'date_start'   => 'nullable|date',
            'date_end'     => 'nullable|date',
            'meta'         => 'nullable|array',
        ]);

        $target       = $validated['target'];
        $ids          = $validated['ids'] ?? [];
        $factureType  = $validated['facture_type'] ?? '*';
        $subtarget    = $validated['subtarget'] ?? null;
        $format       = $validated['format'] ?? 'prets';
        $dateStart    = $validated['date_start'] ?? null;
        $dateEnd      = $validated['date_end'] ?? date('Y-m-d');
        $meta         = $validated['meta'] ?? [];
        $syntheseData = [];
        $typeLabel    = null;
        $title        = ucfirst($target);

        // ── Enrichir meta avec noms lisibles ──
        if (!empty($meta['quartier'])) {
            $q = DB::table('quartier')->where('ID_QUARTIER', $meta['quartier'])->first();
            $meta['quartier_nom'] = $q->NOM ?? null;
        }
        if (!empty($meta['usage'])) {
            $u = DB::table('typeusage')->where('ID_USAGE', $meta['usage'])->first();
            $meta['usage_nom'] = $u->NOM ?? null;
        }

        switch ($target) {

            // ── CLIENTS ──
            case 'clients':
                $title = 'Clients';
                if (empty($ids)) {
                    return response()->json(['error' => 'Aucun ID fourni'], 422);
                }
                $items = DB::table('client as c')
                    ->leftJoin('typeusage as u', 'c.USED',        '=', 'u.ID_USAGE')
                    ->leftJoin('quartier as q',  'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
                    ->whereIn('c.ID_CLIENT', $ids)
                    ->select(
                        'c.*',
                        'q.NOM as QUARTIER',
                        'u.NOM as NOM_USAGE',
                        DB::raw('(SELECT GROUP_CONCAT(cm.NUM_COMPTEUR)
                                  FROM compteur AS cm
                                  WHERE cm.ID_CLIENT = c.ID_CLIENT) AS NUMERO_COMPTEURS')
                    )
                    ->get();
                break;

            // ── FACTURES ──
            case 'factures':
                $title = 'Factures';
                if (empty($ids)) {
                    return response()->json(['error' => 'Aucun ID fourni'], 422);
                }

                $numsP = implode(',', array_fill(0, count($ids), '?'));

                switch ($factureType) {
                    case 'prets':
                        $itemsRaw = DB::select(
                            "SELECT f.*, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT,
                             'MENSUALITÉ PRET' AS CONSOMMATION, q.NOM AS QUARTIER
                             FROM facture_pret AS f
                             LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                             LEFT JOIN quartier AS q ON q.ID_QUARTIER = f.ID_QUARTIER
                             WHERE f.NUMERO_FACTURE IN ($numsP)",
                            $ids
                        );
                        break;
                    case 'retard':
                        $itemsRaw = DB::select(
                            "SELECT NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER,
                             SUM(TOTAL) AS TOTAL, SUM(CONSOMMATION) AS CONSOMMATION,
                             SUM(IMPAYE) AS IMPAYE, SUM(RECU) AS RECU,
                             SUM(BONCOUPURE) AS BONCOUPURE
                             FROM (
                               SELECT f.NUMERO_FACTURE, f.NUM_CLIENT, f.DATEFACTURE, f.CONSOMMATION,
                                      f.DATEECH, f.TOTAL, f.REGLE, f.RECU, f.IMPAYE, f.BONCOUPURE,
                                      CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT, q.NOM AS QUARTIER
                               FROM facture_v2 AS f
                               LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                               LEFT JOIN quartier AS q ON q.ID_QUARTIER = f.ID_QUARTIER
                               WHERE f.NUMERO_FACTURE IN ($numsP)
                               UNION ALL
                               SELECT fp.NUMERO_FACTURE, fp.NUM_CLIENT, fp.DATEFACTURE, 0 AS CONSOMMATION,
                                      fp.DATEECH, fp.TOTAL, fp.REGLE, fp.RECU, fp.IMPAYE, 0 AS BONCOUPURE,
                                      CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT, q.NOM AS QUARTIER
                               FROM facture_pret AS fp
                               LEFT JOIN client AS c ON c.ID_CLIENT = fp.ID_CLIENT
                               LEFT JOIN quartier AS q ON q.ID_QUARTIER = fp.ID_QUARTIER
                               WHERE fp.NUMERO_FACTURE IN ($numsP)
                             ) x
                             GROUP BY NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER
                             ORDER BY DATEFACTURE DESC",
                            array_merge($ids, $ids)
                        );
                        break;
                    default: // '*' ou 'releves'
                        $itemsRaw = DB::select(
                            "SELECT f.*, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT, q.NOM AS QUARTIER
                             FROM facture_v2 AS f
                             LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                             LEFT JOIN quartier AS q ON q.ID_QUARTIER = f.ID_QUARTIER
                             WHERE f.NUMERO_FACTURE IN ($numsP)
                             ORDER BY f.DATEFACTURE",
                            $ids
                        );
                        break;
                }

                // Enrichir avec NUM_COMPTEUR
                $itemsMetaSQL = "SELECT cm.NUM_COMPTEUR FROM compteur AS cm
                    LEFT JOIN releve AS r ON cm.ID_COMPTEUR = r.ID_COMPTEUR
                    LEFT JOIN facture_v2 AS f ON r.ID_INDEX = f.ID_RELEVE
                    WHERE f.NUMERO_FACTURE = ? LIMIT 1";

                $items        = collect($itemsRaw)->map(function ($item) use ($itemsMetaSQL) {
                    $cm = DB::selectOne($itemsMetaSQL, [$item->NUMERO_FACTURE]);
                    $item->NUM_COMPTEUR = $cm->NUM_COMPTEUR ?? '';
                    return $item;
                });

                // Synthèse
                $syntheseData = $subtarget === 'synthese'
                    ? [
                        'Total consommation en m³'  => $items->sum(fn($i) => intval($i->CONSOMMATION ?? 0)),
                        'Montant total facturé'      => $items->sum(fn($i) => intval($i->TOTAL ?? 0)),
                        'Montant total impayé'       => $items->sum(fn($i) => intval($i->IMPAYE ?? 0)),
                    ]
                    : [
                        'Total consommation en m³'  => $items->sum(fn($i) => intval($i->CONSOMMATION ?? 0)),
                        'Montant total à encaisser' => $items->sum(fn($i) => intval($i->IMPAYE ?? 0)),
                    ];
                break;

            // ── RELEVÉS ──
            case 'releves':
                $title = 'Relevés';
                if (empty($ids)) {
                    return response()->json(['error' => 'Aucun ID fourni'], 422);
                }
                $items = DB::table('releve as r')
                    ->leftJoin('client as c',   'r.ID_CLIENT',  '=', 'c.ID_CLIENT')
                    ->leftJoin('quartier as q',  'q.ID_QUARTIER','=', 'c.ID_QUARTIER')
                    ->leftJoin('compteur as cm', 'cm.ID_COMPTEUR','=','r.ID_COMPTEUR')
                    ->whereIn('r.ID_INDEX', $ids)
                    ->select(
                        'r.*',
                        DB::raw("CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT"),
                        'q.NOM as QUARTIER',
                        'cm.NUM_COMPTEUR as COMPTEUR'
                    )
                    ->get();

                $syntheseData = [
                    'Total consommation en m³' => $items->sum(fn($i) => intval($i->RELEVE) - intval($i->ANCIEN_INDEX)),
                ];
                if (!empty($meta['quartier_nom'])) {
                    $syntheseData['Quartier'] = $meta['quartier_nom'];
                }
                break;

            // ── PRÊTS ──
            case 'prets':
                $title = 'Prêts';
                if (empty($ids)) {
                    return response()->json(['error' => 'Aucun ID fourni'], 422);
                }

                if ($format === 'mensualites') {
                    $items = DB::table('facture_pret as p')
                        ->leftJoin('client as c', 'p.ID_CLIENT', '=', 'c.ID_CLIENT')
                        ->whereIn('p.ID_PRET', $ids)
                        ->select('p.*', DB::raw("CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT"))
                        ->get();
                    $syntheseData = [
                        'Montant total'  => $items->sum(fn($i) => intval($i->TOTAL ?? 0)),
                        'Montant réglé'  => $items->sum(fn($i) => intval($i->RECU ?? 0)),
                        'Montant impayé' => $items->sum(fn($i) => intval($i->IMPAYE ?? 0)),
                    ];
                } else {
                    $idsStr = implode(',', array_fill(0, count($ids), '?'));
                    $items  = DB::select(
                        "SELECT p.*, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT
                         FROM pret AS p
                         LEFT JOIN client AS c ON p.ID_CLIENT = c.ID_CLIENT
                         WHERE p.ID_PRET IN ($idsStr)
                            OR p.ID_PRET IN (SELECT ID_PRET FROM facture_pret WHERE NUMERO_FACTURE IN ($idsStr))",
                        array_merge($ids, $ids)
                    );
                    $items = collect($items);
                    $syntheseData = [
                        'Montant total'  => $items->sum(fn($i) => intval($i->MONTANT ?? 0)),
                        'Montant réglé'  => $items->sum(fn($i) => intval($i->PAYER ?? 0)),
                        'Montant impayé' => $items->sum(fn($i) => intval($i->IMPAYER ?? 0)),
                    ];
                }
                break;

            // ── OPÉRATIONS ──
            case 'operations':
                $title = 'Opérations de caisse';
                if (empty($ids)) {
                    return response()->json(['error' => 'Aucun ID fourni'], 422);
                }
                $idsStr = implode(',', $ids);

                // Résoudre le label du type
                if (in_array($subtarget, ['revenues', 'depenses', '*'])) {
                    $typeLabel = match($subtarget) {
                        'revenues' => 'Recettes',
                        'depenses' => 'Dépenses',
                        default    => 'Tous',
                    };
                } else {
                    $typeRow   = DB::table('typeoperation')->where('ID_TYPEOPERATION', $subtarget)->first();
                    $typeLabel = $typeRow->LIBELLE ?? null;
                }

                if (in_array($subtarget, ['12', '13'])) {
                    $items = DB::select(
                        "SELECT o.*, tyop.LIBELLE AS TYPE, CONCAT(u.NOM,' ',u.PRENOM) AS CAISSIER,
                         c.NUM_CLIENT, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT,
                         f.NUMERO_FACTURE, f.CONSOMMATION,
                         IF(f.BONCOUPURE,'OUI','NON') AS BONCOUPURE,
                         IF((SELECT COUNT(*) FROM operation AS oo
                             WHERE oo.ID_OP_TARGET = o.ID_OP_TARGET
                               AND oo.ID_TYPEOPERATION != o.ID_TYPEOPERATION), 2000, 0) AS MONTANT_BONCOUPURE
                         FROM operation_detail AS o
                         LEFT JOIN operation AS oo ON o.ID_OP_PARENT = oo.ID_OPERATION
                         LEFT JOIN typeoperation AS tyop ON o.ID_TYPEOPERATION = tyop.ID_TYPEOPERATION
                         LEFT JOIN facture_v2 AS f ON o.ID_OP_TARGET = f.NUMERO_FACTURE
                         LEFT JOIN utilisateur AS u ON u.ID_USER = o.ID_USER
                         LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                         WHERE o.ID_OP IN ($idsStr) AND oo.STATUS = 'CONFIRM'
                         ORDER BY o.DATE_OPERATION"
                    );
                    $items        = collect($items);
                    $encaisse     = DB::selectOne("SELECT SUM(MONTANT) AS E FROM operation_detail WHERE ID_OP IN ($idsStr)")->E ?? 0;
                    $syntheseData = [
                        'Total m³ consommé'          => $items->sum(fn($i) => intval($i->CONSOMMATION ?? 0)),
                        'Total frais coupure encaissé'=> $items->sum(fn($i) => intval($i->MONTANT_BONCOUPURE ?? 0)),
                        'Montant total encaissé'      => intval($encaisse),
                    ];

                } elseif ($subtarget === '14') {
                    $items = DB::select(
                        "SELECT o.*, tyop.LIBELLE AS TYPE, CONCAT(caissier.NOM,' ',caissier.PRENOM) AS CAISSIER,
                         c.NUM_CLIENT, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT
                         FROM operation AS o
                         LEFT JOIN typeoperation AS tyop ON o.ID_TYPEOPERATION = tyop.ID_TYPEOPERATION
                         LEFT JOIN utilisateur AS caissier ON caissier.ID_USER = o.ID_USER
                         LEFT JOIN pret AS p ON p.ID_PRET = o.ID_OP_TARGET
                         LEFT JOIN client AS c ON c.ID_CLIENT = p.ID_CLIENT
                         WHERE o.ID_OPERATION IN ($idsStr) AND STATUS = 'CONFIRM'
                         ORDER BY DATE_OPERATION"
                    );
                    $items        = collect($items);
                    $syntheseData = ['Montant total prêt encaissé' => $items->sum(fn($i) => intval($i->MONTANT))];

                } elseif ($subtarget === '15') {
                    $items = DB::select(
                        "SELECT o.*, tyop.LIBELLE AS TYPE, CONCAT(u.NOM,' ',u.PRENOM) AS CAISSIER,
                         c.NUM_CLIENT, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT
                         FROM operation AS o
                         LEFT JOIN typeoperation AS tyop ON o.ID_TYPEOPERATION = tyop.ID_TYPEOPERATION
                         LEFT JOIN abonnement AS a ON o.ID_OP_TARGET = a.ID_ABONNEMENT
                         LEFT JOIN utilisateur AS u ON u.ID_USER = o.ID_USER
                         LEFT JOIN client AS c ON c.ID_CLIENT = a.ID_CLIENT
                         WHERE ID_OPERATION IN ($idsStr) AND STATUS = 'CONFIRM'"
                    );
                    $items        = collect($items);
                    $syntheseData = ['Montant total abonnement encaissé' => $items->sum(fn($i) => intval($i->MONTANT))];

                } elseif (in_array($subtarget, ['23', '20', '21', 'facture_full', 'mensualite_pret'])) {
                    $tableFrom = in_array($subtarget, ['mensualite_pret', 'facture_full', '23'])
                        ? 'operation_detail' : 'operation';
                    $idCol     = $tableFrom === 'operation_detail' ? 'ID_OP' : 'ID_OPERATION';
                    $items     = DB::select(
                        "SELECT o.*, tyop.LIBELLE AS TYPE, CONCAT(u.NOM,' ',u.PRENOM) AS CAISSIER,
                         c.NUM_CLIENT, CONCAT(c.NOM,' ',c.PRENOM) AS CLIENT,
                         o.ID_OP_TARGET AS NUMERO_FACTURE
                         FROM {$tableFrom} AS o
                         LEFT JOIN typeoperation AS tyop ON o.ID_TYPEOPERATION = tyop.ID_TYPEOPERATION
                         LEFT JOIN facture_v2 AS f ON o.ID_OP_TARGET = f.NUMERO_FACTURE
                         LEFT JOIN utilisateur AS u ON u.ID_USER = o.ID_USER
                         LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                         WHERE {$idCol} IN ($idsStr)
                         ORDER BY DATE_OPERATION"
                    );
                    $items        = collect($items);
                    $syntheseData = ['Montant total' => $items->sum(fn($i) => intval($i->MONTANT))];

                } else {
                    $items = DB::select(
                        "SELECT o.*, tyop.LIBELLE AS TYPE, tyop.EFFECT,
                         CONCAT(u.NOM,' ',u.PRENOM) AS CAISSIER
                         FROM operation AS o
                         LEFT JOIN typeoperation AS tyop ON o.ID_TYPEOPERATION = tyop.ID_TYPEOPERATION
                         LEFT JOIN utilisateur AS u ON u.ID_USER = o.ID_USER
                         WHERE ID_OPERATION IN ($idsStr) AND STATUS = 'CONFIRM'"
                    );
                    $items        = collect($items);
                    $syntheseData = [
                        'Solde caisse' => $items->sum(fn($i) => ($i->EFFECT ?? '+') === '+' ? intval($i->MONTANT) : -intval($i->MONTANT)),
                    ];
                }
                break;

            default:
                return response()->json(['error' => 'Cible invalide'], 422);
        }

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Aucun enregistrement trouvé'], 404);
        }

        $pdf = PDF::loadView('pdf.print-list', [
            'items'        => $items,
            'target'       => $target,
            'title'        => $title,
            'facture_type' => $factureType,
            'subtarget'    => $subtarget,
            'syntheseData' => $syntheseData,
            'meta'         => $meta,
            'type_label'   => $typeLabel,
            'date_start'   => $dateStart,
            'date_end'     => $dateEnd,
            'parametres'   => $this->getParametres(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('liste_' . $target . '_' . date('Y-m-d') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  JOURNAL DE CAISSE (A4 portrait)
    // ─────────────────────────────────────────────
    public function printOperations(Request $request)
    {
        $validated = $request->validate([
            'date_start'     => 'required|date',
            'date_end'       => 'required|date',
            'status'         => 'nullable|string',
            'type_operation' => 'nullable|string',
        ]);

        $query = DB::table('operation as o')
            ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
            ->leftJoin('utilisateur as u',   'o.ID_USER',          '=', 'u.ID_USER')
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

        $total_credit = $operations->where('IS_REVENUE', 1)->sum('MONTANT');
        $total_debit  = $operations->where('IS_REVENUE', 0)->sum('MONTANT');

        $pdf = PDF::loadView('pdf.operations-caisse', [
            'operations'   => $operations,
            'parametres'   => $this->getParametres(),
            'date_start'   => $validated['date_start'],
            'date_end'     => $validated['date_end'],
            'total_credit' => $total_credit,
            'total_debit'  => $total_debit,
            'solde'        => $total_credit - $total_debit,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('operations_' . $validated['date_start'] . '_' . $validated['date_end'] . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  CLIENTS SUSPENDUS (A4 portrait)
    // ─────────────────────────────────────────────
    public function printClientsSuspendus(Request $request)
    {
        $validated = $request->validate([
            'quartier' => 'nullable|integer',
        ]);

        $query = DB::table('client as c')
            ->leftJoin('quartier as q',  'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED',        '=', 'u.ID_USAGE')
            ->where('c.STATUT', 0)
            ->select(
                'c.*',
                'q.NOM as QUARTIER',
                'u.NOM as USAGE_NOM',
                DB::raw('(SELECT COUNT(*) FROM compteur WHERE ID_CLIENT = c.ID_CLIENT) as NB_COMPTEURS'),
                DB::raw('(SELECT SUM(IMPAYE) FROM facture_v2 WHERE NUM_CLIENT = c.NUM_CLIENT) as TOTAL_IMPAYE')
            );

        if (isset($validated['quartier'])) {
            $query->where('c.ID_QUARTIER', $validated['quartier']);
        }

        $clients = $query->orderBy('q.NOM')->orderBy('c.NOM')->get();

        if ($clients->isEmpty()) {
            return response()->json(['error' => 'Aucun client suspendu trouvé'], 404);
        }

        $pdf = PDF::loadView('pdf.clients-suspendus', [
            'clients'         => $clients,
            'parametres'      => $this->getParametres(),
            'total_impaye'    => $clients->sum('TOTAL_IMPAYE'),
            'total_compteurs' => $clients->sum('NB_COMPTEURS'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('clients_suspendus_' . date('Y-m-d') . '.pdf');
    }

    // ─────────────────────────────────────────────
    //  LISTE FACTURES (tableau A4 paysage)
    // ─────────────────────────────────────────────
    public function printFacturesList(Request $request)
    {
        set_time_limit(120);

        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            $filters        = $request->input('filters', []);
            $factureNumbers = app(FactureController::class)->getFacturesFromFilters($filters);
        } else {
            $validated      = $request->validate([
                'facture_numbers'   => 'required|array',
                'facture_numbers.*' => 'required|string',
            ]);
            $factureNumbers = $validated['facture_numbers'];
        }

        $factures = DB::table('facture_v2 as f')
            ->leftJoin('client as c',   'f.NUM_CLIENT',  '=', 'c.NUM_CLIENT')
            ->leftJoin('quartier as q', 'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->whereIn('f.NUMERO_FACTURE', $factureNumbers)
            ->select(
                'f.*',
                DB::raw("CONCAT(c.NOM, ' ', c.PRENOM) as CLIENT"),
                'q.NOM as QUARTIER',
                DB::raw('(SELECT COALESCE(SUM(TOTAL),0) FROM facture_v2  WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) +
                         (SELECT COALESCE(SUM(TOTAL),0) FROM facture_pret WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) as MONTANT_TOTAL'),
                DB::raw('(SELECT COALESCE(SUM(IMPAYE),0) FROM facture_v2  WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) +
                         (SELECT COALESCE(SUM(IMPAYE),0) FROM facture_pret WHERE NUMERO_FACTURE = f.NUMERO_FACTURE) as RESTANT')
            )
            ->orderBy('f.DATEFACTURE', 'desc')
            ->get();

        if ($factures->isEmpty()) {
            return response()->json(['error' => 'Aucune facture trouvée'], 404);
        }

        $total    = $factures->sum('MONTANT_TOTAL');
        $restant  = $factures->sum('RESTANT');

        $pdf = PDF::loadView('pdf.factures-list', [
            'factures'   => $factures,
            'parametres' => $this->getParametres(),
            'total'      => $total,
            'encaisse'   => $total - $restant,
            'restant'    => $restant,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('liste_factures_' . date('Y-m-d') . '.pdf');
    }

    // ═════════════════════════════════════════════
    //  HELPERS PRIVÉS
    // ═════════════════════════════════════════════

    private function getFactureData(string $numero): ?array
    {
        $facture = DB::table('facture_v2 as f')
            ->leftJoin('client as c',    'f.NUM_CLIENT',  '=', 'c.NUM_CLIENT')
            ->leftJoin('quartier as q',  'f.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('typeusage as u', 'c.USED',        '=', 'u.ID_USAGE')
            ->leftJoin('releve as r',    'f.ID_RELEVE',   '=', 'r.ID_INDEX')
            ->leftJoin('compteur as cpt','r.ID_COMPTEUR', '=', 'cpt.ID_COMPTEUR')
            ->where('f.NUMERO_FACTURE', $numero)
            ->select(
                'f.*',
                'c.NOM', 'c.PRENOM', 'c.TELEPHONE', 'c.NUM_CLIENT', 'c.NUM_CI',
                'q.NOM as QUARTIER',
                'u.NOM as USAGE_NOM',
                'u.TARIF as USAGE_TARIF',
                'r.ANCIEN_INDEX',
                'r.RELEVE as NOUVEL_INDEX',
                'r.DATE_INDEX',
                DB::raw('(r.RELEVE - r.ANCIEN_INDEX) as CONSOMMATION'),
                'cpt.NUM_COMPTEUR'
            )
            ->first();

        if (!$facture) {
            return null;
        }

        // Relevé courant et précédent (pour la période)
        $releve = DB::table('releve')
            ->where('ID_INDEX', $facture->ID_RELEVE)
            ->first();

        $prevReleve = null;
        if ($releve) {
            $prevReleve = DB::table('releve')
                ->where('ID_COMPTEUR', $releve->ID_COMPTEUR)
                ->where('DATE_INDEX', '<', $releve->DATE_INDEX)
                ->orderByDesc('DATE_INDEX')
                ->first();
        }

        // Prêts actifs
        $prets = $this->getPretsActifs($facture->NUM_CLIENT);

        // Arriérés (factures impayées antérieures, hors facture courante)
        $arrieres = $this->getArrieres($facture->NUM_CLIENT, $numero);

        // Réduction éventuelle
        $reduction = DB::table('facture_reduction as fr')
            ->leftJoin('reduction as r', 'fr.ID_REDUCTION', '=', 'r.ID_REDUCTION')
            ->where('fr.NUM_FACTURE', $numero)
            ->select('fr.*', 'r.LIBELLE as REDUCTION_LIBELLE', 'r.DESCRIPTION as REDUCTION_DESCRIPTION')
            ->first();

        return [
            'facture'     => $facture,
            'releve'      => $releve     ? [$releve]    : [],
            'prev_releve' => $prevReleve,
            'prets'       => $prets,
            'arrieres'    => $arrieres,
            'reduction'   => $reduction,
        ];
    }

    private function getPretsActifs(string $numClient): \Illuminate\Support\Collection
    {
        return DB::table('pret')
            ->where('NUM_CLIENT', $numClient)
            ->where('ACTIF', 1)
            ->where('IMPAYER', '>', 0)
            ->select('ID_PRET', 'DATE_PRET', 'MONTANT_PRET', 'MONTANT_TRANCHE', 'PAYER', 'IMPAYER',
                     DB::raw('1 as PRET_ACTIF'),
                     DB::raw('MONTANT_PRET as PRET_MONTANT'),
                     DB::raw('IMPAYER as PRET_IMPAYE'),
                     DB::raw('DATE_PRET as PRET_DATE'))
            ->get();
    }

    private function getArrieres(string $numClient, string $excludeNumero): \Illuminate\Support\Collection
    {
        return DB::table('facture_v2')
            ->where('NUM_CLIENT', $numClient)
            ->where('REGLE', 0)
            ->where('IMPAYE', '>', 0)
            ->where('NUMERO_FACTURE', '!=', $excludeNumero)
            ->orderBy('DATEFACTURE', 'asc')
            ->select(
                'NUMERO_FACTURE',
                'DATEFACTURE as DATE',
                DB::raw("'RELEVE' as TYPE"),
                'IMPAYE'
            )
            ->get();
    }

    private function getParametres(): array
    {
        $params = DB::table('parametres')
            ->whereIn('TYPE', ['entreprise', 'adresse', 'telephone'])
            ->pluck('VALUE', 'TYPE')
            ->toArray();

        return [
            'entreprise' => $params['entreprise'] ?? 'ASUFOR',
            'adresse'    => $params['adresse']    ?? '',
            'telephone'  => $params['telephone']  ?? '',
        ];
    }
}
