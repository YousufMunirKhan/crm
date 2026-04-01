<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'waba_id',
        'phone_number_id',
        'meta_app_id',
        'access_token_encrypted',
        'verify_token',
        'graph_version',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'meta_app_secret_encrypted',
    ];

    /**
     * Get decrypted access token
     */
    public function getAccessTokenAttribute(): ?string
    {
        if (!$this->access_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted access token
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['access_token_encrypted'] = null;
            return;
        }

        $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Meta App Secret (for debug_token) — stored encrypted in meta_app_secret_encrypted.
     */
    public function getMetaAppSecretAttribute(): ?string
    {
        if (empty($this->attributes['meta_app_secret_encrypted'] ?? null)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['meta_app_secret_encrypted']);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setMetaAppSecretAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['meta_app_secret_encrypted'] = null;

            return;
        }

        $this->attributes['meta_app_secret_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Get the active settings (singleton pattern)
     */
    public static function getActive(): ?self
    {
        return static::where('is_enabled', true)->first();
    }
}

