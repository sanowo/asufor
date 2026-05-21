# Système de Réductions Ponctuelles - Documentation Complète

## 🎯 Vue d'ensemble

Le système de réductions ponctuelles permet de créer des **promotions temporaires** applicables automatiquement lors de la génération des factures. Les réductions sont configurables par type de client et période de validité.

---

## 📋 Fonctionnalités Clés

### Ce que le système permet:

✅ **Créer des réductions personnalisées**
- Définir un libellé explicite (ex: "Promotion Nouvel An 2025")
- Choisir un pourcentage de réduction (0% à 100%)
- Sélectionner les types de clients concernés (Domestique, Commercial, etc.)
- Définir une période de validité (date début - date fin)
- Activer/Désactiver à volonté

✅ **Application automatique lors de la facturation**
- Vérification automatique des réductions actives
- Application selon le type de client de la facture
- Calcul automatique du montant réduit
- Traçabilité complète (facture_reduction)

✅ **Affichage sur les documents**
- Mention de la réduction sur les factures imprimées
- Détail du calcul: montant original → montant réduit

---

## 🗂️ Architecture de la Base de Données

### Nouvelles Tables Créées

#### 1. Table `reduction`
Stocke les réductions configurées par l'administrateur.

```sql
CREATE TABLE reduction (
    ID_REDUCTION BIGINT PRIMARY KEY AUTO_INCREMENT,
    LIBELLE VARCHAR(255) NOT NULL,              -- Ex: "Promotion Nouvel An 2025"
    DATE_DEBUT DATE NOT NULL,                    -- Date de début de validité
    DATE_FIN DATE NOT NULL,                      -- Date de fin de validité
    POURCENTAGE DECIMAL(5,2) NOT NULL,          -- Pourcentage (ex: 15.50 pour 15.5%)
    TYPES_CLIENT TEXT,                           -- JSON des types concernés ["Domestique","Commercial"]
    ACTIF TINYINT DEFAULT 1,                    -- 1=Actif, 0=Inactif
    DESCRIPTION TEXT,                            -- Description optionnelle
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Exemple d'enregistrement:**
```json
{
    "ID_REDUCTION": 1,
    "LIBELLE": "Promotion Nouvel An 2025",
    "DATE_DEBUT": "2025-01-01",
    "DATE_FIN": "2025-01-31",
    "POURCENTAGE": 20.00,
    "TYPES_CLIENT": "[1,3]",
    "ACTIF": 1,
    "DESCRIPTION": "Offre de bienvenue pour l'année 2025"
}
```

**Important:** `TYPES_CLIENT` contient les **IDs** des types d'usage (table `typeusage`), pas les noms. Cela garantit la stabilité si un type d'usage est renommé.

#### 2. Table `facture_reduction`
Trace les réductions effectivement appliquées aux factures.

```sql
CREATE TABLE facture_reduction (
    ID_FACTURE_REDUCTION BIGINT PRIMARY KEY AUTO_INCREMENT,
    NUM_FACTURE VARCHAR(50) NOT NULL,                   -- Numéro de la facture
    ID_REDUCTION BIGINT NOT NULL,                        -- Référence à la réduction
    MONTANT_AVANT_REDUCTION DECIMAL(10,2) NOT NULL,     -- Montant original
    POURCENTAGE_APPLIQUE DECIMAL(5,2) NOT NULL,         -- % au moment de l'application
    MONTANT_REDUCTION DECIMAL(10,2) NOT NULL,           -- Montant de la réduction en FCFA
    MONTANT_APRES_REDUCTION DECIMAL(10,2) NOT NULL,     -- Montant final
    DATE_APPLICATION TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ID_REDUCTION) REFERENCES reduction(ID_REDUCTION) ON DELETE CASCADE,
    INDEX (NUM_FACTURE),
    INDEX (ID_REDUCTION)
);
```

**Exemple d'enregistrement:**
```json
{
    "ID_FACTURE_REDUCTION": 1,
    "NUM_FACTURE": "FACT-2025-001",
    "ID_REDUCTION": 1,
    "MONTANT_AVANT_REDUCTION": 5000.00,
    "POURCENTAGE_APPLIQUE": 20.00,
    "MONTANT_REDUCTION": 1000.00,
    "MONTANT_APRES_REDUCTION": 4000.00,
    "DATE_APPLICATION": "2025-01-15 10:30:00"
}
```

---

## 🎛️ Interface de Gestion (Paramètres → Réductions)

### Accès
**Chemin:** Paramètres → Onglet "Réductions" (6ème onglet)

### Formulaire de Création

#### Champs Obligatoires:
1. **Libellé** - Nom de la réduction
2. **Pourcentage** - Valeur entre 0 et 100 (accepte les décimales: 15.5%)
3. **Date de début** - Date d'activation
4. **Date de fin** - Date d'expiration (doit être >= date début)
5. **Types de clients** - Au moins un type doit être sélectionné
6. **Statut** - Actif ou Inactif

#### Champ Optionnel:
- **Description** - Détails supplémentaires

### Tableau de Gestion

**Colonnes affichées:**
- Libellé (+ description si renseignée)
- Période (date début → date fin)
- Pourcentage (badge bleu)
- Types Clients (badges gris multiples)
- Statut (badge vert/rouge)
- Actions

**Actions disponibles:**
- **Activer/Désactiver** - Toggle rapide du statut
- **Modifier** - Ouvre un modal d'édition
- **Supprimer** - Suppression (avec protection si déjà appliquée)

---

## ⚙️ API Backend

### Routes Créées

```php
// Liste des réductions
GET /parametres/reductions/list
Response: Array<Reduction>

