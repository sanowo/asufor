<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste {{ ucfirst($title ?? '') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            padding: 0.5cm;
        }

        /* ── HEADER ── */
        .page-header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-logo { display: table-cell; width: 120px; vertical-align: middle; }
        .header-info { display: table-cell; vertical-align: middle; }
        .header-meta { display: table-cell; width: 160px; vertical-align: middle; text-align: right; font-size: 9px; }

        .company-name { font-size: 15px; font-weight: bold; text-transform: uppercase; }
        .doc-title    { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .doc-filters  { font-size: 9px; color: #555; margin-top: 3px; }

        /* ── TABLES ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }

        thead tr th {
            background-color: #000;
            color: #fff;
            padding: 5px 4px;
            text-align: left;
            font-weight: normal;
            border: 1px solid #000;
            -webkit-print-color-adjust: exact;
        }
        thead tr th.center { text-align: center; }
        thead tr th.right  { text-align: right; }

        tbody tr td {
            padding: 4px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        tbody tr td.center { text-align: center; }
        tbody tr td.right  { text-align: right; }

        tbody tr:nth-child(even) td { background-color: #f7f7f7; }

        .impaye { color: #c62828; font-weight: bold; }
        .regle  { color: #2e7d32; font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 2px;
            font-size: 8px;
        }
        .badge-regle   { background: #e8f5e9; color: #2e7d32; }
        .badge-impaye  { background: #ffebee; color: #c62828; }
        .badge-engage  { background: #e3f2fd; color: #1565c0; }
        .badge-gracier { background: #f3e5f5; color: #6a1b9a; }
        .badge-recouv  { background: #fff3e0; color: #e65100; }

        /* ── SYNTHESE ── */
        .synthese-box {
            margin-top: 20px;
            padding: 12px;
            background-color: #f5f5f5;
            border: 2px solid #000;
            width: 280px;
            float: right;
        }
        .synthese-row {
            padding: 5px 0;
            font-size: 10px;
            border-bottom: 1px solid #ddd;
            display: table;
            width: 100%;
        }
        .synthese-row:last-child { border-bottom: none; }
        .synthese-label { display: table-cell; font-weight: bold; }
        .synthese-value { display: table-cell; text-align: right; font-weight: bold; }

        /* ── FOOTER ── */
        .page-footer {
            clear: both;
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="page-header">
    <div class="header-logo">
        <strong style="font-size:16px; color:#005CB1;">ASUFOR</strong>
    </div>
    <div class="header-info">
        <div class="company-name">{{ $parametres['entreprise'] }}</div>
        <div class="doc-title">
            Liste {{ $title ?? '' }}
            @if(!empty($facture_type) && $facture_type !== '*')
                — {{ ucfirst($facture_type) }}
            @endif
        </div>
        <div class="doc-filters">
            @if(!empty($date_start))
                Du {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
                au {{ \Carbon\Carbon::parse($date_end ?? now())->format('d/m/Y') }}
            @endif
            @if(!empty($meta['quartier_nom']))
                | Quartier : {{ $meta['quartier_nom'] }}
            @endif
            @if(!empty($meta['usage_nom']))
                | Usage : {{ $meta['usage_nom'] }}
            @endif
            @if(!empty($meta['client_nom']))
                | Client : {{ $meta['client_nom'] }}
            @endif
            @if($target === 'operations' && !empty($type_label))
                | Type : {{ $type_label }}
            @endif
        </div>
    </div>
    <div class="header-meta">
        Édité le {{ \Carbon\Carbon::now()->format('d/m/Y') }}<br>
        {{ count($items) }} enregistrement(s)
    </div>
</div>

{{-- ─── TABLE CLIENTS ─── --}}
@if($target === 'clients')
<table>
    <thead>
        <tr>
            <th style="width:80px;">N° Compteur</th>
            <th style="width:70px;">N° Client</th>
            <th>Nom / Prénom</th>
            <th style="width:70px;">Usage</th>
            <th style="width:80px;">Quartier</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ $item->NUMERO_COMPTEURS ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->NOM }} {{ $item->PRENOM }}</td>
            <td>{{ $item->NOM_USAGE ?? '-' }}</td>
            <td>{{ $item->QUARTIER ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ─── TABLE FACTURES ─── --}}
@if($target === 'factures')
<table>
    <thead>
        @if(($subtarget ?? '') === 'synthese')
        <tr>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            <th class="right" style="width:60px;">Consom.</th>
            <th style="width:55px;">Tarif</th>
            <th style="width:80px;">Quartier</th>
            <th class="right" style="width:70px;">Montant</th>
            <th class="right" style="width:70px;">Impayé</th>
        </tr>
        @elseif(($facture_type ?? '') === 'retard')
        <tr>
            <th style="width:70px;">N° Compteur</th>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            <th style="width:90px;">N° Facture</th>
            <th class="right" style="width:55px;">Consom.</th>
            <th style="width:65px;">Date</th>
            <th class="right" style="width:65px;">Montant</th>
            <th class="right" style="width:55px;">Fr. coupure</th>
            <th class="right" style="width:65px;">Total</th>
            <th style="width:55px;">Livraison</th>
            <th class="right" style="width:60px;">Payé</th>
            <th style="width:65px;">Règlement</th>
        </tr>
        @else
        <tr>
            <th style="width:70px;">N° Compteur</th>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            <th style="width:90px;">N° Facture</th>
            <th class="right" style="width:55px;">Consom.</th>
            <th style="width:65px;">Date</th>
            <th class="right" style="width:70px;">Montant</th>
            <th style="width:60px;">Livraison</th>
            <th class="right" style="width:60px;">Payé</th>
            <th style="width:65px;">Règlement</th>
        </tr>
        @endif
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            @if(($subtarget ?? '') === 'synthese')
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->CLIENT }}</td>
            <td class="right">{{ ($facture_type ?? '') !== 'prets' ? number_format($item->CONSOMMATION ?? 0, 0, ',', ' ') : ($item->CONSOMMATION ?? '-') }}</td>
            <td>{{ $item->TARIF ?? '-' }}</td>
            <td>{{ $item->QUARTIER ?? '-' }}</td>
            <td class="right">{{ number_format($item->TOTAL ?? 0, 0, ',', ' ') }}</td>
            <td class="right impaye">{{ number_format($item->IMPAYE ?? 0, 0, ',', ' ') }}</td>
            @elseif(($facture_type ?? '') === 'retard')
            @php $frais = ($item->BONCOUPURE ?? 0) ? 2000 : 0; @endphp
            <td>{{ $item->NUM_COMPTEUR ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->CLIENT }}</td>
            <td>{{ $item->NUMERO_FACTURE }}</td>
            <td class="right">{{ number_format($item->CONSOMMATION ?? 0, 0, ',', ' ') }}</td>
            <td>{{ isset($item->DATEFACTURE) ? \Carbon\Carbon::parse($item->DATEFACTURE)->format('d/m/Y') : '-' }}</td>
            <td class="right">{{ number_format($item->TOTAL ?? 0, 0, ',', ' ') }}</td>
            <td class="right">{{ number_format($frais, 0, ',', ' ') }}</td>
            <td class="right impaye">{{ number_format(($item->TOTAL ?? 0) + $frais, 0, ',', ' ') }}</td>
            <td></td>
            <td class="right"></td>
            <td></td>
            @else
            <td>{{ $item->NUM_COMPTEUR ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->CLIENT }}</td>
            <td>{{ $item->NUMERO_FACTURE }}</td>
            <td class="right">{{ number_format($item->CONSOMMATION ?? 0, 0, ',', ' ') }}</td>
            <td>{{ isset($item->DATEFACTURE) ? \Carbon\Carbon::parse($item->DATEFACTURE)->format('d/m/Y') : '-' }}</td>
            <td class="right">{{ number_format($item->TOTAL ?? 0, 0, ',', ' ') }}</td>
            <td></td>
            <td class="right"></td>
            <td></td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ─── TABLE RELEVÉS ─── --}}
