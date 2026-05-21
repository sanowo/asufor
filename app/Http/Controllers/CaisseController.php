<?php

namespace App\Http\Controllers;

use App\Services\PaiementService;
use App\Services\FacturationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CaisseController extends Controller
{
    protected $paiementService;
    protected $facturationService;

    public function __construct(PaiementService $paiementService, FacturationService $facturationService)
    {
        $this->paiementService = $paiementService;
        $this->facturationService = $facturationService;
    }

    /**
     * Afficher la page caisse
     */
    public function index()
    {
        $typeOperations = DB::table('typeoperation')->get();

        return Inertia::render('Caisse/Index', [
            'typeOperations' => $typeOperations
        ]);
    }

    /**
     * Liste des opérations (DataTable server-side)
     */
    public function listOperations__OLD(Request $request)
    {
        $query = DB::table('operation as o')
            ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
            ->leftJoin('utilisateur as u', 'o.ID_USER', '=', 'u.ID_USER')
            ->select(
                'o.ID_OPERATION',
                'o.ID_OP_TARGET',
                'o.DATE_OPERATION',
                'o.MONTANT',
                'o.STATUS',
                't.LIBELLE as TYPE_OPERATION',
                't.EFFECT',
                'u.NOM as USER_NAME'
            );

        // Filtres
        if ($request->date_start) {
            $query->whereDate('o.DATE_OPERATION', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('o.DATE_OPERATION', '<=', $request->date_end);
        }

        if ($request->status && $request->status != '*') {
            $query->where('o.STATUS', $request->status);
        }

        if ($request->type_operation && $request->type_operation != '*') {
            $query->where('o.ID_TYPEOPERATION', $request->type_operation);
        }

        $query->orderBy('o.DATE_LOG', 'DESC');

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;

        $total = $query->count();
        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data
        ]);
    }

