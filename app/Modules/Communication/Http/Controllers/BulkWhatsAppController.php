<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppCampaign;
use App\Modules\Communication\Models\Communication;
use App\Modules\Communication\Services\WhatsAppService;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\CRM\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BulkWhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsappService,
        private CommunicationService $communicationService
    ) {}

    /**
     * Get all customers with their last WhatsApp message
     */
    public function getCustomersWithLastMessage(Request $request)
    {
        try {
            $query = Customer::query();

            // Search filter
            if ($request->has('search') && $request->get('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('whatsapp_number', 'like', "%{$search}%");
                });
            }

            $customers = $query->with(['communications' => function($q) {
                $q->where('channel', 'whatsapp')
                  ->where('direction', 'outbound')
                  ->with('campaign')
                  ->orderBy('created_at', 'desc')
                  ->limit(1);
            }])
            ->orderBy('name')
            ->paginate($request->get('per_page', 100));

            // Transform to include last message info
            $customers->getCollection()->transform(function($customer) {
                $lastMessage = $customer->communications->first();
                $customer->last_whatsapp_message = $lastMessage ? [
                    'message' => $lastMessage->message,
                    'media_url' => $lastMessage->media_url,
                    'media_type' => $lastMessage->media_type,
                    'status' => $lastMessage->status,
                    'created_at' => $lastMessage->created_at,
                    'campaign_name' => $lastMessage->campaign?->name,
                ] : null;
                unset($customer->communications);
                return $customer;
            });

            return response()->json($customers);
        } catch (\Exception $e) {
            Log::error('Error fetching customers for bulk WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to load customers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send single message to one or multiple customers
     */
    public function sendSingleMessage(Request $request)
    {
        // Handle JSON string for customer_ids
        $customerIds = $request->input('customer_ids');
        if (is_string($customerIds)) {
            $customerIds = json_decode($customerIds, true);
        }
        
        $data = $request->validate([
            'message' => ['required', 'string'],
            'media' => ['nullable', 'image', 'max:10240'], // 10MB max
        ]);
        
        $data['customer_ids'] = $customerIds ?? [];
        
        // Validate customer_ids
        if (empty($data['customer_ids']) || !is_array($data['customer_ids'])) {
            return response()->json(['error' => 'At least one customer must be selected'], 422);
        }
        
        foreach ($data['customer_ids'] as $id) {
            if (!Customer::where('id', $id)->exists()) {
                return response()->json(['error' => "Customer ID {$id} does not exist"], 422);
            }
        }

        $mediaUrl = null;
        $mediaType = null;

        // Handle media upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $path = $file->store('whatsapp-media', 'public');
            $mediaUrl = Storage::url($path);
            $mediaType = $file->getMimeType();
            
            // Determine media type for WhatsApp API
            if (str_starts_with($mediaType, 'image/')) {
                $mediaType = 'image';
            } elseif (str_starts_with($mediaType, 'video/')) {
                $mediaType = 'video';
            } else {
                $mediaType = 'document';
            }
        }

        $results = [];
        $errors = [];

        foreach ($data['customer_ids'] as $customerId) {
            try {
                $customer = Customer::findOrFail($customerId);
                $whatsappNumber = $customer->whatsapp_number ?? $customer->phone;

                if (!$whatsappNumber) {
                    $errors[] = "Customer {$customer->name} has no WhatsApp number";
                    continue;
                }

                // Create communication record
                $communication = Communication::create([
                    'customer_id' => $customer->id,
                    'channel' => 'whatsapp',
                    'direction' => 'outbound',
                    'message' => $data['message'],
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                    'status' => 'pending',
                ]);

                // Send message
                $result = $this->whatsappService->sendMessage(
                    $whatsappNumber,
                    $data['message'],
                    null,
                    $mediaUrl ? url($mediaUrl) : null,
                    $mediaType
                );

                $communication->update([
                    'status' => 'sent',
                    'provider_payload' => $result['response'] ?? null,
                ]);

                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'status' => 'sent',
                ];
            } catch (\Exception $e) {
                Log::error('Single WhatsApp send failed', [
                    'customer_id' => $customerId,
                    'phone_number' => $whatsappNumber ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                if (isset($communication)) {
                    $communication->update(['status' => 'failed']);
                }

                $errorMessage = $e->getMessage();
                // Add helpful message for "not in allowed list" error
                if (strpos($errorMessage, 'not in allowed list') !== false && isset($whatsappNumber)) {
                    $phoneInfo = $this->whatsappService->getAddPhoneNumberInfo($whatsappNumber);
                    $errorMessage .= " | Phone number: {$phoneInfo['formatted_number']} | Add at: {$phoneInfo['add_url']}";
                }
                $errors[] = "Failed to send to {$customer->name}: " . $errorMessage;
            }
        }

        return response()->json([
            'success' => count($results) > 0,
            'sent' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Create and send bulk campaign
     */
    public function createCampaign(Request $request)
    {
        // Handle JSON string for selected_customer_ids
        $selectedCustomerIds = $request->input('selected_customer_ids');
        if (is_string($selectedCustomerIds)) {
            $selectedCustomerIds = json_decode($selectedCustomerIds, true);
        }
        
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'send_type' => ['required', 'in:all,selected'],
            'media' => ['nullable', 'image', 'max:10240'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        
        $data['selected_customer_ids'] = $selectedCustomerIds ?? [];
        
        // Validate selected_customer_ids if send_type is selected
        if ($data['send_type'] === 'selected') {
            if (empty($data['selected_customer_ids']) || !is_array($data['selected_customer_ids'])) {
                return response()->json(['error' => 'At least one customer must be selected'], 422);
            }
            
            foreach ($data['selected_customer_ids'] as $id) {
                if (!Customer::where('id', $id)->exists()) {
                    return response()->json(['error' => "Customer ID {$id} does not exist"], 422);
                }
            }
        }

        $mediaUrl = null;
        $mediaType = null;

        // Handle media upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $path = $file->store('whatsapp-media', 'public');
            $mediaUrl = Storage::url($path);
            $mediaType = $file->getMimeType();
            
            if (str_starts_with($mediaType, 'image/')) {
                $mediaType = 'image';
            } elseif (str_starts_with($mediaType, 'video/')) {
                $mediaType = 'video';
            } else {
                $mediaType = 'document';
            }
        }

        // Get customer IDs
        if ($data['send_type'] === 'all') {
            $customerIds = Customer::whereNotNull('whatsapp_number')
                ->orWhereNotNull('phone')
                ->pluck('id')
                ->toArray();
        } else {
            $customerIds = $data['selected_customer_ids'];
        }

        // Create campaign
        $campaign = WhatsAppCampaign::create([
            'name' => $data['name'],
            'message' => $data['message'],
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'send_type' => $data['send_type'],
            'selected_customer_ids' => $data['send_type'] === 'selected' ? $customerIds : null,
            'status' => $data['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $data['scheduled_at'] ? now()->parse($data['scheduled_at']) : null,
            'total_customers' => count($customerIds),
            'created_by' => auth()->id(),
        ]);

        // If scheduled, return campaign
        if ($data['scheduled_at']) {
            return response()->json([
                'success' => true,
                'campaign' => $campaign->load('creator'),
                'message' => 'Campaign scheduled successfully',
            ]);
        }

        // Send immediately in chunks
        $this->sendCampaign($campaign);

        return response()->json([
            'success' => true,
            'campaign' => $campaign->fresh()->load('creator'),
            'message' => 'Campaign started',
        ]);
    }

    /**
     * Send campaign messages in chunks
     */
    private function sendCampaign(WhatsAppCampaign $campaign): void
    {
        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);

        // Get customers
        if ($campaign->send_type === 'all') {
            $customers = Customer::where(function($q) {
                $q->whereNotNull('whatsapp_number')
                  ->orWhereNotNull('phone');
            })->get();
        } else {
            $customers = Customer::whereIn('id', $campaign->selected_customer_ids ?? [])->get();
        }

        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        // Send in chunks of 10 (to avoid rate limits)
        $chunks = $customers->chunk(10);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $customer) {
                try {
                    $whatsappNumber = $customer->whatsapp_number ?? $customer->phone;

                    if (!$whatsappNumber) {
                        $failedCount++;
                        continue;
                    }

                    // Create communication record
                    $communication = Communication::create([
                        'customer_id' => $customer->id,
                        'campaign_id' => $campaign->id,
                        'channel' => 'whatsapp',
                        'direction' => 'outbound',
                        'message' => $campaign->message,
                        'media_url' => $campaign->media_url,
                        'media_type' => $campaign->media_type,
                        'status' => 'pending',
                    ]);

                    // Send message - convert relative URL to absolute
                    $fullMediaUrl = $campaign->media_url ? (str_starts_with($campaign->media_url, 'http') ? $campaign->media_url : url($campaign->media_url)) : null;
                    $result = $this->whatsappService->sendMessage(
                        $whatsappNumber,
                        $campaign->message,
                        null,
                        $fullMediaUrl,
                        $campaign->media_type
                    );

                    $communication->update([
                        'status' => 'sent',
                        'provider_payload' => $result['response'] ?? null,
                    ]);

                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error('Campaign WhatsApp send failed', [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);

                    if (isset($communication)) {
                        $communication->update(['status' => 'failed']);
                    }

                    $failedCount++;
                    $errors[] = "Failed to send to {$customer->name}: " . $e->getMessage();
                }
            }

            // Small delay between chunks to avoid rate limits
            if ($chunks->count() > 1) {
                usleep(500000); // 0.5 second delay
            }
        }

        // Update campaign status
        $campaign->update([
            'status' => $failedCount === $campaign->total_customers ? 'failed' : 'completed',
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'completed_at' => now(),
            'error_message' => !empty($errors) ? implode('; ', array_slice($errors, 0, 5)) : null,
        ]);
    }

    /**
     * Get all campaigns
     */
    public function getCampaigns(Request $request)
    {
        $campaigns = WhatsAppCampaign::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Get campaign details
     */
    public function getCampaign($id)
    {
        $campaign = WhatsAppCampaign::with(['creator', 'communications.customer'])
            ->findOrFail($id);

        return response()->json($campaign);
    }

    /**
     * Delete campaign
     */
    public function deleteCampaign($id)
    {
        $campaign = WhatsAppCampaign::findOrFail($id);
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    /**
     * Get phone number info for adding to Meta allowed list
     * Meta doesn't provide API for this, but we can provide formatted number and direct link
     */
    public function getPhoneNumberInfo(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phoneInfo = $this->whatsappService->getAddPhoneNumberInfo($request->phone);

        return response()->json([
            'formatted_number' => $phoneInfo['formatted_number'],
            'add_url' => $phoneInfo['add_url'],
            'instructions' => $phoneInfo['instructions'],
            'note' => 'Meta does not provide an API to automatically add phone numbers. You must add them manually through the Meta Business Dashboard.',
        ]);
    }
}