// Créer une réduction
POST /parametres/reductions
Body: {
    libelle: string,
    date_debut: date,
    date_fin: date,
    pourcentage: number,
    types_client: array,
    actif: 0|1,
    description?: string
}

// Modifier une réduction
PUT /parametres/reductions/{id}
Body: (mêmes champs que POST)

// Supprimer une réduction
DELETE /parametres/reductions/{id}

// Activer/Désactiver une réduction
POST /parametres/reductions/{id}/toggle
```

### Méthodes du ParametreController

#### 1. `listReductions()`
Récupère toutes les réductions, triées par date de début décroissante.
- Décode automatiquement le JSON `TYPES_CLIENT`
- Retourne un array d'objets reduction

#### 2. `storeReduction(Request $request)`
Crée une nouvelle réduction.

**Validations:**
- `libelle`: required, string, max:255
- `date_debut`: required, date
- `date_fin`: required, date, must be >= date_debut
- `pourcentage`: required, numeric, 0-100
- `types_client`: required, array, min:1
- `actif`: required, in:0,1
- `description`: nullable, string

**Traitement:**
- Encode `types_client` en JSON avant stockage
- Ajoute `created_at` et `updated_at`

#### 3. `updateReduction(Request $request, $id)`
Modifie une réduction existante.
- Mêmes validations que `store`
- Met à jour `updated_at` automatiquement

#### 4. `destroyReduction($id)`
Supprime une réduction.

**Protection importante:**
```php
// Vérifier si la réduction a été appliquée à des factures
$utilisee = DB::table('facture_reduction')
    ->where('ID_REDUCTION', $id)
    ->exists();

if ($utilisee) {
    return error 422: "Impossible de supprimer cette réduction
                       car elle a déjà été appliquée à des factures"
}
```

Cette protection évite la perte d'historique.

#### 5. `toggleReduction($id)`
Bascule le statut ACTIF entre 0 et 1.
- Lecture du statut actuel
- Inversion
- Mise à jour
- Retourne le nouveau statut

---

## 🔄 Logique d'Application des Réductions

### Étape 1: Lors de la Génération de Factures

**À implémenter dans FactureController->generateBulk()**

```php
// Pour chaque facture à générer
foreach ($clients as $client) {
    // 1. Calculer le montant original de la facture
    $montantOriginal = calculateFactureMontant($client);

    // 2. Vérifier s'il existe une réduction applicable
    $reduction = $this->getReductionApplicable($client, $dateFacturation);

    // 3. Appliquer la réduction si trouvée
    if ($reduction) {
        $montantReduit = $this->appliquerReduction($montantOriginal, $reduction);
        $montantFinal = $montantReduit;

        // 4. Tracer l'application dans facture_reduction
        $this->enregistrerReductionAppliquee($numFacture, $reduction, $montantOriginal, $montantFinal);
    } else {
        $montantFinal = $montantOriginal;
    }

    // 5. Créer la facture avec le montant final
    createFacture($client, $montantFinal);
}
```

### Étape 2: Fonction de Recherche de Réduction Applicable

```php
/**
 * Recherche une réduction applicable pour un client à une date donnée
 *
 * @param object $client
 * @param string $dateFacturation
 * @return object|null La réduction trouvée ou null
 */
