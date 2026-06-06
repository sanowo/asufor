<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket de Caisse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            width: 62mm;
        }

        .ticket {
            width: 62mm;
            padding: 3mm;
        }

        /* ── HEADER ── */
        .ticket-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .company-name {
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        .company-sub {
            font-size: 8px;
            color: #444;
            margin-top: 2px;
        }

        /* ── META (caissier, date) ── */
        .ticket-meta {
            font-size: 9px;
            margin-bottom: 6px;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
        }
        .ticket-meta .meta-row {
            display: table;
            width: 100%;
        }
        .ticket-meta .meta-left  { display: table-cell; }
        .ticket-meta .meta-right { display: table-cell; text-align: right; }

        /* ── TABLE OPÉRATIONS ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        th {
            border-bottom: 1px solid #000;
            padding: 2px 1px;
            text-align: left;
            font-weight: bold;
        }
        th.right { text-align: right; }
        td {
            padding: 2px 1px;
            vertical-align: top;
        }
        td.right { text-align: right; }

        /* ── TOTAL ── */
        .ticket-total {
            border-top: 1px dashed #000;
            margin-top: 4px;
            padding-top: 4px;
            display: table;
            width: 100%;
            font-size: 11px;
            font-weight: bold;
        }
        .ticket-total .tl { display: table-cell; }
        .ticket-total .tr { display: table-cell; text-align: right; }

        /* ── FOOTER ── */
        .ticket-footer {
            margin-top: 8px;
            border-top: 1px dashed #000;
            padding-top: 4px;
            text-align: center;
            font-size: 8px;
            color: #555;
        }
    </style>
</head>
<body>

<div class="ticket">

    {{-- HEADER --}}
    <div class="ticket-header">
        <div class="company-name">{{ $parametres['entreprise'] }}</div>
        <div class="company-sub">{{ $parametres['adresse'] }}</div>
        <div class="company-sub">Tél: {{ $parametres['telephone'] }}</div>
    </div>

    {{-- META --}}
    <div class="ticket-meta">
        <div class="meta-row">
            <span class="meta-left">
                Caissier #{{ isset($operations[0]) ? ($operations[0]->ID_USER ?? '-') : '-' }}
            </span>
            <span class="meta-right">
                {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
            </span>
        </div>
        @if($type === 'facture')
        <div style="margin-top:2px;">Type: <strong>Règlement Facture</strong></div>
        @else
        <div style="margin-top:2px;">Type: <strong>Opération Caisse</strong></div>
        @endif
    </div>

    {{-- OPÉRATIONS --}}
    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($operations as $op)
            @php $grandTotal += intval($op->MONTANT ?? 0); @endphp
            <tr>
                <td>{{ $op->OPERATION ?? $op->TYPE_LABEL ?? $op->LIBELLE ?? '-' }}</td>
                <td class="right">{{ number_format($op->MONTANT ?? 0, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTAL --}}
    <div class="ticket-total">
        <span class="tl">TOTAL</span>
        <span class="tr">{{ number_format($grandTotal, 0, ',', ' ') }} FCFA</span>
    </div>

    {{-- FOOTER --}}
    <div class="ticket-footer">
        <p>Merci de votre confiance</p>
        <p style="margin-top:3px;">{{ \Carbon\Carbon::now()->format('d/m/Y à H:i:s') }}</p>
    </div>

</div>

</body>
</html>
