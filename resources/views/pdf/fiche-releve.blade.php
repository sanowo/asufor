<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fiche de Relevé</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            background: #fff;
        }

        /* DomPDF : landscape A4 = 29.7cm × 21cm */
        .page {
            width: 27cm;
            min-height: 19cm;
            padding: 0.5cm 0.8cm;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* ── HEADER ── */
        .page-header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .header-logo  { display: table-cell; width: 120px; vertical-align: middle; }
        .header-info  { display: table-cell; vertical-align: middle; text-align: center; }
        .header-date  { display: table-cell; width: 120px; vertical-align: middle; text-align: right; font-size: 8px; }

        .company-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .doc-title    { font-size: 12px; font-weight: bold; margin-top: 4px; text-transform: uppercase; }

        /* ── TABLE ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th {
            background-color: #000;
            color: #fff;
            padding: 4px 3px;
            text-align: left;
            font-weight: normal;
            font-size: 8px;
            border: 1px solid #000;
            -webkit-print-color-adjust: exact;
        }
        th.center { text-align: center; }

        td {
            padding: 4px 3px;
            border: 1px solid #aaa;
            font-size: 9px;
            height: 22px;
        }
        td.center { text-align: center; }
        td.index-cell {
            min-width: 50px;
            width: 55px;
            text-align: center;
        }

        tr:nth-child(even) td { background-color: #f9f9f9; }

        /* ── FOOTER ── */
        .page-footer {
            margin-top: 10px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 8px;
            color: #555;
            text-align: center;
        }

        /* ── SYNTHESE ── */
        .synthese-table {
            margin-top: 16px;
            width: 60%;
        }
        .synthese-table th {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            text-align: left;
            padding: 5px;
        }
        .synthese-table td {
            padding: 5px;
            border: 1px solid #ccc;
            font-weight: bold;
        }
    </style>
</head>
<body>

@php
    // Découper les clients en groupes de 25 par page
    $perPage = 25;
    $chunks  = array_chunk($clients->toArray(), $perPage);
    $total   = count($clients);
    $pages   = count($chunks);
@endphp

@foreach($chunks as $pageIndex => $group)
<div class="page">

    {{-- HEADER --}}
    <div class="page-header">
        <div class="header-logo">
            <strong style="font-size:14px; color:#005CB1;">ASUFOR</strong>
        </div>
        <div class="header-info">
            <div class="company-name">{{ $parametres['entreprise'] }}</div>
            <div class="doc-title">
                Fiche de Relevé
                @if($quartier) — {{ $quartier->NOM }} @else — Tous les Quartiers @endif
            </div>
            <div style="margin-top:3px; font-size:9px;">
                Période :
                <strong>{{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}</strong>
                au
                <strong>{{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}</strong>
            </div>
        </div>
        <div class="header-date">
            Édité le {{ \Carbon\Carbon::now()->format('d/m/Y') }}<br>
            Page {{ $pageIndex + 1 }} / {{ $pages }}
        </div>
    </div>

    {{-- TABLEAU --}}
    <table>
        <thead>
            <tr>
                <th style="width:22px;">N°</th>
                <th style="width:70px;">N° Client</th>
                <th>Nom &amp; Prénom</th>
                @if(!$quartier)
                <th style="width:80px;">Quartier</th>
                @endif
                <th style="width:65px;">Usage</th>
                <th style="width:75px;">Adresse</th>
                <th style="width:70px;">N° Compteur</th>
                <th class="center" style="width:55px;">Dernier Index</th>
                <th class="center" style="width:55px;">Nouvel Index</th>
                <th class="center" style="width:50px;">Consom.</th>
                <th style="width:75px;">Date Relevé</th>
                <th style="width:70px;">Observations</th>
            </tr>
        </thead>
        <tbody>
            @foreach($group as $i => $client)
            <tr>
                <td class="center">{{ $pageIndex * $perPage + $i + 1 }}</td>
                <td>{{ $client->NUM_CLIENT }}</td>
                <td>{{ $client->NOM }} {{ $client->PRENOM }}</td>
                @if(!$quartier)
                <td>{{ $client->QUARTIER ?? '-' }}</td>
                @endif
                <td>{{ $client->USAGE_NOM ?? '-' }}</td>
                <td>{{ $client->ADRESSE ?? '-' }}</td>
                <td>{{ $client->NUM_COMPTEUR ?? '-' }}</td>
                <td class="index-cell">{{ $client->INDEX_COMPTEUR ?? '' }}</td>
                <td class="index-cell"></td>
                <td class="center"></td>
                <td>{{ $client->DATE_DERNIER_RELEVE ? \Carbon\Carbon::parse($client->DATE_DERNIER_RELEVE)->format('d/m/Y') : '' }}</td>
                <td></td>
            </tr>
            @endforeach

            {{-- Lignes vides si dernière page avec peu d'entrées --}}
            @if($pageIndex == $pages - 1)
                @for($e = count($group); $e < min($perPage, 5); $e++)
                <tr>
                    <td class="center">{{ $pageIndex * $perPage + $e + 1 }}</td>
                    <td></td><td></td>
                    @if(!$quartier)<td></td>@endif
                    <td></td><td></td><td></td>
                    <td class="index-cell"></td>
                    <td class="index-cell"></td>
                    <td></td><td></td><td></td>
                </tr>
                @endfor
            @endif
        </tbody>
    </table>

    {{-- SYNTHESE sur la dernière page --}}
    @if($pageIndex == $pages - 1)
    <table class="synthese-table">
        <thead>
            <tr>
                <th colspan="2">Synthèse</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Nombre total de clients</td>
                <td>{{ $total }}</td>
            </tr>
            @if($quartier)
            <tr>
                <td>Quartier</td>
                <td>{{ $quartier->NOM }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    {{-- FOOTER --}}
    <div class="page-footer">
        Agent releveur : ___________________________ &nbsp;|&nbsp;
        Signature : ___________________________ &nbsp;|&nbsp;
        Date : ___/___/______
        <br>
        {{ $parametres['entreprise'] }} — {{ $parametres['adresse'] }} — Tél: {{ $parametres['telephone'] }}
    </div>

</div>
@endforeach

</body>
</html>
