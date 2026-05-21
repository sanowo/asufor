# Système d'Analytics Mensuelles - Documentation

## Vue d'Ensemble

Ce système permet de **suivre les statistiques en temps réel** sans recalculer constamment toute la base de données (60 000+ lignes).

### Principe : Mise à Jour Incrémentale

Au lieu de faire :
```sql
-- ❌ LENT (60 000 lignes à scanner)
SELECT COUNT(*), SUM(TOTAL) FROM facture_v2 WHERE MONTH(DATEFACTURE) = 12
```

On fait :
```sql
-- ✅ RAPIDE (1 seule ligne)
SELECT * FROM factures_monthly WHERE year = 2025 AND month = 12
```

**Comment ?** Chaque fois qu'une facture/relevé/opération est créée/modifiée/supprimée, on **ajuste les compteurs** dans les tables analytics.

## Architecture

### Tables Créées

#### 1. `releve_monthly`
Statistiques mensuelles des relevés de compteurs

| Colonne | Type | Description |
|---------|------|-------------|
| year | int | Année (ex: 2025) |
| month | int | Mois (1-12) |
| releves_count | int | Nombre de relevés |
| consommation_total | decimal | Consommation totale en m³ |
| consommation_moyenne | decimal | Consommation moyenne calculée |

#### 2. `factures_monthly`
Statistiques mensuelles des factures

| Colonne | Type | Description |
|---------|------|-------------|
| year | int | Année |
| month | int | Mois |
| factures_count | int | Nombre total de factures |
| factures_reglees | int | Nombre de factures payées |
| factures_impayees | int | Nombre de factures impayées |
| montant_total | decimal | Montant total facturé |
| montant_regle | decimal | Montant total payé |
| montant_impaye | decimal | Montant total impayé |
| factures_avec_reduction | int | Nombre de factures avec réduction |
| montant_reductions | decimal | Total des réductions accordées |

#### 3. `caisse_monthly`
Statistiques mensuelles de la caisse (opérations)

| Colonne | Type | Description |
|---------|------|-------------|
| year | int | Année |
| month | int | Mois |
| paiements_count | int | Nombre de paiements |
| paiements_total | decimal | Montant total des paiements |
| remboursements_count | int | Nombre de remboursements prêts |
| remboursements_total | decimal | Montant total remboursé |
| frais_coupure_count | int | Nombre de frais de coupure |
| frais_coupure_total | decimal | Montant total frais coupure |
| operations_confirmees | int | Nombre d'opérations confirmées |
| operations_annulees | int | Nombre d'opérations annulées |
| montant_confirme | decimal | Montant total confirmé |
| montant_annule | decimal | Montant total annulé |

## Utilisation dans le Code

### Service: `AnalyticsService`

Le service est **automatiquement injecté** dans ReleveController et PaiementService.

#### Méthodes Disponibles

**Pour les Relevés:**
```php
// Création d'un relevé
$this->analyticsService->handleReleveCreated($date, $consommation);

// Suppression d'un relevé
$this->analyticsService->handleReleveDeleted($date, $consommation);
```

**Pour les Factures:**
```php
// Création d'une facture
$this->analyticsService->handleFactureCreated(
    $date,
    $total,
    $recu,
    $impaye,
    $regle,
    $hasReduction,    // bool
    $montantReduction // montant de la réduction
);

// Paiement d'une facture
$this->analyticsService->handleFacturePaid(
    $date,
    $montantPaye,
    $wasRegle,  // bool: état avant paiement
    $isRegle    // bool: état après paiement
);

// Suppression d'une facture
$this->analyticsService->handleFactureDeleted(
    $date,
    $total,
    $recu,
    $impaye,
    $regle,
    $hasReduction,
    $montantReduction
);
```

