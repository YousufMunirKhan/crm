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
        return [
            $ticket->ticket_number,
            $ticket->customer?->name,
            $ticket->subject,
            $ticket->priority,
            $ticket->status,
            $ticket->assignee?->name,
            $ticket->created_at,
            $ticket->resolved_at,
        ];
    }
}

