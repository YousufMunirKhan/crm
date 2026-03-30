<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailUnsubscribe extends Model
{
    protected $fillable = ['email', 'unsubscribed_at'];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public static function isUnsubscribed(string $email): bool
    {
        if (empty($email)) {
            return false;
        }
        return static::where('email', strtolower(trim($email)))->exists();
    }

    public static function unsubscribe(string $email): bool
    {
        $email = strtolower(trim($email));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        static::updateOrCreate(
            ['email' => $email],
            ['unsubscribed_at' => now()]
        );
        return true;
    }
}
