<?php

namespace App\Modules\ImportExport\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private $customers) {}

    public function collection()
    {
        return $this->customers;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Phone',
            'Email',
            'Address',
            'Postcode',
            'City',
            'VAT Number',
            'Notes',
            'Created At',
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->id,
            $customer->name,
            $customer->phone,
            $customer->email,
            $customer->address,
            $customer->postcode,
            $customer->city,
            $customer->vat_number,
            $customer->notes,
            $customer->created_at,
        ];
    }
}

