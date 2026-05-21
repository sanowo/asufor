<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liste des Clients Suspendus</title>
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

        .subtitle {
            margin-top: 5px;
            font-size: 14px;
            color: #d32f2f;
            font-weight: bold;
        }

        .date {
            margin-top: 10px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background-color: #d32f2f;
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

        .impaye {
            color: #d32f2f;
            font-weight: bold;
        }

        .summary-box {
            margin-top: 30px;
            padding: 20px;
            background-color: #ffebee;
            border: 2px solid #d32f2f;
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
            width: 250px;
            font-weight: bold;
        }

        .summary-value {
            float: right;
            font-weight: bold;
            font-size: 14px;
        }

        footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #d32f2f;
            color: white;
            border-radius: 3px;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <header>
        <div class="title">{{ $parametres['entreprise'] }}</div>
        <div class="subtitle">⚠️ LISTE DES CLIENTS SUSPENDUS ⚠️</div>
        <div class="date">
            Édité le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 100px;">N° Client</th>
                <th>Nom & Prénom</th>
                <th>Quartier</th>
                <th>Téléphone</th>
                <th class="right" style="width: 120px;">Total Impayé (FCFA)</th>
                <th class="center" style="width: 100px;">Nb Compteurs</th>
                <th class="center" style="width: 80px;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clients as $client)
            <tr>
                <td>{{ $client->NUM_CLIENT }}</td>
                <td>{{ $client->NOM }} {{ $client->PRENOM }}</td>
                <td>{{ $client->QUARTIER ?? '-' }}</td>
                <td>{{ $client->TELEPHONE ?? '-' }}</td>
                <td class="right impaye">
                    {{ number_format($client->TOTAL_IMPAYE ?? 0, 0, ',', ' ') }}
                </td>
                <td class="center">{{ $client->NB_COMPTEURS ?? 0 }}</td>
                <td class="center">
                    <span class="status-badge">SUSPENDU</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Nombre total de clients suspendus:</span>
            <span class="summary-value">{{ count($clients) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total des impayés cumulés:</span>
            <span class="summary-value impaye">{{ number_format($total_impaye, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Nombre de compteurs affectés:</span>
            <span class="summary-value">{{ $total_compteurs }}</span>
        </div>
    </div>

    <footer>
        <p><strong>Note:</strong> Ce document liste tous les clients actuellement suspendus pour impayés.</p>
        <p style="margin-top: 5px;">{{ $parametres['entreprise'] }} - {{ $parametres['adresse'] }} - Tél: {{ $parametres['telephone'] }}</p>
    </footer>
</body>
</html>
