<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppCloudClient;
use Illuminate\Http\Request;

class WhatsAppSettingsController extends Controller
{
    public function index()
    {
        $settings = WhatsAppSetting::first();
        
        if (!$settings) {
            return response()->json([
                'waba_id' => null,
                'phone_number_id' => null,
                'verify_token' => null,
                'graph_version' => config('services.whatsapp.graph_version', 'v20.0'),
                'is_enabled' => false,
            ]);
        }

        return response()->json([
            'waba_id' => $settings->waba_id,
            'phone_number_id' => $settings->phone_number_id,
            'verify_token' => $settings->verify_token,
            'graph_version' => $settings->graph_version,
            'is_enabled' => $settings->is_enabled,
            // Note: access_token is hidden for security
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'waba_id' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string'],
            'graph_version' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = WhatsAppSetting::first();
        
        if (!$settings) {
            $settings = new WhatsAppSetting();
        }

        if (isset($data['access_token'])) {
            $settings->access_token = $data['access_token'];
        }

        $settings->fill($data);
        $settings->save();

        return response()->json([
            'message' => 'WhatsApp settings saved successfully',
            'settings' => [
                'waba_id' => $settings->waba_id,
                'phone_number_id' => $settings->phone_number_id,
                'verify_token' => $settings->verify_token,
                'graph_version' => $settings->graph_version,
                'is_enabled' => $settings->is_enabled,
            ],
        ]);
    }

    public function testConnection(Request $request)
    {
        $settings = WhatsAppSetting::first();
        
        if (!$settings || !$settings->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp is not enabled or configured',
            ], 400);
        }

        if (!$settings->access_token || !$settings->phone_number_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access token or phone number ID is missing',
            ], 400);
        }

        try {
            $client = new WhatsAppCloudClient(
                $settings->access_token,
                $settings->graph_version
            );

            // Try to get phone number info (lightweight test)
            $response = $client->listTemplates($settings->waba_id, ['limit' => 1]);
            
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

