<?php

namespace App\Modules\ImportExport\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private $leads) {}

    public function collection()
    {
        return $this->leads;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Customer Name',
            'Customer Phone',
            'Stage',
            'Source',
            'Assigned To',
            'Pipeline Value',
            'Lost Reason',
            'Next Follow-up',
            'Created At',
        ];
    }

    public function map($lead): array
    {
        return [
            $lead->id,
            $lead->customer?->name,
            $lead->customer?->phone,
            $lead->stage,
            $lead->source,
            $lead->assignee?->name,
            $lead->pipeline_value,
            $lead->lost_reason,
            $lead->next_follow_up_at,
            $lead->created_at,
        ];
    }
}

