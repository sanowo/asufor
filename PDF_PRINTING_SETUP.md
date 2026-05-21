# PDF Printing System - Setup Guide

## Installation

### 1. Install barryvdh/dompdf Package

```bash
composer require barryvdh/laravel-dompdf
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### 3. Add Alias in config/app.php (if not auto-discovered)

```php
'aliases' => [
    // ...
    'PDF' => Barryvdh\DomPDF\Facade\Pdf::class,
]
```

## Available Print Endpoints

### 1. Print Factures (Single or Bulk)
**Endpoint:** `POST /print/factures`
**Body:**
```json
{
    "facture_numbers": ["F2024-001", "F2024-002"]
}
```
**Features:**
- Prints one or multiple factures
- Each facture on separate page
- Shows consumption details, active loans
- Watermark "IMPAYÉ" for unpaid invoices

### 2. Print Bons de Coupure (Disconnection Notices)
**Endpoint:** `POST /print/bons-coupure`
**Body:**
```json
{
    "facture_numbers": ["F2024-001", "F2024-002"]
}
```
**Features:**
- Red warning theme
- Each notice on separate page
- Includes 2000 FCFA reconnection fee
- Warning messages and payment terms

### 3. Print Fiche Relevé (Meter Reading Form)
**Endpoint:** `POST /print/fiche-releve`
**Body:**
```json
{
    "quartier_id": 1
}
```
**Features:**
- Landscape format (A4)
- Lists all clients in quartier
- Shows current meter index
- Blank fields for new readings
- Space for observations

### 4. Print Operations Caisse (Cash Register Journal)
**Endpoint:** `POST /print/operations`
**Body:**
```json
{
    "date_start": "2024-01-01",
    "date_end": "2024-01-31"
}
```
**Features:**
- Period-based operations listing
- Credit/Debit columns
- Total calculations
- Final balance (Solde)
- Status indicators

### 5. Print Clients Suspendus List
**Endpoint:** `GET /print/clients-suspendus`
**Features:**
- Lists all suspended clients
- Shows total unpaid amounts
- Number of meters per client
- Summary with totals

## PDF Templates Location

All Blade templates are in `resources/views/pdf/`:
- `factures.blade.php`
- `bons-coupure.blade.php`
- `fiche-releve.blade.php`
- `operations-caisse.blade.php`
- `clients-suspendus.blade.php`

## Key Features

### Page Breaks for Bulk Printing
Each template uses `page-break-after: always` CSS property to ensure each item prints on a separate page:

```css
.page {
    page-break-after: always;
}

.page:last-child {
    page-break-after: avoid;
}
```

### Company Parameters
All templates pull company info from `parametres` table:
- `entreprise` - Company name
- `adresse` - Address
- `telephone` - Phone number

### Professional Styling
- A4 paper format (portrait or landscape)
- Professional headers and footers
- Color-coded amounts (green for credit, red for debit)
- Status indicators
- Watermarks for unpaid items

## Testing

To test the printing system:

1. **Test Single Facture:**
```bash
curl -X POST http://localhost:8000/print/factures \
  -H "Content-Type: application/json" \
  -d '{"facture_numbers": ["F2024-001"]}'
```

2. **Test Bulk Factures:**
```bash
curl -X POST http://localhost:8000/print/factures \
  -H "Content-Type: application/json" \
  -d '{"facture_numbers": ["F2024-001", "F2024-002", "F2024-003"]}'
```

3. **Test Caisse Operations:**
```bash
curl -X POST http://localhost:8000/print/operations \
  -H "Content-Type: application/json" \
  -d '{"date_start": "2024-01-01", "date_end": "2024-01-31"}'
```

## Integration with React Frontend

Add print buttons to your React pages. Example:

```jsx
const handlePrintFactures = async (factureNumbers) => {
    try {
        const response = await axios.post('/print/factures', {
            facture_numbers: factureNumbers
        }, {
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `factures_${Date.now()}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
    } catch (error) {
        console.error('Print error:', error);
    }
};
```

## Troubleshooting

### Issue: "Class 'PDF' not found"
**Solution:** Clear config cache
```bash
php artisan config:clear
php artisan cache:clear
```

### Issue: Fonts not displaying correctly
**Solution:** Install required fonts or use web-safe fonts (Arial, Times New Roman, etc.)

### Issue: Page breaks not working
**Solution:** Ensure you're using DomPDF (not other PDF libraries). Page breaks work with CSS `page-break-after: always`

### Issue: Images not showing
**Solution:** Use absolute paths for images or base64-encode them

## Performance Notes

- Bulk printing is optimized to generate all items in a single PDF
- Each PrintController method queries only necessary data
- Uses eager loading to avoid N+1 queries
- PDF generation happens in memory before streaming to browser