@if($target === 'releves')
<table>
    <thead>
        <tr>
            <th style="width:70px;">N° Compteur</th>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            @if(empty($meta['quartier']))
            <th style="width:80px;">Quartier</th>
            @endif
            <th class="right" style="width:70px;">Nouvel Index</th>
            <th style="width:80px;">Date Relevé</th>
            @if(!empty($meta['quartier']))
            <th style="width:80px;">Observations</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ $item->COMPTEUR ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->CLIENT }}</td>
            @if(empty($meta['quartier']))
            <td>{{ $item->QUARTIER ?? '-' }}</td>
            @endif
            <td class="right">{{ number_format($item->RELEVE ?? 0, 0, ',', ' ') }}</td>
            <td>{{ isset($item->DATE_INDEX) ? \Carbon\Carbon::parse($item->DATE_INDEX)->format('d/m/Y') : '-' }}</td>
            @if(!empty($meta['quartier']))
            <td></td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ─── TABLE PRÊTS ─── --}}
@if($target === 'prets')
<table>
    <thead>
        <tr>
            <th style="width:70px;">N° Client</th>
            <th>Nom / Prénom</th>
            <th class="right" style="width:80px;">Montant</th>
            <th class="center" style="width:55px;">Tranches</th>
            <th class="right" style="width:80px;">Réglé</th>
            <th class="right" style="width:80px;">Impayé</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ $item->NUM_CLIENT }}</td>
            <td>{{ $item->CLIENT }}</td>
            @if(!empty($item->MONTANT))
            <td class="right">{{ number_format($item->MONTANT ?? 0, 0, ',', ' ') }}</td>
            <td class="center">{{ $item->TRANCHE ?? '-' }}</td>
            <td class="right regle">{{ number_format($item->PAYER ?? 0, 0, ',', ' ') }}</td>
            <td class="right impaye">{{ number_format($item->IMPAYER ?? 0, 0, ',', ' ') }}</td>
            @else
            <td class="right">{{ number_format($item->TOTAL ?? 0, 0, ',', ' ') }}</td>
            <td class="center">-</td>
            <td class="right regle">{{ number_format($item->RECU ?? 0, 0, ',', ' ') }}</td>
            <td class="right impaye">{{ number_format($item->IMPAYE ?? 0, 0, ',', ' ') }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ─── TABLE OPÉRATIONS ─── --}}
