<?php

namespace App\Modules\ImportExport\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private $tickets) {}

    public function collection()
    {
        return $this->tickets;
    }

    public function headings(): array
    {
        return [
            'Ticket Number',
            'Customer Name',
            'Subject',
            'Priority',
            'Status',
            'Assigned To',
            'Created At',
            'Resolved At',
        ];
    }

    public function map($ticket): array
    {
        $assignedNames = $ticket->relationLoaded('assignees') && $ticket->assignees->isNotEmpty()
            ? $ticket->assignees->pluck('name')->filter()->implode(', ')
            : ($ticket->assignee?->name);

        return [
            $ticket->ticket_number,
            $ticket->customer?->name,
            $ticket->subject,
            $ticket->priority,
            $ticket->status,
            $assignedNames,
            $ticket->created_at,
            $ticket->resolved_at,
        ];
    }
}

