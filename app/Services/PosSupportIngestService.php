<?php

namespace App\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Support\Str;

class PosSupportIngestService
{
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
            $externalId = (string) ($row['id'] ?? '');
            if ($externalId === '') {
                $out[] = ['id' => null, 'error' => 'missing id'];
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
            $subject = '[POS] ' . Str::limit($shopName . ': ' . ($message !== '' ? $message : 'Support request'), 120, '…');

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
                'id' => (int) $row['id'],
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

        $tickets = Ticket::query()
            ->where('source', 'pos_support')
            ->whereIn('pos_external_id', $ids)
            ->get(['id', 'pos_external_id', 'pos_support_status', 'pos_resolution_notes', 'updated_at']);

        $byExternal = $tickets->keyBy(fn (Ticket $t) => (string) $t->pos_external_id);

        $result = [];
        foreach ($ids as $id) {
            $t = $byExternal->get($id);
            if (!$t) {
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