public function listOperations(Request $request)
{
    $request->validate([
        'date_start'     => 'nullable|date_format:Y-m-d',
        'date_end'       => 'nullable|date_format:Y-m-d',
        'status'         => 'nullable|string|max:20',
        'type_operation' => 'nullable|string|max:20',
        'start'          => 'nullable|integer|min:0',
        'length'         => 'nullable|integer|min:1|max:200',
        'draw'           => 'nullable|integer',
    ]);

    // Dates par défaut : mois en cours
    $dateStart = $request->filled('date_start')
        ? $request->date_start
        : now()->startOfMonth()->format('Y-m-d');

    $dateEnd = $request->filled('date_end')
        ? $request->date_end
        : now()->endOfMonth()->format('Y-m-d');

    $query = DB::table('operation as o')
        ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
        ->leftJoin('utilisateur as u', 'o.ID_USER', '=', 'u.ID_USER')
        ->whereDate('o.DATE_OPERATION', '>=', $dateStart)
        ->whereDate('o.DATE_OPERATION', '<=', $dateEnd);

    if ($request->filled('status') && $request->status !== '*') {
        $query->where('o.STATUS', $request->status);
    }

    if ($request->filled('type_operation') && $request->type_operation !== '*') {
        $query->where('o.ID_TYPEOPERATION', $request->type_operation);
    }

    // Agrégats sur la même base filtrée (requête indépendante)
    $stats = DB::table('operation as o')
        ->leftJoin('typeoperation as t', 'o.ID_TYPEOPERATION', '=', 't.ID_TYPEOPERATION')
        ->whereDate('o.DATE_OPERATION', '>=', $dateStart)
        ->whereDate('o.DATE_OPERATION', '<=', $dateEnd)
        ->when($request->filled('status') && $request->status !== '*', fn($q) =>
            $q->where('o.STATUS', $request->status)
        )
        ->when($request->filled('type_operation') && $request->type_operation !== '*', fn($q) =>
            $q->where('o.ID_TYPEOPERATION', $request->type_operation)
        )
        ->selectRaw("
            COUNT(*)                                                                                      AS count,
            IFNULL(SUM(CASE WHEN t.EFFECT = '+' AND o.STATUS != 'ANNULE' THEN o.MONTANT ELSE 0 END), 0) AS total_credit,
            IFNULL(SUM(CASE WHEN t.EFFECT = '-' AND o.STATUS != 'ANNULE' THEN o.MONTANT ELSE 0 END), 0) AS total_debit,
            IFNULL(SUM(CASE WHEN o.STATUS = 'ATTENTE' THEN o.MONTANT ELSE 0 END), 0)                    AS total_attente,
            IFNULL(SUM(CASE WHEN o.STATUS = 'ANNULE'  THEN o.MONTANT ELSE 0 END), 0)                    AS total_annule
        ")
        ->first();

    // Données paginées
    $start  = max(0, (int) ($request->start  ?? 0));
    $length = min(200, max(1, (int) ($request->length ?? 10)));

    $data = $query
        ->select(
            'o.ID_OPERATION',
            'o.ID_OP_TARGET',
            'o.DATE_OPERATION',
            'o.MONTANT',
            'o.STATUS',
            't.LIBELLE as TYPE_OPERATION',
            't.EFFECT',
            'u.NOM as USER_NAME'
        )
        ->orderBy('o.DATE_LOG', 'DESC')
        ->skip($start)
        ->take($length)
        ->get();

    return response()->json([
        'draw'            => (int) ($request->draw ?? 1),
        'recordsTotal'    => (int) $stats->count,
        'recordsFiltered' => (int) $stats->count,
        'data' => [
            'meta' => [
                'count'         => (int)   $stats->count,
                'total_credit'  => (float) $stats->total_credit,
                'total_debit'   => (float) $stats->total_debit,
                'total_attente' => (float) $stats->total_attente,
                'total_annule'  => (float) $stats->total_annule,
                'solde'         => (float) ($stats->total_credit - $stats->total_debit),
                'periode' => [
                    'date_start' => $dateStart,
                    'date_end'   => $dateEnd,
                    'is_default' => !$request->filled('date_start') && !$request->filled('date_end'),
                ],
            ],
            'result' => $data,
        ],
    ]);
}

    /**
     * PAIEMENT - Algorithme waterfall CRITIQUE
     */
    public function paiement(Request $request)
    {
        $validated = $request->validate([
            'numero_facture' => 'required|string',
            'montant_recu' => 'required|integer|min:0',
            'pret_include' => 'array',
            'pay_frais_coupure' => 'boolean',
            'date_operation' => 'required|date'
        ]);

        try {
            // Récupérer détails facture
            $details = $this->facturationService->getFactureDetails($validated['numero_facture']);

            if (empty($details)) {
                return response()->json([
                    'errors' => ['facture' => 'Facture non trouvée']
                ], 404);
            }

            // Appliquer waterfall
            $result = $this->paiementService->applyPaymentWaterfall(
                $validated['numero_facture'],
                $validated['montant_recu'],
                $validated['pret_include'] ?? [],
                $validated['pay_frais_coupure'] ?? false
            );

            // Créer opération
            $operation_id = $this->paiementService->createPaymentOperation(
                $validated['numero_facture'],
                $result['montant_utilise'],
                $result['details'],
                1 // TODO: Récupérer ID user authentifié
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'operation_id' => $operation_id,
                'montant_utilise' => $result['montant_utilise'],
                'montant_restant' => $result['montant_restant']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Confirmer une opération
     */
    public function confirmOperation(Request $request)
    {
        $validated = $request->validate([
            'id_operation' => 'required|integer'
        ]);

        try {
            $success = $this->paiementService->confirmOperation(
                $validated['id_operation'],
                1 // TODO: Récupérer ID user authentifié
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Opération confirmée avec succès'
                ]);
            }

            return response()->json([
                'errors' => ['operation' => 'Opération non trouvée ou déjà annulée']
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Annuler une opération - ROLLBACK COMPLEXE
     */
    public function cancelOperation(Request $request)
    {
        $validated = $request->validate([
            'id_operation' => 'required|integer',
            'id_typeop' => 'required|integer'
        ]);

        DB::beginTransaction();

        try {
            $operation = DB::table('operation')
                ->where('ID_OPERATION', $validated['id_operation'])
                ->first();

            if (!$operation) {
                return response()->json([
                    'errors' => ['operation' => 'Opération non trouvée']
                ], 404);
            }

            // Récupérer les détails d'opération
            $details = DB::table('operation_detail')
                ->where('ID_OP_PARENT', $validated['id_operation'])
                ->get();

            // ROLLBACK selon type
            foreach ($details as $detail) {
                switch ($detail->ID_TYPEOPERATION) {
                    case 13: // PAIEMENT_FACTURE
                    case 12: // PAIEMENT_FACTURE_ARRIERE
                        // Reverser facture_v2
                        DB::table('facture_v2')
                            ->where('NUMERO_FACTURE', $detail->ID_OP_TARGET)
                            ->update([
                                'RECU' => DB::raw('RECU - ' . $detail->MONTANT),
                                'IMPAYE' => DB::raw('IMPAYE + ' . $detail->MONTANT),
                                'REGLE' => 0
                            ]);
                        break;

                    case 14: // REMBOURSEMENT_PRET
                        // Reverser facture_pret
                        $facture_pret = DB::table('facture_pret')
                            ->where('NUMERO_FACTURE', $detail->ID_OP_TARGET)
                            ->first();

                        if ($facture_pret) {
                            DB::table('facture_pret')
                                ->where('NUMERO_FACTURE', $detail->ID_OP_TARGET)
                                ->update([
                                    'RECU' => DB::raw('RECU - ' . $detail->MONTANT),
                                    'IMPAYE' => DB::raw('IMPAYE + ' . $detail->MONTANT),
                                    'REGLE' => 0
                                ]);

                            // Reverser pret master
                            DB::table('pret')
                                ->where('ID_PRET', $facture_pret->ID_PRET)
                                ->update([
                                    'PAYER' => DB::raw('PAYER - ' . $detail->MONTANT),
                                    'IMPAYER' => DB::raw('IMPAYER + ' . $detail->MONTANT),
                                    'ACTIF' => 1
                                ]);
                        }
                        break;

                    case 23: // FRAIS_COUPURE
                        // Reverser le paiement du bon de coupure
                        DB::table('facture_v2')
                            ->where('NUMERO_FACTURE', $detail->ID_OP_TARGET)
                            ->update([
                                'BONCOUPURE' => 1 // Remettre le statut à impayé
                            ]);
                        break;
                }

                // Supprimer détail
                DB::table('operation_detail')
                    ->where('ID_OP', $detail->ID_OP)
                    ->delete();
            }

            // Marquer opération comme annulée
            DB::table('operation')
                ->where('ID_OPERATION', $validated['id_operation'])
                ->update([
                    'STATUS' => 'ANNULE',
                    'ID_USER_STATUS' => 1 // TODO: User authentifié
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opération annulée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Payer abonnement
     */
    public function payerAbonnement(Request $request)
    {
        $validated = $request->validate([
            'num_client' => 'required|string',
            'montant' => 'required|numeric|min:0',
            'date_operation' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier client existe
            $client = DB::table('client')->where('NUM_CLIENT', $validated['num_client'])->first();
            if (!$client) {
                return response()->json(['errors' => ['num_client' => 'Client non trouvé']], 404);
            }

            // Type d'opération pour abonnement (ex: ID 11)
            $typeOp = DB::table('typeoperation')->where('LIBELLE', 'LIKE', '%ABONNEMENT%')->first();
            $idTypeOp = $typeOp ? $typeOp->ID_TYPEOPERATION : 11;

            // Créer opération
            $idOp = DB::table('operation')->insertGetId([
                'ID_TYPEOPERATION' => $idTypeOp,
                'ID_OP_TARGET' => $validated['num_client'],
                'DATE_OPERATION' => $validated['date_operation'],
                'MONTANT' => $validated['montant'],
                'STATUS' => 'ATTENTE',
                'ID_USER' => 1 // TODO: User authentifié
            ]);

            // Créer détail
            DB::table('operation_detail')->insert([
                'ID_OP_PARENT' => $idOp,
                'ID_TYPEOPERATION' => $idTypeOp,
                'ID_OP_TARGET' => $validated['num_client'],
                'MONTANT' => $validated['montant']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Abonnement enregistré',
                'id_operation' => $idOp
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Payer prêt (standalone, pas via facture)
     */
    public function payerPret(Request $request)
    {
        $validated = $request->validate([
            'num_client' => 'required|string',
            'montant_recu' => 'required|numeric|min:0',
            'date_operation' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Récupérer prêts actifs du client
            $prets = DB::table('pret')
                ->where('NUM_CLIENT', $validated['num_client'])
                ->where('ACTIF', 1)
                ->orderBy('DATE_PRET', 'asc')
                ->get();

            if ($prets->isEmpty()) {
                return response()->json(['errors' => ['general' => 'Aucun prêt actif trouvé']], 404);
            }

            $montant_restant = $validated['montant_recu'];
            $typeOp = 14; // REMBOURSEMENT_PRET

            // Créer opération principale
            $idOp = DB::table('operation')->insertGetId([
                'ID_TYPEOPERATION' => $typeOp,
                'ID_OP_TARGET' => $validated['num_client'],
                'DATE_OPERATION' => $validated['date_operation'],
                'MONTANT' => $validated['montant_recu'],
                'STATUS' => 'ATTENTE',
                'ID_USER' => 1
            ]);

            // Répartir sur les prêts
            foreach ($prets as $pret) {
                if ($montant_restant <= 0) break;

                $impaye = $pret->IMPAYER;
                $montant_a_payer = min($montant_restant, $impaye);

                if ($montant_a_payer > 0) {
                    // Mettre à jour le prêt master
                    DB::table('pret')
                        ->where('ID_PRET', $pret->ID_PRET)
                        ->update([
                            'PAYER' => DB::raw('PAYER + ' . $montant_a_payer),
                            'IMPAYER' => DB::raw('IMPAYER - ' . $montant_a_payer),
                            'ACTIF' => DB::raw('IF(IMPAYER - ' . $montant_a_payer . ' = 0, 0, 1)')
                        ]);

                    // Créer détail
                    DB::table('operation_detail')->insert([
                        'ID_OP_PARENT' => $idOp,
                        'ID_TYPEOPERATION' => $typeOp,
                        'ID_OP_TARGET' => $pret->ID_PRET,
                        'MONTANT' => $montant_a_payer
                    ]);

                    $montant_restant -= $montant_a_payer;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement prêt enregistré',
                'montant_utilise' => $validated['montant_recu'] - $montant_restant,
                'montant_restant' => $montant_restant
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Enregistrer revenues
     */
    public function enregistrerRevenues(Request $request)
    {
        $validated = $request->validate([
            'type_operation' => 'required|integer',
            'montant' => 'required|numeric|min:0',
            'date_operation' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier que c'est bien un revenue
            $typeOp = DB::table('typeoperation')
                ->where('ID_TYPEOPERATION', $validated['type_operation'])
                ->where('IS_REVENUE', 1)
                ->first();

            if (!$typeOp) {
                return response()->json(['errors' => ['type_operation' => 'Type opération invalide']], 400);
            }

            // Créer opération
            $idOp = DB::table('operation')->insertGetId([
                'ID_TYPEOPERATION' => $validated['type_operation'],
                'ID_OP_TARGET' => 'CAISSE',
                'DATE_OPERATION' => $validated['date_operation'],
                'MONTANT' => $validated['montant'],
                'STATUS' => 'ATTENTE',
                'ID_USER' => 1
            ]);

            // Créer détail
            DB::table('operation_detail')->insert([
                'ID_OP_PARENT' => $idOp,
                'ID_TYPEOPERATION' => $validated['type_operation'],
                'ID_OP_TARGET' => 'CAISSE',
                'MONTANT' => $validated['montant']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Revenue enregistré',
                'id_operation' => $idOp
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Enregistrer dépenses
     */
    public function enregistrerDepenses(Request $request)
    {
        $validated = $request->validate([
            'type_operation' => 'required|integer',
            'montant' => 'required|numeric|min:0',
            'date_operation' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Vérifier que c'est bien une dépense
            $typeOp = DB::table('typeoperation')
                ->where('ID_TYPEOPERATION', $validated['type_operation'])
                ->where('IS_REVENUE', 0)
                ->first();

            if (!$typeOp) {
                return response()->json(['errors' => ['type_operation' => 'Type opération invalide']], 400);
            }

            // Créer opération
            $idOp = DB::table('operation')->insertGetId([
                'ID_TYPEOPERATION' => $validated['type_operation'],
                'ID_OP_TARGET' => 'CAISSE',
                'DATE_OPERATION' => $validated['date_operation'],
                'MONTANT' => $validated['montant'],
                'STATUS' => 'ATTENTE',
                'ID_USER' => 1
            ]);

            // Créer détail
            DB::table('operation_detail')->insert([
                'ID_OP_PARENT' => $idOp,
                'ID_TYPEOPERATION' => $validated['type_operation'],
                'ID_OP_TARGET' => 'CAISSE',
                'MONTANT' => $validated['montant']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dépense enregistrée',
                'id_operation' => $idOp
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }
}
