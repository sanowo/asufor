<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Journal de Caisse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; padding: 1cm; }

        header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .period {
            margin-top: 10px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background-color: #000;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-weight: normal;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .right { text-align: right; }
        .center { text-align: center; }

        .credit { color: #388e3c; font-weight: bold; }
        .debit { color: #d32f2f; font-weight: bold; }

        .summary-box {
            margin-top: 30px;
            padding: 20px;
            background-color: #f5f5f5;
            border: 2px solid #000;
        }

        .summary-row {
            padding: 10px 0;
            font-size: 13px;
            border-bottom: 1px solid #ddd;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            display: inline-block;
            width: 200px;
            font-weight: bold;
        }

        .summary-value {
            float: right;
            font-weight: bold;
        }

        .final-balance {
            font-size: 16px;
            margin-top: 10px;
            padding: 15px;
            background-color: #fff;
            border: 2px solid #000;
            text-align: center;
        }

        footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <div class="title">{{ $parametres['entreprise'] }}</div>
        <div style="margin-top: 5px;">JOURNAL DE CAISSE</div>
        <div class="period">
            Période: du {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
            au {{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 90px;">Date</th>
                <th>Type Opération</th>
                <th style="width: 150px;">Référence</th>
                <th class="right" style="width: 100px;">Crédit (FCFA)</th>
                <th class="right" style="width: 100px;">Débit (FCFA)</th>
                <th class="center" style="width: 60px;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($operations as $op)
            <tr>
                <td>{{ \Carbon\Carbon::parse($op->DATE_OPERATION)->format('d/m/Y') }}</td>
                <td>{{ $op->TYPE_LABEL }}</td>
                <td>{{ $op->NUMERO_FACTURE ?? $op->NUM_CLIENT ?? '-' }}</td>
                <td class="right {{ $op->IS_REVENUE == 1 ? 'credit' : '' }}">
                    {{ $op->IS_REVENUE == 1 ? number_format($op->MONTANT, 0, ',', ' ') : '' }}
                </td>
                <td class="right {{ $op->IS_REVENUE == 0 ? 'debit' : '' }}">
                    {{ $op->IS_REVENUE == 0 ? number_format($op->MONTANT, 0, ',', ' ') : '' }}
                </td>
                <td class="center">
                    @if($op->VALIDE == 1)
                    <span style="color: #388e3c;">✓</span>
                    @else
                    <span style="color: #ff9800;">⏳</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Total Encaissements (Crédits):</span>
            <span class="summary-value credit">+{{ number_format($total_credit, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Décaissements (Débits):</span>
            <span class="summary-value debit">-{{ number_format($total_debit, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="final-balance">
            <div>SOLDE DE LA PÉRIODE</div>
            <div style="font-size: 20px; margin-top: 10px; color: {{ $solde >= 0 ? '#388e3c' : '#d32f2f' }};">
                {{ number_format($solde, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    <footer>
        <p>Nombre d'opérations: {{ count($operations) }}</p>
        <p style="margin-top: 5px;">Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
    </footer>
</body>
</html>
