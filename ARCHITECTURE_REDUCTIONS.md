# Architecture du Système de Réductions - IMPORTANT

## Principe de Base

### ❌ MAUVAISE APPROCHE (Initiale)
```
facture_v2.TOTAL = Montant réduit (ex: 8000 FCFA)
facture_reduction stocke juste l'historique
```

**Problème:** On perd le montant original ! Le caissier et le client ne savent pas si c'est déjà réduit.

### ✅ BONNE APPROCHE (Corrigée)
```
facture_v2.TOTAL = Montant ORIGINAL (ex: 10000 FCFA) - TOUJOURS
facture_reduction.MONTANT_APRES_REDUCTION = Montant à payer (ex: 8000 FCFA)
```

**Avantage:**
- Transparence totale
- Le montant original est TOUJOURS visible
- Le caissier sait qu'une réduction est appliquée
- Le client comprend l'économie réalisée

## Architecture des Tables

### Table `facture_v2`
```sql
NUMERO_FACTURE: FACT050125001
TOTAL: 10000  ← MONTANT ORIGINAL (jamais modifié)
REGLE: 0
IMPAYE: 10000
```

### Table `facture_reduction`
```sql
NUM_FACTURE: FACT050125001
ID_REDUCTION: 1
MONTANT_AVANT_REDUCTION: 10000
POURCENTAGE_APPLIQUE: 20.00
MONTANT_REDUCTION: 2000
MONTANT_APRES_REDUCTION: 8000  ← MONTANT À PAYER
```

## Calcul du Montant à Payer

**RÈGLE D'OR:** Le montant à payer = `facture_reduction.MONTANT_APRES_REDUCTION` SI elle existe, SINON `facture_v2.TOTAL`

### Méthode Helper
```php
// FactureController::getMontantAPayer($numeroFacture)
[
    'montant_original' => 10000,
    'montant_a_payer' => 8000,  // ← À utiliser pour les paiements
    'reduction' => {...}
]
```

## Affichage sur la Facture PDF

```
─────────────────────────────────────
Montant Facture:           10 000 FCFA
─────────────────────────────────────
Réduction [20%]            -2 000 FCFA
  (Promotion Nouvel An 2025)
─────────────────────────────────────
Montant à Payer:            8 000 FCFA  ← EN VERT/GRAS
─────────────────────────────────────
Déjà Payé:                      0 FCFA
─────────────────────────────────────
RESTANT À PAYER:            8 000 FCFA
─────────────────────────────────────
```

## Affichage dans l'Interface de Paiement (Caisse)

### AVANT (Mauvais)
```
Facture FACT050125001
Montant: 8000 FCFA  ← Le caissier ne sait pas si c'est réduit
```

### APRÈS (Bon)
```
Facture FACT050125001
Montant original:  10 000 FCFA
Réduction (20%):   -2 000 FCFA
─────────────────────────────────
Montant à payer:    8 000 FCFA ← CLAIR
```

## Deux Chemins de Création de Factures

Le système a DEUX chemins pour créer des factures :

### 1. Génération Groupée (Bulk) - FactureController::generateBulk()
```php
// Utilisé pour générer toutes les factures d'un coup
// Route: POST /facturation/generate
// Vérifie les réductions pour chaque client
// Applique automatiquement si applicable
```

### 2. Création Individuelle - ReleveController::store()
```php
// Utilisé quand on entre un relevé de compteur manuellement
// Route: POST /releves
// IMPORTANT: Vérifie AUSSI les réductions applicables
// Code ajouté ligne 211-234 de ReleveController.php
```

**CRITIQUE**: Les réductions DOIVENT être vérifiées dans les DEUX chemins !

## Utilisation dans le Code

### ❌ NE PAS FAIRE
```php
// Modifier le montant de la facture
DB::table('facture_v2')
    ->update(['TOTAL' => $montantReduit]); // ERREUR !
```

### ✅ À FAIRE
```php
// Enregistrer uniquement dans facture_reduction
$this->enregistrerReductionAppliquee(
    $numeroFacture,
    $reduction->ID_REDUCTION,
    $montantOriginal,  // 10000
    $pourcentage,      // 20
    $montantFinal      // 8000
);

// La facture_v2.TOTAL reste inchangé à 10000
```

## Interface de Liste des Factures