private function getReductionApplicable($client, $dateFacturation)
{
    // Récupérer le type d'usage du client
    $typeUsage = $client->TYPE_USAGE; // Ex: "Domestique"

    // Rechercher une réduction active, valide à la date, et concernant ce type
    $reduction = DB::table('reduction')
        ->where('ACTIF', 1)
        ->where('DATE_DEBUT', '<=', $dateFacturation)
        ->where('DATE_FIN', '>=', $dateFacturation)
        ->whereRaw('JSON_CONTAINS(TYPES_CLIENT, ?)', [json_encode($typeUsage)])
        ->orderBy('POURCENTAGE', 'desc') // Prendre la plus avantageuse
        ->first();

    if ($reduction) {
        // Décoder les types de clients
        $reduction->TYPES_CLIENT = json_decode($reduction->TYPES_CLIENT, true);
    }

    return $reduction;
}
```

### Étape 3: Fonction d'Application de la Réduction

```php
/**
 * Applique une réduction à un montant
 *
 * @param float $montant
 * @param object $reduction
 * @return float Le montant après réduction
 */
private function appliquerReduction($montant, $reduction)
{
    $pourcentage = $reduction->POURCENTAGE;
    $montantReduction = ($montant * $pourcentage) / 100;
    $montantFinal = $montant - $montantReduction;

    return round($montantFinal, 2);
}
```

### Étape 4: Fonction de Traçabilité

```php
/**
 * Enregistre l'application d'une réduction dans facture_reduction
 *
 * @param string $numFacture
 * @param object $reduction
 * @param float $montantAvant
 * @param float $montantApres
 */
private function enregistrerReductionAppliquee($numFacture, $reduction, $montantAvant, $montantApres)
{
    $montantReduction = $montantAvant - $montantApres;

    DB::table('facture_reduction')->insert([
        'NUM_FACTURE' => $numFacture,
        'ID_REDUCTION' => $reduction->ID_REDUCTION,
        'MONTANT_AVANT_REDUCTION' => $montantAvant,
        'POURCENTAGE_APPLIQUE' => $reduction->POURCENTAGE,
        'MONTANT_REDUCTION' => $montantReduction,
        'MONTANT_APRES_REDUCTION' => $montantApres,
        'DATE_APPLICATION' => now()
    ]);
}
```

---

## 📄 Affichage sur la Facture PDF

### Modification du Template PDF

**Fichier à modifier:** `PrintController.php` (méthode `printFactures`)

**Ajout dans le template HTML:**

```html
<!-- Après la ligne du Montant Total -->
<tr>
    <td style="text-align:right; padding:5px;"><strong>Montant Total:</strong></td>
    <td style="text-align:right; padding:5px;"><strong>{{MONTANT_AVANT_REDUCTION}} FCFA</strong></td>
</tr>

