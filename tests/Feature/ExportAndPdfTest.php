<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\ImportExport\Exports\CustomersExport;
use App\Modules\ImportExport\Exports\InvoicesExport;
use App\Modules\ImportExport\Exports\LeadsExport;
use App\Modules\ImportExport\Exports\TicketsExport;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Covers the spreadsheet and PDF chain.
 *
 * These paths had no tests at all, which is how a breaking change in
 * maatwebsite/excel v4 - FromCollection::collection() gained a
 * ": Illuminate\Support\Enumerable" return type - could have reached
 * production as a fatal error on every export.
 */
class ExportAndPdfTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Acme Ltd',
            'phone' => '447700900900',
            'email' => 'acme@example.com',
            'city' => 'Manchester',
            'postcode' => 'M1 1AA',
        ]);
    }

    public function test_customers_export_produces_a_readable_xlsx(): void
    {
        $this->customer();

        $raw = Excel::raw(
            new CustomersExport(Customer::all()),
            ExcelFormat::XLSX
        );

        $this->assertNotEmpty($raw);
        // XLSX is a zip archive - "PK" is its magic number.
        $this->assertSame('PK', substr($raw, 0, 2));
    }

    public function test_customers_export_produces_csv_containing_the_row(): void
    {
        $this->customer();

        $csv = Excel::raw(new CustomersExport(Customer::all()), ExcelFormat::CSV);

        $this->assertStringContainsString('Acme Ltd', $csv);
        $this->assertStringContainsString('447700900900', $csv);
    }

    public function test_exports_accept_a_plain_array_as_well_as_a_collection(): void
    {
        // The constructor is untyped, so both shapes reach collection().
        $this->customer();
        $asArray = Customer::all()->all();

        $csv = Excel::raw(new CustomersExport($asArray), ExcelFormat::CSV);

        $this->assertStringContainsString('Acme Ltd', $csv);
    }

    public function test_every_export_class_renders_without_error(): void
    {
        $customer = $this->customer();

        $lead = Lead::create(['customer_id' => $customer->id, 'stage' => 'lead']);

        $exports = [
            new CustomersExport(Customer::all()),
            new LeadsExport(Lead::with('customer')->get()),
            new InvoicesExport(new Collection()),
            new TicketsExport(new Collection()),
        ];

        foreach ($exports as $export) {
            $csv = Excel::raw($export, ExcelFormat::CSV);
            $this->assertIsString($csv, $export::class.' failed to render');
        }
    }

    public function test_invoice_pdf_renders(): void
    {
        $customer = $this->customer();

        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Retail ePOS', 'quantity' => 1, 'unit_price' => 500.00],
            ],
        ]);

        $output = app(InvoiceService::class)->generatePDF($invoice->fresh(['customer', 'items']))->output();

        $this->assertNotEmpty($output);
        // Every PDF starts with the %PDF- header.
        $this->assertSame('%PDF-', substr($output, 0, 5));
    }

    public function test_pdf_filename_is_capped_and_filesystem_safe(): void
    {
        $customer = Customer::create([
            'name' => str_repeat('Very Long Business Name ', 12),
            'phone' => '447700900901',
        ]);

        $invoice = app(InvoiceService::class)->create([
            'customer_id' => $customer->id,
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10.00]],
        ]);

        $name = app(InvoiceService::class)->pdfFileName($invoice->fresh('customer'));

        $this->assertStringEndsWith('.pdf', $name);
        $this->assertLessThan(160, strlen($name), 'Filename should be capped');
        // pdfFileName sanitises to [A-Za-z0-9_-] plus the .pdf suffix.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.pdf$/', $name);
    }
}
