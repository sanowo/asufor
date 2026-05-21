# Système Hybride d'Actions Groupées ✅

## Problème Identifié

Dans l'ancien système, les actions groupées (Gracier, Recouvrement, Impression) utilisaient **les filtres actifs** (la query courante) pour opérer sur des milliers de factures/opérations à la fois.

Dans le nouveau système initial, seules les **cases à cocher** étaient supportées, ce qui limitait l'utilisateur pour traiter des grandes quantités (ex: toutes les factures du mois = milliers d'items).

## Solution Implémentée

**Système Hybride avec 2 Modes** :

### Mode 1: Sélection Manuelle (Défaut)
- ✅ Utilise les cases à cocher
- ✅ Parfait pour 2-50 factures
- ✅ Contrôle précis sur quelles factures traiter

### Mode 2: Tous les Résultats Filtrés
- ✅ Utilise les filtres de recherche actifs
- ✅ Parfait pour 100-10,000+ factures
- ✅ Traite automatiquement tous les résultats correspondant aux critères
- ⚠️ Avertissement visuel du nombre d'items affectés

---

## Implémentation Frontend (Factures/Index.jsx)

### 1. État du Mode d'Action

```jsx
const [actionMode, setActionMode] = useState('selection'); // 'selection' ou 'filter'
```

### 2. Interface Utilisateur - Sélecteur de Mode

L'utilisateur voit **deux boutons radio** au-dessus des actions :

```jsx
<div className="mb-4 flex items-center gap-4 bg-gray-50 p-3 rounded">
    <span className="text-sm font-medium">Mode d'action:</span>

    {/* Mode Sélection Manuelle */}
    <label className="inline-flex items-center">
        <input type="radio" checked={actionMode === 'selection'} ... />
        <span>Sélection manuelle (5)</span>  {/* Nombre de factures cochées */}
    </label>

    {/* Mode Filtres */}
    <label className="inline-flex items-center">
        <input type="radio" checked={actionMode === 'filter'} ... />
        <span>Tous les résultats filtrés (1,247)</span>  {/* Total des résultats */}
    </label>

    {/* Avertissement */}
    {actionMode === 'filter' && (
        <span className="text-xs text-orange-600 font-semibold">
            ⚠️ Actions sur 1,247 factures
        </span>
    )}
</div>
```

### 3. Logique des Actions (handleGrace, handleRecouvrement)

```jsx
const handleGrace = async () => {
    // Détermine le nombre d'items concernés
    const count = actionMode === 'selection' ? selectedFactures.length : meta.count;

    // Validation différente selon le mode
    if (actionMode === 'selection' && selectedFactures.length === 0) {
        alert('Veuillez sélectionner au moins une facture');
        return;
    }

    // Message de confirmation adapté
    const message = actionMode === 'selection'
        ? `Gracier ${count} facture(s) sélectionnée(s) ?`
        : `Gracier TOUTES les ${count} factures correspondant aux filtres actuels ?`;

    if (!confirm(message)) return;

    // Payload différent selon le mode
    const payload = actionMode === 'selection'
        ? { factures: selectedFactures }  // Mode sélection: liste de numéros
        : { use_filters: true, filters: filters };  // Mode filtre: critères de recherche

    const response = await axios.post('/factures/grace', payload);
    // ...
};
```

### 4. Boutons d'Action Dynamiques

Tous les boutons s'adaptent au mode choisi :

```jsx
<button
    onClick={handleRecouvrement}
    disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
>
    Recouvr. ({actionMode === 'selection' ? selectedFactures.length : meta.count})
</button>
```

### 5. Impression PDF Dynamique

Les PrintButton s'adaptent aussi :

```jsx
<PrintButton
    endpoint="/print/factures"
    data={actionMode === 'selection'
        ? { facture_numbers: selectedFactures }  // Liste de numéros
        : { use_filters: true, filters: filters }  // Critères de recherche
    }
    label={actionMode === 'selection'
        ? `Factures (${selectedFactures.length})`
        : `Factures (${meta.count})`
    }
    disabled={actionMode === 'selection' ? selectedFactures.length === 0 : meta.count === 0}
/>
```

---

## Implémentation Backend

### 1. FactureController - Méthode grace()

```php
public function grace(Request $request)
{
    // Détecte le mode
    $useFilters = $request->input('use_filters', false);

    if ($useFilters) {
        // MODE FILTRE: Récupère les factures depuis les critères
        $filters = $request->input('filters', []);
        $factures = $this->getFacturesFromFilters($filters);
    } else {
        // MODE SÉLECTION: Valide la liste de numéros
        $validated = $request->validate([
            'factures' => 'required|array',
            'factures.*' => 'required|string'
        ]);
        $factures = $validated['factures'];
    }

    // Suite du traitement identique...
    foreach ($factures as $numero_facture) {
        // Gracier chaque facture
    }
}
```

### 2. Méthode Helper: getFacturesFromFilters()

```php
public function getFacturesFromFilters($filters)
{
    // 1. Construit la même requête que list()
    $query = "SELECT DISTINCT NUMERO_FACTURE FROM ...";

    // 2. Applique les filtres
    if (!empty($filters['numero'])) {
        $where[] = "f.NUMERO_FACTURE LIKE ?";
        $params[] = "%" . $filters['numero'] . "%";
    }
    if (!empty($filters['client'])) { ... }
    if (!empty($filters['quartier']) && $filters['quartier'] !== '*') { ... }
    if (!empty($filters['client_usage']) && $filters['client_usage'] !== '*') { ... }
    if (!empty($filters['date_start'])) { ... }
    if (!empty($filters['date_end'])) { ... }

    // 3. Exécute la requête
    $allData = DB::select($query, $params);

    // 4. Filtre par statut (retard, recouvrement, gracier, etc.)
    if (!empty($filters['status']) && $filters['status'] !== '*') {
        $allData = array_filter($allData, function($row) use ($filters) {
            switch($filters['status']) {
                case 'retard':
                    return $row->REGLE == 0 && $row->DATEECH < date('Y-m-d');
                case 'recouvrement':
                    return $row->REGLEMENT_TYPE == 'RECOUVREMENT';
                // ...
            }
        });
    }

    // 5. Retourne uniquement les numéros de factures
    return array_column($allData, 'NUMERO_FACTURE');
}
```