@if($target === 'operations')
<table>
    <thead>
        @php $sub = $subtarget ?? ''; @endphp
        @if(in_array($sub, ['13', 'facture_full', '12', '23', '20', '21']))
        <tr>
            <th>Caissier</th>
            <th style="width:90px;">N° Facture</th>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            <th style="width:75px;">Date</th>
            <th class="right" style="width:80px;">Montant</th>
        </tr>
        @elseif(in_array($sub, ['14', '15', 'mensualite_pret']))
        <tr>
            <th>Caissier</th>
            <th style="width:70px;">N° Client</th>
            <th>Client</th>
            <th style="width:75px;">Date</th>
            <th class="right" style="width:80px;">Montant</th>
        </tr>
        @else
        <tr>
            <th>Caissier</th>
            <th style="width:80px;">Date</th>
            <th>Type</th>
            <th class="right" style="width:90px;">Montant</th>
        </tr>
        @endif
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            @if(in_array($sub, ['13', 'facture_full', '12', '23', '20', '21']))
            <td>{{ $item->CAISSIER ?? '-' }}</td>
            <td>{{ $item->NUMERO_FACTURE ?? $item->ID_OP_TARGET ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT ?? '-' }}</td>
            <td>{{ $item->CLIENT ?? $item->ID_OP_TARGET ?? '-' }}</td>
            <td>{{ isset($item->DATE_OPERATION) ? \Carbon\Carbon::parse($item->DATE_OPERATION)->format('d/m/Y') : '-' }}</td>
            <td class="right">{{ number_format($item->MONTANT ?? 0, 0, ',', ' ') }}</td>
            @elseif(in_array($sub, ['14', '15', 'mensualite_pret']))
            <td>{{ $item->CAISSIER ?? '-' }}</td>
            <td>{{ $item->NUM_CLIENT ?? '-' }}</td>
            <td>{{ $item->CLIENT ?? $item->ID_OP_TARGET ?? '-' }}</td>
            <td>{{ isset($item->DATE_OPERATION) ? \Carbon\Carbon::parse($item->DATE_OPERATION)->format('d/m/Y') : '-' }}</td>
            <td class="right">{{ number_format($item->MONTANT ?? 0, 0, ',', ' ') }}</td>
            @else
            <td>{{ $item->CAISSIER ?? '-' }}</td>
            <td>{{ isset($item->DATE_OPERATION) ? \Carbon\Carbon::parse($item->DATE_OPERATION)->format('d/m/Y') : '-' }}</td>
            <td>{{ $item->TYPE ?? $item->TYPE_LABEL ?? '-' }}</td>
            <td class="right">{{ number_format($item->MONTANT ?? 0, 0, ',', ' ') }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ─── SYNTHESE ─── --}}
@if(!empty($syntheseData))
<div class="synthese-box">
    @foreach($syntheseData as $label => $value)
    <div class="synthese-row">
        <span class="synthese-label">{{ $label }}</span>
        <span class="synthese-value">{{ is_numeric($value) ? number_format($value, 0, ',', ' ') : $value }}</span>
    </div>
    @endforeach
</div>
@endif

{{-- FOOTER --}}
<div class="page-footer">
    {{ $parametres['entreprise'] }} — {{ $parametres['adresse'] }} — Tél: {{ $parametres['telephone'] }}
    &nbsp;|&nbsp; Document généré le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
