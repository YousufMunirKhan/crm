<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Get public settings (for login page, etc.)
     */
    public function publicSettings()
    {
        $publicKeys = ['logo_url', 'company_name', 'company_registration_no', 'company_vat', 'company_phone', 'company_address', 'company_email', 'company_website', 'pwa_enabled', 'social_facebook_url', 'social_twitter_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url'];
        $settings = Setting::whereIn('key', $publicKeys)->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($request->settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function get($key)
    {
        $setting = Setting::where('key', $key)->first();
        return response()->json(['value' => $setting?->value]);
    }

    /**
     * Upload logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,gif,svg,webp', 'max:2048'],
        ]);

        // Delete old logo if exists
        $oldLogo = Setting::where('key', 'logo_url')->first();
        if ($oldLogo && $oldLogo->value) {
            $oldPath = str_replace('/storage/', '', $oldLogo->value);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos', 'public');
        $url = '/storage/' . $path;

        Setting::updateOrCreate(
            ['key' => 'logo_url'],
            ['value' => $url]
        );

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'url' => $url,
        ]);
    }

    /**
     * Delete logo
     */
    public function deleteLogo()
    {
        $logo = Setting::where('key', 'logo_url')->first();
        if ($logo && $logo->value) {
            $path = str_replace('/storage/', '', $logo->value);
            Storage::disk('public')->delete($path);
            $logo->delete();
        }

        return response()->json(['message' => 'Logo deleted successfully']);
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtp(Request $request)
    {
        $request->validate([
            'smtp_host' => ['nullable', 'string'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_from_email' => ['nullable', 'email'],
            'smtp_from_name' => ['nullable', 'string'],
            'admin_notification_email' => ['nullable', 'email'],
        ]);

        foreach ($request->all() as $key => $value) {
            if ($value !== null && $value !== '') {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        return response()->json(['message' => 'SMTP settings updated successfully']);
    }

    /**
     * Test SMTP connection
     * Uses Settings SMTP config and ensures mail.default is 'smtp' so emails actually send.
     */
    public function testSmtp(Request $request)
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $host = Setting::where('key', 'smtp_host')->first()?->value;
        if (empty($host) || trim($host) === '') {
            return response()->json([
                'message' => 'Please save SMTP settings (including SMTP Host) first before sending test email.',
            ], 400);
        }

        try {
            \App\Services\MailConfigFromDatabase::apply();

            Mail::raw('This is a test email from your CRM system to verify SMTP settings.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('CRM - SMTP Test Email');
            });

            return response()->json(['message' => 'Test email sent successfully! Check your inbox.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send test email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update SMS settings (VoodooSMS)
     */
    public function updateSms(Request $request)
    {
        $request->validate([
            'sms_api_key' => ['nullable', 'string'],
            'sms_secret_key' => ['nullable', 'string'],
            'sms_sender_name' => ['nullable', 'string', 'max:11'], // Max 11 chars for sender name
            'sms_default_message' => ['nullable', 'string'],
        ]);

        foreach ($request->all() as $key => $value) {
            if ($value !== null && $value !== '') {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        return response()->json(['message' => 'SMS settings updated successfully']);
    }

    /**
     * Test SMS sending
     */
    public function testSms(Request $request)
    {
        $request->validate([
            'test_phone' => ['required', 'string'],
            'test_message' => ['nullable', 'string'],
        ]);

        try {
            $smsService = app(\App\Services\SmsService::class);
            $result = $smsService->test($request->test_phone, $request->test_message);

            if ($result['success']) {
                return response()->json([
                    'message' => 'Test SMS sent successfully!',
                    'response' => $result['response']
                ]);
            } else {
                return response()->json([
                    'message' => $result['message'],
                    'response' => $result['response']
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update WhatsApp settings
     */
    public function updateWhatsapp(Request $request)
    {
        $request->validate([
            'whatsapp_provider' => ['nullable', 'in:twilio,360dialog,wati,other'],
            'whatsapp_api_key' => ['nullable', 'string'],
            'whatsapp_phone_id' => ['nullable', 'string'],
            'whatsapp_business_id' => ['nullable', 'string'],
            'whatsapp_access_token' => ['nullable', 'string'],
        ]);

        foreach ($request->all() as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        return response()->json(['message' => 'WhatsApp settings updated successfully']);
    }

    /**
     * Update Facebook/Meta settings
     */
    public function updateFacebook(Request $request)
    {
        $request->validate([
            'facebook_app_id' => ['nullable', 'string'],
            'facebook_app_secret' => ['nullable', 'string'],
            'facebook_page_id' => ['nullable', 'string'],
            'facebook_access_token' => ['nullable', 'string'],
            'facebook_pixel_id' => ['nullable', 'string'],
        ]);

        foreach ($request->all() as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        return response()->json(['message' => 'Facebook settings updated successfully']);
    }

    /**
     * Get PWA settings (public endpoint)
     */
    public function pwa()
    {
        $enabled = Setting::where('key', 'pwa_enabled')->first();
        
        return response()->json([
            'enabled' => $enabled ? filter_var($enabled->value, FILTER_VALIDATE_BOOLEAN) : true,
        ]);
    }

    /**
     * Update PWA settings (admin only)
     */
    public function updatePwa(Request $request)
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'pwa_enabled'],
            ['value' => $request->enabled ? 'true' : 'false']
        );

        return response()->json(['message' => 'PWA settings updated successfully']);
    }

    /**
     * Helper method to get a setting value
     */
    public static function getValue($key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}