**Pour les Opérations (Caisse):**
```php
// Création d'une opération
$this->analyticsService->handleOperationCreated(
    $date,
    $typeOperation,  // 13=PAIEMENT, 14=PRET, 23=FRAIS_COUPURE
    $montant,
    $status          // 'CONFIRM', 'ATTENTE', 'ANNULE'
);

// Changement de statut
$this->analyticsService->handleOperationStatusChanged(
    $date,
    $typeOperation,
    $montant,
    $oldStatus,
    $newStatus
);
```

#### Récupération des Stats

```php
// Stats d'un mois spécifique
$releveStats = $this->analyticsService->getReleveMonthly(2025, 12);
$factureStats = $this->analyticsService->getFacturesMonthly(2025, 12);
$caisseStats = $this->analyticsService->getCaisseMonthly(2025, 12);

// Stats des 12 derniers mois
$last12Months = $this->analyticsService->getLastNMonths('factures_monthly', 12);
```

## Intégration Actuelle

### ✅ Déjà Intégré

1. **ReleveController::store()**
   - Ligne 246: `handleReleveCreated()`
   - Ligne 247: `handleFactureCreated()`

2. **PaiementService::payWaterBills()**
   - Ligne 127: `handleFacturePaid()` lors des paiements

3. **PaiementService::confirmOperation()**
   - Ligne 342: `handleOperationStatusChanged()` lors de la confirmation

### 🔜 À Intégrer (si nécessaire)

Si vous avez d'autres endroits où vous créez/modifiez/supprimez des données :

- **FactureController::generateBulk()** - Ajouter `handleFactureCreated()` pour chaque facture
- **Opérations d'annulation** - Ajouter `handleOperationStatusChanged()`
- **Suppression de factures** - Ajouter `handleFactureDeleted()`
- **Suppression de relevés** - Ajouter `handleReleveDeleted()`

## Exemple Complet : Dashboard

### Créer un DashboardController

```php
<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

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

        return Inertia::render('Dashboard/Index', [
            'releve_stats' => $releveStats,
            'facture_stats' => $factureStats,
            'caisse_stats' => $caisseStats,
            'factures_chart' => $facturesChart,
            'caisse_chart' => $caisseChart,
        ]);
    }
}
```

### Exemple de Carte Statistique (Frontend)

```jsx
// Dashboard/Index.jsx
export default function Dashboard({ facture_stats }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <StatCard
                title="Factures Émises"
                value={facture_stats?.factures_count || 0}
                subtitle="Ce mois"
                icon="📄"
            />
            <StatCard
                title="Montant Facturé"
                value={`${facture_stats?.montant_total?.toLocaleString() || 0} FCFA`}
                subtitle="Ce mois"
                icon="💰"
            />
            <StatCard
                title="Taux de Recouvrement"
                value={`${facture_stats ? (facture_stats.montant_regle / facture_stats.montant_total * 100).toFixed(1) : 0}%`}
                subtitle="Ce mois"
                icon="✅"
            />
        </div>
    );
}
```

## Migration et Installation

### 1. Créer les Tables

```bash
php artisan migrate
```

Cela crée les 3 tables : `releve_monthly`, `factures_monthly`, `caisse_monthly`

### 2. Peupler avec les Données Historiques (Optionnel)

Si vous voulez avoir les stats des mois précédents, créez une commande :

