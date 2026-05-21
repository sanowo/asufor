<?php

namespace App\Http\Controllers;

use App\Services\ConsommationService;
use App\Services\FacturationService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReleveController extends Controller
{
    protected $consommationService;
    protected $facturationService;
    protected $analyticsService;

    public function __construct(
        ConsommationService $consommationService,
        FacturationService $facturationService,
        AnalyticsService $analyticsService
    ) {
        $this->consommationService = $consommationService;
        $this->facturationService = $facturationService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Afficher la page des relevés
     */
    public function index()
    {
        $quartiers = DB::table('quartier')->get();
        $usages = DB::table('typeusage')->get();

        return Inertia::render('Releves/Index', [
            'quartiers' => $quartiers,
            'usages' => $usages
        ]);
    }

    /**
     * Liste des relevés avec filtres (DataTable server-side)
     */
    public function list(Request $request)
    {
        $query = DB::table('releve as r')
            ->leftJoin('client as c', 'r.ID_CLIENT', '=', 'c.ID_CLIENT')
            ->leftJoin('quartier as q', 'c.ID_QUARTIER', '=', 'q.ID_QUARTIER')
            ->leftJoin('facture_v2 as f', 'r.ID_INDEX', '=', 'f.ID_RELEVE')
            ->leftJoin('compteur as compt', 'r.ID_COMPTEUR', '=', 'compt.ID_COMPTEUR')
            ->select(
                'r.ID_INDEX',
                'compt.NUM_COMPTEUR',
                'f.NUMERO_FACTURE',
                'r.NUM_CLIENT',
                'r.DATE_INDEX',
                'r.ANCIEN_INDEX',
                DB::raw('CONCAT(c.NOM, " ", c.PRENOM) AS CLIENT'),
                'q.NOM AS QUARTIER',
                'r.RELEVE',
                DB::raw('r.RELEVE - r.ANCIEN_INDEX AS CONSOMMATION'),
                'f.TOTAL',
                DB::raw('(SELECT IF(SUM(MONTANT),SUM(MONTANT),0) FROM operation_detail WHERE ID_OP_TARGET = f.NUMERO_FACTURE AND ID_TYPEOPERATION IN (12,13)) AS ENCAISSE')
            );

        // Filtres
        if ($request->client) {
            if (is_numeric($request->client)) {
                $query->where('c.NUM_CLIENT', $request->client);
            } else {
                $query->where(function($q) use ($request) {
                    $q->where('c.NOM', 'LIKE', '%' . $request->client . '%')
                      ->orWhere('c.PRENOM', 'LIKE', '%' . $request->client . '%');
                });
            }
        }

        if ($request->client_usage && $request->client_usage != '*') {
            $query->where('c.USED', $request->client_usage);
        }

        if ($request->id_quartier && $request->id_quartier != '*') {
            $query->where('c.ID_QUARTIER', $request->id_quartier);
        }

        if ($request->min_index) {
            $query->where('r.RELEVE', '>=', $request->min_index);
        }

        if ($request->max_index) {
            $query->where('r.RELEVE', '<=', $request->max_index);
        }

        if ($request->date_start) {
            $query->whereDate('r.DATE_INDEX', '>=', $request->date_start);
        }

        if ($request->date_end) {
            $query->whereDate('r.DATE_INDEX', '<=', $request->date_end);
        }

        $query->orderBy('r.DATE_LOG', 'DESC');

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;

        $total = $query->count();
        $data = $query->skip($start)->take($length)->get();

        // Métadonnées
        $meta = [
            'consommation' => $data->sum('CONSOMMATION'),
            'total' => $data->sum('TOTAL'),
            'encaisse' => $data->sum('ENCAISSE'),
            'count' => $total
        ];

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => [
                'meta' => $meta,
                'result' => $data
            ]
        ]);
    }

    /**
     * Créer un nouveau relevé
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'date' => 'required|date',
            'ancien_index' => 'required|integer|min:0',
            'nouvel_index' => 'required|integer|min:0|gte:ancien_index',
            'id_compteur' => 'required|integer',
            'num_client' => 'required|string',
            'id_client' => 'required|integer',
            'id_quartier' => 'required|integer',
            'tarif' => 'required|integer'
        ]);

        // Validation date J-1 ou J-2
        $dateValidation = $this->consommationService->validateReleveDate($validated['date']);
        if (!$dateValidation['valid']) {
            return response()->json([
                'errors' => ['date' => $dateValidation['error']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Calcul consommation
            $consommation = $this->consommationService->calculateConsommation(
                $validated['ancien_index'],
                $validated['nouvel_index']
            );

            // Récupérer client
            $client = DB::table('client')->where('ID_CLIENT', $validated['id_client'])->first();

            if (!$client) {
                throw new \Exception('Client non trouvé');
            }

            // Générer numéro facture
            $num_facture = $this->facturationService->generateInvoiceNumber(
                $validated['date'],
                $validated['num_client']
            );

            // Vérifier doublon
            $doublon = DB::table('facture_v2')
                ->join('releve', 'facture_v2.ID_RELEVE', '=', 'releve.ID_INDEX')
                ->where('facture_v2.NUMERO_FACTURE', $num_facture)
                ->where('releve.ID_COMPTEUR', $validated['id_compteur'])
                ->exists();

            if ($doublon) {
                return response()->json([
                    'errors' => ['doublon' => 'Un relevé pour ce compteur et ce mois existe déjà']
                ], 422);
            }

            // INSERT releve
            $releve_id = DB::table('releve')->insertGetId([
                'ID_CLIENT' => $validated['id_client'],
                'NUM_CLIENT' => $validated['num_client'],
                'ID_COMPTEUR' => $validated['id_compteur'],
                'ANCIEN_INDEX' => $validated['ancien_index'],
                'RELEVE' => $validated['nouvel_index'],
                'DATE_INDEX' => $validated['date'],
                'DATE_LOG' => now(),
                'ETAT' => 0
            ]);

            // UPDATE compteur
            DB::table('compteur')
                ->where('ID_COMPTEUR', $validated['id_compteur'])
                ->update(['LAST_RELEVE' => $validated['date']]);

            // Créer facture_v2
            $factureData = $this->facturationService->createInvoiceForReleve([
                'id_releve' => $releve_id,
                'consommation' => $consommation,
                'date' => $validated['date']
            ], (array)$client, $validated['tarif']);

            DB::table('facture_v2')->insert($factureData);

            // IMPORTANT: Vérifier si une réduction est applicable
            $factureController = app(\App\Http\Controllers\FactureController::class);
            $reduction = $factureController->getReductionApplicable(
                $client->USED,
                $validated['date']
            );

            $montantReduction = 0;
            if ($reduction) {
                // Appliquer la réduction (sans modifier facture_v2.TOTAL)
                $montantOriginal = $factureData['TOTAL'];
                $montantFinal = $factureController->appliquerReduction(
                    $montantOriginal,
                    $reduction->POURCENTAGE
                );

                $montantReduction = $montantOriginal - $montantFinal;

                // Enregistrer dans facture_reduction
                $factureController->enregistrerReductionAppliquee(
                    $num_facture,
                    $reduction->ID_REDUCTION,
                    $montantOriginal,
                    $reduction->POURCENTAGE,
                    $montantFinal
                );
            }

            // ANALYTICS: Mettre à jour les stats mensuelles
            $this->analyticsService->handleReleveCreated($validated['date'], $consommation);
            $this->analyticsService->handleFactureCreated(
                $validated['date'],
                $factureData['TOTAL'],
                $factureData['RECU'],
                $factureData['IMPAYE'],
                $factureData['REGLE'],
                $reduction !== null,
                $montantReduction
            );

            // Créer factures_pret (prêts actifs)
            $factures_pret = $this->facturationService->createLoanInvoices(
                $num_facture,
                (array)$client,
                $validated['date']
            );

            foreach ($factures_pret as $facture_pret) {
                DB::table('facture_pret')->insert($facture_pret);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Relevé créé avec succès',
                'releve_id' => $releve_id,
                'numero_facture' => $num_facture
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Supprimer un relevé
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            // Récupérer le relevé
            $releve = DB::table('releve')->where('ID_INDEX', $id)->first();

            if (!$releve) {
                return response()->json(['errors' => ['not_found' => 'Relevé non trouvé']], 404);
            }

            // Supprimer facture_v2
            DB::table('facture_v2')->where('ID_RELEVE', $id)->delete();

            // Supprimer facture_pret associées (même numéro facture)
            $num_facture = DB::table('facture_v2')->where('ID_RELEVE', $id)->value('NUMERO_FACTURE');
            if ($num_facture) {
                DB::table('facture_pret')->where('NUMERO_FACTURE', $num_facture)->delete();
            }

            // Supprimer relevé
            DB::table('releve')->where('ID_INDEX', $id)->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Relevé supprimé avec succès']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => ['general' => $e->getMessage()]], 500);
        }
    }
}