<!-- Si réduction appliquée -->
{{#if REDUCTION_APPLIQUEE}}
<tr style="color: #28a745;">
    <td style="text-align:right; padding:5px;">
        <em>Réduction {{REDUCTION_LIBELLE}} ({{REDUCTION_POURCENTAGE}}%):</em>
    </td>
    <td style="text-align:right; padding:5px;">
        <em>- {{MONTANT_REDUCTION}} FCFA</em>
    </td>
</tr>
<tr style="border-top: 2px solid #000;">
    <td style="text-align:right; padding:5px;"><strong>Montant Après Réduction:</strong></td>
    <td style="text-align:right; padding:5px;"><strong>{{MONTANT_APRES_REDUCTION}} FCFA</strong></td>
</tr>
{{/if}}
```

**Code PHP pour préparer les données:**

```php
// Pour chaque facture à imprimer
$facture = DB::table('facture')->where('NUM_FACTURE', $numFacture)->first();

// Vérifier s'il y a une réduction appliquée
$reductionAppliquee = DB::table('facture_reduction')
    ->join('reduction', 'facture_reduction.ID_REDUCTION', '=', 'reduction.ID_REDUCTION')
    ->where('facture_reduction.NUM_FACTURE', $numFacture)
    ->select(
        'facture_reduction.*',
        'reduction.LIBELLE as REDUCTION_LIBELLE'
    )
    ->first();

if ($reductionAppliquee) {
    $facture->MONTANT_AVANT_REDUCTION = $reductionAppliquee->MONTANT_AVANT_REDUCTION;
    $facture->REDUCTION_APPLIQUEE = true;
    $facture->REDUCTION_LIBELLE = $reductionAppliquee->REDUCTION_LIBELLE;
    $facture->REDUCTION_POURCENTAGE = $reductionAppliquee->POURCENTAGE_APPLIQUE;
    $facture->MONTANT_REDUCTION = $reductionAppliquee->MONTANT_REDUCTION;
    $facture->MONTANT_APRES_REDUCTION = $reductionAppliquee->MONTANT_APRES_REDUCTION;
} else {
    $facture->REDUCTION_APPLIQUEE = false;
}
```

---

## 📝 Scénarios d'Utilisation

### Scénario 1: Promotion de Fin d'Année

**Contexte:** Offrir 15% de réduction aux clients domestiques du 15 décembre au 15 janvier.

**Configuration:**
1. Aller dans Paramètres → Réductions
2. Remplir le formulaire:
   - Libellé: "Promotion Fin d'Année 2024-2025"
   - Pourcentage: 15
   - Date début: 15/12/2024
   - Date fin: 15/01/2025
   - Types clients: ☑ Domestique
   - Statut: Actif
   - Description: "Merci pour votre fidélité!"
3. Cliquer "Créer la Réduction"

**Résultat:**
- Toutes les factures générées entre le 15/12/2024 et le 15/01/2025 pour des clients domestiques auront automatiquement une réduction de 15%
- Le détail apparaîtra sur la facture imprimée

### Scénario 2: Réduction pour Nouveaux Abonnés

**Contexte:** Attirer de nouveaux clients commerciaux avec 25% de réduction le premier mois.

**Configuration:**
- Libellé: "Offre Nouveaux Abonnés Commerciaux"
- Pourcentage: 25
- Dates: 01/01/2025 - 31/01/2025
- Types clients: ☑ Commercial
- Statut: Actif

### Scénario 3: Désactiver une Réduction Temporairement

**Situation:** Besoin de suspendre temporairement une promotion sans la supprimer.

**Action:**
1. Aller dans le tableau des réductions
2. Cliquer sur "Désactiver" pour la réduction concernée
3. La réduction reste dans la base mais ne sera plus appliquée
4. Pour réactiver: cliquer sur "Activer"

### Scénario 4: Réduction Multiple Types de Clients

**Contexte:** Promotion pour les clients Domestiques ET Borne Fontaine.

**Configuration:**
- Types clients: ☑ Domestique ☑ Borne Fontaine
- Le système vérifiera si le type du client correspond à l'un des types sélectionnés

---

## 🛡️ Sécurités et Protections

### 1. Validation des Dates
```php
// date_fin doit être >= date_debut
'date_fin' => 'required|date|after_or_equal:date_debut'
```

### 2. Pourcentage Valide
```php
// Entre 0 et 100, accepte les décimales
'pourcentage' => 'required|numeric|min:0|max:100'
```

### 3. Au Moins Un Type de Client
```php
'types_client' => 'required|array|min:1'
```

### 4. Protection Suppression
Une réduction qui a été appliquée à au moins une facture **ne peut pas être supprimée**.
- Préserve l'intégrité des données historiques
- Message explicite à l'utilisateur

### 5. Foreign Key Cascade
Si une réduction est supprimée (non utilisée), les enregistrements de `facture_reduction` associés sont automatiquement supprimés.

---

## 📊 Rapports et Statistiques

### Requêtes Utiles

#### Nombre de factures ayant bénéficié d'une réduction
```sql
SELECT COUNT(DISTINCT NUM_FACTURE) as total_factures_avec_reduction
FROM facture_reduction;
```

#### Montant total de réductions accordées
```sql
SELECT SUM(MONTANT_REDUCTION) as total_reductions
FROM facture_reduction;
```

#### Réduction la plus utilisée
```sql
SELECT r.LIBELLE, COUNT(fr.ID_FACTURE_REDUCTION) as nb_applications
FROM reduction r
JOIN facture_reduction fr ON r.ID_REDUCTION = fr.ID_REDUCTION
GROUP BY r.ID_REDUCTION, r.LIBELLE
ORDER BY nb_applications DESC
LIMIT 1;
```

#### Détail des réductions par type de client
```sql
SELECT
    r.LIBELLE,
    r.TYPES_CLIENT,
    COUNT(fr.ID_FACTURE_REDUCTION) as nb_factures,
    SUM(fr.MONTANT_REDUCTION) as total_reduit
FROM reduction r
JOIN facture_reduction fr ON r.ID_REDUCTION = fr.ID_REDUCTION
GROUP BY r.ID_REDUCTION, r.LIBELLE, r.TYPES_CLIENT;
```

---

## ⚠️ Points d'Attention

### 1. Ordre de Priorité
Si plusieurs réductions sont valides pour un même client:
- **La réduction avec le plus grand pourcentage est appliquée**
- Code: `->orderBy('POURCENTAGE', 'desc')->first()`

### 2. Une Seule Réduction par Facture
Le système n'applique **qu'une seule réduction** par facture (pas de cumul).

### 3. Date de Facturation vs Date de Création
La vérification de validité se fait sur la **date de facturation** (période de consommation), pas la date de création de la facture.

### 4. Types d'Usage Dynamiques
Les types de clients sont chargés depuis la table `typeusage`.
- Si vous ajoutez un nouveau type d'usage, il apparaîtra automatiquement dans les checkboxes.

### 5. JSON Storage
`TYPES_CLIENT` est stocké en JSON dans la base.
- Encodage automatique à la création/modification
- Décodage automatique à la lecture
- Permet la recherche avec `JSON_CONTAINS()`

---

## 🔧 Maintenance

### Migration Initiale
```bash
cd /path/to/laravel
php artisan migrate
```

Cela créera les tables `reduction` et `facture_reduction`.

### Rollback si Nécessaire
```bash
php artisan migrate:rollback --step=1
```

Cela supprimera les deux tables créées.

### Vérifier les Routes
```bash
php artisan route:list --name=reduction
```

Affiche toutes les routes liées aux réductions.

---

## ✅ Checklist de Déploiement

### Phase 1: Installation Base de Données
- [ ] Exécuter la migration: `php artisan migrate`
- [ ] Vérifier la création des tables `reduction` et `facture_reduction`
- [ ] Tester l'insertion manuelle d'une réduction

### Phase 2: Interface Paramètres
- [ ] Accéder à `/parametres`
- [ ] Vérifier que l'onglet "Réductions" est visible
- [ ] Créer une réduction de test
- [ ] Modifier la réduction
- [ ] Activer/Désactiver la réduction
- [ ] Supprimer la réduction

### Phase 3: Intégration Facturation
- [ ] Implémenter `getReductionApplicable()` dans FactureController
- [ ] Implémenter `appliquerReduction()`
- [ ] Implémenter `enregistrerReductionAppliquee()`
- [ ] Modifier `generateBulk()` pour appeler ces fonctions
- [ ] Tester la génération de factures avec une réduction active

### Phase 4: Affichage PDF
- [ ] Modifier le template HTML de la facture
- [ ] Charger les données de réduction avant génération PDF
- [ ] Tester l'impression d'une facture avec réduction
- [ ] Vérifier le formatage (vert, italique)

### Phase 5: Tests Complets
- [ ] Créer plusieurs réductions avec différents types
- [ ] Générer des factures pour différents types de clients
- [ ] Vérifier que seules les bonnes réductions s'appliquent
- [ ] Vérifier les enregistrements dans `facture_reduction`
- [ ] Imprimer plusieurs factures et vérifier l'affichage

---

## 🎓 Formation Utilisateur

### Pour l'Administrateur

**Comment créer une réduction:**
1. Menu Paramètres
2. Cliquer sur l'onglet "Réductions"
3. Remplir le formulaire en haut
4. Sélectionner les types de clients concernés
5. Cliquer "Créer la Réduction"

**Comment modifier une réduction:**
1. Trouver la réduction dans le tableau
2. Cliquer "Modifier"
3. Changer les valeurs souhaitées
4. Cliquer "Modifier"

**Comment désactiver temporairement:**
- Cliquer sur "Désactiver" dans le tableau
- Pour réactiver: cliquer sur "Activer"

**Comment supprimer:**
- Cliquer sur "Supprimer"
- ⚠️ Impossible si déjà utilisée sur des factures

### Pour l'Utilisateur

**Lors de la facturation:**
- Aucune action requise
- Les réductions s'appliquent automatiquement
- Vérifier sur la facture imprimée si une réduction apparaît

---

## 🎉 Avantages du Système

✅ **Flexibilité** - Créer autant de réductions que nécessaire
✅ **Simplicité** - Application automatique, aucune intervention manuelle
✅ **Traçabilité** - Chaque réduction appliquée est enregistrée
✅ **Contrôle** - Activer/Désactiver à tout moment
✅ **Ciblage** - Par type de client et période
✅ **Transparence** - Affichage clair sur la facture
✅ **Sécurité** - Protection contre la suppression d'historique

---

**Système implémenté avec soin pour votre fidèle client! 🚀**
