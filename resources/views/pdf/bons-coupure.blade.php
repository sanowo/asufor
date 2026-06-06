<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bons de Coupure</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #000;
            background: #fff;
        }

        .page {
            width: 19cm;
            min-height: 27.7cm;
            padding: 1cm;
            page-break-after: always;
            position: relative;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* ── HEADER ── */
        .page-header {
            display: table;
            width: 100%;
            border-bottom: 1px solid #ccc;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .page-header-logo { display: table-cell; width: 160px; vertical-align: middle; }
        .page-header-info { display: table-cell; vertical-align: middle; font-size: 12px; }
        .page-header-info .company-name { font-weight: bold; font-size: 15px; }
        .page-header-meta { display: table-cell; width: 180px; text-align: right; vertical-align: middle; font-size: 12px; }

        /* ── TITRE BON DE COUPURE ── */
        .bc-title {
            font-family: 'Courier New', Courier, monospace;
            text-decoration: underline;
            text-transform: uppercase;
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0 16px;
        }

        /* ── FIELDSETS ── */
        .meta-row {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }
        .meta-cell { display: table-cell; vertical-align: top; }
        .meta-cell:first-child { width: 55%; padding-right: 10px; }

        fieldset {
            border: 1px solid #aaa;
            padding: 6px 10px;
            font-size: 12px;
            line-height: 1.7;
        }
        fieldset legend { font-weight: bold; padding: 0 4px; font-size: 11px; }

        /* ── TABLES ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 6px;
        }
        table.main-table { width: 90%; margin: 0 auto 0 0; }
        table.sub-table  { width: 80%; }

        thead tr th {
            background-color: #005CB1;
            color: #fff;
            padding: 5px 6px;
            text-align: center;
            font-weight: normal;
            border: 1px solid #000;
            -webkit-print-color-adjust: exact;
        }
        tbody tr td {
            border: 1px solid #CFD1D2;
            padding: 3px 6px;
            text-align: center;
            color: #333;
        }

        .section-title {
            font-family: 'Courier New', Courier, monospace;
            text-decoration: underline;
            margin: 14px 0 6px;
            font-size: 12px;
        }

        /* ── TOTAL BANNER ── */
        .total-banner {
            border-top: 4px dashed #000;
            border-bottom: 4px dashed #000;
            width: 80%;
            margin: 20px auto 0;
        }
        .total-banner p {
            background-color: #005CB1;
            color: #fff;
            padding: 6px 8px;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 13px;
            margin: 0;
            -webkit-print-color-adjust: exact;
        }

        /* ── COUPON ── */
        .coupon-divider {
            border-top: 3px dashed #000;
            position: absolute;
            left: 0;
            right: 0;
            bottom: 3cm;
            padding: 14px 1cm 0;
        }
        .coupon-divider .scissors {
            position: absolute;
            top: -12px;
            left: 0.8cm;
            font-size: 20px;
            background: #fff;
            padding-right: 4px;
            line-height: 1;
        }
        .coupon-title {
            background-color: #005CB1;
            color: #fff;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 15px;
            text-align: center;
            padding: 6px 10px;
            width: 50%;
            margin: 0 auto 14px;
            border: 2px outset #005CB1;
            -webkit-print-color-adjust: exact;
        }
        .coupon-grid {
            display: table;
            width: 80%;
            margin: 0 auto;
        }
        .coupon-col { display: table-cell; width: 50%; font-size: 12px; line-height: 1.8; }
    </style>
</head>
<body>

@foreach($factures as $facture)
@php
    $isBF = ($facture->USAGE_NOM ?? '') === 'BF';
    $impaye   = intval($facture->IMPAYE ?? 0);
    $frais    = 2000;
    $total    = $impaye + $frais;

    // Prêts actifs
    $prets    = $facture->prets    ?? [];
    $arrieres = $facture->arrieres ?? [];

    $pretTotal = 0;
    foreach ($prets as $p) {
        if (!empty($p->ACTIF) || !empty($p->PRET_ACTIF)) {
            $pretTotal += intval($p->IMPAYER ?? $p->IMPAYE ?? 0);
        }
    }
    $total += $pretTotal;

    $arrTotal = 0;
    foreach ($arrieres as $arr) {
        $cur = intval($arr->IMPAYE ?? 0);
        if ($cur > 0) { $arrTotal += $cur; }
    }
    $total += $arrTotal;
@endphp

<div class="page">

    {{-- HEADER --}}
    <div class="page-header">
        <div class="page-header-logo">
            <strong style="font-size:18px; color:#005CB1;">ASUFOR</strong>
        </div>
        <div class="page-header-info">
            <div class="company-name">{{ $parametres['entreprise'] }}</div>
            <div>{{ $parametres['adresse'] }}</div>
            <div>Tél: {{ $parametres['telephone'] }}</div>
        </div>
        <div class="page-header-meta">
            <strong>Facture N° :</strong> {{ $facture->NUMERO_FACTURE }}<br>
            <strong>Date Facture :</strong> {{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}
        </div>
    </div>

    {{-- INFO CLIENT / FACTURE --}}
    <div class="meta-row">
        <div class="meta-cell">
            <fieldset>
                <legend>Facture</legend>
                Date : <strong>{{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}</strong><br>
                Facture N° : <strong>{{ $facture->NUMERO_FACTURE }}</strong><br>
                Quartier : <strong>{{ $facture->QUARTIER ?? '-' }}</strong>
                &nbsp; Usage : <strong>{{ $facture->USAGE_NOM ?? '-' }}</strong>
            </fieldset>
        </div>
        <div class="meta-cell">
            <fieldset>
                <legend>Client</legend>
                Client : <strong>{{ $facture->NOM }} {{ $facture->PRENOM }}</strong><br>
                N° CI : <strong>{{ $facture->NUM_CI ?? '-' }}</strong><br>
                Téléphone : <strong>{{ $facture->TELEPHONE ?? '-' }}</strong>
            </fieldset>
        </div>
    </div>

    {{-- TITRE --}}
    <h1 class="bc-title">Bon de Coupure</h1>

    {{-- TABLE PRINCIPALE --}}
    <table class="main-table">
        <thead>
            <tr>
                <th>N° Client</th>
                <th>Tarif/m³</th>
                <th>Montant facture</th>
                @if($isBF)
                <th>Fontainier (4)</th>
                @endif
                <th>Impayé (1)</th>
                <th>Frais de coupure (3)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $facture->NUM_CLIENT }}</td>
                <td>{{ $facture->USAGE_TARIF ?? $facture->TARIF ?? '-' }}</td>
                <td>{{ number_format($facture->TOTAL ?? 0, 0, ',', ' ') }}</td>
                @if($isBF)
                <td>{{ number_format(intval($facture->TOTAL ?? 0) * 0.2, 0, ',', ' ') }}</td>
                @endif
                <td>{{ number_format($impaye, 0, ',', ' ') }}</td>
                <td>{{ number_format($frais, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- MENSUALITÉS PRÊT --}}
    <p class="section-title">Mensualités Prêt</p>
    <table class="sub-table">
        <thead>
            <tr>
                <th>Date du Prêt</th>
                <th>Montant Total</th>
                <th>Montant Restant</th>
                <th>Mensualité à régler (2)</th>
            </tr>
        </thead>
        <tbody>
            @php $pretDisplayTotal = 0; @endphp
            @foreach($prets as $p)
            @if(!empty($p->ACTIF) || !empty($p->PRET_ACTIF))
            @php $pretDisplayTotal += intval($p->IMPAYER ?? $p->IMPAYE ?? 0); @endphp
            <tr>
                <td>{{ isset($p->DATE_PRET) ? \Carbon\Carbon::parse($p->DATE_PRET)->format('d/m/Y') : ($p->PRET_DATE ?? '-') }}</td>
                <td>{{ number_format($p->MONTANT_PRET ?? $p->PRET_MONTANT ?? 0, 0, ',', ' ') }}</td>
                <td>{{ number_format($p->IMPAYER ?? $p->PRET_IMPAYE ?? 0, 0, ',', ' ') }}</td>
                <td>{{ number_format(intval($p->IMPAYER ?? $p->IMPAYE ?? 0), 0, ',', ' ') }}</td>
            </tr>
            @endif
            @endforeach
            <tr>
                <td colspan="3"></td>
                <td><strong>Total : {{ number_format($pretDisplayTotal, 0, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- ARRIÉRÉS --}}
    <p class="section-title">Rappel des dernières factures impayées</p>
    <table class="sub-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Numéro facture</th>
                <th>Montant Restant (3)</th>
            </tr>
        </thead>
        <tbody>
            @php $arrDisplayTotal = 0; @endphp
            @foreach($arrieres as $arr)
            @php $cur = intval($arr->IMPAYE ?? 0); @endphp
            @if($cur > 0)
            @php $arrDisplayTotal += $cur; @endphp
            <tr>
                <td>{{ isset($arr->DATE) ? \Carbon\Carbon::parse($arr->DATE)->format('d/m/Y') : '-' }}</td>
                <td>{{ $arr->TYPE ?? '-' }}</td>
                <td>{{ $arr->NUMERO_FACTURE ?? '-' }}</td>
                <td>{{ number_format($cur, 0, ',', ' ') }}</td>
            </tr>
            @endif
            @endforeach
            <tr>
                <td colspan="3"></td>
                <td><strong>Total : {{ number_format($arrDisplayTotal, 0, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- TOTAL + REMISE --}}
    <div class="total-banner">
        <p>MONTANT NET À PAYER (1)+(2)+(3) : {{ number_format($total, 0, ',', ' ') }} FCFA</p>
        <p>REMISE 48H APRÈS PAIEMENT</p>
    </div>

    {{-- COUPON D'ENCAISSEMENT (positionné en bas) --}}
    <div class="coupon-divider">
        <span class="scissors">✂</span>
        <div class="coupon-title">COUPON D'ENCAISSEMENT</div>
        <div class="coupon-grid">
            <div class="coupon-col">
                <p><strong>FACTURE N° :</strong> {{ $facture->NUMERO_FACTURE }}</p>
                <p><strong>DATE FACTURE :</strong> {{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}</p>
                <p><strong>NOM CLIENT :</strong> {{ $facture->NOM }} {{ $facture->PRENOM }}</p>
            </div>
            <div class="coupon-col">
                <p><strong>N° CLIENT :</strong> {{ $facture->NUM_CLIENT }}</p>
                <p><strong>MONTANT FACTURE :</strong> {{ number_format($total, 0, ',', ' ') }} FCFA</p>
                <p><strong>MONTANT PAYÉ :</strong> _______________</p>
            </div>
        </div>
    </div>

</div>
@endforeach

</body>
</html>
