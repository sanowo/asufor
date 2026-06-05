<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bons de Coupure</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
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
            border-bottom: 3px solid #d32f2f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .company-info {
            text-align: center;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .document-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #d32f2f;
            margin: 20px 0;
            padding: 15px;
            background-color: #ffebee;
            border: 2px solid #d32f2f;
        }

        .notice-box {
            background-color: #fff3cd;
            border: 2px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .notice-title {
            font-size: 14px;
            font-weight: bold;
            color: #f57c00;
            margin-bottom: 10px;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            padding: 8px 0;
            font-weight: bold;
            width: 180px;
        }

        .info-value {
            display: table-cell;
            padding: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background-color: #d32f2f;
            color: #fff;
            padding: 10px;
            text-align: left;
            font-weight: normal;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .right-align {
            text-align: right;
        }

        .amount-box {
            font-size: 16px;
            font-weight: bold;
            padding: 15px;
            margin: 20px 0;
            background-color: #ffebee;
            border: 2px solid #d32f2f;
            text-align: center;
            color: #d32f2f;
        }

        .warning-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #fff9c4;
            border-left: 4px solid #fbc02d;
        }

        .warning-title {
            font-size: 13px;
            font-weight: bold;
            color: #f57f17;
            margin-bottom: 10px;
        }

        .warning-text {
            font-size: 11px;
            line-height: 1.6;
        }

        footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #d32f2f;
        }

        .signature-area {
            margin-top: 40px;
            display: table;
            width: 100%;
        }

        .signature-block {
            display: table-cell;
            width: 50%;
            text-align: center;
        }

        .signature-line {
            margin-top: 50px;
            border-top: 1px solid #000;
            display: inline-block;
            width: 200px;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(211, 47, 47, 0.08);
            font-weight: bold;
            z-index: -1;
        }

        .date-badge {
            display: inline-block;
            padding: 5px 15px;
            background-color: #d32f2f;
            color: white;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @foreach($factures as $facture)
    <div class="page">
        <div class="watermark">COUPURE</div>

        <header>
            <div class="company-info">
                <div class="company-name">{{ $parametres['entreprise'] }}</div>
                <div>{{ $parametres['adresse'] }} | Tél: {{ $parametres['telephone'] }}</div>
            </div>
        </header>

        <div class="document-title">
            ⚠️ AVIS DE COUPURE D'EAU ⚠️
        </div>

        <div class="notice-box">
            <div class="notice-title">⚠️ AVERTISSEMENT IMPORTANT</div>
            <p>Votre service d'eau sera interrompu si le paiement n'est pas effectué avant la date limite.</p>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">N° Bon de Coupure:</div>
                <div class="info-value">BC-{{ $facture->NUMERO_FACTURE }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date d'émission:</div>
                <div class="info-value"><span class="date-badge">{{ \Carbon\Carbon::parse($date_bc)->format('d/m/Y') }}</span></div>
            </div>
            <div class="info-row">
                <div class="info-label">N° Client:</div>
                <div class="info-value">{{ $facture->NUM_CLIENT }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nom:</div>
                <div class="info-value"><strong>{{ $facture->NOM }} {{ $facture->PRENOM }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Téléphone:</div>
                <div class="info-value">{{ $facture->TELEPHONE ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Quartier:</div>
                <div class="info-value">{{ $facture->QUARTIER }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">N° Facture Impayée:</div>
                <div class="info-value">{{ $facture->NUMERO_FACTURE }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date Facture:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($facture->DATEFACTURE)->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date Échéance:</div>
                <div class="info-value" style="color: #d32f2f; font-weight: bold;">{{ \Carbon\Carbon::parse($facture->DATEECH)->format('d/m/Y') }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right-align">Montant (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Montant Facture</td>
                    <td class="right-align">{{ number_format($facture->TOTAL ?? 0, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td>Déjà Payé</td>
                    <td class="right-align" style="color: #388e3c;">-{{ number_format($facture->RECU ?? 0, 0, ',', ' ') }}</td>
                </tr>
                <tr style="background-color: #ffebee;">
                    <td><strong>Frais de Coupure</strong></td>
                    <td class="right-align" style="color: #d32f2f;"><strong>{{ number_format(2000, 0, ',', ' ') }}</strong></td>
                </tr>
                <tr style="font-weight: bold; font-size: 13px; background-color: #f5f5f5;">
                    <td>TOTAL À PAYER</td>
                    <td class="right-align" style="color: #d32f2f;">{{ number_format($facture->IMPAYE + 2000, 0, ',', ' ') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="amount-box">
            MONTANT TOTAL À RÉGLER: {{ number_format($facture->IMPAYE + 2000, 0, ',', ' ') }} FCFA
        </div>

        <div class="warning-section">
            <div class="warning-title">📋 MODALITÉS DE PAIEMENT</div>
            <div class="warning-text">
                <p><strong>Délai de Paiement:</strong> Veuillez régler cette somme dans un délai de 7 jours à compter de la date d'émission de cet avis.</p>
                <p style="margin-top: 10px;"><strong>Lieu de Paiement:</strong> Vous pouvez effectuer votre paiement à nos bureaux situés à {{ $parametres['adresse'] }}.</p>
                <p style="margin-top: 10px;"><strong>Conséquences du Non-Paiement:</strong> En cas de non-paiement dans les délais impartis, votre alimentation en eau sera interrompue.</p>
                <p style="margin-top: 10px;"><strong>Frais de Reconnexion:</strong> Des frais supplémentaires seront appliqués pour la remise en service de votre compteur.</p>
            </div>
        </div>

        <footer>
            <div class="signature-area">
                <div class="signature-block">
                    <div>Agent ASUFOR</div>
                    <div class="signature-line"></div>
                    <div style="margin-top: 5px; font-size: 10px;">Nom et Signature</div>
                </div>
                <div class="signature-block">
                    <div>Client</div>
                    <div class="signature-line"></div>
                    <div style="margin-top: 5px; font-size: 10px;">Nom et Signature</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #666;">
                <p>Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
                <p style="margin-top: 5px;">Pour toute question, veuillez contacter le {{ $parametres['telephone'] }}</p>
            </div>
        </footer>
    </div>
    @endforeach
</body>
</html>
