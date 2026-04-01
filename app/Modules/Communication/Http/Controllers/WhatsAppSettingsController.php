<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppCloudClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppSettingsController extends Controller
{
    public function index()
    {
        $settings = WhatsAppSetting::first();
        
        if (!$settings) {
            return response()->json([
                'waba_id' => null,
                'phone_number_id' => null,
                'meta_app_id' => null,
                'verify_token' => null,
                'graph_version' => config('services.whatsapp.graph_version', 'v20.0'),
                'is_enabled' => false,
            ]);
        }

        return response()->json([
            'waba_id' => $settings->waba_id,
            'phone_number_id' => $settings->phone_number_id,
            'meta_app_id' => $settings->meta_app_id,
            'verify_token' => $settings->verify_token,
            'graph_version' => $settings->graph_version,
            'is_enabled' => $settings->is_enabled,
            // Note: access_token and meta_app_secret are not returned
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'waba_id' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'meta_app_id' => ['nullable', 'string', 'max:64'],
            'meta_app_secret' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string'],
            'graph_version' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = WhatsAppSetting::first();
        
        if (!$settings) {
            $settings = new WhatsAppSetting();
        }

        if (isset($data['access_token']) && $data['access_token'] !== '') {
            $settings->access_token = $data['access_token'];
        }

        if (array_key_exists('meta_app_secret', $data) && $data['meta_app_secret'] !== '') {
            $settings->meta_app_secret = $data['meta_app_secret'];
        }

        $settings->fill(collect($data)->except(['access_token', 'meta_app_secret'])->all());
        $settings->save();

        return response()->json([
            'message' => 'WhatsApp settings saved successfully',
            'settings' => [
                'waba_id' => $settings->waba_id,
                'phone_number_id' => $settings->phone_number_id,
                'meta_app_id' => $settings->meta_app_id,
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
        if (!$settings->waba_id) {
            return response()->json([
                'success' => false,
                'message' => 'WABA ID is missing',
            ], 400);
        }

        try {
            $client = new WhatsAppCloudClient(
                $settings->access_token,
                $settings->graph_version
            );

            $client->listTemplates($settings->waba_id, [
                'limit' => 1,
                'fields' => 'name',
            ]);

            $appId = $settings->meta_app_id ?: config('services.whatsapp.app_id');
            $appSecret = $settings->meta_app_secret ?: config('services.whatsapp.app_secret');

            $inspection = $this->inspectTokenSendPermission(
                $settings->access_token,
                $settings->graph_version ?? 'v20.0',
                (string) $settings->phone_number_id,
                $appId,
                $appSecret
            );

            if ($inspection['detail'] === 'missing_app_credentials') {
                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp API reachable (templates list works). To verify send permission (and explain error #10), add Meta App ID and Meta App Secret in the fields below (same as Developer app Basic settings), save, then Test connection again.',
                    'hint' => 'If customer sends fail with (#10): your access token still needs whatsapp_business_messaging for this Phone Number ID in Meta Business / System users.',
                    'token_inspection' => $inspection,
                ]);
            }

            if ($inspection['can_send'] === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'This token can reach your WhatsApp Business Account, but Meta reports it is not allowed to send messages from Phone Number ID ' . $settings->phone_number_id . '. That matches WhatsApp send error (#10) Application does not have permission.',
                    'hint' => 'In Meta Business Settings: assign WhatsApp message-sending permission to the user/system user used for this token, scoped to this phone number (whatsapp_business_messaging). Then generate a new permanent token and paste it into CRM. Reading templates uses a different permission than sending.',
                    'token_inspection' => $inspection,
                ], 422);
            }

            if ($inspection['can_send'] === null) {
                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp API reachable. Could not confirm send permission (debug_token failed). If sends still return (#10), regenerate the token with whatsapp_business_messaging for this phone number.',
                    'token_inspection' => $inspection,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection successful. Token can list templates and Meta reports permission to send from this phone number.',
                'token_inspection' => $inspection,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'hint' => 'Check WABA ID, Phone Number ID, token scopes, and whether app mode/number permissions are correct in Meta.',
            ], 500);
        }
    }

    /**
     * Uses Graph debug_token to see if whatsapp_business_messaging covers this Phone Number ID.
     *
     * @return array{detail: string, checked: bool, can_send: ?bool, targets?: array}
     */
    private function inspectTokenSendPermission(
        string $accessToken,
        string $graphVersion,
        string $phoneNumberId,
        ?string $appId,
        ?string $appSecret
    ): array {
        if (!$appId || !$appSecret) {
            return [
                'checked' => false,
                'can_send' => null,
                'detail' => 'missing_app_credentials',
            ];
        }

        $gv = trim($graphVersion) !== '' ? trim($graphVersion) : 'v20.0';
        if (!str_starts_with(strtolower($gv), 'v')) {
            $gv = 'v' . $gv;
        }

        $url = "https://graph.facebook.com/{$gv}/debug_token";
        $response = Http::timeout(15)->get($url, [
            'input_token' => $accessToken,
            'access_token' => $appId . '|' . $appSecret,
        ]);

        if (!$response->successful()) {
            return [
                'checked' => true,
                'can_send' => null,
                'detail' => 'debug_token_http_error',
                'http_status' => $response->status(),
            ];
        }

        $data = $response->json('data');
        if (!is_array($data) || empty($data['is_valid'])) {
            return [
                'checked' => true,
                'can_send' => false,
                'detail' => 'token_invalid_or_expired',
            ];
        }

        $granular = $data['granular_scopes'] ?? [];
        $flatScopes = $data['scopes'] ?? [];

        if (is_array($granular)) {
            foreach ($granular as $gs) {
                if (!is_array($gs) || ($gs['scope'] ?? '') !== 'whatsapp_business_messaging') {
                    continue;
                }
                $targets = $gs['target_ids'] ?? [];
                if (!is_array($targets) || $targets === []) {
                    return [
                        'checked' => true,
                        'can_send' => true,
                        'detail' => 'messaging_scope_no_specific_targets',
                    ];
                }
                $phoneStr = (string) $phoneNumberId;
                foreach ($targets as $tid) {
                    if ((string) $tid === $phoneStr) {
                        return [
                            'checked' => true,
                            'can_send' => true,
                            'detail' => 'messaging_scope_includes_phone_number_id',
                        ];
                    }
                }

                return [
                    'checked' => true,
                    'can_send' => false,
                    'detail' => 'messaging_scope_other_assets_only',
                    'targets' => array_values($targets),
                ];
            }
        }

        if (is_array($flatScopes)) {
            foreach ($flatScopes as $scope) {
                if (is_string($scope) && $scope === 'whatsapp_business_messaging') {
                    return [
                        'checked' => true,
                        'can_send' => true,
                        'detail' => 'legacy_flat_messaging_scope',
                    ];
                }
            }
        }

        return [
            'checked' => true,
            'can_send' => false,
            'detail' => 'no_whatsapp_business_messaging_scope',
            'flat_scopes_sample' => is_array($flatScopes) ? array_slice($flatScopes, 0, 12) : [],
        ];
    }
}

