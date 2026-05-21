# Gestion des Impayés avec Réductions

## Problématique

Avec le système de réductions, il y a un **décalage logique** entre :
- `TOTAL` = Montant original (ex: 10000 FCFA)
- `Montant à payer` = Montant après réduction (ex: 8000 FCFA)
- `RECU` = Montant encaissé
- `IMPAYE` = Reste à payer

## ❌ Approche Incorrecte

```sql
TOTAL = 10000  (original)
RECU = 8000    (client a payé le montant réduit)
IMPAYE = TOTAL - RECU = 2000  ← FAUX ! Le client a tout payé!
REGLE = 0  ← FAUX ! La facture est réglée!
```

## ✅ Approche Correcte

### Option 1 : IMPAYE basé sur le montant réduit (RECOMMANDÉE)

```sql
-- Lors de la génération
TOTAL = 10000
IMPAYE = 8000  (montant_apres_reduction, pas TOTAL)
RECU = 0
REGLE = 0

-- Après paiement de 8000 FCFA
TOTAL = 10000  (toujours original)
RECU = 8000
IMPAYE = 0  (8000 - 8000)
REGLE = 1  ← CORRECT !
```

**Avantage :** Cohérence comptable - `IMPAYE` représente vraiment ce qui reste à payer

### Option 2 : IMPAYE basé sur TOTAL, mais logique REGLE modifiée (ACTUELLE)

```sql
-- Lors de la génération
TOTAL = 10000
IMPAYE = 10000  (comme d'habitude)
RECU = 0
REGLE = 0

-- Après paiement de 8000 FCFA
TOTAL = 10000
RECU = 8000
IMPAYE = 2000  (10000 - 8000)
REGLE = 1  ← Déterminé par comparaison avec MONTANT_APRES_REDUCTION
```

**Avantage :** Pas de modification de la logique de génération
**Inconvénient :** `IMPAYE` ne représente pas vraiment l'impayé réel

## Solution Implémentée : Option 2 (Logique REGLE modifiée)

### Dans PaiementService.php

```php
// SANS réduction
$is_regle = ($new_impaye == 0) ? 1 : 0;

// AVEC réduction
$reduction = DB::table('facture_reduction')
    ->where('NUM_FACTURE', $numero_facture)
    ->first();

if ($reduction) {
    // Comparer RECU avec MONTANT_APRES_REDUCTION
    $is_regle = ($new_recu >= $reduction->MONTANT_APRES_REDUCTION) ? 1 : 0;
} else {
    // Logique normale
    $is_regle = ($new_impaye == 0) ? 1 : 0;
}
```

### Exemple Complet

#### Facture avec réduction 20%

1. **Génération (FactureController)**
```sql
INSERT INTO facture_v2 (TOTAL, RECU, IMPAYE, REGLE)
VALUES (10000, 0, 10000, 0);

INSERT INTO facture_reduction (MONTANT_APRES_REDUCTION)
VALUES (8000);
```

2. **Paiement de 5000 FCFA (PaiementService)**
```sql
UPDATE facture_v2 SET
  RECU = 5000,
  IMPAYE = 5000,  -- 10000 - 5000
  REGLE = 0       -- 5000 < 8000 (montant_apres_reduction)
```

3. **Paiement de 3000 FCFA supplémentaires**
```sql
UPDATE facture_v2 SET
  RECU = 8000,    -- 5000 + 3000
  IMPAYE = 2000,  -- 10000 - 8000
  REGLE = 1       -- 8000 >= 8000 (montant_apres_reduction) ✓
```

## Gestion de l'Auto-Réactivation

Dans `checkAutoReactivation()`, il faut également modifier la logique :

```php
// AVANT (incorrect avec réductions)
$total_impaye = DB::table('facture_v2')
    ->where('NUM_CLIENT', $num_client)
    ->sum('IMPAYE');

if ($total_impaye == 0) {
    // Réactiver
}

// APRÈS (correct avec réductions)
$factures_impayees = DB::table('facture_v2')
    ->where('NUM_CLIENT', $num_client)
    ->where('REGLE', 0)  // Utiliser REGLE au lieu de IMPAYE
    ->count();

if ($factures_impayees == 0) {
    // Réactiver
}
```

## Affichage dans l'Interface

### Liste des Factures

