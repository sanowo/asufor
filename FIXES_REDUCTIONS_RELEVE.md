# Fix: Réductions non appliquées dans ReleveController

## Problème Identifié

Le système avait **deux chemins** pour créer des factures :

1. **FactureController::generateBulk()** - Génération groupée (bulk)
   - ✅ Vérifiait et appliquait les réductions correctement

2. **ReleveController::store()** - Création individuelle (lors de la saisie de relevé)
   - ❌ **NE vérifiait PAS** les réductions applicables
   - Créait des factures SANS réduction même si le client y avait droit

## Impact du Bug

Quand un agent de terrain entrait un relevé de compteur manuellement :
- La facture était créée avec le montant TOTAL original
- Aucune réduction n'était appliquée
- Le client ne bénéficiait PAS de sa réduction
- Incohérence : factures bulk avaient réductions, factures manuelles non

## Solution Implémentée

### Fichier: ReleveController.php (lignes 211-234)

Après la création de la facture (ligne 209), on a ajouté :

```php
// IMPORTANT: Vérifier si une réduction est applicable
$factureController = app(\App\Http\Controllers\FactureController::class);
$reduction = $factureController->getReductionApplicable(
    $client->USED,
    $validated['date']
);

if ($reduction) {
    // Appliquer la réduction (sans modifier facture_v2.TOTAL)
    $montantOriginal = $factureData['TOTAL'];
    $montantFinal = $factureController->appliquerReduction(
        $montantOriginal,
        $reduction->POURCENTAGE
    );

    // Enregistrer dans facture_reduction
    $factureController->enregistrerReductionAppliquee(
        $num_facture,
        $reduction->ID_REDUCTION,
        $montantOriginal,
        $reduction->POURCENTAGE,
        $montantFinal
    );
}
```

### Fichier: FactureController.php (lignes 710-763)

Trois méthodes rendues **publiques** pour être réutilisables :

1. **getReductionApplicable()** - Trouver la réduction applicable
2. **appliquerReduction()** - Calculer le montant réduit
3. **enregistrerReductionAppliquee()** - Stocker dans facture_reduction

**Avant:** `private function`
**Après:** `public function`

## Principe Architectural Respecté

✅ **facture_v2.TOTAL** reste TOUJOURS le montant original (immuable)
✅ La réduction est stockée dans **facture_reduction**
✅ Les deux chemins (bulk + individuel) appliquent maintenant les réductions
✅ Cohérence totale du système

## Test de Validation

Pour tester que le fix fonctionne :

1. Créer une réduction active (ex: 20% pour "Domestique")
2. Entrer un relevé de compteur pour un client domestique
3. Vérifier que :
   - `facture_v2.TOTAL` = Montant original
   - `facture_reduction` a un enregistrement
   - Le PDF affiche : Montant Original → Réduction → Montant à Payer
   - Le paiement du montant réduit marque la facture comme REGLE

## Fichiers Modifiés

- [ReleveController.php](app/Http/Controllers/ReleveController.php) - Lignes 211-234
- [FactureController.php](app/Http/Controllers/FactureController.php) - Lignes 710-763
- [ARCHITECTURE_REDUCTIONS.md](ARCHITECTURE_REDUCTIONS.md) - Section "Deux Chemins"

## Prochaines Étapes

Les réductions fonctionnent maintenant correctement pour :
- ✅ Génération groupée de factures
- ✅ Saisie manuelle de relevés
- ✅ Paiement avec réductions
- ✅ Auto-réactivation des clients

Il reste à finaliser :
- [ ] Interface de paiement (afficher montant original + réduction)
- [ ] Liste des factures (colonne "À Payer")
- [ ] Rapports de trésorerie (basés sur montants reçus)
