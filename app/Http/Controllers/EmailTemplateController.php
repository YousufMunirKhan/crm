<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmailTemplateController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        $user = auth()->user();
        $role = $user->role->name ?? '';
        if (!in_array($role, ['Admin', 'System Admin'])) {
            abort(403, 'Only administrators can access email templates');
        }
    }

    /**
     * List active email templates for sending (e.g. from customer page). Any authenticated user.
     */
    public function listForSending(Request $request)
    {
        $templates = EmailTemplate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'subject', 'category']);
        return response()->json($templates);
    }

    /**
     * Send template email to customer (from customer detail page). Any authenticated user.
     */
    public function sendTemplateToCustomer(Request $request, $id)
    {
        $data = $request->validate([
            'template_id' => ['required', 'exists:email_templates,id'],
        ]);
        $customer = \App\Modules\CRM\Models\Customer::findOrFail($id);
        return $this->sendTemplateEmail($data['template_id'], $customer);
    }

    public function index(Request $request)
    {
        $this->checkAdmin();
        $query = EmailTemplate::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }

        $templates = $query->with('creator')->orderBy('created_at', 'desc')->get();

        return response()->json($templates);
    }

    public function sendEmail(Request $request)
    {
        $this->checkAdmin();
        $data = $request->validate([
            'template_id' => ['required', 'exists:email_templates,id'],
            'customer_id' => ['required', 'exists:customers,id'],
        ]);
        $customer = \App\Modules\CRM\Models\Customer::findOrFail($data['customer_id']);
        return $this->sendTemplateEmail($data['template_id'], $customer);
    }

    /**
     * Test send template (during creation/edit, before or after save).
     * Accepts raw template content and sends to specified email with sample data.
     * Requires SMTP settings (Settings → Email/SMTP) to be configured.
     */
    public function testSend(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:500'],
            'content' => ['required', 'array'],
            'content.sections' => ['required', 'array'],
        ]);

        $host = \App\Modules\Settings\Models\Setting::where('key', 'smtp_host')->first()?->value;
        if (empty($host) || trim($host) === '') {
            return response()->json([
                'message' => 'Please configure SMTP settings in Settings → Email/SMTP before sending test emails.',
            ], 400);
        }

        $sampleCustomer = $this->getSampleCustomerForTestSend();
        $sampleCustomer->email = $request->email; // Use test recipient for unsubscribe link
        $subject = $this->replaceVariables($request->subject, $sampleCustomer);

        $virtualTemplate = (object) [
            'content' => $request->content,
        ];
        $content = $this->renderTemplate($virtualTemplate, $sampleCustomer);

        \App\Services\MailConfigFromDatabase::apply();

        try {
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($request, $subject, $content) {
                $message->to($request->email)
                    ->subject('[Test] ' . $subject)
                    ->html($content);
            });
            return response()->json(['message' => 'Test email sent successfully to ' . $request->email]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send test email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Return fully rendered HTML (same as sent email) for iframe preview in the template builder.
     */
    public function previewHtml(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'content' => ['required', 'array'],
            'content.sections' => ['required', 'array'],
        ]);

        $sampleCustomer = $this->getSampleCustomerForTestSend();
        $virtualTemplate = (object) [
            'content' => $request->content,
        ];
        $html = $this->renderTemplate($virtualTemplate, $sampleCustomer);

        return response()->json(['html' => $html]);
    }

    /**
     * Merge tags supported in raw HTML (and subject). Same tokens replaceVariables() replaces when sending.
     */
    public function mergeTagsReference()
    {
        $this->checkAdmin();

        return response()->json([
            'tags' => $this->emailMergeTagDefinitions(),
            'html_examples' => [
                'logo' => '<img src="{{header_logo_url}}" alt="Logo" width="200" style="display:block;max-width:200px;height:auto;">',
                'logo_settings_only' => '<img src="{{logo_src}}" alt="Logo" width="200" style="display:block;">',
                'website_link' => '<a href="{{company_website}}">Visit our website</a>',
                'facebook' => '<a href="{{social_facebook_url}}">Facebook</a>',
                'linkedin' => '<a href="{{social_linkedin_url}}">LinkedIn</a>',
                'instagram' => '<a href="{{social_instagram_url}}">Instagram</a>',
                'tiktok' => '<a href="{{social_tiktok_url}}">TikTok</a>',
                'greeting' => '<p>Hello {{first_name}},</p>',
                'unsubscribe' => '<a href="{{unsubscribe_url}}">Unsubscribe</a>',
                'image_from_public_folder' => '<img src="{{email_welcome_dir_url}}/your-file.png" alt="" width="120">',
            ],
        ]);
    }

    /**
     * Create an email template from an uploaded .html / .htm file (stored as one raw_html section).
     */
    public function importFromHtml(Request $request)
    {
        $this->checkAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preview_line' => ['nullable', 'string', 'max:500'],
            'html_file' => ['required', 'file', 'max:6144'],
        ]);

        $file = $request->file('html_file');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['html', 'htm', 'txt'], true)) {
            return response()->json(['message' => 'File must be .html, .htm, or .txt'], 422);
        }

        $raw = file_get_contents($file->getRealPath());
        if ($raw === false || trim($raw) === '') {
            return response()->json(['message' => 'HTML file is empty'], 422);
        }

        $html = $this->normalizeImportedHtml($raw, $request->boolean('extract_body', true));
        if ($html === '') {
            return response()->json(['message' => 'No usable HTML after import (check &lt;body&gt; or file content)'], 422);
        }

        $template = EmailTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? 'Imported from HTML file',
            'content' => [
                'skip_brand_footer' => $request->boolean('skip_brand_footer', true),
                'preview_line' => $data['preview_line'] ?? '',
                'sections' => [
                    [
                        'type' => 'raw_html',
                        'content' => [
                            'html' => $html,
                        ],
                    ],
                ],
            ],
            'variables' => $this->variableKeysFromMergeDefinitions(),
            'is_active' => true,
            'is_prebuilt' => false,
            'created_by' => auth()->id(),
        ]);

        return response()->json($template->load('creator'), 201);
    }

    /**
     * @return array<int, array{group: string, tag: string, description: string, example: string}>
     */
    private function emailMergeTagDefinitions(): array
    {
        return [
            ['group' => 'Recipient', 'tag' => '{{first_name}}', 'description' => 'First name (from contact name)', 'example' => 'Jane'],
            ['group' => 'Recipient', 'tag' => '{{customer_name}}', 'description' => 'Full name', 'example' => 'Jane Smith'],
            ['group' => 'Recipient', 'tag' => '{{customer_email}}', 'description' => 'Email address', 'example' => 'jane@example.com'],
            ['group' => 'Recipient', 'tag' => '{{customer_phone}}', 'description' => 'Phone number', 'example' => '+44 7700 900000'],
            ['group' => 'Recipient', 'tag' => '{{prospect_products}}', 'description' => 'Comma-separated products (prospect pipeline)', 'example' => 'Card Machine'],
            ['group' => 'Recipient', 'tag' => '{{customer_products}}', 'description' => 'Comma-separated owned/won products', 'example' => 'EPOS'],
            ['group' => 'Company (Settings)', 'tag' => '{{company_name}}', 'description' => 'Company name', 'example' => 'Switch & Save'],
            ['group' => 'Company (Settings)', 'tag' => '{{company_phone}}', 'description' => 'Main phone', 'example' => '0333 038 9707'],
            ['group' => 'Company (Settings)', 'tag' => '{{company_address}}', 'description' => 'Address (multiline ok)', 'example' => '1 High St'],
            ['group' => 'Company (Settings)', 'tag' => '{{company_website}}', 'description' => 'Website URL (https added if missing)', 'example' => 'https://switch-and-save.uk'],
            ['group' => 'Logo & images', 'tag' => '{{header_logo_url}}', 'description' => 'Header logo: uses public/images/email/welcome/main-logo.png when that file exists; otherwise Settings logo; else constructed URL. For Settings-only use {{logo_src}}.', 'example' => 'https://…/images/email/welcome/main-logo.png'],
            ['group' => 'Logo & images', 'tag' => '{{logo_src}}', 'description' => 'Settings logo URL only (empty if not set)', 'example' => 'https://…/logo.png'],
            ['group' => 'Logo & images', 'tag' => '{{email_welcome_dir_url}}', 'description' => 'Base URL for files in public/images/email/welcome/ (partner strip, icons)', 'example' => 'https://…/images/email/welcome'],
            ['group' => 'Logo & images', 'tag' => '{{app_url}}', 'description' => 'Application base URL (no trailing slash)', 'example' => 'https://crm.example.com'],
            ['group' => 'Social (Settings)', 'tag' => '{{social_facebook_url}}', 'description' => 'Facebook profile/page URL (# if empty)', 'example' => 'https://facebook.com/…'],
            ['group' => 'Social (Settings)', 'tag' => '{{social_linkedin_url}}', 'description' => 'LinkedIn URL (# if empty)', 'example' => 'https://linkedin.com/…'],
            ['group' => 'Social (Settings)', 'tag' => '{{social_instagram_url}}', 'description' => 'Instagram URL (# if empty)', 'example' => 'https://instagram.com/…'],
            ['group' => 'Social (Settings)', 'tag' => '{{social_tiktok_url}}', 'description' => 'TikTok URL (# if empty)', 'example' => 'https://tiktok.com/…'],
            ['group' => 'Legal & footer', 'tag' => '{{unsubscribe_url}}', 'description' => 'Marketing unsubscribe link for this recipient', 'example' => 'https://…/unsubscribe?email=…'],
            ['group' => 'Legal & footer', 'tag' => '{{current_year}}', 'description' => 'Current year', 'example' => '2026'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function variableKeysFromMergeDefinitions(): array
    {
        return array_values(array_unique(array_map(function (array $row) {
            return str_replace(['{{', '}}'], '', $row['tag']);
        }, $this->emailMergeTagDefinitions())));
    }

    private function normalizeImportedHtml(string $raw, bool $extractBody): string
    {
        $raw = (string) $raw;
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $raw) ?? $raw;
        if ($extractBody && preg_match('/<body[^>]*>(.*)<\/body>/is', $raw, $m)) {
            return trim($m[1]);
        }

        return trim($raw);
    }

    private function getSampleCustomerForTestSend()
    {
        $customer = \App\Modules\CRM\Models\Customer::with(['leads.items.product', 'leads.product'])
            ->whereNotNull('email')->where('email', '!=', '')->first();
        if ($customer) {
            return $customer;
        }
        return new \App\Modules\CRM\Models\Customer([
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '+44 7700 900000',
            'business_name' => 'Sample Business',
            'type' => 'customer',
        ]);
    }

    private function sendTemplateEmail(int $templateId, \App\Modules\CRM\Models\Customer $customer)
    {
        $template = EmailTemplate::findOrFail($templateId);

        if (!$customer->email) {
            return response()->json(['message' => 'Customer does not have an email address'], 422);
        }

        if (\App\Models\EmailUnsubscribe::isUnsubscribed($customer->email)) {
            return response()->json(['message' => 'This contact has unsubscribed from marketing emails.'], 422);
        }

        $subject = $this->replaceVariables($template->subject, $customer);
        $content = $this->renderTemplate($template, $customer);

        \App\Services\MailConfigFromDatabase::apply();

        try {
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($customer, $subject, $content) {
                $message->to($customer->email)
                    ->subject($subject)
                    ->html($content);
            });

            \App\Models\SentCommunication::create([
                'type' => 'email',
                'template_type' => 'email_template',
                'template_id' => $template->id,
                'customer_id' => $customer->id,
                'recipient_email' => $customer->email,
                'subject' => $subject,
                'content' => $content,
                'status' => 'sent',
                'sent_at' => now(),
                'sent_by' => auth()->id(),
            ]);

            return response()->json(['message' => 'Email sent successfully']);
        } catch (\Exception $e) {
            \App\Models\SentCommunication::create([
                'type' => 'email',
                'template_type' => 'email_template',
                'template_id' => $template->id,
                'customer_id' => $customer->id,
                'recipient_email' => $customer->email,
                'subject' => $subject,
                'content' => $content,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_by' => auth()->id(),
            ]);

            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $this->checkAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['required', 'array'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $data['created_by'] = auth()->id();
        $data['is_active'] = $data['is_active'] ?? true;

        $template = EmailTemplate::create($data);

        return response()->json($template->load('creator'), 201);
    }

    public function show($id)
    {
        $this->checkAdmin();
        $template = EmailTemplate::findOrFail($id);
        return response()->json($template->load('creator'));
    }

    public function update(Request $request, $id)
    {
        $this->checkAdmin();
        $template = EmailTemplate::findOrFail($id);

        // Prevent editing pre-built templates' structure (but allow text/image changes)
        if ($template->is_prebuilt && $request->has('content')) {
            // Allow content updates for pre-built templates
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['sometimes', 'array'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $template->update($data);

        return response()->json($template->load('creator'));
    }

    public function destroy($id)
    {
        $this->checkAdmin();
        $template = EmailTemplate::findOrFail($id);

        if ($template->is_prebuilt) {
            return response()->json(['message' => 'Pre-built templates cannot be deleted'], 403);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function duplicate($id)
    {
        $this->checkAdmin();
        $template = EmailTemplate::findOrFail($id);
        
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->is_prebuilt = false;
        $newTemplate->created_by = auth()->id();
        $newTemplate->save();

        return response()->json($newTemplate->load('creator'), 201);
    }

    public function uploadImage(Request $request)
    {
        $this->checkAdmin();
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        $path = $request->file('image')->store('email-templates', 'public');
        $url = '/storage/'.$path;
        // Use the request host so thumbnails work when APP_URL differs (e.g. Laragon *.test vs localhost).
        $base = rtrim($request->getSchemeAndHttpHost(), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        $absolute = $base.$url;

        // `url` for legacy Vue callers; `data` for GrapesJS Asset Manager (autoAdd)
        return response()->json([
            'url' => $url,
            'data' => [$absolute],
        ]);
    }

    private function replaceVariables($text, $customer)
    {
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name', 'company_phone', 'company_address', 'company_registration_no', 'company_vat'
        ])->pluck('value', 'key');

        $customer->load(['leads.items.product']);
        $prospectProductNames = [];
        $customerProductNames = [];
        foreach ($customer->leads as $lead) {
            foreach ($lead->items as $item) {
                $name = $item->product->name ?? null;
                if (!$name) {
                    continue;
                }
                if ($lead->stage === 'won' && $item->status === 'won') {
                    $customerProductNames[$name] = true;
                } else {
                    $prospectProductNames[$name] = true;
                }
            }
            if ($lead->product && !isset($prospectProductNames[$lead->product->name]) && $lead->stage !== 'won') {
                $prospectProductNames[$lead->product->name] = true;
            }
        }
        $prospectProducts = implode(', ', array_keys($prospectProductNames));
        $customerProducts = implode(', ', array_keys($customerProductNames));

        $unsubscribeUrl = config('app.url') . '/unsubscribe?email=' . urlencode($customer->email ?? '');
        $extra = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_website', 'logo_url',
            'social_facebook_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url',
        ])->pluck('value', 'key');
        $settings = $settings->merge($extra);
        $appUrl = rtrim((string) config('app.url'), '/');
        $rawLogo = trim((string) ($settings['logo_url'] ?? ''));
        $logoSrc = '';
        if ($rawLogo !== '') {
            $logoSrc = $rawLogo;
            if (! str_starts_with($logoSrc, 'http')) {
                if (($logoSrc[0] ?? '') !== '/') {
                    $logoSrc = '/' . $logoSrc;
                }
                $logoSrc = $appUrl . $logoSrc;
            }
        }

        $welcomeLogoPath = public_path('images/email/welcome/main-logo.png');
        $defaultWelcomeLogoUrl = is_file($welcomeLogoPath)
            ? asset('images/email/welcome/main-logo.png')
            : '';

        // Prefer bundled public/images/email/welcome/main-logo.png when it exists so a bad Settings → logo URL
        // does not hide the header logo in welcome-style templates. Use {{logo_src}} for Settings-only.
        if ($defaultWelcomeLogoUrl !== '') {
            $headerLogoUrl = $defaultWelcomeLogoUrl;
        } elseif ($logoSrc !== '') {
            $headerLogoUrl = $logoSrc;
        } else {
            $headerLogoUrl = $appUrl . '/images/email/welcome/main-logo.png';
        }

        $welcomeSlash = $defaultWelcomeLogoUrl !== '' ? strrpos($defaultWelcomeLogoUrl, '/') : false;
        $emailWelcomeDirUrl = $welcomeSlash !== false
            ? substr($defaultWelcomeLogoUrl, 0, $welcomeSlash)
            : ($appUrl . '/images/email/welcome');

        $text = (string) $text;

        return str_replace(
            [
                '{{customer_name}}',
                '{{first_name}}',
                '{{customer_email}}',
                '{{customer_phone}}',
                '{{company_name}}',
                '{{company_phone}}',
                '{{company_address}}',
                '{{prospect_products}}',
                '{{customer_products}}',
                '{{unsubscribe_url}}',
                '{{app_url}}',
                '{{logo_src}}',
                '{{header_logo_url}}',
                '{{email_welcome_dir_url}}',
                '{{company_website}}',
                '{{social_facebook_url}}',
                '{{social_linkedin_url}}',
                '{{social_instagram_url}}',
                '{{social_tiktok_url}}',
                '{{current_year}}',
            ],
            [
                $customer->name ?? '',
                trim(explode(' ', $customer->name ?? '')[0] ?? ''),
                $customer->email ?? '',
                $customer->phone ?? '',
                $settings['company_name'] ?? 'Switch & Save',
                $settings['company_phone'] ?? '',
                $settings['company_address'] ?? '',
                $prospectProducts,
                $customerProducts,
                $unsubscribeUrl,
                $appUrl,
                $logoSrc,
                $headerLogoUrl,
                $emailWelcomeDirUrl,
                $this->mergeTagOutboundUrl($settings['company_website'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_facebook_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_linkedin_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_instagram_url'] ?? ''),
                $this->mergeTagOutboundUrl($settings['social_tiktok_url'] ?? ''),
                (string) now()->year,
            ],
            $text
        );
    }

    private function mergeTagOutboundUrl(?string $url): string
    {
        $u = trim((string) $url);
        if ($u === '') {
            return '#';
        }
        if (! preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }

        return $u;
    }

    private function renderTemplate($template, $customer)
    {
        $baseUrl = config('app.url');
        $unsubscribeUrl = $baseUrl . '/unsubscribe?email=' . urlencode($customer->email ?? '');
        $responsiveStyles = '
<style type="text/css">
/* Base: works on iOS, Android, Gmail, Apple Mail, Samsung */
html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
body { margin: 0 !important; padding: 0 !important; width: 100% !important; overflow-x: hidden !important; -webkit-text-size-adjust: 100%; }
/* Do not force display:block on all images — it breaks horizontal icon rows in imported HTML (social links).
   Table-based layouts still work; builder sections set their own img styles where needed. */
img { max-width: 100% !important; height: auto !important; border: 0; vertical-align: middle; -ms-interpolation-mode: bicubic; }
a { text-decoration: none; -webkit-tap-highlight-color: rgba(2, 132, 199, 0.2); }
table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
/* Mobile: iOS & Android */
@media only screen and (max-width: 620px) {
  .email-wrapper { width: 100% !important; min-width: 0 !important; padding: 12px 15px !important; box-sizing: border-box !important; overflow-x: hidden !important; }
  .two-col { display: block !important; width: 100% !important; }
  .two-col > div { width: 100% !important; display: block !important; padding: 8px 0 !important; }
  .fluid-txt { font-size: 16px !important; line-height: 1.5 !important; }
  .fluid-txt-lg { font-size: 20px !important; }
  .fluid-padding { padding: 12px 15px !important; }
  .btn-block { display: block !important; width: 100% !important; min-height: 44px !important; text-align: center !important; padding: 14px 20px !important; box-sizing: border-box !important; }
  .header-txt { font-size: 22px !important; }
  .welcome-three-col > tbody > tr > td { display: block !important; width: 100% !important; max-width: 100% !important; padding: 6px 0 !important; box-sizing: border-box !important; }
  .welcome-offer-row > tbody > tr > td { display: block !important; width: 100% !important; max-width: 100% !important; text-align: center !important; padding: 10px 0 !important; box-sizing: border-box !important; }
}
</style>';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"><meta http-equiv="X-UA-Compatible" content="IE=edge"><title>Email</title>' . $responsiveStyles . '</head><body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,Arial,sans-serif;line-height:1.6;color:#333;width:100%;-webkit-text-size-adjust:100%;overflow-x:hidden;">';
        $html .= '<div class="email-wrapper" style="max-width:600px;width:100%;margin:0 auto;padding:20px;background:#fff;box-sizing:border-box;">';

        $previewLine = trim($template->content['preview_line'] ?? '');
        if ($previewLine !== '') {
            $previewText = $this->replaceVariables($previewLine, $customer);
            $html .= '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;">' . e($previewText) . '</div>';
        }

        $sections = $template->content['sections'] ?? [];
        $hasCustomFooter = false;
        foreach ($sections as $section) {
            if (($section['type'] ?? '') === 'footer') {
                $hasCustomFooter = true;
            }
            $html .= $this->renderSection($section, $customer, $unsubscribeUrl);
        }
        if (empty($template->content['skip_brand_footer'])) {
            $html .= $this->buildBrandFooter($unsubscribeUrl, $hasCustomFooter);
        }
        $html .= '</div></body></html>';
        return $html;
    }

    private function buildBrandFooter(string $unsubscribeUrl, bool $hasCustomFooter = false): string
    {
        $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
            'company_name', 'company_phone', 'company_address', 'company_registration_no', 'company_vat',
            'company_website', 'social_facebook_url', 'social_twitter_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url',
        ])->pluck('value', 'key');
        $companyName = e($settings['company_name'] ?? 'Switch & Save');
        $html = '<div class="fluid-padding" style="text-align:center;padding:24px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;word-wrap:break-word;overflow-wrap:break-word;">';

        // If template already has a custom footer section, keep this minimal to avoid duplicated company block
        if (!$hasCustomFooter) {
            $html .= '<p style="margin:4px 0;font-weight:600;color:#475569;">' . $companyName . '</p>';
            if (!empty($settings['company_address'])) {
                $html .= '<p style="margin:4px 0;">' . nl2br(e($settings['company_address'])) . '</p>';
            }
            if (!empty($settings['company_phone'])) {
                $html .= '<p style="margin:4px 0;">📞 ' . e($settings['company_phone']) . '</p>';
            }
            if (!empty($settings['company_website'])) {
                $html .= '<p style="margin:4px 0;"><a href="' . e($settings['company_website']) . '" style="color:#0284c7;text-decoration:none;">' . e($settings['company_website']) . '</a></p>';
            }
            $socialUrls = [
                'social_facebook_url' => ['https://facebook.com/', 'Facebook'],
                'social_twitter_url' => ['https://twitter.com/', 'Twitter'],
                'social_linkedin_url' => ['https://linkedin.com/', 'LinkedIn'],
                'social_instagram_url' => ['https://instagram.com/', 'Instagram'],
                'social_tiktok_url' => ['https://tiktok.com/', 'TikTok'],
            ];
            $hasSocial = false;
            foreach ($socialUrls as $key => $label) {
                if (!empty($settings[$key])) {
                    if (!$hasSocial) {
                        $html .= '<p style="margin:12px 0 4px;">Follow us:</p>';
                        $hasSocial = true;
                    }
                    $url = $settings[$key];
                    if (!preg_match('#^https?://#', $url)) {
                        $url = 'https://' . $url;
                    }
                    $html .= '<a href="' . e($url) . '" style="display:inline-block;margin:0 6px;color:#0284c7;text-decoration:none;">' . e($label[1]) . '</a>';
                }
            }
            if (!empty($settings['company_registration_no'])) {
                $html .= '<p style="margin:8px 0 4px;">Company No: ' . e($settings['company_registration_no']) . '</p>';
            }
            if (!empty($settings['company_vat'])) {
                $html .= '<p style="margin:4px 0;">VAT: ' . e($settings['company_vat']) . '</p>';
            }
        }

        $html .= '<p style="margin:16px 0 0;"><a href="' . e($unsubscribeUrl) . '" style="color:#94a3b8;text-decoration:underline;font-size:11px;">Unsubscribe from marketing emails</a></p>';
        $html .= '</div>';
        return $html;
    }

    private function buildSectionTextStyle(array $content): string
    {
        $parts = [];
        if (!empty($content['font_family'])) {
            $parts[] = 'font-family:' . e($content['font_family']);
        }
        if (!empty($content['font_size'])) {
            $parts[] = 'font-size:' . e($content['font_size']);
        }
        if (!empty($content['font_color'])) {
            $color = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $content['font_color']) ? $content['font_color'] : '#333333';
            $parts[] = 'color:' . e($color);
        }
        return implode(';', $parts);
    }

    private function renderSection($section, $customer, string $unsubscribeUrl = '')
    {
        $html = '';
        $content = $section['content'] ?? [];

        switch ($section['type']) {
            case 'raw_html':
                $html .= $this->replaceVariables($content['html'] ?? '', $customer);
                break;

            case 'header':
                $logoUrl = $content['logo'] ?? null;
                if (empty($logoUrl)) {
                    $logoSetting = \App\Modules\Settings\Models\Setting::where('key', 'logo_url')->first();
                    if ($logoSetting && $logoSetting->value) {
                        $logoUrl = $logoSetting->value;
                    }
                }
                if (!empty($logoUrl) && !str_starts_with($logoUrl, 'http')) {
                    if ($logoUrl[0] !== '/') {
                        $logoUrl = '/' . $logoUrl;
                    }
                    $logoUrl = rtrim(config('app.url'), '/') . $logoUrl;
                }
                $textStyle = $this->buildSectionTextStyle($content);
                $h1Style = 'margin:0;font-size:24px;' . ($textStyle ? $textStyle . ';' : '');
                $html .= '<div class="fluid-padding" style="text-align:center;padding:20px;background:linear-gradient(135deg,#1e293b 0%,#334155 100%);color:white;">';
                if (!empty($logoUrl)) {
                    $html .= '<img src="' . e($logoUrl) . '" alt="Logo" style="max-height:50px;max-width:200px;width:auto;height:auto;margin-bottom:10px;">';
                }
                $html .= '<h1 class="header-txt" style="' . $h1Style . 'word-wrap:break-word;overflow-wrap:break-word;">' . $this->replaceVariables($content['text'] ?? '', $customer) . '</h1>';
                $html .= '</div>';
                break;

            case 'text':
                $blocks = $content['blocks'] ?? null;
                $sectionTextStyle = $this->buildSectionTextStyle($content);
                if ($blocks && is_array($blocks)) {
                    $html .= '<div class="fluid-padding" style="padding:20px;">';
                    foreach ($blocks as $block) {
                        $blockText = $this->replaceVariables($block['text'] ?? '', $customer);
                        $style = $sectionTextStyle ? ' style="' . $sectionTextStyle . ';margin:0 0 8px 0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;"' : ' style="margin:0 0 8px 0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;"';
                        $html .= '<p class="fluid-txt"' . $style . '>' . nl2br(e($blockText)) . '</p>';
                    }
                    $html .= '</div>';
                } else {
                    $text = $this->replaceVariables($content['text'] ?? '', $customer);
                    $textStyle = $sectionTextStyle;
                    $pStyle = 'padding:20px;margin:0;word-wrap:break-word;overflow-wrap:break-word;max-width:100%;' . ($textStyle ? $textStyle . ';' : '');
                    $html .= '<p class="fluid-txt fluid-padding" style="' . $pStyle . '">' . nl2br(e($text)) . '</p>';
                }
                break;

            case 'image':
                if (!empty($content['image_url'])) {
                    $imageUrl = $content['image_url'];
                    if (!str_starts_with($imageUrl, 'http')) {
                        if ($imageUrl[0] !== '/') {
                            $imageUrl = '/' . $imageUrl;
                        }
                        $imageUrl = rtrim(config('app.url'), '/') . $imageUrl;
                    }
                    $html .= '<div style="text-align: center; padding: 20px;">';
                    $imageTag = '<img src="' . e($imageUrl) . '" alt="' . e($content['alt'] ?? '') . '" style="max-width: 100%; height: auto;">';
                    
                    // Make image clickable if link_url is provided
                    if (!empty($content['link_url']) && $content['link_url'] !== '#') {
                        $html .= '<a href="' . $content['link_url'] . '" style="display: inline-block;">' . $imageTag . '</a>';
                    } else {
                        $html .= $imageTag;
                    }
                    $html .= '</div>';
                }
                break;

            case 'button':
                $btnStyle = $this->buildSectionTextStyle($content);
                $aStyle = 'display:inline-block;padding:12px 24px;background:#0284c7;color:white;text-decoration:none;border-radius:6px;font-weight:600;word-wrap:break-word;max-width:100%;box-sizing:border-box;' . ($btnStyle ? $btnStyle . ';' : '');
                $html .= '<div class="fluid-padding" style="text-align:center;padding:20px;">';
                $html .= '<a href="' . e($content['url'] ?? '#') . '" class="btn-block" style="' . $aStyle . '">';
                $html .= $this->replaceVariables($content['text'] ?? 'Click Here', $customer);
                $html .= '</a></div>';
                break;

            case 'two_column':
                $colStyle = $this->buildSectionTextStyle($content);
                $colAttr = $colStyle ? ' style="' . $colStyle . 'word-wrap:break-word;overflow-wrap:break-word;min-width:0;"' : ' style="word-wrap:break-word;overflow-wrap:break-word;min-width:0;"';
                $html .= '<div class="two-col fluid-padding" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:20px;width:100%;">';
                $html .= '<div class="fluid-txt"' . $colAttr . '>' . nl2br(e($this->replaceVariables($content['left_text'] ?? '', $customer))) . '</div>';
                $html .= '<div class="fluid-txt"' . $colAttr . '>' . nl2br(e($this->replaceVariables($content['right_text'] ?? '', $customer))) . '</div>';
                $html .= '</div>';
                break;

            case 'footer':
                // Legacy footer section - brand footer is now always appended; this adds extra content if present
                $settings = \App\Modules\Settings\Models\Setting::whereIn('key', [
                    'company_name', 'company_phone', 'company_address'
                ])->pluck('value', 'key');
                $footerStyle = $this->buildSectionTextStyle($content);
                $pStyle = 'margin:4px 0;color:#64748b;font-size:14px;' . ($footerStyle ? $footerStyle . ';' : '');
                $html .= '<div style="text-align:center;padding:16px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;">';
                if (!empty($content['text'])) {
                    $html .= '<p style="' . $pStyle . '">' . nl2br(e($this->replaceVariables($content['text'], $customer))) . '</p>';
                }
                $html .= '<p style="' . $pStyle . '">' . e($settings['company_name'] ?? 'Switch & Save') . '</p>';
                if (!empty($settings['company_phone'])) {
                    $html .= '<p style="' . $pStyle . '">📞 ' . e($settings['company_phone']) . '</p>';
                }
                $html .= '</div>';
                break;
        }

        return $html;
    }
}
