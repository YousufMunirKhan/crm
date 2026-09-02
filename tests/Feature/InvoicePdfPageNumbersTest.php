<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Services\InvoiceService;
use App\Support\PdfDocumentBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice PDFs footed every page with "Page 1 / 0".
 *
 * The template asked for it in CSS - counter(page) " / " counter(pages) - and
 * DomPDF does not resolve counter(pages) there: the total is only known after
 * pagination, and the :after content is laid out before it. The counter is
 * stamped from the canvas instead, where DomPDF substitutes {PAGE_COUNT} per
 * page as it writes the file out.
 */
class InvoicePdfPageNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWith(int $lineItems): Invoice
    {
        $customer = Customer::create(['name' => 'The Black Horse Pub', 'phone' => '447700900500']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/'.str_pad((string) $lineItems, 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100, 'vat_rate' => 20, 'vat_amount' => 20, 'total' => 120,
            'amount_paid' => 0, 'currency' => 'GBP', 'status' => 'draft',
        ]);

        for ($i = 1; $i <= $lineItems; $i++) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Line item '.$i.' - a reasonably long product description',
                'quantity' => 1, 'unit_price' => 10, 'line_total' => 10,
            ]);
        }

        return $invoice;
    }

    /**
     * Render the real template, but in a core font and with compression off, so
     * the counter can be read straight back out of the file. DejaVu Sans is
     * embedded as glyph codes and cannot be.
     */
    private function readableCounters(Invoice $invoice): array
    {
        $branding = PdfDocumentBranding::package();
        $html = view('invoices.pdf', [
            'invoice' => $invoice->load(['customer', 'items', 'payments.receivedBy']),
            'logoUrl' => $branding['logoUrl'],
            'settings' => $branding['settings'],
        ])->render();

        $html = str_replace('DejaVu Sans', 'Helvetica', $html);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        // Same stamping the service does.
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('Helvetica', 'normal');
        $width = $metrics->getTextWidth('Page 00 / 00', $font, 9);
        $canvas->page_text(
            ($canvas->get_width() - $width) / 2,
            $canvas->get_height() - 26,
            'Page {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            9,
            [0.37, 0.39, 0.45]
        );

        $canvas->get_cpdf()->options['compression'] = 0;
        $raw = $dompdf->output();

        preg_match_all('/Page (\d+) \/ (\d+)/', $raw, $m, PREG_SET_ORDER);

        return array_values(array_unique(array_map(
            fn ($set) => 'Page '.$set[1].' / '.$set[2],
            $m
        )));
    }

    public function test_a_one_page_invoice_reads_page_1_of_1(): void
    {
        $this->assertSame(['Page 1 / 1'], $this->readableCounters($this->invoiceWith(3)));
    }

    public function test_every_page_of_a_long_invoice_is_numbered_against_the_real_total(): void
    {
        $seen = $this->readableCounters($this->invoiceWith(90));

        $pages = count($seen);
        $this->assertGreaterThan(1, $pages, 'expected this invoice to span several pages');

        $expected = [];
        for ($i = 1; $i <= $pages; $i++) {
            $expected[] = 'Page '.$i.' / '.$pages;
        }

        // The point of the bug: the total must be the real page count, never 0.
        $this->assertSame($expected, $seen);
    }

    public function test_the_service_lays_the_document_out_only_once(): void
    {
        $pdf = app(InvoiceService::class)->generatePDF($this->invoiceWith(90));

        // stampPageNumbers() renders early so it can reach the canvas. If
        // output() laid the document out again the file would carry every page
        // twice, so count the page objects in the file the user receives.
        $pages = $pdf->getDomPDF()->getCanvas()->get_page_count();
        $raw = $pdf->output();
        $pageObjects = preg_match_all('#/Type\s*/Page[^s]#', $raw);

        $this->assertGreaterThan(1, $pages, 'expected this invoice to span several pages');
        $this->assertSame($pages, $pageObjects, 'the PDF carries a different number of pages than were laid out');
    }
}
