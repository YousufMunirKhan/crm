<?php

namespace Tests\Feature;

use App\Modules\Ticket\Models\Ticket;
use App\Services\PosSupportIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two faults in the desktop POS feed, both found by looking at the live queue.
 *
 * One crash at Gurkha Corner is sitting in it six times, because the key used
 * to recognise a repeat included `sentAt` - when the client managed to
 * transmit - and the client retries. And not one of the 34 tickets had an SLA
 * date, so the nightly breach check walked past every one of them: a POS ticket
 * could not be late, whatever happened to it.
 */
class PosSupportIngestTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => '5',
            'shopName' => 'Gurkha Corner',
            'telephone' => '02085773456',
            'message' => "=== Context ===\nError saving product:\n\n=== Exception ===\nMessage: Product with ProductID 0 not found.",
            'createdAt' => '2026-08-28 16:58:41',
            'sentAt' => '2026-08-28 17:02:00',
            'computerName' => 'POS-20230926DFO',
        ], $overrides);
    }

    private function service(): PosSupportIngestService
    {
        return app(PosSupportIngestService::class);
    }

    public function test_the_same_crash_sent_again_does_not_become_a_second_ticket(): void
    {
        $this->service()->syncFromPos([$this->row()]);

        // The client retried. Same crash, same moment, different transmit time.
        $this->service()->syncFromPos([$this->row(['sentAt' => '2026-08-28 18:30:00'])]);
        $this->service()->syncFromPos([$this->row(['sentAt' => '2026-08-29 09:15:00'])]);

        $this->assertSame(1, Ticket::where('source', 'pos_support')->count());
    }

    public function test_a_genuinely_different_crash_still_opens_its_own_ticket(): void
    {
        $this->service()->syncFromPos([$this->row()]);
        $this->service()->syncFromPos([$this->row([
            'createdAt' => '2026-08-29 11:00:00',
            'message' => "=== Context ===\nError printing receipt:\n\n=== Exception ===\nMessage: Printer not responding.",
        ])]);

        $this->assertSame(2, Ticket::where('source', 'pos_support')->count());
    }

    public function test_a_pos_ticket_now_has_a_deadline_it_can_miss(): void
    {
        $this->service()->syncFromPos([$this->row()]);

        $ticket = Ticket::where('source', 'pos_support')->firstOrFail();

        $this->assertNotNull($ticket->sla_due_at, 'Without this the breach check skips it forever.');

        // The clock starts when the shop hit the problem, not when we synced -
        // otherwise a fault from this morning arrives looking brand new.
        $this->assertSame(
            '2026-08-29 00:58:41',
            $ticket->sla_due_at->format('Y-m-d H:i:s')
        );
    }

    public function test_the_subject_says_what_broke(): void
    {
        $this->service()->syncFromPos([$this->row()]);

        $ticket = Ticket::where('source', 'pos_support')->firstOrFail();

        // It used to read "[POS] Gurkha Corner: === Context ===" on every row.
        $this->assertStringContainsString('Error saving product', $ticket->subject);
        $this->assertStringNotContainsString('=== Context ===', $ticket->subject);
    }

    public function test_a_repeat_does_not_push_the_deadline_back(): void
    {
        $this->service()->syncFromPos([$this->row()]);
        $first = Ticket::where('source', 'pos_support')->firstOrFail()->sla_due_at;

        $this->travel(3)->hours();
        $this->service()->syncFromPos([$this->row(['sentAt' => '2026-08-28 20:00:00'])]);

        // A ticket that resets its own clock every time the client checks in
        // can never breach either.
        $this->assertSame(
            $first->format('Y-m-d H:i:s'),
            Ticket::where('source', 'pos_support')->firstOrFail()->sla_due_at->format('Y-m-d H:i:s')
        );
    }
}
