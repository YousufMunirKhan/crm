<?php

namespace App\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Support\Str;

class PosSupportIngestService
{
    /**
     * POS tickets are ingested at `high` priority, and high is 8 hours
     * everywhere else in this product - see TicketService::calculateSLADueDate.
     */
    private const SLA_HOURS = 8;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{created: int, updated: int, items: array<int, array<string, mixed>>}
     */
    public function syncFromPos(array $items): array
    {
        $created = 0;
        $updated = 0;
        $out = [];

        foreach ($items as $row) {
            $posRowId = trim((string) ($row['id'] ?? ''));
            if ($posRowId === '') {
                $out[] = ['id' => null, 'error' => 'missing id'];
                continue;
            }

            // Desktop POS often sends the same `id` for every message (e.g. shop or machine id).
            // Without a per-message key, every sync updates one row. Composite id = POS id + payload fingerprint.
            $externalId = $this->resolvePosExternalId($row, $posRowId);
            if ($externalId === '') {
                $out[] = ['id' => null, 'error' => 'invalid external id'];
                continue;
            }

            $ticket = Ticket::query()
                ->where('source', 'pos_support')
                ->where('pos_external_id', $externalId)
                ->first();

            $phone = isset($row['telephone']) ? trim((string) $row['telephone']) : '';
            $customerId = null;
            if ($phone !== '') {
                $customer = Customer::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => isset($row['shopName']) ? (string) $row['shopName'] : $phone]
                );
                $customerId = $customer->id;
            }

            $shopName = (string) ($row['shopName'] ?? 'POS Shop');
            $message = (string) ($row['message'] ?? '');
            $subject = '[POS] ' . Str::limit($shopName . ': ' . self::summarise($message), 120, '…');

            $description = $this->buildDescription($row);

            $posSubmitted = $this->parseDate($row['createdAt'] ?? null);
            $posSent = $this->parseDate($row['sentAt'] ?? null);

            $payload = [
                'customer_id' => $customerId,
                'subject' => $subject,
                'description' => $description,
                'priority' => 'high',
                'status' => 'open',
                'pos_shop_name' => $shopName,
                'pos_telephone' => $phone !== '' ? $phone : null,
                'pos_address' => isset($row['address']) ? Str::limit((string) $row['address'], 500) : null,
                'pos_computer_name' => isset($row['computerName']) ? Str::limit((string) $row['computerName'], 120) : null,
                'pos_submitted_at' => $posSubmitted,
                'pos_sent_at' => $posSent,
            ];

            if (!$ticket) {
                // All 34 POS tickets had a null sla_due_at, so the nightly
                // breach check walked past every one of them: they could not be
                // late, whatever happened. The clock starts when the shop hit
                // the problem, not when we happened to sync - otherwise an
                // eight-hour-old fault arrives looking brand new.
                $payload['sla_due_at'] = ($posSubmitted ?? now())->copy()->addHours(self::SLA_HOURS);
                $payload['ticket_number'] = $this->generateTicketNumber();
                $payload['source'] = 'pos_support';
                $payload['pos_external_id'] = $externalId;
                $payload['pos_support_status'] = 'pending';
                $payload['created_by'] = null;
                $payload['assigned_to'] = null;
                $ticket = Ticket::create($payload);
                $created++;
            } else {
                if (in_array($ticket->pos_support_status, ['solved', 'not_an_issue'], true)) {
                    $ticket->update(array_merge($payload, [
                        'pos_shop_name' => $payload['pos_shop_name'],
                        'pos_telephone' => $payload['pos_telephone'],
                        'pos_address' => $payload['pos_address'],
                        'pos_computer_name' => $payload['pos_computer_name'],
                        'description' => $description,
                        'subject' => $subject,
                        'customer_id' => $customerId,
                        'pos_submitted_at' => $posSubmitted ?? $ticket->pos_submitted_at,
                        'pos_sent_at' => $posSent,
                    ]));
                } else {
                    $ticket->update(array_merge($payload, [
                        'pos_support_status' => $this->mapIncomingPosStatus($row['status'] ?? 'Pending'),
                    ]));
                }
                $updated++;
            }

            $out[] = [
                'id' => is_numeric($row['id']) ? (int) $row['id'] : $row['id'],
                'crm_ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ];
        }

