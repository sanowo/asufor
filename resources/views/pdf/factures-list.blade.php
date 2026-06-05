<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste des Factures</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; padding: 0.5cm; }

        header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .date {
            margin-top: 5px;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th {
            background-color: #000;
            color: #fff;
            padding: 6px;
            text-align: left;
            font-weight: normal;
            font-size: 9px;
        }

        td {
            padding: 5px 6px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }

        .right { text-align: right; }
        .center { text-align: center; }

        .impaye {
            color: #d32f2f;
            font-weight: bold;
        }

        .regle {
            color: #388e3c;
            font-weight: bold;
        }

        .summary-box {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 2px solid #000;
            width: 300px;
            float: right;
        }

        .summary-row {
            padding: 8px 0;
            font-size: 11px;
            border-bottom: 1px solid #ddd;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            display: inline-block;
            width: 150px;
            font-weight: bold;
        }

        .summary-value {
            float: right;
            font-weight: bold;
        }

        footer {
            clear: both;
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
        }

        .badge-regle {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .badge-impaye {
            background-color: #ffebee;
            color: #c62828;
        }

        .badge-engage {
            background-color: #e3f2fd;
            color: #1565c0;
        }
    </style>
</head>
<body>
    <header>
        <div class="title">{{ $parametres['entreprise'] }}</div>
        <div>LISTE DES FACTURES</div>
        <div class="date">
            Édité le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;">N° Facture</th>
                <th style="width: 70px;">Date</th>
                <th>Client</th>
                <th style="width: 90px;">Quartier</th>
                <th class="right" style="width: 80px;">Montant (FCFA)</th>
                <th class="right" style="width: 80px;">Encaissé (FCFA)</th>
                <th class="right" style="width: 80px;">Restant (FCFA)</th>
                <th class="center" style="width: 60px;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($factures as $facture)
            <tr>
                <td>{{ $facture->NUMERO_FACTURE }}</td>
                <td>{{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}</td>
                <td>{{ $facture->CLIENT }}</td>
                <td>{{ $facture->QUARTIER ?? '-' }}</td>
                <td class="right">
                    {{ number_format($facture->MONTANT_TOTAL, 0, ',', ' ') }}
                </td>
                <td class="right regle">
                    {{ number_format($facture->MONTANT_TOTAL - $facture->RESTANT, 0, ',', ' ') }}
                </td>
                <td class="right impaye">
                    {{ number_format($facture->RESTANT, 0, ',', ' ') }}
                </td>
                <td class="center">
                    @if($facture->REGLEMENT_TYPE === 'GRACIER')
                        <span class="status-badge" style="background-color: #f3e5f5; color: #6a1b9a;">Gracié</span>
                    @elseif($facture->REGLEMENT_TYPE === 'RECOUVREMENT')
                        <span class="status-badge" style="background-color: #fff3e0; color: #e65100;">Recouvr.</span>
                    @elseif($facture->REGLE == 1)
                        <span class="status-badge badge-regle">Réglé</span>
                    @elseif(($facture->RECU ?? 0) > 0)
                        <span class="status-badge badge-engage">Engagé</span>
                    @else
                        <span class="status-badge badge-impaye">Impayé</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Nombre de factures:</span>
            <span class="summary-value">{{ count($factures) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Montant total:</span>
            <span class="summary-value">{{ number_format($total, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Encaissé:</span>
            <span class="summary-value regle">{{ number_format($encaisse, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Restant:</span>
            <span class="summary-value impaye">{{ number_format($restant, 0, ',', ' ') }} FCFA</span>
        </div>
    </div>

    <footer>
        <p>{{ $parametres['entreprise'] }} - {{ $parametres['adresse'] }} - Tél: {{ $parametres['telephone'] }}</p>
    </footer>
</body>
</html>
