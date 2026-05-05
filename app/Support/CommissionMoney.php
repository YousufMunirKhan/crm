<?php

namespace App\Support;

final class CommissionMoney
{
    public static function format(string $currency, float|string|int|null $amount): string
    {
        $n = number_format(round((float) $amount, 2), 2, '.', ',');

        return match ($currency) {
            'GBP' => '£'.$n,
            'PKR' => 'PKR '.$n,
            default => $currency.' '.$n,
        };
    }

    /** @param  mixed  $role */
    public static function humanizeRole(?string $role): string
    {
        return match ($role) {
            'appointment_creator' => 'Appointment Creator',
            'single_owner' => 'Single Owner',
            'closer' => 'Closer',
            default => $role ? (string) $role : '—',
        };
    }
}