```php
// app/Console/Commands/PopulateAnalytics.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PopulateAnalytics extends Command
{
    protected $signature = 'analytics:populate {--from=} {--to=}';
    protected $description = 'Peupler les analytics avec les données historiques';

    public function handle()
    {
        $from = $this->option('from') ?: '2020-01-01';
        $to = $this->option('to') ?: Carbon::now()->toDateString();

        $this->info("Calcul des analytics de {$from} à {$to}...");

        // Pour chaque mois dans la plage
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();

        while ($start <= $end) {
            $year = $start->year;
            $month = $start->month;

            $this->info("Traitement de {$year}-{$month}...");

            // RELEVES
            $releveData = DB::table('releve')
                ->whereYear('DATE_INDEX', $year)
                ->whereMonth('DATE_INDEX', $month)
                ->selectRaw('COUNT(*) as count, SUM(CONSOMMATION) as total')
                ->first();

            if ($releveData->count > 0) {
                DB::table('releve_monthly')->updateOrInsert(
                    ['year' => $year, 'month' => $month],
                    [
                        'releves_count' => $releveData->count,
                        'consommation_total' => $releveData->total ?? 0,
                        'consommation_moyenne' => $releveData->total / $releveData->count,
                    ]
                );
            }

            // FACTURES
            $factureData = DB::table('facture_v2')
                ->whereYear('DATEFACTURE', $year)
                ->whereMonth('DATEFACTURE', $month)
                ->selectRaw('
                    COUNT(*) as count,
                    SUM(CASE WHEN REGLE = 1 THEN 1 ELSE 0 END) as reglees,
                    SUM(CASE WHEN REGLE = 0 THEN 1 ELSE 0 END) as impayees,
                    SUM(TOTAL) as total,
                    SUM(RECU) as regle,
                    SUM(IMPAYE) as impaye
                ')
                ->first();

            if ($factureData->count > 0) {
                DB::table('factures_monthly')->updateOrInsert(
                    ['year' => $year, 'month' => $month],
                    [
                        'factures_count' => $factureData->count,
                        'factures_reglees' => $factureData->reglees,
                        'factures_impayees' => $factureData->impayees,
                        'montant_total' => $factureData->total,
                        'montant_regle' => $factureData->regle,
                        'montant_impaye' => $factureData->impaye,
                    ]
                );
            }

            // CAISSE
            $caisseData = DB::table('operation')
                ->whereYear('DATE_OPERATION', $year)
                ->whereMonth('DATE_OPERATION', $month)
                ->where('STATUS', 'CONFIRM')
                ->selectRaw('
                    SUM(CASE WHEN ID_TYPEOPERATION = 13 THEN 1 ELSE 0 END) as paiements_count,
                    SUM(CASE WHEN ID_TYPEOPERATION = 13 THEN MONTANT ELSE 0 END) as paiements_total,
                    COUNT(*) as total_ops,
                    SUM(MONTANT) as total_montant
                ')
                ->first();

            if ($caisseData->total_ops > 0) {
                DB::table('caisse_monthly')->updateOrInsert(
                    ['year' => $year, 'month' => $month],
                    [
                        'paiements_count' => $caisseData->paiements_count,
                        'paiements_total' => $caisseData->paiements_total,
                        'operations_confirmees' => $caisseData->total_ops,
                        'montant_confirme' => $caisseData->total_montant,
                    ]
                );
            }

            $start->addMonth();
        }

        $this->info("✅ Analytics peuplées avec succès !");
    }
}
```

Puis exécuter :

```bash
php artisan analytics:populate --from=2020-01-01 --to=2025-12-31
```

## Avantages

✅ **Performance**: Requêtes instantanées (1 ligne au lieu de 60 000)
✅ **Temps réel**: Stats mises à jour automatiquement
✅ **Fiabilité**: Pas de calculs manuels, pas d'erreurs
✅ **Scalabilité**: Fonctionne même avec des millions de lignes
✅ **Historique**: Garde les stats même si les données sources sont supprimées

## Cas d'Usage

1. **Dashboard d'accueil** - Afficher les stats du mois en cours
2. **Graphiques de tendance** - 12 derniers mois de facturation
3. **Rapports mensuels** - Export Excel des stats par mois
4. **Alertes** - Notification si le montant impayé dépasse un seuil
5. **KPI** - Taux de recouvrement, consommation moyenne, etc.

## Notes Importantes

- Les analytics sont **automatiques** - pas besoin d'action manuelle
- Si une ligne mensuelle n'existe pas, elle est créée automatiquement
- Les compteurs utilisent `GREATEST(x, 0)` pour éviter les valeurs négatives
- Les stats des **mois précédents** peuvent être peuplées avec la commande `analytics:populate`
