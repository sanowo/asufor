<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Page d'accueil - Dashboard général
     */
    public function index()
    {
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        // Stats du mois en cours
        $releveStats = $this->analyticsService->getReleveMonthly($currentYear, $currentMonth);
        $factureStats = $this->analyticsService->getFacturesMonthly($currentYear, $currentMonth);
        $caisseStats = $this->analyticsService->getCaisseMonthly($currentYear, $currentMonth);

        // Stats des 12 derniers mois pour graphiques
        $facturesChart = $this->analyticsService->getLastNMonths('factures_monthly', 12);
        $caisseChart = $this->analyticsService->getLastNMonths('caisse_monthly', 12);

        // Stats globales (toutes tables)
        $totalClients = DB::table('client')->count();
        $clientsActifs = DB::table('client')->where('STATUT', 0)->count();
        $clientsSuspendus = DB::table('client')->where('STATUT', 1)->count();

        $totalCompteurs = DB::table('compteur')->count();
        $compteursFonctionnels = DB::table('compteur')->where('ACTIF', 1)->count();

        $pretsActifs = DB::table('pret')->where('ACTIF', 1)->count();
        $totalPretsImpaye = DB::table('pret')->where('ACTIF', 1)->sum('IMPAYER');

        return Inertia::render('Dashboard/Index', [
            'currentMonth' => Carbon::now()->format('F Y'),
            'releve_stats' => $releveStats,
            'facture_stats' => $factureStats,
            'caisse_stats' => $caisseStats,
            'factures_chart' => $facturesChart,
            'caisse_chart' => $caisseChart,
            'global_stats' => [
                'total_clients' => $totalClients,
                'clients_actifs' => $clientsActifs,
                'clients_suspendus' => $clientsSuspendus,
                'total_compteurs' => $totalCompteurs,
                'compteurs_fonctionnels' => $compteursFonctionnels,
                'prets_actifs' => $pretsActifs,
                'total_prets_impaye' => $totalPretsImpaye,
            ],
        ]);
    }
}