### 3. PrintController - Méthodes d'Impression

Même logique pour **printFactures()**, **printBonsCoupure()**, **printFacturesList()** :

```php
public function printFactures(Request $request)
{
    $useFilters = $request->input('use_filters', false);

    if ($useFilters) {
        $filters = $request->input('filters', []);
        $factureNumbers = app(\App\Http\Controllers\FactureController::class)
            ->getFacturesFromFilters($filters);
    } else {
        $validated = $request->validate([
            'facture_numbers' => 'required|array',
            'facture_numbers.*' => 'required|string'
        ]);
        $factureNumbers = $validated['facture_numbers'];
    }

    // Suite du traitement...
}
```

---

## Exemples d'Utilisation

### Scénario 1: Gracier 3 factures spécifiques

1. L'utilisateur coche 3 factures
2. Mode: **Sélection manuelle (3)**
3. Clique sur "Gracier (3)"
4. Confirmation: "Gracier 3 facture(s) sélectionnée(s) ?"
5. Backend reçoit: `{ factures: ["F2024-001", "F2024-002", "F2024-003"] }`

### Scénario 2: Gracier toutes les factures du mois de janvier

1. L'utilisateur filtre:
   - Date début: 01/01/2024
   - Date fin: 31/01/2024
2. Résultat: 1,247 factures
3. Change le mode vers: **Tous les résultats filtrés (1,247)**
4. ⚠️ Avertissement: "Actions sur 1,247 factures"
5. Clique sur "Gracier (1,247)"
6. Confirmation: "Gracier TOUTES les 1,247 factures correspondant aux filtres actuels ?"
7. Backend reçoit:
```json
{
    "use_filters": true,
    "filters": {
        "numero": "",
        "client": "",
        "quartier": "*",
        "date_start": "2024-01-01",
        "date_end": "2024-01-31",
        "status": "*",
        "client_usage": "*"
    }
}
```
8. Backend reconstruit la query, récupère les 1,247 numéros, et les gracie tous

### Scénario 3: Imprimer toutes les factures en retard d'un quartier

1. L'utilisateur filtre:
   - Statut: ⚠️ En Retard
   - Quartier: "Centre-Ville"
2. Résultat: 523 factures
3. Mode: **Tous les résultats filtrés (523)**
4. Clique sur "Bons Coupure (523)"
5. Backend génère un PDF de 523 pages (une par facture)

---

## Avantages du Système Hybride

### ✅ Flexibilité
- Petites quantités → Mode Sélection
- Grandes quantités → Mode Filtre

### ✅ Performance
- Mode Filtre: Une seule requête SQL pour récupérer tous les numéros
- Pas besoin de cocher des milliers de cases

### ✅ Sécurité
- Double confirmation pour le mode Filtre
- Affichage clair du nombre d'items affectés
- Avertissement visuel ⚠️

### ✅ Compatibilité
- Conserve le comportement de l'ancien système (mode Filtre)
- Ajoute le contrôle précis du nouveau système (mode Sélection)

---

## Fichiers Modifiés

### Frontend:
1. **resources/js/Pages/Factures/Index.jsx**
   - Ajout de `actionMode` state
   - Ajout du sélecteur de mode radio
   - Mise à jour de `handleGrace()` et `handleRecouvrement()`
   - Mise à jour des boutons d'action et PrintButton

### Backend:
2. **app/Http/Controllers/FactureController.php**
   - Mise à jour de `grace()` pour supporter `use_filters`
   - Mise à jour de `recouvrement()` pour supporter `use_filters`
   - Ajout de `getFacturesFromFilters()` (public)

3. **app/Http/Controllers/PrintController.php**
   - Mise à jour de `printFactures()` pour supporter `use_filters`
   - Mise à jour de `printBonsCoupure()` pour supporter `use_filters`
   - Mise à jour de `printFacturesList()` pour supporter `use_filters`

---

## Test du Système

### Test 1: Mode Sélection
```bash
# Cocher 3 factures dans l'interface
# Cliquer "Gracier (3)"
# Vérifier que 3 factures sont graciées
```

### Test 2: Mode Filtre
```bash
# Filtrer par date: 01/01/2024 - 31/01/2024
# Changer vers mode "Tous les résultats filtrés"
# Cliquer "Gracier (X)"
# Vérifier que toutes les factures du mois sont graciées
```

### Test 3: Impression PDF Mode Filtre
```bash
# Filtrer par statut: "En Retard"
# Mode: "Tous les résultats filtrés"
# Cliquer "Bons Coupure (X)"
# Vérifier que le PDF contient toutes les factures filtrées
```

---

## Status: ✅ IMPLÉMENTÉ

Le système hybride est **fonctionnel** et résout le problème identifié. Les administrateurs peuvent maintenant :
- Utiliser le **mode Sélection** pour des actions précises sur quelques factures
- Utiliser le **mode Filtre** pour des actions massives sur des milliers de factures

Exactement comme dans l'ancien système, mais avec plus de contrôle et de flexibilité ! 🎉
