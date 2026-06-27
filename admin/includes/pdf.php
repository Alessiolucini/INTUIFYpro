<?php
/**
 * IntuiFy Admin — PDF Generator
 * Generates branded PDF documents for contracts and invoices using DomPDF.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate and output a Contract PDF.
 */
function generateContractPDF(array $contract, ?array $client, array $config): void
{
    $html = buildContractHTML($contract, $client, $config);
    outputPDF($html, 'Contratto-' . ($contract['contract_number'] ?? 'draft') . '.pdf');
}

/**
 * Generate and output an Invoice PDF.
 */
function generateInvoicePDF(array $invoice, ?array $client, array $config): void
{
    $html = buildInvoiceHTML($invoice, $client, $config);
    outputPDF($html, 'Fattura-' . ($invoice['invoice_number'] ?? 'draft') . '.pdf');
}

/**
 * Render HTML to PDF and stream to browser.
 */
function outputPDF(string $html, string $filename): void
{
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => false]);
}

/**
 * Build HTML for a contract document.
 */
function buildContractHTML(array $contract, ?array $client, array $config): string
{
    $companyName = $config['company_legal_name'] ?? 'IntuiFy';
    $companyVat = $config['company_vat'] ?? '';
    $companyAddr = $config['company_address'] ?? '';
    $companyEmail = $config['company_email'] ?? '';
    
    $clientName = $client['company_name'] ?? 'N/A';
    $clientVat = $client['vat_number'] ?? '';
    $clientAddr = $client['address'] ?? '';
    
    $contractNum = htmlspecialchars($contract['contract_number'] ?? '');
    $title = htmlspecialchars($contract['title'] ?? '');
    $description = nl2br(htmlspecialchars($contract['description'] ?? ''));
    $amount = number_format((float)($contract['amount'] ?? 0), 2, ',', '.');
    $startDate = $contract['start_date'] ? date('d/m/Y', strtotime($contract['start_date'])) : '—';
    $endDate = $contract['end_date'] ? date('d/m/Y', strtotime($contract['end_date'])) : '—';
    $today = date('d/m/Y');

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            {$pdfStyles()}
        </style>
    </head>
    <body>
        <div class="header">
            <div class="brand">
                <h1>INTUIFY</h1>
                <p>{$companyName}<br>{$companyAddr}<br>CIF: {$companyVat}<br>{$companyEmail}</p>
            </div>
            <div class="doc-info">
                <h2>CONTRATTO</h2>
                <p><strong>N°:</strong> {$contractNum}</p>
                <p><strong>Data:</strong> {$today}</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="parties">
            <div class="party">
                <h3>Prestatore</h3>
                <p><strong>{$companyName}</strong><br>{$companyAddr}<br>CIF: {$companyVat}</p>
            </div>
            <div class="party">
                <h3>Cliente</h3>
                <p><strong>{$clientName}</strong><br>{$clientAddr}<br>{$clientVat}</p>
            </div>
        </div>

        <div class="section">
            <h3>{$title}</h3>
            <div class="content">{$description}</div>
        </div>

        <table class="details-table">
            <tr><td class="label">Importo</td><td>€{$amount}</td></tr>
            <tr><td class="label">Data Inizio</td><td>{$startDate}</td></tr>
            <tr><td class="label">Data Fine</td><td>{$endDate}</td></tr>
        </table>

        <div class="signatures">
            <div class="sig-box">
                <p>Per {$companyName}</p>
                <div class="sig-line"></div>
                <p class="sig-label">Firma e timbro</p>
            </div>
            <div class="sig-box">
                <p>Per {$clientName}</p>
                <div class="sig-line"></div>
                <p class="sig-label">Firma e timbro</p>
            </div>
        </div>

        <div class="footer">
            <p>{$companyName} — {$companyAddr} — CIF: {$companyVat}</p>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Build HTML for an invoice document.
 */
function buildInvoiceHTML(array $invoice, ?array $client, array $config): string
{
    $companyName = $config['company_legal_name'] ?? 'IntuiFy';
    $companyVat = $config['company_vat'] ?? '';
    $companyAddr = $config['company_address'] ?? '';
    $companyEmail = $config['company_email'] ?? '';
    $companyIban = $config['company_iban'] ?? '';
    
    $clientName = htmlspecialchars($client['company_name'] ?? 'N/A');
    $clientVat = htmlspecialchars($client['vat_number'] ?? '');
    $clientAddr = htmlspecialchars($client['address'] ?? '');
    
    $invoiceNum = htmlspecialchars($invoice['invoice_number'] ?? '');
    $issueDate = $invoice['issue_date'] ? date('d/m/Y', strtotime($invoice['issue_date'])) : date('d/m/Y');
    $dueDate = $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '—';
    
    $items = is_string($invoice['items'] ?? '') ? json_decode($invoice['items'], true) : ($invoice['items'] ?? []);
    $subtotal = number_format((float)($invoice['subtotal'] ?? 0), 2, ',', '.');
    $taxRate = number_format((float)($invoice['tax_rate'] ?? 21), 0);
    $taxAmount = number_format((float)($invoice['tax_amount'] ?? 0), 2, ',', '.');
    $total = number_format((float)($invoice['total'] ?? 0), 2, ',', '.');
    $notes = htmlspecialchars($invoice['notes'] ?? '');

    $itemsHTML = '';
    foreach ($items as $item) {
        $desc = htmlspecialchars($item['description'] ?? '');
        $qty = number_format((float)($item['quantity'] ?? 0), 2, ',', '.');
        $price = number_format((float)($item['unit_price'] ?? 0), 2, ',', '.');
        $lineTotal = number_format((float)($item['total'] ?? 0), 2, ',', '.');
        $itemsHTML .= "<tr><td>{$desc}</td><td class='right'>{$qty}</td><td class='right'>€{$price}</td><td class='right'>€{$lineTotal}</td></tr>";
    }

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            {$pdfStyles()}
            .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .items-table th { background: #f0f0f5; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #ddd; }
            .items-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 11px; }
            .items-table .right { text-align: right; }
            .totals { width: 250px; margin-left: auto; margin-top: 10px; }
            .totals td { padding: 6px 12px; font-size: 11px; }
            .totals .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 13px; padding-top: 10px; }
            .payment-info { background: #f8f8fc; border: 1px solid #e0e0e8; border-radius: 6px; padding: 15px; margin-top: 30px; font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="brand">
                <h1>INTUIFY</h1>
                <p>{$companyName}<br>{$companyAddr}<br>CIF: {$companyVat}<br>{$companyEmail}</p>
            </div>
            <div class="doc-info">
                <h2>FATTURA</h2>
                <p><strong>N°:</strong> {$invoiceNum}</p>
                <p><strong>Data:</strong> {$issueDate}</p>
                <p><strong>Scadenza:</strong> {$dueDate}</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="parties">
            <div class="party">
                <h3>Da</h3>
                <p><strong>{$companyName}</strong><br>{$companyAddr}<br>CIF: {$companyVat}</p>
            </div>
            <div class="party">
                <h3>A</h3>
                <p><strong>{$clientName}</strong><br>{$clientAddr}<br>{$clientVat}</p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Descrizione</th>
                    <th class="right">Qtà</th>
                    <th class="right">Prezzo Unit.</th>
                    <th class="right">Totale</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHTML}
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Imponibile:</td><td class="right">€{$subtotal}</td></tr>
            <tr><td>IVA ({$taxRate}%):</td><td class="right">€{$taxAmount}</td></tr>
            <tr class="total-row"><td>TOTALE:</td><td class="right">€{$total}</td></tr>
        </table>

        <div class="payment-info">
            <strong>Dati di pagamento</strong><br>
            Beneficiario: {$companyName}<br>
            IBAN: {$companyIban}<br>
            Causale: {$invoiceNum}
        </div>

        {$notes ? "<div class='section'><h3>Note</h3><p style='font-size:10px'>{$notes}</p></div>" : ''}

        <div class="footer">
            <p>{$companyName} — {$companyAddr} — CIF: {$companyVat}</p>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Shared PDF styles.
 */
function pdfStyles(): string
{
    return <<<CSS
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1a1a2e; font-size: 11px; line-height: 1.5; padding: 40px; }
    .header { display: table; width: 100%; margin-bottom: 20px; }
    .brand { display: table-cell; width: 60%; vertical-align: top; }
    .brand h1 { font-size: 28px; font-weight: 800; letter-spacing: 3px; color: #6366f1; margin-bottom: 8px; }
    .brand p { font-size: 9px; color: #666; line-height: 1.6; }
    .doc-info { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
    .doc-info h2 { font-size: 18px; color: #1a1a2e; margin-bottom: 8px; }
    .doc-info p { font-size: 10px; color: #555; margin: 2px 0; }
    .divider { border-top: 3px solid #6366f1; margin: 15px 0 25px; }
    .parties { display: table; width: 100%; margin-bottom: 25px; }
    .party { display: table-cell; width: 50%; vertical-align: top; }
    .party h3 { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 6px; }
    .party p { font-size: 10px; line-height: 1.6; }
    .section { margin: 25px 0; }
    .section h3 { font-size: 13px; font-weight: 700; margin-bottom: 10px; color: #1a1a2e; }
    .content { font-size: 10px; line-height: 1.7; color: #333; }
    .details-table { width: 100%; margin: 20px 0; }
    .details-table td { padding: 8px 12px; font-size: 11px; border-bottom: 1px solid #eee; }
    .details-table .label { font-weight: 600; color: #666; width: 150px; }
    .signatures { display: table; width: 100%; margin-top: 60px; }
    .sig-box { display: table-cell; width: 50%; text-align: center; padding: 0 30px; }
    .sig-box p { font-size: 10px; color: #555; }
    .sig-line { border-bottom: 1px solid #333; margin: 50px 0 8px; }
    .sig-label { font-size: 8px; color: #999; }
    .footer { position: fixed; bottom: 20px; left: 40px; right: 40px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
    CSS;
}
