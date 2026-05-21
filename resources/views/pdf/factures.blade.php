<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factures</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .page {
            width: 100%;
            padding: 1cm;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .company-info {
            text-align: center;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .company-details {
            font-size: 10px;
            color: #666;
        }

        .facture-header {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .facture-header-left,
        .facture-header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .facture-header-right {
            text-align: right;
        }

        .facture-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info-block {
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
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

        .right-align {
            text-align: right;
        }

        .center-align {
            text-align: center;
        }

        .total-section {
            margin-top: 20px;
            text-align: right;
        }

        .total-row {
            margin: 5px 0;
            font-size: 13px;
        }

        .total-label {
            display: inline-block;
            width: 150px;
            text-align: right;
            margin-right: 10px;
            font-weight: bold;
        }

        .total-value {
            display: inline-block;
            width: 120px;
            text-align: right;
        }

        .final-total {
            font-size: 16px;
            font-weight: bold;
            padding: 10px;
            background-color: #f0f0f0;
            margin-top: 10px;
        }

        .pret-section {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }

        .pret-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        footer {
            position: fixed;
            bottom: 1cm;
            left: 1cm;
            right: 1cm;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(255, 0, 0, 0.1);
            font-weight: bold;
            z-index: -1;
        }

        .impaye-badge {
            color: #d32f2f;
            font-weight: bold;
        }

        .paid-badge {
            color: #388e3c;
            font-weight: bold;
        }

        .reduction-row {
            color: #2e7d32;
            font-style: italic;
            background-color: #e8f5e9;
            padding: 5px;
            border-radius: 3px;
        }

        .reduction-badge {
            display: inline-block;
            background-color: #4caf50;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    @foreach($factures as $data)
    <div class="page">
        <header>
            <div class="company-info">
                <div class="company-name">{{ $parametres['entreprise'] }}</div>
                <div class="company-details">
                    {{ $parametres['adresse'] }}<br>
                    Tél: {{ $parametres['telephone'] }}
                </div>
            </div>
        </header>

        @if($data['facture']->REGLE == 0 && $data['facture']->IMPAYE > 0)
        <div class="watermark">IMPAYÉ</div>
        @endif

        <div class="facture-header">
            <div class="facture-header-left">
                <div class="info-block">
                    <span class="info-label">Client:</span>
                    <span>{{ $data['facture']->NOM }} {{ $data['facture']->PRENOM }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">N° Client:</span>
                    <span>{{ $data['facture']->NUM_CLIENT }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Téléphone:</span>
                    <span>{{ $data['facture']->TELEPHONE ?? '-' }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Quartier:</span>
                    <span>{{ $data['facture']->QUARTIER }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Usage:</span>
                    <span>{{ $data['facture']->USAGE_NOM }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">N° Compteur:</span>
                    <span>{{ $data['facture']->NUM_COMPTEUR ?? '-' }}</span>
                </div>
            </div>

            <div class="facture-header-right">
                <div class="facture-title">FACTURE</div>
                <div class="info-block">
                    <span class="info-label">N° Facture:</span>
                    <span>{{ $data['facture']->NUMERO_FACTURE }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Date:</span>
                    <span>{{ \Carbon\Carbon::parse($data['facture']->DATEFACTURE)->format('d/m/Y') }}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Échéance:</span>
                    <span>{{ \Carbon\Carbon::parse($data['facture']->DATEECH)->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="center-align">Ancien Index</th>
                    <th class="center-align">Nouvel Index</th>
                    <th class="center-align">Consommation</th>
                    <th class="right-align">Prix Unitaire</th>
                    <th class="right-align">Montant (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Consommation eau</td>
                    <td class="center-align">{{ number_format($data['facture']->ANCIEN_INDEX ?? 0, 0, ',', ' ') }}</td>
                    <td class="center-align">{{ number_format($data['facture']->NOUVEL_INDEX ?? 0, 0, ',', ' ') }}</td>
                    <td class="center-align">{{ number_format($data['facture']->CONSOMMATION ?? 0, 0, ',', ' ') }} m³</td>
                    <td class="right-align">{{ number_format($data['facture']->TARIF ?? 0, 0, ',', ' ') }}</td>
                    <td class="right-align">{{ number_format($data['facture']->MONTANTCONSOMMATION ?? 0, 0, ',', ' ') }}</td>
                </tr>
                @if($data['facture']->MONTANTABONNEMENT > 0)
                <tr>
                    <td colspan="5">Abonnement</td>
                    <td class="right-align">{{ number_format($data['facture']->MONTANTABONNEMENT, 0, ',', ' ') }}</td>
                </tr>
                @endif
                @if($data['facture']->MONTANTPENALITE > 0)
                <tr>
                    <td colspan="5">Pénalités de retard</td>
                    <td class="right-align">{{ number_format($data['facture']->MONTANTPENALITE, 0, ',', ' ') }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        @if(count($data['prets']) > 0)
        <div class="pret-section">
            <div class="pret-title">PRÊTS EN COURS</div>
            <table>
                <thead>
                    <tr>
                        <th>Date Prêt</th>
                        <th class="right-align">Montant Total</th>
                        <th class="right-align">Mensualité</th>
                        <th class="right-align">Payé</th>
                        <th class="right-align">Restant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['prets'] as $pret)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($pret->DATE_PRET)->format('d/m/Y') }}</td>
                        <td class="right-align">{{ number_format($pret->MONTANT_PRET, 0, ',', ' ') }}</td>
                        <td class="right-align">{{ number_format($pret->MONTANT_TRANCHE, 0, ',', ' ') }}</td>
                        <td class="right-align">{{ number_format($pret->PAYER, 0, ',', ' ') }}</td>
                        <td class="right-align impaye-badge">{{ number_format($pret->IMPAYER, 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Montant Facture:</span>
                <span class="total-value">{{ number_format($data['facture']->TOTFACTURE, 0, ',', ' ') }} FCFA</span>
            </div>
            @if(isset($data['reduction']) && $data['reduction'])
            <div class="total-row reduction-row">
                <span class="total-label">
                    Réduction <span class="reduction-badge">{{ number_format($data['reduction']->POURCENTAGE_APPLIQUE, 0, ',', ' ') }}%</span>
                    <br><small style="font-size: 10px;">{{ $data['reduction']->REDUCTION_LIBELLE }}</small>
                </span>
                <span class="total-value">-{{ number_format($data['reduction']->MONTANT_REDUCTION, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="total-row" style="font-weight: bold; color: #2e7d32;">
                <span class="total-label">Montant à Payer:</span>
                <span class="total-value">{{ number_format($data['reduction']->MONTANT_APRES_REDUCTION, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            @if($data['facture']->TOTAL_RECU > 0)
            <div class="total-row">
                <span class="total-label">Déjà Payé:</span>
                <span class="total-value paid-badge">-{{ number_format($data['facture']->TOTAL_RECU, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="total-row final-total">
                <span class="total-label">
                    @if($data['facture']->IMPAYE > 0)
                    RESTANT À PAYER:
                    @else
                    TOTAL:
                    @endif
                </span>
                <span class="total-value {{ $data['facture']->IMPAYE > 0 ? 'impaye-badge' : 'paid-badge' }}">
                    {{ number_format($data['facture']->IMPAYE > 0 ? $data['facture']->IMPAYE : $data['facture']->TOTFACTURE, 0, ',', ' ') }} FCFA
                </span>
            </div>
        </div>

        <footer>
            <p>Merci de votre confiance | {{ $parametres['entreprise'] }}</p>
            <p style="font-size: 9px; margin-top: 5px;">Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
        </footer>
    </div>
    @endforeach
</body>
</html>
