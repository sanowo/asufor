# Tests de Vérification - Modules Compteurs et Prêts

## Checklist de Tests

### Module Compteurs

#### Page `/compteurs`
- [ ] La page charge correctement
- [ ] Les filtres fonctionnent (recherche, quartier, statut)
- [ ] Le tableau affiche les compteurs existants
- [ ] Le bouton "+ Nouveau Compteur" ouvre le modal
- [ ] Création d'un compteur fonctionne
- [ ] La validation anti-doublon fonctionne (même N° pour même client)
- [ ] Modification d'un compteur fonctionne
- [ ] Suppression d'un compteur fonctionne
- [ ] Impossible de supprimer un compteur avec relevés

#### Intégration dans Page Clients
- [ ] Section Compteurs affiche les compteurs du client
- [ ] Bouton "+ Ajouter Compteur" visible
- [ ] Modal d'ajout s'ouvre avec infos client pré-remplies
- [ ] Création depuis modal client fonctionne
- [ ] Modal de modification s'ouvre avec données correctes
- [ ] Modification depuis modal client fonctionne
- [ ] Suppression depuis modal client fonctionne
- [ ] Les données se rechargent automatiquement après chaque opération
- [ ] Les badges de statut s'affichent correctement (Vert/Rouge)

### Module Prêts

#### Page `/prets`
- [ ] La page charge correctement
- [ ] Les 4 statistiques s'affichent correctement
- [ ] Les filtres fonctionnent (recherche, statut, dates)
- [ ] Le tableau affiche les prêts existants
- [ ] Le bouton "+ Nouveau Prêt" ouvre le modal
- [ ] Création d'un prêt fonctionne
- [ ] La validation du N° client fonctionne
- [ ] Modification d'un prêt fonctionne
- [ ] Le recalcul de l'impayé est correct
- [ ] Suspension d'un prêt fonctionne
- [ ] Réactivation d'un prêt fonctionne
- [ ] Suppression d'un prêt sans paiement fonctionne
- [ ] Impossible de supprimer un prêt avec paiements (PAYER > 0)
- [ ] Les montants sont formatés en FCFA avec séparateurs

## Tests Unitaires Backend

### CompteurController

#### Test 1: Créer un compteur
```bash
POST /compteurs
{
    "id_client": 1,
    "num_compteur": "TEST-001",
    "date_start": "2024-01-01",
    "actif": 1
}
```
**Résultat attendu**: Status 200, message succès, ID retourné

#### Test 2: Tentative doublon
```bash
POST /compteurs
{
    "id_client": 1,
    "num_compteur": "TEST-001",
    "date_start": "2024-01-01",
    "actif": 1
}
```
**Résultat attendu**: Status 422, erreur "Ce numéro de compteur est déjà utilisé pour ce client"

#### Test 3: Client inexistant
```bash
POST /compteurs
{
    "id_client": 99999,
    "num_compteur": "TEST-002",
    "date_start": "2024-01-01",
    "actif": 1
}
```
**Résultat attendu**: Status 422, erreur validation "id_client"

#### Test 4: Liste avec filtres
```bash
GET /compteurs/list?search=TEST&quartier=1&status=1
```
**Résultat attendu**: Status 200, JSON avec data paginée

#### Test 5: Modifier un compteur
```bash
PUT /compteurs/1
{
    "num_compteur": "TEST-001-MODIFIED",
    "actif": 0
}
```
**Résultat attendu**: Status 200, message succès

#### Test 6: Supprimer compteur avec relevés
```bash
DELETE /compteurs/1
```
**Résultat attendu**: Status 422, erreur "Impossible de supprimer ce compteur car il a des relevés associés"

#### Test 7: Obtenir compteurs d'un client
```bash
GET /compteurs/client/CL001
```
**Résultat attendu**: Status 200, JSON array des compteurs

### PretController

#### Test 8: Créer un prêt
```bash
POST /prets
{
    "num_client": "CL001",
    "montant": 500000,
    "motif": "Achat compteur",
    "date_pret": "2024-01-01",
    "tranche": 50000,
    "mensualite": 10
}
```
**Résultat attendu**: Status 200, message succès, ID retourné

#### Test 9: Client inexistant
```bash
POST /prets
{
    "num_client": "XXXX",
    "montant": 500000,
    "motif": "Test",
    "date_pret": "2024-01-01",
    "tranche": 50000,
    "mensualite": 10
}
```
**Résultat attendu**: Status 422, erreur "num_client"

#### Test 10: Liste avec statistiques
```bash
GET /prets/list?search=CL001&status=1
```
**Résultat attendu**: Status 200, JSON avec data + meta (count, total_montant, total_paye, total_impaye)

#### Test 11: Modifier un prêt
```bash
PUT /prets/1
{
    "montant": 600000,
    "tranche": 60000,
    "mensualite": 10
}
```
**Résultat attendu**: Status 200, IMPAYER recalculé = 600000 - PAYER

