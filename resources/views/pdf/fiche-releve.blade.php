<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fiche de Relevé</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9px; }

        .page {
            width: 100%;
            padding: 0.5cm;
            page-break-after: always;
        }

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

        .subtitle {
            font-size: 12px;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th {
            background-color: #000;
            color: #fff;
            padding: 5px 3px;
            text-align: left;
            font-weight: normal;
            font-size: 8px;
        }

        td {
            padding: 5px 3px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }

        .center { text-align: center; }
        .right { text-align: right; }

        .index-cell {
            width: 60px;
            border: 1px solid #ccc;
            min-height: 30px;
        }

        footer {
            position: fixed;
            bottom: 0.5cm;
            font-size: 8px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <div class="title">{{ $parametres['entreprise'] }}</div>
            <div class="subtitle">FICHE DE RELEVÉ DES COMPTEURS</div>
            @if($quartier)
            <div style="margin-top: 5px; font-size: 11px;">Quartier: <strong>{{ $quartier->NOM }}</strong></div>
            @else
            <div style="margin-top: 5px; font-size: 11px;">Tous les Quartiers</div>
            @endif
            <div style="margin-top: 5px; font-size: 10px;">Date: {{ now()->format('d/m/Y') }}</div>
        </header>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">N°</th>
                    <th style="width: 80px;">N° Client</th>
                    <th>Nom & Prénom</th>
                    <th>Quartier</th>
                    <th style="width: 80px;">N° Compteur</th>
                    <th class="center" style="width: 60px;">Ancien Index</th>
                    <th class="center" style="width: 60px;">Nouvel Index</th>
                    <th class="center" style="width: 50px;">Consom.</th>
                    <th style="width: 80px;">Observations</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $index => $client)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $client->NUM_CLIENT }}</td>
                    <td>{{ $client->NOM }} {{ $client->PRENOM }}</td>
                    <td>{{ $client->QUARTIER }}</td>
                    <td>{{ $client->NUM_COMPTEUR ?? '-' }}</td>
                    <td class="center index-cell">{{ $client->INDEX_COMPTEUR }}</td>
                    <td class="center index-cell"></td>
                    <td class="center"></td>
                    <td></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <footer>
            <p>Agent releveur: ___________________ | Signature: ___________________ | Date: ___/___/______</p>
            <p>Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
        </footer>
    </div>
</body>
</html>