        return ['created' => $created, 'updated' => $updated, 'items' => $out];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statusForIds(string $idsQuery): array
    {
        $ids = collect(explode(',', $idsQuery))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $id) {
            $t = $this->latestTicketForPosRootId((string) $id);
            if (! $t) {
                $result[] = ['id' => is_numeric($id) ? (int) $id : $id, 'status' => null, 'found' => false];
                continue;
            }
            $result[] = [
                'id' => is_numeric($id) ? (int) $id : $id,
                'status' => $this->toPosStatusLabel($t->pos_support_status),
                'found' => true,
                'updatedAt' => $t->updated_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    /**
     * Stable CRM key for one POS payload. Must stay within tickets.pos_external_id (64).
     * Pairs with {@see latestTicketForPosRootId()} so POS can still poll status using its `id`.
     */
    private function resolvePosExternalId(array $row, string $posRowId): string
    {
        $posRowId = trim($posRowId);
        if ($posRowId === '') {
            return '';
        }

        $idHead = Str::limit($posRowId, 48, '');
        $msg = (string) ($row['message'] ?? '');
        $created = (string) ($row['createdAt'] ?? '');
        $computer = (string) ($row['computerName'] ?? '');

        // Deliberately not `sentAt`. That is when the desktop client managed to
        // transmit, not part of what happened - and the client retries, so
        // including it turned one crash into a new ticket per attempt. Six rows
        // in the live queue are the same Gurkha Corner fault sent six times.
        $fingerprint = substr(hash('sha256', $posRowId . "\0" . $msg . "\0" . $created . "\0" . $computer), 0, 12);

        return Str::limit($idHead . '-' . $fingerprint, 64, '');
    }

    /**
     * Latest ticket for a POS root id: legacy bare `5` or composite `5-abc…`.
     */
    private function latestTicketForPosRootId(string $rootId): ?Ticket
    {
        $rootId = trim($rootId);
        if ($rootId === '') {
            return null;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $rootId);

        return Ticket::query()
            ->where('source', 'pos_support')
            ->where(function ($q) use ($rootId, $escaped) {
                $q->where('pos_external_id', $rootId)
                    ->orWhere('pos_external_id', 'like', $escaped . '-%');
            })
            ->orderByDesc('updated_at')
            ->first(['id', 'pos_external_id', 'pos_support_status', 'pos_resolution_notes', 'updated_at']);
    }

    /**
     * One readable line out of a desktop crash report.
     *
     * The POS client posts its whole report in `message`, and that report opens
     * with a literal "=== Context ===" header. Taking the message verbatim gave
     * every ticket the same subject and the same preview - a support queue where
     * thirty-four rows read "=== Context ===" and nothing tells them apart until
     * you open each one.
     *
     * The two lines that actually identify a crash are the context line the user
     * or the code wrote ("Error saving product:") and the exception's own
     * message ("Product with ProductID 0 not found."). A message that is not in
     * this format - somebody typing a real sentence - is passed through
     * untouched.
     */
    public static function summarise(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return 'Support request';
        }

        if (! str_contains($message, '=== Context ===')) {
            return Str::limit($message, 139, '…');
        }

        $parts = [];

        // The context block: everything between its header and the next one.
        if (preg_match('/=== Context ===\s*(.*?)(?:\n===|\z)/s', $message, $m)) {
            $context = trim(preg_replace('/\s+/', ' ', $m[1]));

            if ($context !== '') {
                $parts[] = rtrim($context, ': ');
            }
        }

        // The exception's own one-line message, which is the actual fault.
        if (preg_match('/=== Exception ===[\s\S]*?\n\s*Message:[ \t]*([^\n]+)/', $message, $m)) {
            $parts[] = trim($m[1]);
        }

        if ($parts === []) {
            return Str::limit($message, 139, '…');
        }

        return Str::limit(implode(' - ', $parts), 139, '…');
    }

    private function buildDescription(array $row): string
    {
        $lines = [
            '— POS Desktop support —',
            'Message: ' . ($row['message'] ?? ''),
            'Computer: ' . ($row['computerName'] ?? '—'),
            'Shop: ' . ($row['shopName'] ?? '—'),
            'Telephone: ' . ($row['telephone'] ?? '—'),
            'Address: ' . ($row['address'] ?? '—'),
        ];

        return implode("\n", $lines);
    }

    private function parseDate(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateTicketNumber(): string
    {
        return 'TKT-POS-' . date('Ymd') . '-' . strtoupper(Str::random(5));
    }

    private function mapIncomingPosStatus(mixed $status): string
    {
        $s = strtolower(trim((string) $status));

        return match ($s) {
            'solved' => 'solved',
            'not an issue', 'not_an_issue', 'notanissue' => 'not_an_issue',
            default => 'pending',
        };
    }

    private function toPosStatusLabel(string $internal): string
    {
        return match ($internal) {
            'solved' => 'Solved',
            'not_an_issue' => 'Not an Issue',
            default => 'Pending',
        };
    }
}