### Colonnes à afficher
```
N° Facture | Client | Montant | Réduction | À Payer | Restant
FACT001    | Dupont | 10000   | -2000(20%)| 8000    | 8000
FACT002    | Martin | 5000    | -         | 5000    | 2000
```

## Système de Paiement (CaisseController)

Lors d'un paiement, il faut **utiliser le montant à payer** (avec réduction) :

```php
// CaisseController::paiement()
$infos = app(\App\Http\Controllers\FactureController::class)
    ->getMontantAPayer($numeroFacture);

$montantAPayer = $infos['montant_a_payer'];  // 8000 au lieu de 10000
$reduction = $infos['reduction'];

// Afficher à l'utilisateur
if ($reduction) {
    echo "Montant original: {$infos['montant_original']} FCFA\n";
    echo "Réduction: -{$reduction->MONTANT_REDUCTION} FCFA\n";
    echo "À payer: {$montantAPayer} FCFA\n";
}
```

## Cas d'Usage Complets

### 1. Génération de Facture avec Réduction
```php
// 1. Créer la facture (TOTAL = montant ORIGINAL)
DB::table('facture_v2')->insert([
    'TOTAL' => 10000,  // Montant ORIGINAL
    // ...
]);

// 2. Si réduction applicable, l'enregistrer
if ($reduction) {
    $montantReduit = 8000;
    DB::table('facture_reduction')->insert([
        'MONTANT_AVANT_REDUCTION' => 10000,
        'MONTANT_APRES_REDUCTION' => 8000,
        // ...
    ]);
}
```

### 2. Paiement d'une Facture
```php
// 1. Récupérer le montant à payer
$infos = $this->getMontantAPayer($numeroFacture);
$montantAPayer = $infos['montant_a_payer']; // 8000

// 2. Créer l'opération de paiement
DB::table('operation')->insert([
    'MONTANT' => $montantAPayer,  // Utiliser le montant réduit
    // ...
]);
```

### 3. Impression de Facture
```php
// Le template PDF reçoit :
[
    'facture' => [
        'TOTFACTURE' => 10000  // Montant original
    ],
    'reduction' => [
        'MONTANT_REDUCTION' => 2000,
        'MONTANT_APRES_REDUCTION' => 8000
    ]
]

// Et affiche les 3 lignes
```

## Checklist d'Intégration

- [x] FactureController::generateBulk() - N'écrire que dans facture_reduction
- [x] ReleveController::store() - Vérifier et appliquer réductions pour factures individuelles
- [x] PrintController::getFactureData() - Récupérer la réduction
- [x] factures.blade.php - Afficher les 3 montants
- [x] PaiementService::payWaterBills() - Logique REGLE avec réductions
- [x] PaiementService::checkAutoReactivation() - Basé sur REGLE au lieu de IMPAYE
- [ ] CaisseController::paiement() - Utiliser getMontantAPayer()
- [ ] CaisseController::index() - Afficher les réductions dans la liste
- [ ] Factures/Index.jsx - Afficher la colonne "À Payer"
- [ ] API /factures/list - Inclure montant_a_payer dans la réponse

## Questions Fréquentes

### Q: Que se passe-t-il si on supprime une réduction utilisée ?
**R:** On ne peut PAS ! La méthode `destroyReduction()` vérifie que la réduction n'a pas été appliquée à des factures.

### Q: Peut-on modifier le pourcentage d'une réduction après application ?
**R:** OUI, car `facture_reduction.POURCENTAGE_APPLIQUE` est une copie. La modification de `reduction.POURCENTAGE` n'affecte que les NOUVELLES factures.

### Q: Comment savoir si une facture a une réduction ?
**R:**
```php
$reduction = DB::table('facture_reduction')
    ->where('NUM_FACTURE', $numero)
    ->exists();
```

### Q: Lors du paiement partiel, on paie sur quel montant ?
**R:** Sur le **montant à payer** (réduit). Si facture = 10000, réduction = 2000, et client paie 5000, il reste 3000 (sur les 8000 à payer), pas 5000.

## Résumé

🎯 **LA RÈGLE:** `facture_v2.TOTAL` = Source de vérité IMMUABLE
🎯 **PAIEMENTS:** Toujours utiliser `getMontantAPayer()` ou `facture_reduction.MONTANT_APRES_REDUCTION`
🎯 **AFFICHAGE:** Toujours montrer les 3 lignes si réduction existe