#### Test 12: Suspendre un prêt
```bash
POST /prets/1/suspend
```
**Résultat attendu**: Status 200, ACTIF = 0

#### Test 13: Réactiver un prêt
```bash
POST /prets/1/reactivate
```
**Résultat attendu**: Status 200, ACTIF = 1

#### Test 14: Supprimer prêt sans paiement
```bash
DELETE /prets/1
```
**Résultat attendu**: Status 200 si PAYER = 0

#### Test 15: Supprimer prêt avec paiements
```bash
DELETE /prets/1
```
**Résultat attendu**: Status 422 si PAYER > 0, erreur "Impossible de supprimer ce prêt car des paiements ont été effectués"

## Tests d'Intégration

### Test 16: Flux complet Compteur depuis Client
1. Ouvrir détails d'un client
2. Cliquer "+ Ajouter Compteur"
3. Remplir formulaire (N°, Date, Statut)
4. Soumettre
5. Vérifier que le compteur apparaît dans le tableau
6. Cliquer "Modifier" sur le compteur
7. Changer le statut
8. Soumettre
9. Vérifier le badge de statut
10. Cliquer "Supprimer"
11. Confirmer
12. Vérifier que le compteur disparaît

### Test 17: Flux complet Prêt
1. Aller sur `/prets`
2. Vérifier les statistiques (doivent être à 0 ou cohérentes)
3. Cliquer "+ Nouveau Prêt"
4. Remplir tous les champs
5. Soumettre
6. Vérifier l'ajout dans le tableau
7. Vérifier les statistiques mises à jour
8. Cliquer "Modifier"
9. Changer le montant
10. Vérifier le recalcul de l'impayé
11. Cliquer "Suspendre"
12. Vérifier le badge "Inactif"
13. Cliquer "Réactiver"
14. Vérifier le badge "Actif"

## Scénarios de Edge Cases

### Edge Case 1: Compteur avec espaces
```bash
POST /compteurs
{
    "id_client": 1,
    "num_compteur": "  TEST-001  ",
    "date_start": "2024-01-01",
    "actif": 1
}
```
**Résultat attendu**: trim() appliqué, pas d'espaces stockés

### Edge Case 2: Prêt avec mensualité = 0
```bash
POST /prets
{
    "num_client": "CL001",
    "montant": 500000,
    "motif": "Test",
    "date_pret": "2024-01-01",
    "tranche": 50000,
    "mensualite": 0
}
```
**Résultat attendu**: Status 422, erreur "mensualite must be at least 1"

### Edge Case 3: Modification prêt avec PAYER > MONTANT
```bash
PUT /prets/1
{
    "montant": 100000,
    "tranche": 10000,
    "mensualite": 10
}
```
**Si PAYER = 150000**
**Résultat attendu**: IMPAYER = -50000 (comportement à valider)

## Tests de Performance

### Test Perf 1: Liste de 1000 compteurs
- Temps de chargement < 2 secondes
- Pagination fonctionne correctement

### Test Perf 2: Filtres avec grande quantité de données
- Recherche instantanée (< 500ms)
- Filtres combinés fonctionnent

### Test Perf 3: Statistiques sur 500 prêts
- Calcul des meta rapide (< 1 seconde)
- Affichage correct des totaux

## Validation Visuelle

### Design Compteurs
- [ ] Layout responsive
- [ ] Modals centrés
- [ ] Badges couleurs cohérentes (Vert/Rouge)
- [ ] Boutons bien alignés
- [ ] Messages d'erreur visibles

### Design Prêts
- [ ] Cartes statistiques alignées
- [ ] Montants formatés avec séparateurs
- [ ] Couleurs cohérentes (Vert pour payé, Rouge pour impayé)
- [ ] Tableau responsive
- [ ] Modals bien proportionnés

## Tests de Sécurité

### Sécurité 1: Injection SQL
- Tester avec `search='; DROP TABLE pret; --`
- Résultat attendu: Requête échappée, pas d'injection

### Sécurité 2: XSS
- Tester avec `motif=<script>alert('XSS')</script>`
- Résultat attendu: Texte échappé, pas d'exécution

### Sécurité 3: Validation côté serveur
- Bypass validation frontend et envoyer données invalides
- Résultat attendu: Validation backend rejette

## Résultat Final

**Date de test**: _____________

**Testeur**: _____________

**Modules testés**:
- [ ] Compteurs: ___/10 tests réussis
- [ ] Prêts: ___/10 tests réussis
- [ ] Intégration Client: ___/10 tests réussis

**Bugs trouvés**: _____________

**Status global**:
- [ ] PRÊT POUR PRODUCTION
- [ ] CORRECTIONS MINEURES NÉCESSAIRES
- [ ] CORRECTIONS MAJEURES NÉCESSAIRES

**Notes**:
___________________________________________________
___________________________________________________
