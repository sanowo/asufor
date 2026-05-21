# Factures Retard - Implementation Complete ✓

## Summary

Successfully migrated all functionality from the old [factures-retard.php](../asufor_old/factures-retard.php) page into the new Laravel/React system. The old separate page is now integrated as a **filter option** in the main Factures page.

## What Was the Old System?

The old `factures-retard.php` was a **dedicated page** specifically for managing overdue invoices with these features:
- Filtered view showing only factures past their due date (DATEECH < today)
- Statistics showing count, total, received, and remaining amounts
- Bulk actions: Recouvrement, Gracier, Generate Bons Coupures, Print List
- Individual actions in detail rows

## New Implementation Strategy

Instead of a separate page, we **integrated "Retard" as a status filter** in the main Factures page. This provides:
- ✅ All the same functionality
- ✅ Better UX (single page for all facture management)
- ✅ Consistent interface
- ✅ Same filtering capabilities

---

## Changes Made

### 1. Backend - FactureController.php

#### Added "retard" Status Filter
[app/Http/Controllers/FactureController.php:126-127](app/Http/Controllers/FactureController.php#L126-L127)

```php
case 'retard': // Factures en retard (past due date)
    return $row->REGLE == 0 && $row->DATEECH < date('Y-m-d');
```

This filters factures that are:
- Not paid (`REGLE == 0`)
- Past their due date (`DATEECH < today`)

#### Added Recouvrement Functionality
[app/Http/Controllers/FactureController.php:257-294](app/Http/Controllers/FactureController.php#L257-L294)

New `recouvrement()` method that marks factures for recovery:
```php
public function recouvrement(Request $request)
{
    // Validates array of facture numbers
    // Updates REGLEMENT_TYPE to 'RECOUVREMENT' in both:
    // - facture_v2 table
    // - facture_pret table
}
```

**What is Recouvrement?**
In the old system, "Recouvrement" was a special status to mark factures that are being actively pursued for debt collection. It's different from:
- **Gracier** - Debt forgiven
- **Retard** - Simply late
- **Recouvrement** - Actively being collected (legal/formal process)

### 2. Backend - PrintController.php

#### Fixed printClientsSuspendus()
[app/Http/Controllers/PrintController.php:189-228](app/Http/Controllers/PrintController.php#L189-L228)

Added missing totals calculations:
- `total_impaye` - Sum of all unpaid amounts
- `total_compteurs` - Total number of meters

#### Added printFacturesList()
[app/Http/Controllers/PrintController.php:233-275](app/Http/Controllers/PrintController.php#L233-L275)

New method to print a table-style list of factures (landscape format):
```php
public function printFacturesList(Request $request)
{
    // Takes array of facture numbers
    // Queries factures with client and quartier info
    // Calculates totals
    // Generates landscape PDF with table format
}
```

### 3. PDF Template - factures-list.blade.php

Created [resources/views/pdf/factures-list.blade.php](resources/views/pdf/factures-list.blade.php)

**Features:**
- **Landscape A4 format** for better table view
- Columns: N° Facture, Date, Client, Quartier, Montant, Encaissé, Restant, Statut
- Color-coded amounts (green for paid, red for unpaid)
- Status badges (Gracié, Recouvrement, Réglé, Engagé, Impayé)
- Summary box with totals
- Company header/footer

### 4. Routes - web.php

Added two new routes:

```php
// Recouvrement action
Route::post('/recouvrement', [FactureController::class, 'recouvrement'])
    ->name('factures.recouvrement');

// Print list
Route::post('/factures-list', [PrintController::class, 'printFacturesList'])
    ->name('print.factures.list');
```

### 5. Frontend - Factures/Index.jsx

#### Added "Retard" Filter Option
[resources/js/Pages/Factures/Index.jsx:278](resources/js/Pages/Factures/Index.jsx#L278)

```jsx
<option value="retard">⚠️ En Retard</option>
```

When selected, shows only factures past their due date.

#### Added handleRecouvrement() Function
[resources/js/Pages/Factures/Index.jsx:93-116](resources/js/Pages/Factures/Index.jsx#L93-L116)

```jsx
const handleRecouvrement = async () => {
    // Validates selection
    // Confirms action with user
    // Posts to /factures/recouvrement
    // Refreshes data on success
};
```

#### Added Three New Buttons

**1. Print List Button**
```jsx
<PrintButton
    endpoint="/print/factures-list"
    data={{ facture_numbers: selectedFactures }}
    label={`Liste (${selectedFactures.length})`}
    filename={`liste-factures-${Date.now()}.pdf`}
    className="bg-gray-700 hover:bg-gray-800"
/>
```

**2. Recouvrement Button**
```jsx
<button onClick={handleRecouvrement} className="bg-orange-600">
    Recouvr. ({selectedFactures.length})
</button>
```

**3. Updated Button Layout**
Now shows 5 action buttons:
1. **Factures** (blue) - Print full factures
2. **Bons Coupure** (red) - Print disconnection notices
3. **Liste** (gray) - Print table list
4. **Recouvr.** (orange) - Mark for recovery
5. **Gracier** (purple) - Forgive debt

---

## Comparison: Old vs New

### Old System (factures-retard.php)
```
Separate Page → factures-retard.php
URL: /factures-retard.php
Navigation: Users had to navigate to separate page
Filters: Built into the page specifically for late factures
Actions: Recouvrement, Gracier, Print Bons, Print List
```

### New System (Factures/Index.jsx)
```
Integrated Filter → Factures page with "Retard" status filter
URL: /factures?status=retard
Navigation: Single factures page, select filter
Filters: Same filters, plus ability to combine with other filters
Actions: All same actions + more (now 5 action buttons)
```

## Benefits of New Approach

✅ **Single Source of Truth** - One factures page instead of multiple
✅ **Better UX** - No need to navigate between pages
✅ **More Flexible** - Can combine "Retard" with other filters (quartier, usage, date range)
✅ **Consistent Interface** - Same UI patterns across all facture views
✅ **Maintainable** - Less code duplication

## How to Use

### View Late Factures:
1. Go to Factures page
2. Select "⚠️ En Retard" from status dropdown
3. View only factures past their due date

### Mark for Recovery:
1. Select factures (checkbox)
2. Click "Recouvr." button
3. Confirm action
4. Factures marked as RECOUVREMENT

### Print List:
1. Select factures (checkbox)
2. Click "Liste" button
3. Downloads landscape PDF with table format

## Files Created/Modified

### Created:
1. `resources/views/pdf/factures-list.blade.php` - Table-style factures list template
2. `FACTURES_RETARD_IMPLEMENTATION.md` - This documentation

### Modified:
1. `app/Http/Controllers/FactureController.php`
   - Added 'retard' case in status filter (line 126-127)
   - Added `recouvrement()` method (lines 257-294)

2. `app/Http/Controllers/PrintController.php`
   - Fixed `printClientsSuspendus()` with totals (lines 189-228)
   - Added `printFacturesList()` method (lines 233-275)

3. `routes/web.php`
   - Added `/factures/recouvrement` route (line 35)
   - Added `/print/factures-list` route (line 80)

4. `resources/js/Pages/Factures/Index.jsx`
   - Added `handleRecouvrement()` function (lines 93-116)
   - Added "retard" filter option (line 278)
   - Added 3 new buttons (Print List, Recouvrement, updated layout)

---

## Status: ✅ COMPLETE

The old `factures-retard.php` functionality has been **fully migrated** to the new system. All features are now available through the main Factures page with the "En Retard" filter.

### Functionality Checklist:
- ✅ Filter late factures (past due date)
- ✅ Statistics display
- ✅ Bulk actions (Recouvrement, Gracier)
- ✅ Generate Bons Coupures
- ✅ Print List (table format)
- ✅ All individual actions preserved
