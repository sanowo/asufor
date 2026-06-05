<?php

namespace App\Http\Controllers;

use App\Services\FacturationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FactureController extends Controller
{
    protected $facturationService;

    public function __construct(FacturationService $facturationService)
    {
        $this->facturationService = $facturationService;
    }

    /**
     * Afficher la page des factures
     */
    public function index()
    {
        $quartiers = DB::table('quartier')->get();
        $usages = DB::table('typeusage')->get();

        return Inertia::render('Factures/Index', [
            'quartiers' => $quartiers,
            'usages' => $usages
        ]);
    }

    /**
     * Liste des factures avec filtres complexes (DataTable server-side)
     */
    public function list___OLD(Request $request)
    {
        DB::beginTransaction();

        try {
            // Créer table temporaire filtrée
            $sql = [];
            $params = [];

            if ($request->numero) {
                $sql[] = "NUMERO_FACTURE = ?";
                $params[] = $request->numero;
            }

            if ($request->client) {
                if (is_numeric($request->client)) {
                    $sql[] = "c.NUM_CLIENT = ?";
                    $params[] = $request->client;
                } else {
                    $sql[] = "(c.NOM LIKE ? OR c.PRENOM LIKE ?)";
                    $params[] = "%{$request->client}%";
                    $params[] = "%{$request->client}%";
                }
            }

            if ($request->quartier && $request->quartier != '*') {
                $sql[] = "c.ID_QUARTIER = ?";
                $params[] = $request->quartier;
            }

            if ($request->date_start) {
                $sql[] = "DATEFACTURE >= CAST(? AS DATE)";
                $params[] = $request->date_start;
            }

            if ($request->date_end) {
                $sql[] = "DATEFACTURE <= CAST(? AS DATE)";
                $params[] = $request->date_end;
            }

            if ($request->client_usage && $request->client_usage != '*') {
                $sql[] = "c.USED = ?";
                $params[] = $request->client_usage;
            }

            $whereClause = count($sql) ? 'WHERE ' . implode(' AND ', $sql) : '';

            // Requête principale: UNION facture_v2 + facture_pret
            $query = "
                SELECT NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER,
                SUM(TOTAL) AS TOTAL, SUM(RECU) AS TOTAL_RECU,
                IF(SUM(REGLE), 1, 0) AS IS_REGLE,
                IF(SUM(REGLE) = COUNT(*), 1, 0) AS REGLE,
                IF(SUM(BONCOUPURE), 1, 0) AS BONCOUPURE,
                (SELECT IF(SUM(MONTANT),SUM(MONTANT),0) FROM operation_detail WHERE ID_OP_TARGET = NUMERO_FACTURE) AS ENCAISSE,
                (SELECT COUNT(*) FROM operation WHERE ID_OP_TARGET = NUMERO_FACTURE AND STATUS = 'ATTENTE') AS ATTENTE,
                REGLEMENT_TYPE
                FROM (
                    SELECT f.NUMERO_FACTURE, f.NUM_CLIENT, f.DATEFACTURE, f.DATEECH,
                    f.TOTAL, f.REGLE, f.RECU, f.REGLEMENT_TYPE, f.BONCOUPURE,
                    CONCAT(c.NOM, ' ', c.PRENOM) AS CLIENT, q.NOM AS QUARTIER
                    FROM facture_v2 AS f
                    LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
                    LEFT JOIN quartier AS q ON q.ID_QUARTIER = f.ID_QUARTIER
                    $whereClause

                    UNION ALL

                    SELECT fp.NUMERO_FACTURE, fp.NUM_CLIENT, fp.DATEFACTURE, fp.DATEECH,
                    fp.TOTAL, fp.REGLE, fp.RECU, fp.REGLEMENT_TYPE, 0 AS BONCOUPURE,
                    CONCAT(c.NOM, ' ', c.PRENOM) AS CLIENT, q.NOM AS QUARTIER
                    FROM facture_pret AS fp
                    LEFT JOIN client AS c ON c.ID_CLIENT = fp.ID_CLIENT
                    LEFT JOIN quartier AS q ON q.ID_QUARTIER = fp.ID_QUARTIER
                    WHERE fp.NUMERO_FACTURE IN (
                        SELECT NUMERO_FACTURE FROM facture_v2 AS f2
                        LEFT JOIN client AS c2 ON c2.ID_CLIENT = f2.ID_CLIENT
                        $whereClause
                    )
                ) AS facture_full
                GROUP BY NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER, REGLEMENT_TYPE
                ORDER BY DATEFACTURE DESC, ENCAISSE DESC
            ";

            $allData = DB::select($query, array_merge($params, $params));

            // Filtrage par status
            if ($request->status && $request->status != '*') {
                $allData = array_filter($allData, function($row) use ($request) {
                    switch($request->status) {
                        case 'retard': // Factures en retard (past due date)
                            return $row->REGLE == 0 && $row->DATEECH < date('Y-m-d');
                        case 'recouvrement':
                            return $row->REGLEMENT_TYPE == 'RECOUVREMENT';
                        case 'gracier':
                            return $row->REGLEMENT_TYPE == 'GRACIER';
                        case '>': // Arrièré
                            return $row->BONCOUPURE == 1 && $row->REGLE == 1;
                        case '_': // Engagé
                            return $row->REGLE == 0 && $row->TOTAL_RECU > 0;
                        case '1': // Réglé
                            return $row->BONCOUPURE == 0 && $row->REGLE == 1;
                        case '-': // Impayé
                            return $row->REGLE == 0 && $row->TOTAL_RECU == 0;
                        default:
                            return true;
                    }
                });
                $allData = array_values($allData);
            }

            // Métadonnées
            $meta = [
                'total' => array_sum(array_column($allData, 'TOTAL')),
                'total_recu' => array_sum(array_column($allData, 'TOTAL_RECU')),
                'encaisse' => array_sum(array_column($allData, 'ENCAISSE')),
                'count' => count($allData)
            ];

            // Pagination
            $start = $request->start ?? 0;
            $length = $request->length ?? 10;
            $paginatedData = array_slice($allData, $start, $length);

            // Ajouter les détails pour chaque facture
            foreach ($paginatedData as &$facture) {
                $facture->META = $this->facturationService->getFactureDetails($facture->NUMERO_FACTURE);
            }

            DB::commit();

            return response()->json([
                'draw' => $request->draw,
                'recordsTotal' => $meta['count'],
                'recordsFiltered' => $meta['count'],
                'data' => [
                    'meta' => $meta,
                    'result' => $paginatedData
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
public function list(Request $request)
{
    // -------------------------------------------------------------------------
    // 1. VALIDATION
    // -------------------------------------------------------------------------
    $request->validate([
        'numero'       => 'nullable|string|max:50',
        'client'       => 'nullable|string|max:100',
        'quartier'     => 'nullable|string|max:50',
        'date_start'   => 'nullable|date_format:Y-m-d',
        'date_end'     => 'nullable|date_format:Y-m-d',
        'client_usage' => 'nullable|string|max:50',
        'status'       => 'nullable|string|max:30',
        'start'        => 'nullable|integer|min:0',
        'length'       => 'nullable|integer|min:1|max:200',
        'draw'         => 'nullable|integer',
    ]);

    try {
        // -------------------------------------------------------------------------
        // 2. CONSTRUCTION DU WHERE sur facture_v2 / facture_pret
        // -------------------------------------------------------------------------
        $whereParts  = [];
        $whereParams = [];

        if ($request->filled('numero')) {
            $whereParts[]  = 'f.NUMERO_FACTURE = ?';
            $whereParams[] = $request->numero;
        }

        if ($request->filled('client')) {
            if (is_numeric($request->client)) {
                $whereParts[]  = 'c.NUM_CLIENT = ?';
                $whereParams[] = $request->client;
            } else {
                $whereParts[]  = '(c.NOM LIKE ? OR c.PRENOM LIKE ?)';
                $whereParams[] = "%{$request->client}%";
                $whereParams[] = "%{$request->client}%";
            }
        }

        if ($request->filled('quartier') && $request->quartier !== '*') {
            $whereParts[]  = 'c.ID_QUARTIER = ?';
            $whereParams[] = $request->quartier;
        }

        // Par défaut : mois en cours si aucune date n'est fournie
        $dateStart = $request->filled('date_start')
            ? $request->date_start
            : now()->startOfMonth()->format('Y-m-d');

        $dateEnd = $request->filled('date_end')
            ? $request->date_end
            : now()->endOfMonth()->format('Y-m-d');

        $whereParts[]  = 'f.DATEFACTURE >= CAST(? AS DATE)';
        $whereParams[] = $dateStart;

        $whereParts[]  = 'f.DATEFACTURE <= CAST(? AS DATE)';
        $whereParams[] = $dateEnd;

        if ($request->filled('client_usage') && $request->client_usage !== '*') {
            $whereParts[]  = 'c.USED = ?';
            $whereParams[] = $request->client_usage;
        }

        $whereSQL = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        // -------------------------------------------------------------------------
        // 3. FILTRE STATUS → condition SQL sur les colonnes calculées
        //    Appliqué en outer WHERE sur la sous-requête agrégée.
        // -------------------------------------------------------------------------
        $statusSQL    = '';
        $statusParams = [];

        if ($request->filled('status') && $request->status !== '*') {
            switch ($request->status) {
                case 'retard':
                    $statusSQL = 'AND r.REGLE = 0 AND r.DATEECH < CURDATE()';
                    break;
                case 'recouvrement':
                    $statusSQL = "AND r.REGLEMENT_TYPE = 'RECOUVREMENT'";
                    break;
                case 'gracier':
                    $statusSQL = "AND r.REGLEMENT_TYPE = 'GRACIER'";
                    break;
                case '>': // Arriéré : boncoupure + réglé
                    $statusSQL = 'AND r.BONCOUPURE = 1 AND r.REGLE = 1';
                    break;
                case '_': // Engagé : partiellement payé
                    $statusSQL = 'AND r.REGLE = 0 AND r.TOTAL_RECU > 0';
                    break;
                case '1': // Réglé normal
                    $statusSQL = 'AND r.BONCOUPURE = 0 AND r.REGLE = 1';
                    break;
                case '-': // Impayé
                    $statusSQL = 'AND r.REGLE = 0 AND r.TOTAL_RECU = 0';
                    break;
            }
        }

        // -------------------------------------------------------------------------
        // 4. REQUÊTE PRINCIPALE : UNION + JOINs groupés + filtre status en SQL
        //
        //    Stratégie :
        //    - Étape A : UNION ALL facture_v2 + facture_pret filtrés par les IDs
        //                matchant le $whereSQL (appliqué UNE seule fois sur f2)
        //    - Étape B : GROUP BY pour agréger TOTAL, RECU, REGLE, BONCOUPURE
        //    - Étape C : LEFT JOIN sur operation_detail et operation (groupés)
        //                pour ENCAISSE et ATTENTE → plus de sous-requêtes scalaires
        //    - Étape D : Outer WHERE pour le filtre status sur colonnes calculées
        // -------------------------------------------------------------------------

        // Sous-requête : IDs de factures matchant les filtres (sur facture_v2)
        // Utilisée pour filtrer facture_pret sans dupliquer $whereParams
        $idsSubQuery = "
            SELECT f.NUMERO_FACTURE
            FROM facture_v2 AS f
            LEFT JOIN client AS c ON c.ID_CLIENT = f.ID_CLIENT
            $whereSQL
        ";

        $mainQuery = "
            SELECT
                r.NUMERO_FACTURE,
                r.NUM_CLIENT,
                r.CLIENT,
                r.DATEFACTURE,
                r.DATEECH,
                r.QUARTIER,
                r.TOTAL,
                r.TOTAL_RECU,
                r.IS_REGLE,
                r.REGLE,
                r.BONCOUPURE,
                r.REGLEMENT_TYPE,
                IFNULL(od.ENCAISSE, 0)  AS ENCAISSE,
                IFNULL(op.ATTENTE, 0)   AS ATTENTE
            FROM (
                -- Agrégation de l'UNION facture_v2 + facture_pret
                -- REGLEMENT_TYPE pris uniquement depuis facture_v2 via MAX() pour éviter
                -- que la valeur vide de facture_pret crée une ligne GROUP BY séparée
                SELECT
                    NUMERO_FACTURE,
                    NUM_CLIENT,
                    CLIENT,
                    DATEFACTURE,
                    DATEECH,
                    QUARTIER,
                    SUM(TOTAL)                          AS TOTAL,
                    SUM(RECU)                           AS TOTAL_RECU,
                    IF(SUM(REGLE), 1, 0)                AS IS_REGLE,
                    IF(SUM(REGLE) = COUNT(*), 1, 0)     AS REGLE,
                    IF(SUM(BONCOUPURE), 1, 0)           AS BONCOUPURE,
                    MAX(REGLEMENT_TYPE)                 AS REGLEMENT_TYPE
                FROM (
                    -- Branche 1 : factures principales
                    SELECT
                        f.NUMERO_FACTURE, f.NUM_CLIENT, f.DATEFACTURE, f.DATEECH,
                        f.TOTAL, f.REGLE, f.RECU, f.REGLEMENT_TYPE, f.BONCOUPURE,
                        CONCAT(c.NOM, ' ', c.PRENOM) AS CLIENT,
                        q.NOM                         AS QUARTIER
                    FROM facture_v2 AS f
                    LEFT JOIN client   AS c ON c.ID_CLIENT   = f.ID_CLIENT
                    LEFT JOIN quartier AS q ON q.ID_QUARTIER = f.ID_QUARTIER
                    $whereSQL

                    UNION ALL

                    -- Branche 2 : prêts liés aux factures filtrées (REGLEMENT_TYPE vide '')
                    SELECT
                        fp.NUMERO_FACTURE, fp.NUM_CLIENT, fp.DATEFACTURE, fp.DATEECH,
                        fp.TOTAL, fp.REGLE, fp.RECU, fp.REGLEMENT_TYPE, 0 AS BONCOUPURE,
                        CONCAT(c.NOM, ' ', c.PRENOM) AS CLIENT,
                        q.NOM                         AS QUARTIER
                    FROM facture_pret AS fp
                    LEFT JOIN client   AS c ON c.ID_CLIENT   = fp.ID_CLIENT
                    LEFT JOIN quartier AS q ON q.ID_QUARTIER = fp.ID_QUARTIER
                    WHERE fp.NUMERO_FACTURE IN ($idsSubQuery)

                ) AS facture_full
                GROUP BY NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER

            ) AS r

            -- JOIN groupé pour ENCAISSE (remplace sous-requête scalaire)
            LEFT JOIN (
                SELECT ID_OP_TARGET, IFNULL(SUM(MONTANT), 0) AS ENCAISSE
                FROM operation_detail
                GROUP BY ID_OP_TARGET
            ) AS od ON od.ID_OP_TARGET = r.NUMERO_FACTURE

            -- JOIN groupé pour ATTENTE (remplace sous-requête scalaire)
            LEFT JOIN (
                SELECT ID_OP_TARGET, COUNT(*) AS ATTENTE
                FROM operation
                WHERE STATUS = 'ATTENTE'
                GROUP BY ID_OP_TARGET
            ) AS op ON op.ID_OP_TARGET = r.NUMERO_FACTURE

            WHERE 1=1
            $statusSQL
        ";

        // Params pour la requête principale :
        // $whereParams x2 (branche 1 de l'UNION + $idsSubQuery pour la branche 2)
        $mainParams = array_merge($whereParams, $whereParams, $statusParams);

        // -------------------------------------------------------------------------
        // 5. REQUÊTE DE COMPTAGE ET AGRÉGATS (légère, sans LIMIT)
        // -------------------------------------------------------------------------
        $metaQuery = "
            SELECT
                COUNT(*)        AS cnt,
                SUM(r.TOTAL)    AS total,
                SUM(r.TOTAL_RECU) AS total_recu,
                SUM(IFNULL(od.ENCAISSE, 0)) AS encaisse
            FROM ($mainQuery) AS r
            LEFT JOIN (
                SELECT ID_OP_TARGET, IFNULL(SUM(MONTANT), 0) AS ENCAISSE
                FROM operation_detail
                GROUP BY ID_OP_TARGET
            ) AS od ON od.ID_OP_TARGET = r.NUMERO_FACTURE
        ";

        // ⚠️ Note : on ré-encapsule $mainQuery dans metaQuery, donc on double les params
        // Card État : explique pourquoi Montant Total ≠ Total Reçu
        // TOTAL = TOTAL_RECU + Gracié + Recouvert + Impayé + Réductions appliquées
        $metaResult = DB::selectOne(
            "SELECT
                COUNT(*) AS cnt,
                SUM(agg.TOTAL) AS total,
                SUM(agg.TOTAL_RECU) AS total_recu,
                COALESCE(SUM(fr.MONTANT_REDUCTION), 0) AS total_reduction,
                SUM(CASE WHEN agg.REGLEMENT_TYPE = 'GRACIER' THEN agg.TOTAL - agg.TOTAL_RECU ELSE 0 END) AS total_gracie,
                COUNT(CASE WHEN agg.REGLEMENT_TYPE = 'GRACIER' THEN 1 END) AS nb_gracie,
                SUM(CASE WHEN agg.REGLEMENT_TYPE = 'RECOUVREMENT' THEN agg.TOTAL - agg.TOTAL_RECU ELSE 0 END) AS total_recouvrement,
                COUNT(CASE WHEN agg.REGLEMENT_TYPE = 'RECOUVREMENT' THEN 1 END) AS nb_recouvrement,
                SUM(CASE WHEN agg.REGLE = 0 AND agg.REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN agg.TOTAL - agg.TOTAL_RECU ELSE 0 END) AS total_impaye,
                COUNT(CASE WHEN agg.REGLE = 0 AND agg.REGLEMENT_TYPE NOT IN ('GRACIER','RECOUVREMENT') THEN 1 END) AS nb_impaye
             FROM ($mainQuery) AS agg
             LEFT JOIN facture_reduction fr ON fr.NUM_FACTURE = agg.NUMERO_FACTURE",
            $mainParams
        );

        $meta = [
            'count'              => (int)   ($metaResult->cnt               ?? 0),
            'total'              => (float) ($metaResult->total              ?? 0),
            'total_recu'         => (float) ($metaResult->total_recu         ?? 0),
            'total_reduction'    => (float) ($metaResult->total_reduction    ?? 0),
            'total_gracie'       => (float) ($metaResult->total_gracie       ?? 0),
            'nb_gracie'          => (int)   ($metaResult->nb_gracie          ?? 0),
            'total_recouvrement' => (float) ($metaResult->total_recouvrement ?? 0),
            'nb_recouvrement'    => (int)   ($metaResult->nb_recouvrement    ?? 0),
            'total_impaye'       => (float) ($metaResult->total_impaye       ?? 0),
            'nb_impaye'          => (int)   ($metaResult->nb_impaye          ?? 0),
            'periode'            => [
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
                'is_default' => !$request->filled('date_start') && !$request->filled('date_end'),
            ],
        ];

        // -------------------------------------------------------------------------
        // 6. REQUÊTE PAGINÉE (LIMIT / OFFSET)
        // -------------------------------------------------------------------------
        $start  = max(0, (int) ($request->start  ?? 0));
        $length = min(200, max(1, (int) ($request->length ?? 10)));

        $paginatedQuery = "
            $mainQuery
            ORDER BY r.DATEFACTURE DESC, ENCAISSE DESC
            LIMIT ? OFFSET ?
        ";

        $paginatedParams   = array_merge($mainParams, [$length, $start]);
        $paginatedData     = DB::select($paginatedQuery, $paginatedParams);

        // -------------------------------------------------------------------------
        // 7. BATCH getFactureDetails → 1 requête au lieu de N
        // -------------------------------------------------------------------------
        $numeroFactures = array_column($paginatedData, 'NUMERO_FACTURE');
        $allDetails     = [];

        if (!empty($numeroFactures)) {
            $allDetails = $this->facturationService->getFactureDetailsBatch($numeroFactures);
        }

        // Indexer par NUMERO_FACTURE pour accès O(1)
        $detailsMap = [];
        foreach ($allDetails as $detail) {
            $detailsMap[$detail['numero_facture']] = $detail;
        }

        foreach ($paginatedData as &$facture) {
            $facture->META = $detailsMap[$facture->NUMERO_FACTURE] ?? null;
        }
        unset($facture);

        // -------------------------------------------------------------------------
        // 8. RÉPONSE
        // -------------------------------------------------------------------------
        return response()->json([
            'draw'            => (int) ($request->draw ?? 1),
            'recordsTotal'    => $meta['count'],
            'recordsFiltered' => $meta['count'],
            'data' => [
                'meta'   => $meta,
                'result' => $paginatedData,
            ],
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    /**
     * Détails d'une facture
     */
    public function show($numero)
    {
        $details = $this->facturationService->getFactureDetails($numero);

        if (empty($details)) {
            return response()->json(['error' => 'Facture non trouvée'], 404);
        }

        return response()->json(['success' => $details]);
    }

    /**
     * Gracier des factures (pardon de dette)
     */
    public function grace(Request $request)
    {
        // Check if using filters or facture list
        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            // Get factures from filters
            $filters = $request->input('filters', []);
            $factures = $this->getFacturesFromFilters($filters);
        } else {
            // Validate factures array
            $validated = $request->validate([
                'factures' => 'required|array',
                'factures.*' => 'required|string'
            ]);
            $factures = $validated['factures'];
        }

        if (empty($factures)) {
            return response()->json(['errors' => ['general' => 'Aucune facture à gracier']], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($factures as $numero_facture) {
                // Mettre à jour facture_v2
                DB::table('facture_v2')
                    ->where('NUMERO_FACTURE', $numero_facture)
                    ->update([
                        'REGLE' => 1,
                        'IMPAYE' => 0,
                        'REGLEMENT_TYPE' => 'GRACIER'
                    ]);

                // Mettre à jour facture_pret
                DB::table('facture_pret')
                    ->where('NUMERO_FACTURE', $numero_facture)
                    ->update([
                        'REGLE' => 1,
                        'IMPAYE' => 0,
                        'REGLEMENT_TYPE' => 'GRACIER'
                    ]);

                // Mettre à jour les prêts associés
                $prets = DB::table('facture_pret')
                    ->where('NUMERO_FACTURE', $numero_facture)
                    ->get();

                foreach ($prets as $pret) {
                    DB::table('pret')
                        ->where('ID_PRET', $pret->ID_PRET)
                        ->update(['ACTIF' => 0]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($factures) . ' facture(s) graciée(s) avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Mettre factures en recouvrement
     */
    public function recouvrement(Request $request)
    {
        // Check if using filters or facture list
        $useFilters = $request->input('use_filters', false);

        if ($useFilters) {
            // Get factures from filters
            $filters = $request->input('filters', []);
            $factures = $this->getFacturesFromFilters($filters);
        } else {
            // Validate factures array
            $validated = $request->validate([
                'factures' => 'required|array',
                'factures.*' => 'required|string'
            ]);
            $factures = $validated['factures'];
        }

        if (empty($factures)) {
            return response()->json(['errors' => ['general' => 'Aucune facture à mettre en recouvrement']], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($factures as $numero_facture) {
                // Mettre à jour facture_v2
                DB::table('facture_v2')
                    ->where('NUMERO_FACTURE', $numero_facture)
                    ->update([
                        'REGLEMENT_TYPE' => 'RECOUVREMENT'
                    ]);

                // Mettre à jour facture_pret
                DB::table('facture_pret')
                    ->where('NUMERO_FACTURE', $numero_facture)
                    ->update([
                        'REGLEMENT_TYPE' => 'RECOUVREMENT'
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($factures) . ' facture(s) mise(s) en recouvrement avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Get facture numbers from filters
     */
    public function getFacturesFromFilters($filters)
    {
        // Build same query as list() method but return only facture numbers
        $params = [];
        $query = "
            SELECT DISTINCT NUMERO_FACTURE
            FROM (
                SELECT NUMERO_FACTURE, NUM_CLIENT, CLIENT, DATEFACTURE, DATEECH, QUARTIER,
                       TOTAL, TOTAL_RECU, ENCAISSE, IMPAYE, REGLE, BONCOUPURE, REGLEMENT_TYPE
                FROM (
                    SELECT f.NUMERO_FACTURE, f.NUM_CLIENT, CONCAT(c.NOM, ' ', c.PRENOM) as CLIENT,
                           f.DATEFACTURE, f.DATEECH, q.NOM as QUARTIER,
                           SUM(f.TOTAL) as TOTAL, SUM(f.TOTAL_RECU) as TOTAL_RECU,
                           SUM(f.ENCAISSE) as ENCAISSE, SUM(f.IMPAYE) as IMPAYE,
                           MAX(f.REGLE) as REGLE, MAX(f.BONCOUPURE) as BONCOUPURE,
                           MAX(f.REGLEMENT_TYPE) as REGLEMENT_TYPE
                    FROM facture_v2 f
                    LEFT JOIN client c ON f.NUM_CLIENT = c.NUM_CLIENT
                    LEFT JOIN quartier q ON f.ID_QUARTIER = q.ID_QUARTIER";

        // Apply filters
        $where = [];
        if (!empty($filters['numero'])) {
            $where[] = "f.NUMERO_FACTURE LIKE ?";
            $params[] = "%" . $filters['numero'] . "%";
        }
        if (!empty($filters['client'])) {
            $where[] = "(c.NUM_CLIENT LIKE ? OR CONCAT(c.NOM, ' ', c.PRENOM) LIKE ?)";
            $params[] = "%" . $filters['client'] . "%";
            $params[] = "%" . $filters['client'] . "%";
        }
        if (!empty($filters['quartier']) && $filters['quartier'] !== '*') {
            $where[] = "f.ID_QUARTIER = ?";
            $params[] = $filters['quartier'];
        }
        if (!empty($filters['client_usage']) && $filters['client_usage'] !== '*') {
            $where[] = "c.USED = ?";
            $params[] = $filters['client_usage'];
        }
        if (!empty($filters['date_start'])) {
            $where[] = "f.DATEFACTURE >= ?";
            $params[] = $filters['date_start'];
        }
        if (!empty($filters['date_end'])) {
            $where[] = "f.DATEFACTURE <= ?";
            $params[] = $filters['date_end'];
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " GROUP BY f.NUMERO_FACTURE, f.NUM_CLIENT, c.NOM, c.PRENOM, f.DATEFACTURE, f.DATEECH, q.NOM
                ) as factures_base
            ) as all_factures";

        $allData = DB::select($query, $params);

        // Filter by status if specified
        if (!empty($filters['status']) && $filters['status'] !== '*') {
            $allData = array_filter($allData, function($row) use ($filters) {
                switch($filters['status']) {
                    case 'retard':
                        return $row->REGLE == 0 && $row->DATEECH < date('Y-m-d');
                    case 'recouvrement':
                        return $row->REGLEMENT_TYPE == 'RECOUVREMENT';
                    case 'gracier':
                        return $row->REGLEMENT_TYPE == 'GRACIER';
                    case '>':
                        return $row->BONCOUPURE == 1 && $row->REGLE == 1;
                    case '_':
                        return $row->REGLE == 0 && $row->TOTAL_RECU > 0;
                    case '1':
                        return $row->BONCOUPURE == 0 && $row->REGLE == 1;
                    case '-':
                        return $row->REGLE == 0 && $row->TOTAL_RECU == 0;
                    default:
                        return true;
                }
            });
        }

        return array_column($allData, 'NUMERO_FACTURE');
    }

    /**
     * Imprimer une facture (PDF)
     */
    public function print($numero)
    {
        $details = $this->facturationService->getFactureDetails($numero);

        if (empty($details)) {
            abort(404, 'Facture non trouvée');
        }

        // TODO: Générer PDF avec les détails
        return response()->json(['message' => 'Impression PDF - À implémenter', 'details' => $details]);
    }

    /**
     * Page génération factures en masse
     */
    public function generateBulkPage()
    {
        $quartiers = DB::table('quartier')->get();

        return Inertia::render('Factures/GenerateBulk', [
            'quartiers' => $quartiers
        ]);
    }

    /**
     * Générer factures en masse
     */
    public function generateBulk(Request $request)
    {
        $validated = $request->validate([
            'date_debut_releve' => 'required|date',
            'date_fin_releve' => 'required|date',
            'id_quartier' => 'required|array',
            'date_facturation' => 'required|date',
            'date_ech' => 'required|date',
            'remplace_existing' => 'nullable|boolean'
        ]);

        $dateStart = $validated['date_debut_releve'];
        $dateEnd = $validated['date_fin_releve'];
        $quartiers = $validated['id_quartier'];
        $dateFacturation = $validated['date_facturation'];
        $dateEch = $validated['date_ech'];

        // Si remplace_existing n'est pas défini, vérifier les conflits
        if (!isset($validated['remplace_existing'])) {
            // Vérifier s'il existe déjà des factures générées pour cette période
            $conflicts = DB::table('releve as r')
                ->leftJoin('quartier as q', 'r.ID_QUARTIER', '=', 'q.ID_QUARTIER')
                ->leftJoin('facture_v2 as f', 'r.ID_RELEVE', '=', 'f.ID_RELEVE')
                ->select(
                    'r.ID_QUARTIER',
                    'q.NOM',
                    DB::raw('COUNT(CASE WHEN f.DATEFACTURE IS NOT NULL THEN 1 END) as count'),
                    DB::raw('COUNT(*) as total')
                )
                ->whereBetween('r.DATE_INDEX', [$dateStart, $dateEnd])
                ->whereIn('r.ID_QUARTIER', $quartiers)
                ->whereNotNull('f.DATEFACTURE')
                ->groupBy('r.ID_QUARTIER', 'q.NOM')
                ->having('count', '>', 0)
                ->get();

            if ($conflicts->count() > 0) {
                // Il y a des conflits, renvoyer à l'utilisateur
                return response()->json([
                    'pending' => $conflicts
                ]);
            }
        }

        // Pas de conflit OU l'utilisateur a choisi la stratégie
        DB::beginTransaction();

        try {
            // Préparer le numéro de facture
            $day = date('d', strtotime($dateFacturation));
            $month = date('m', strtotime($dateFacturation));
            $year = date('y', strtotime($dateFacturation));
            $numFacturePrefix = "FACT{$day}{$month}{$year}";

            // Récupérer tous les relevés de la période
            $releves = DB::table('releve as r')
                ->leftJoin('facture_v2 as f', 'r.ID_RELEVE', '=', 'f.ID_RELEVE')
                ->select('r.*', 'f.ID_FACTURE', 'f.NUMERO_FACTURE')
                ->whereBetween('r.DATE_INDEX', [$dateStart, $dateEnd])
                ->whereIn('r.ID_QUARTIER', $quartiers);

            // Si on ne remplace pas, filtrer uniquement ceux sans facture
            if (isset($validated['remplace_existing']) && !$validated['remplace_existing']) {
                $releves->whereNull('f.DATEFACTURE');
            }

            $relevesData = $releves->get();
            $count = 0;

            foreach ($relevesData as $releve) {
                // Si facture existe déjà, mettre à jour
                if ($releve->ID_FACTURE) {
                    // Récupérer le client pour vérifier les réductions
                    $client = DB::table('client')->where('NUM_CLIENT', $releve->NUM_CLIENT)->first();

                    // Vérifier s'il existe une réduction applicable
                    $reduction = $client ? $this->getReductionApplicable($client->USED, $dateFacturation) : null;

                    $updateData = [
                        'DATEFACTURE' => $dateFacturation,
                        'DATEECH' => $dateEch,
                        'DATEBONCOUPURE' => $dateEch
                        // IMPORTANT : On ne modifie PAS le TOTAL - il reste le montant original
                    ];

                    DB::table('facture_v2')
                        ->where('ID_FACTURE', $releve->ID_FACTURE)
                        ->update($updateData);

                    // Supprimer les anciennes réductions pour cette facture
                    DB::table('facture_reduction')
                        ->where('NUM_FACTURE', $releve->NUMERO_FACTURE)
                        ->delete();

                    // Si une réduction est applicable, l'enregistrer
                    if ($reduction) {
                        $montantOriginal = $releve->TOTAL;
                        $montantFinal = $this->appliquerReduction($montantOriginal, $reduction->POURCENTAGE);

                        $this->enregistrerReductionAppliquee(
                            $releve->NUMERO_FACTURE,
                            $reduction->ID_REDUCTION,
                            $montantOriginal,
                            $reduction->POURCENTAGE,
                            $montantFinal
                        );
                    }

                    $count++;
                } else {
                    // Créer nouvelle facture (normalement déjà créée par ReleveController)
                    // Mais au cas où, on peut la créer ici
                    $client = DB::table('client')->where('NUM_CLIENT', $releve->NUM_CLIENT)->first();
                    if ($client) {
                        $numeroFacture = $numFacturePrefix . $client->NUM_CLIENT;

                        // Insérer la facture avec le montant ORIGINAL (pas réduit)
                        DB::table('facture_v2')->insert([
                            'NUMERO_FACTURE' => $numeroFacture,
                            'NUM_CLIENT' => $releve->NUM_CLIENT,
                            'ID_RELEVE' => $releve->ID_RELEVE,
                            'TOTAL' => $releve->TOTAL, // Montant ORIGINAL - pas réduit
                            'RECU' => $releve->RECU,
                            'IMPAYE' => $releve->IMPAYE,
                            'REGLE' => $releve->REGLE,
                            'DATEFACTURE' => $dateFacturation,
                            'DATEECH' => $dateEch,
                            'DATEBONCOUPURE' => $dateEch
                        ]);

                        // Vérifier s'il existe une réduction applicable
                        $reduction = $this->getReductionApplicable($client->USED, $dateFacturation);

                        // Si une réduction est applicable, l'enregistrer
                        if ($reduction) {
                            $montantOriginal = $releve->TOTAL;
                            $montantFinal = $this->appliquerReduction($montantOriginal, $reduction->POURCENTAGE);

                            $this->enregistrerReductionAppliquee(
                                $numeroFacture,
                                $reduction->ID_REDUCTION,
                                $montantOriginal,
                                $reduction->POURCENTAGE,
                                $montantFinal
                            );
                        }

                        $count++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "$count facture(s) générée(s) avec succès",
                'count' => $count
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Page mise à jour échéances en masse
     */
    public function updateEcheanceBulkPage()
    {
        $quartiers = DB::table('quartier')->get();

        return Inertia::render('Factures/UpdateEcheanceBulk', [
            'quartiers' => $quartiers
        ]);
    }

    /**
     * Mettre à jour les échéances en masse
     */
    public function updateEcheanceBulk(Request $request)
    {
        $validated = $request->validate([
            'date_debut_facture' => 'required|date',
            'date_fin_facture' => 'required|date',
            'id_quartier' => 'required|array',
            'nouvelle_echeance' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            // Mettre à jour facture_v2
            $updated = DB::table('facture_v2')
                ->whereBetween('DATEFACTURE', [$validated['date_debut_facture'], $validated['date_fin_facture']])
                ->whereIn(DB::raw('(SELECT ID_QUARTIER FROM releve WHERE ID_RELEVE = facture_v2.ID_RELEVE)'), $validated['id_quartier'])
                ->update([
                    'DATEECH' => $validated['nouvelle_echeance'],
                    'DATEBONCOUPURE' => $validated['nouvelle_echeance']
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "$updated facture(s) mise(s) à jour avec succès",
                'count' => $updated
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    // ==================== RÉDUCTIONS ====================

    /**
     * Calculer le montant à payer pour une facture (avec réduction si applicable)
     *
     * @param string $numeroFacture Numéro de la facture
     * @return array ['montant_original' => float, 'montant_a_payer' => float, 'reduction' => object|null]
     */
    public function getMontantAPayer($numeroFacture)
    {
        // Récupérer la facture
        $facture = DB::table('facture_v2')->where('NUMERO_FACTURE', $numeroFacture)->first();

        if (!$facture) {
            return [
                'montant_original' => 0,
                'montant_a_payer' => 0,
                'reduction' => null
            ];
        }

        $montantOriginal = $facture->TOTAL;

        // Vérifier s'il y a une réduction appliquée
        $reduction = DB::table('facture_reduction')
            ->where('NUM_FACTURE', $numeroFacture)
            ->first();

        if ($reduction) {
            return [
                'montant_original' => $montantOriginal,
                'montant_a_payer' => $reduction->MONTANT_APRES_REDUCTION,
                'reduction' => $reduction
            ];
        }

        return [
            'montant_original' => $montantOriginal,
            'montant_a_payer' => $montantOriginal,
            'reduction' => null
        ];
    }

    /**
     * Trouver la réduction applicable pour un client à une date donnée
     *
     * @param int $idUsage ID du type d'usage du client
     * @param string $dateFacturation Date de facturation
     * @return object|null Réduction applicable ou null
     */
    /**
     * Récupérer la réduction applicable pour un type de client et une date donnée
     * PUBLIC: Utilisable par ReleveController pour les factures individuelles
     */
    public function getReductionApplicable($idUsage, $dateFacturation)
    {
        $reductions = DB::table('reduction')
            ->where('ACTIF', 1)
            ->where('DATE_DEBUT', '<=', $dateFacturation)
            ->where('DATE_FIN', '>=', $dateFacturation)
            ->get();

        foreach ($reductions as $reduction) {
            $typesClient = json_decode($reduction->TYPES_CLIENT, true) ?? [];
            if (in_array($idUsage, $typesClient)) {
                return $reduction; // Retourner la première réduction applicable
            }
        }

        return null;
    }

    /**
     * Calculer le montant après application de la réduction
     * PUBLIC: Utilisable par ReleveController pour les factures individuelles
     *
     * @param float $montantOriginal Montant avant réduction
     * @param float $pourcentage Pourcentage de réduction
     * @return float Montant après réduction
     */
    public function appliquerReduction($montantOriginal, $pourcentage)
    {
        $montantReduction = ($montantOriginal * $pourcentage) / 100;
        return round($montantOriginal - $montantReduction, 2);
    }

    /**
     * Enregistrer l'application d'une réduction dans la table facture_reduction
     * PUBLIC: Utilisable par ReleveController pour les factures individuelles
     *
     * @param string $numeroFacture Numéro de la facture
     * @param int $idReduction ID de la réduction appliquée
     * @param float $montantOriginal Montant avant réduction
     * @param float $pourcentage Pourcentage appliqué
     * @param float $montantFinal Montant après réduction
     */
    public function enregistrerReductionAppliquee($numeroFacture, $idReduction, $montantOriginal, $pourcentage, $montantFinal)
    {
        $montantReduction = $montantOriginal - $montantFinal;

        DB::table('facture_reduction')->insert([
            'NUM_FACTURE' => $numeroFacture,
            'ID_REDUCTION' => $idReduction,
            'MONTANT_AVANT_REDUCTION' => $montantOriginal,
            'POURCENTAGE_APPLIQUE' => $pourcentage,
            'MONTANT_REDUCTION' => $montantReduction,
            'MONTANT_APRES_REDUCTION' => $montantFinal,
            'DATE_APPLICATION' => now()
        ]);
    }
}
