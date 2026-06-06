<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Imprimer Factures</title>
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
        .page-header-logo  { display: table-cell; width: 160px; vertical-align: middle; }
        .page-header-info  { display: table-cell; vertical-align: middle; font-size: 12px; }
        .page-header-info .company-name { font-weight: bold; font-size: 15px; }
        .page-header-meta  { display: table-cell; width: 180px; text-align: right; vertical-align: middle; font-size: 12px; }

        /* ── FIELDSETS CLIENT / FACTURE ── */
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
            -webkit-print-color-adjust: exact;
        }
        tbody tr td {
            border: 1px solid #CFD1D2;
            padding: 3px 6px;
            text-align: center;
            color: #333;
        }
        thead tr th, tbody tr td { border: 1px solid #CFD1D2; }
        thead tr th { border-color: #000; }

        .section-title {
            font-family: 'Courier New', Courier, monospace;
            text-decoration: underline;
            margin: 14px 0 6px;
            font-size: 12px;
        }

        /* ── DETAIL FACTURATION ── */
        .detail-block {
            margin-top: 20px;
            text-align: center;
        }
        .detail-block p {
            line-height: 1.8;
            font-size: 12px;
            margin: 4px 0;
        }

        .total-banner {
            border-top: 4px dashed #000;
            border-bottom: 4px dashed #000;
            width: 80%;
            margin: 10px auto 0;
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
            margin-top: 20px;
            padding-top: 14px;
            position: relative;
        }
        .coupon-divider .scissors {
            position: absolute;
            top: -12px;
            left: 0;
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

@foreach($factures as $data)
@php
    $facture  = $data['facture'];
    $prets    = $data['prets'];
    $arrieres = $data['arrieres'] ?? [];
    $releve   = $data['releve']   ?? null;
    $prevReleve = $data['prev_releve'] ?? null;

    $totalFacture = intval($facture->TOTAL ?? 0);
    $isBF = ($facture->USAGE_NOM ?? '') === 'BF';
    $netFacture = $isBF ? intval($totalFacture * 0.8) : $totalFacture;

    $pretTotal = 0;
    $arrTotal  = 0;
    $grandTotal = $netFacture;

    // Calcul prêts
    foreach ($prets as $p) {
        if (!empty($p->ACTIF) || !empty($p->PRET_ACTIF)) {
            $pretTotal += intval($p->IMPAYER ?? $p->IMPAYE ?? 0);
        }
    }
    $grandTotal += $pretTotal;

    // Calcul arriérés
    foreach ($arrieres as $arr) {
        $cur = intval($arr->IMPAYE ?? 0);
        if ($cur > 0) { $arrTotal += $cur; }
    }
    $grandTotal += $arrTotal;

    // Dates période
    $dateDebut = $prevReleve ? ($prevReleve->DATE_INDEX ?? $facture->DATE ?? '') : ($facture->DATE ?? '');
    $dateFin   = $releve ? ($releve->DATE_INDEX ?? '') : '';
    $nbJour    = '';
    if ($dateDebut && $dateFin) {
        try {
            $d1 = \Carbon\Carbon::parse($dateDebut);
            $d2 = \Carbon\Carbon::parse($dateFin);
            $nbJour = $d1->diffInDays($d2);
        } catch (\Exception $e) { $nbJour = ''; }
    }
@endphp

<div class="page">

    {{-- HEADER --}}
    <div class="page-header">
        <div class="page-header-logo">
            {{-- Logo texte si l'image n'est pas disponible en PDF --}}
            <strong style="font-size:18px; color:#005CB1;">ASUFOR</strong>
        </div>
        <div class="page-header-info">
            <div class="company-name">{{ $parametres['entreprise'] }}</div>
            <div>{{ $parametres['adresse'] }}</div>
            <div>Tél: {{ $parametres['telephone'] }}</div>
        </div>
        <div class="page-header-meta">
            <strong>Facture N°:</strong> {{ $facture->NUMERO_FACTURE }}<br>
            <strong>Date Facture:</strong> {{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}
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
                <th>Net à payer (1)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $facture->NUM_CLIENT }}</td>
                <td>{{ $facture->USAGE_TARIF ?? $facture->TARIF ?? '-' }}</td>
                <td>{{ number_format($totalFacture, 0, ',', ' ') }}</td>
                @if($isBF)
                <td>{{ number_format($totalFacture * 0.2, 0, ',', ' ') }}</td>
                @endif
                <td><strong>{{ number_format($netFacture, 0, ',', ' ') }}</strong></td>
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
            @php
                $actif = !empty($p->ACTIF) || !empty($p->PRET_ACTIF);
                $montantPret = intval($p->IMPAYER ?? $p->IMPAYE ?? 0);
            @endphp
            @if($actif)
            <tr>
                <td>{{ isset($p->DATE_PRET) ? \Carbon\Carbon::parse($p->DATE_PRET)->format('d/m/Y') : ($p->PRET_DATE ?? '-') }}</td>
                <td>{{ number_format($p->MONTANT_PRET ?? $p->PRET_MONTANT ?? 0, 0, ',', ' ') }}</td>
                <td>{{ number_format($p->IMPAYER ?? $p->PRET_IMPAYE ?? 0, 0, ',', ' ') }}</td>
                <td>@php $pretDisplayTotal += $montantPret; @endphp{{ number_format($montantPret, 0, ',', ' ') }}</td>
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
            <tr>
                <td>{{ isset($arr->DATE) ? \Carbon\Carbon::parse($arr->DATE)->format('d/m/Y') : '-' }}</td>
                <td>{{ $arr->TYPE ?? '-' }}</td>
                <td>{{ $arr->NUMERO_FACTURE ?? '-' }}</td>
                <td>@php $arrDisplayTotal += $cur; @endphp{{ number_format($cur, 0, ',', ' ') }}</td>
            </tr>
            @endif
            @endforeach
            <tr>
                <td colspan="3"></td>
                <td><strong>Total : {{ number_format($arrDisplayTotal, 0, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- DÉTAIL FACTURATION --}}
    <div class="detail-block">
        <p style="font-family:'Courier New',Courier,monospace; text-decoration:underline; font-weight:bold;">
            DETAIL DE LA FACTURATION
        </p>
        <p>
            <strong>PÉRIODE DU :</strong>
            {{ $dateDebut ? \Carbon\Carbon::parse($dateDebut)->format('d/m/Y') : '-' }}
            &nbsp; <strong>AU :</strong>
            {{ $dateFin ? \Carbon\Carbon::parse($dateFin)->format('d/m/Y') : '-' }}
            &nbsp; <strong>NOMBRE DE JOUR :</strong> {{ $nbJour }}
        </p>
        <p>
            <strong>ANCIEN INDEX :</strong> {{ $releve->ANCIEN_INDEX ?? ($facture->ANCIEN_INDEX ?? '-') }}
            &nbsp; <strong>NOUVEL INDEX :</strong> {{ $releve->RELEVE ?? ($releve->NOUVEAU_INDEX ?? ($facture->NOUVEL_INDEX ?? '-')) }}
            &nbsp; <strong>CONSOMMATION :</strong> {{ $facture->CONSOMMATION ?? '-' }} m³
        </p>

        <div class="total-banner">
            <p>MONTANT NET À PAYER (1)+(2)+(3) : {{ number_format($grandTotal, 0, ',', ' ') }} FCFA</p>
            <p>À RÉGLER AU PLUS TARD LE : {{ isset($facture->DATEECH) ? \Carbon\Carbon::parse($facture->DATEECH)->format('d/m/Y') : '-' }}</p>
        </div>
    </div>

    {{-- COUPON D'ENCAISSEMENT --}}
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
                <p><strong>MONTANT FACTURE :</strong> {{ number_format($grandTotal, 0, ',', ' ') }} FCFA</p>
                <p><strong>MONTANT PAYÉ :</strong> _______________</p>
            </div>
        </div>
    </div>

</div>
@endforeach

</body>
</html>
