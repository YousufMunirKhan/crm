<style>
    @page {
        size: A4;
        margin: 20mm 12mm 12mm 12mm;
    }
    @font-face {
        font-family: 'DejaVu Sans';
        src: url('{{ storage_path('fonts/DejaVuSans.ttf') }}') format('truetype');
    }
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 10px;
        color: #1a1a2e;
        line-height: 1.4;
        background: #fff;
    }
    .container {
        max-width: 100%;
        padding: 0 5px;
    }
    .page-break-avoid {
        page-break-inside: avoid;
    }

    .top-bar {
        height: 4px;
        background: #0d9488;
        margin: 0 -5px 15px -5px;
    }

    .logo-section {
        text-align: center;
        margin-bottom: 28px;
        page-break-inside: avoid;
    }
    .logo-img {
        max-height: 68px;
        max-width: 200px;
    }
    .logo-fallback {
        font-size: 22px;
        font-weight: bold;
        color: #1a1a2e;
    }

    .invoice-header {
        display: table;
        width: 100%;
        margin-bottom: 18px;
        page-break-inside: avoid;
    }
    .invoice-info-section {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        text-align: left;
        padding-right: 15px;
    }
    .bill-to-section {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding-left: 15px;
    }
    .section-title {
        font-size: 11px;
        font-weight: bold;
        color: #0d9488;
        text-transform: uppercase;
        margin-bottom: 6px;
        padding-bottom: 3px;
        border-bottom: 2px solid #0d9488;
        letter-spacing: 0.4px;
    }
    .invoice-title {
        font-size: 26px;
        font-weight: bold;
        color: #0d9488;
        margin-bottom: 10px;
        letter-spacing: 1px;
    }
    .commission-doc-title {
        font-size: 20px;
        font-weight: bold;
        color: #0d9488;
        margin-bottom: 4px;
        letter-spacing: 0.5px;
    }
    .commission-sub {
        font-size: 9px;
        color: #64748b;
        margin-bottom: 3px;
    }
    .customer-name {
        font-weight: bold;
        font-size: 12px;
        color: #1a1a2e;
        margin-bottom: 3px;
    }
    .customer-details {
        color: #555;
        line-height: 1.45;
        font-size: 9px;
    }
    .invoice-meta-table {
        border-collapse: collapse;
    }
    .invoice-meta-table td {
        padding: 2px 0;
        font-size: 10px;
    }
    .invoice-meta-table .label {
        color: #666;
        padding-right: 10px;
        text-align: left;
        font-weight: 500;
    }
    .invoice-meta-table .value {
        color: #1a1a2e;
        font-weight: 600;
        text-align: right;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        margin-top: 10px;
        page-break-inside: auto;
    }
    .items-table thead tr {
        background-color: #6b7280;
    }
    .items-table th {
        color: #ffffff;
        font-weight: bold;
        font-size: 9px;
        padding: 5px 7px;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        border: 1px solid #4b5563;
    }
    .items-table th.text-right {
        text-align: right;
    }
    .items-table th.text-center {
        text-align: center;
    }
    .items-table td {
        padding: 5px 7px;
        border: 1px solid #e5e7eb;
        font-size: 9px;
        vertical-align: middle;
    }
    .items-table td.text-right {
        text-align: right;
    }
    .items-table td.text-center {
        text-align: center;
    }
    .items-table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }
    .items-table:not(.items-table-break) {
        page-break-inside: avoid;
    }
    .items-table.items-table-break tbody tr {
        page-break-inside: avoid;
    }

    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }
    .status-paid {
        background-color: #dcfce7;
        color: #166534;
    }
    .status-sent {
        background-color: #dbeafe;
        color: #1e40af;
    }
    .status-overdue {
        background-color: #fee2e2;
        color: #991b1b;
    }
    .status-draft {
        background-color: #f1f5f9;
        color: #475569;
    }
    .status-partially_paid {
        background-color: #fef3c7;
        color: #92400e;
    }

    .totals-section {
        display: table;
        width: 100%;
        margin-top: 12px;
        page-break-inside: avoid;
    }
    .totals-spacer {
        display: table-cell;
        width: 60%;
    }
    .totals-box {
        display: table-cell;
        width: 40%;
    }
    .totals-table {
        width: 100%;
        border-collapse: collapse;
    }
    .totals-table td {
        padding: 4px 9px;
        font-size: 10px;
    }
    .totals-table .label {
        text-align: right;
        color: #555;
        padding-right: 12px;
        font-weight: 500;
    }
    .totals-table .value {
        text-align: right;
        color: #1a1a2e;
        font-weight: 600;
    }
    .totals-table tr.total-row {
        background-color: #0d9488;
        border: none;
    }
    .totals-table tr.total-row td {
        color: #fff;
        font-weight: bold;
        font-size: 12px;
        padding: 8px 10px;
    }
    .totals-table tr.total-row .label {
        color: #fff;
        font-size: 10px;
        text-transform: uppercase;
    }

    .payment-section {
        margin-top: 72px;
        padding-top: 36px;
        padding-bottom: 14px;
        padding-left: 16px;
        padding-right: 16px;
        background-color: #f0fdf4;
        border-left: 4px solid #22c55e;
        page-break-inside: avoid;
    }
    .payment-title {
        font-size: 11px;
        font-weight: bold;
        color: #166534;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }
    .payment-details {
        display: table;
        width: 100%;
    }
    .payment-row {
        display: table-row;
    }
    .payment-label {
        display: table-cell;
        padding: 3px 0;
        color: #475569;
        width: 130px;
        font-weight: 600;
        font-size: 9px;
    }
    .payment-value {
        display: table-cell;
        padding: 3px 0;
        color: #1a1a2e;
        font-weight: 700;
        font-size: 10px;
    }

    .footer {
        margin-top: 18px;
        padding: 10px 12px;
        border-top: 2px solid #0d9488;
        font-size: 9px;
        color: #666;
        background-color: #f8fafc;
        page-break-inside: avoid;
    }
    .footer--minimal {
        padding: 8px 10px;
        margin-top: 14px;
    }
    .footer-row {
        display: table;
        width: 100%;
        margin-bottom: 6px;
    }
    .footer-col {
        display: table-cell;
        width: 33.33%;
        vertical-align: top;
        padding: 0 8px;
    }
    .footer-col.center {
        text-align: center;
    }
    .footer-col.right {
        text-align: right;
    }
    .footer-note {
        text-align: center;
        margin-top: 6px;
        font-style: italic;
        color: #0d9488;
        font-weight: 500;
        font-size: 9px;
    }
    .footer-note--meta {
        color: #64748b;
        font-style: normal;
        font-size: 8px;
        margin-top: 0;
    }
    .footer-label {
        font-weight: 700;
        color: #0d9488;
        margin-bottom: 2px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }
    .pdf-badge {
        padding: 1px 4px;
        background: #e4e4e7;
        border-radius: 2px;
        font-size: 8px;
    }
    .user-block-heading {
        font-size: 12px;
        margin: 14px 0 6px;
        padding-bottom: 3px;
        border-bottom: 1px solid #cbd5e1;
        color: #1a1a2e;
    }

    /* After centred logo: ensure new block formatting context (DomPDF) */
    .pdf-flow-reset {
        clear: both;
        width: 100%;
        height: 0;
        line-height: 0;
        font-size: 0;
        overflow: hidden;
    }

    /* Commission PDFs: keep data tables flush left, full width */
    .commission-pdf-main {
        width: 100%;
        text-align: left !important;
    }
    table.commission-items-table {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        float: none !important;
        clear: both;
        display: table;
        table-layout: fixed;
        page-break-inside: auto !important;
    }

    /* Right-aligned sub-totals without display:table row (avoids DomPDF table glitches) */
    .commission-summary-totals {
        margin-top: 16px;
        width: 100%;
        text-align: right;
        page-break-inside: avoid;
    }
    .commission-summary-totals table.totals-table {
        width: 42%;
        max-width: 100%;
        margin-left: auto;
        margin-right: 0;
        display: table;
    }
</style>