```
N° Facture | Montant | Réduction | À Payer | Reçu  | Reste | Statut
FACT001    | 10 000  | -2000(20%)| 8 000   | 8 000 | 2 000 | RÉGLÉ ✓
FACT002    | 5 000   | -         | 5 000   | 2 000 | 3 000 | IMPAYÉ
```

**Important :** La colonne "Reste" affiche `IMPAYE`, mais on affiche aussi "À Payer" pour clarté

### Interface de Paiement

```
Facture: FACT001
─────────────────────────────────
Montant facture:       10 000 FCFA
Réduction (20%):       -2 000 FCFA
─────────────────────────────────
Montant à payer:        8 000 FCFA ✓
Déjà reçu:              5 000 FCFA
─────────────────────────────────
RESTE À PAYER:          3 000 FCFA
```

**Calcul :** Reste = `MONTANT_APRES_REDUCTION - RECU` (pas `IMPAYE`)

## Reporting et Comptabilité

### Chiffre d'Affaires

```php
// CA = Somme des montants REÇUS (pas TOTAL)
$ca = DB::table('facture_v2')->sum('RECU');
```

### Impayés Réels

```php
// Pour les factures SANS réduction
$impaye_sans_reduction = DB::table('facture_v2 as f')
    ->leftJoin('facture_reduction as fr', 'f.NUMERO_FACTURE', '=', 'fr.NUM_FACTURE')
    ->whereNull('fr.ID_FACTURE_REDUCTION')
    ->sum('f.IMPAYE');

// Pour les factures AVEC réduction
$impaye_avec_reduction = DB::table('facture_v2 as f')
    ->join('facture_reduction as fr', 'f.NUMERO_FACTURE', '=', 'fr.NUM_FACTURE')
    ->selectRaw('SUM(fr.MONTANT_APRES_REDUCTION - f.RECU) as impaye_reel')
    ->value('impaye_reel');

$total_impaye = $impaye_sans_reduction + $impaye_avec_reduction;
```

## Cas Particuliers

### Paiement Excédentaire

Si un client paie **plus** que le montant réduit :

```sql
-- Facture avec réduction
TOTAL = 10000
MONTANT_APRES_REDUCTION = 8000

-- Client paie 9000 FCFA
RECU = 9000
IMPAYE = 1000  (10000 - 9000)
REGLE = 1  (9000 >= 8000) ✓

-- Les 1000 FCFA supplémentaires restent en avance
```

### Annulation de Réduction

Si une réduction est **désactivée** après application :

```sql
-- La réduction reste dans facture_reduction
-- Le MONTANT_APRES_REDUCTION ne change PAS
-- Les factures déjà générées gardent leur réduction

-- Seules les NOUVELLES factures ne bénéficient plus de la réduction
```

### Paiement Partiel

```sql
-- Facture 10000 FCFA, réduction 20% → à payer 8000
-- Client paie 3000 FCFA

RECU = 3000
IMPAYE = 7000
REGLE = 0  (3000 < 8000)

-- Reste à payer : 8000 - 3000 = 5000 FCFA (à afficher)
-- IMPAYE = 7000 (différence technique)
```

## Résumé des Règles

1. **`TOTAL`** = Toujours le montant ORIGINAL (jamais modifié)
2. **`IMPAYE`** = `TOTAL - RECU` (logique technique)
3. **`REGLE`** = Déterminé par comparaison `RECU >= MONTANT_APRES_REDUCTION` si réduction
4. **Montant à afficher au client** = `MONTANT_APRES_REDUCTION - RECU`
5. **Trésorerie** = Basée sur `RECU`, pas sur `TOTAL`
6. **Auto-réactivation** = Basée sur `REGLE = 1` pour toutes les factures

## Checklist de Validation

- [ ] Facture avec réduction 100% → Client paie 0 → REGLE = 1
- [ ] Facture avec réduction 50% → Client paie 50% → REGLE = 1
- [ ] Facture avec réduction 20% → Client paie montant réduit → REGLE = 1
- [ ] Facture avec réduction 20% → Client paie montant TOTAL → REGLE = 1 + excédent
- [ ] Client suspendu → Paie toutes factures réduites → Auto-réactivation
- [ ] Reporting CA → Utilise RECU, pas TOTAL
- [ ] Interface caisse → Affiche montant à payer correct
