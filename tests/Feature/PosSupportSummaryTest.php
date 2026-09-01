<?php

namespace Tests\Feature;

use App\Services\PosSupportIngestService;
use Tests\TestCase;

/**
 * The desktop POS posts its entire crash report in one `message` field, and
 * that report opens with a literal "=== Context ===" header. Using the message
 * verbatim gave every ticket the same subject and the same list preview: 34
 * rows in the support queue all reading "=== Context ===", with nothing to tell
 * them apart until somebody opened each one.
 */
class PosSupportSummaryTest extends TestCase
{
    /** A real report, trimmed, copied from production. */
    private const REPORT = <<<'TXT'
    === Context ===
    Error saving product:

    === Exception ===
    [1] System.InvalidOperationException
    Message: Product with ProductID 0 not found.
    StackTrace:
       at ePOS.Repositories.ProductsRepository.UpdateOnlyProduct(Product prd) in C:\ePOS\ProductsRepository.cs:line 1570

    === Environment ===
    Machine: POS-20230926DFO
    User: Manager
    TXT;

    public function test_a_crash_report_becomes_the_two_lines_that_identify_it(): void
    {
        $this->assertSame(
            'Error saving product - Product with ProductID 0 not found.',
            PosSupportIngestService::summarise(self::REPORT)
        );
    }

    public function test_two_different_crashes_no_longer_read_the_same(): void
    {
        $other = str_replace(
            ['Error saving product:', 'Product with ProductID 0 not found.'],
            ['Error printing receipt:', 'Printer not responding.'],
            self::REPORT
        );

        $this->assertNotSame(
            PosSupportIngestService::summarise(self::REPORT),
            PosSupportIngestService::summarise($other),
            'Two unrelated faults must not produce the same summary - that is the bug this fixes.'
        );
    }

    public function test_somebody_typing_a_real_sentence_is_left_alone(): void
    {
        $this->assertSame(
            'Till 2 keeps freezing when we open the reports screen.',
            PosSupportIngestService::summarise('Till 2 keeps freezing when we open the reports screen.')
        );
    }

    public function test_an_empty_message_still_says_something(): void
    {
        $this->assertSame('Support request', PosSupportIngestService::summarise('   '));
    }

    public function test_a_report_with_context_but_no_exception_still_summarises(): void
    {
        $partial = "=== Context ===\nCard reader disconnected mid sale\n\n=== Environment ===\nMachine: POS-1";

        $this->assertSame(
            'Card reader disconnected mid sale',
            PosSupportIngestService::summarise($partial)
        );
    }

    public function test_the_summary_is_short_enough_for_a_subject_line(): void
    {
        $long = "=== Context ===\n".str_repeat('a very long context line ', 40);

        $this->assertLessThanOrEqual(140, mb_strlen(PosSupportIngestService::summarise($long)));
    }
}
