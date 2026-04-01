<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $welcomeHtmlPath = resource_path('email-templates/welcome-rich.partial.html');
        $welcomeHtml = is_file($welcomeHtmlPath)
            ? file_get_contents($welcomeHtmlPath)
            : '<p>Welcome {{first_name}} — add resources/email-templates/welcome-rich.partial.html and re-seed.</p>';

        $templates = [
            [
                'name' => 'Welcome Email',
                'category' => 'welcome',
                'subject' => 'Welcome to {{company_name}}, {{first_name}}!',
                'description' => 'Branded Switch & Save welcome email (logo from Settings, links from company & social URLs)',
                'content' => [
                    'skip_brand_footer' => true,
                    'preview_line' => 'Smart solutions to grow your business — book your free demo',
                    'sections' => [
                        [
                            'type' => 'raw_html',
                            'content' => [
                                'html' => $welcomeHtml,
                            ],
                        ],
                    ],
                ],
                'variables' => [
                    'first_name', 'customer_name', 'company_name', 'company_phone', 'company_website',
                    'app_url', 'header_logo_url', 'email_welcome_dir_url', 'logo_src',
                    'social_facebook_url', 'social_linkedin_url', 'social_instagram_url', 'social_tiktok_url',
                    'current_year', 'unsubscribe_url',
                ],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Epos Description Email',
                'category' => 'epos',
                'subject' => 'Epos Hospitality - Product Information',
                'description' => 'Product description email with specifications and images',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Epos Hospitality',
                            ],
                        ],
                        [
                            'type' => 'image',
                            'content' => [
                                'url' => '',
                                'alt' => 'Epos Hospitality System',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Dear {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'We are excited to share information about our Epos Hospitality solution. This comprehensive system is designed to streamline your restaurant operations.',
                            ],
                        ],
                        [
                            'type' => 'two_column',
                            'content' => [
                                'left_text' => 'Key Features:\n• Table Management\n• Order Processing\n• Inventory Control',
                                'right_text' => 'Benefits:\n• Increased Efficiency\n• Better Customer Service\n• Real-time Analytics',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'Learn More',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Teya Product Email',
                'category' => 'teya',
                'subject' => 'Teya Card Machine - Payment Solution',
                'description' => 'Teya product information email',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Teya Card Machine',
                            ],
                        ],
                        [
                            'type' => 'image',
                            'content' => [
                                'url' => '',
                                'alt' => 'Teya Card Machine',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Hello {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Discover the Teya Card Machine - a modern payment solution designed for businesses like yours. Accept payments seamlessly with our secure and reliable system.',
                            ],
                        ],
                        [
                            'type' => 'two_column',
                            'content' => [
                                'left_text' => 'Features:\n• Contactless Payments\n• Receipt Printing\n• Multi-card Support',
                                'right_text' => 'Why Choose Teya:\n• Fast Transactions\n• Secure Processing\n• 24/7 Support',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'Request Demo',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Appointment Confirmation',
                'category' => 'appointment',
                'subject' => 'Appointment Confirmation - {{company_name}}',
                'description' => 'Appointment confirmation email',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Appointment Confirmed',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Dear {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'We are pleased to confirm your appointment with {{company_name}}.',
                            ],
                        ],
                        [
                            'type' => 'two_column',
                            'content' => [
                                'left_text' => 'Date:\n{{appointment_date}}',
                                'right_text' => 'Time:\n{{appointment_time}}',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'If you need to reschedule or cancel, please contact us at {{company_phone}}.',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'appointment_date', 'appointment_time', 'company_name', 'company_phone'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Invoice Email - Standard',
                'category' => 'invoice',
                'subject' => 'Invoice #{{invoice_number}} from {{company_name}}',
                'description' => 'Standard invoice email template',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Invoice',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Dear {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Please find attached your invoice #{{invoice_number}} for the amount of £{{invoice_amount}}.',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'View Invoice',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Payment is due by {{due_date}}. Thank you for your business!',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'invoice_number', 'invoice_amount', 'due_date', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Follow-up Reminder',
                'category' => 'follow_up',
                'subject' => 'Follow-up Reminder - {{company_name}}',
                'description' => 'Follow-up reminder email',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Follow-up Reminder',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Hello {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'This is a friendly reminder about your upcoming follow-up scheduled for {{follow_up_date}}.',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'View Details',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'follow_up_date', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Quote/Proposal Email',
                'category' => 'quote',
                'subject' => 'Quote for {{product_name}} - {{company_name}}',
                'description' => 'Quote and proposal email',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Your Quote',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Dear {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Thank you for your interest in {{product_name}}. We are pleased to provide you with a detailed quote.',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'View Quote',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'This quote is valid until {{quote_expiry_date}}. If you have any questions, please don\'t hesitate to contact us.',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'product_name', 'quote_expiry_date', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Thank You Email',
                'category' => 'thank_you',
                'subject' => 'Thank You from {{company_name}}',
                'description' => 'Thank you email after purchase',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Thank You!',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Dear {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Thank you for choosing {{company_name}}! We truly appreciate your business and look forward to serving you.',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'Leave a Review',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Payment Reminder',
                'category' => 'reminder',
                'subject' => 'Payment Reminder - Invoice #{{invoice_number}}',
                'description' => 'Payment reminder email',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => 'Payment Reminder',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'Hello {{customer_name}},',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'This is a friendly reminder that payment for Invoice #{{invoice_number}} in the amount of £{{invoice_amount}} is now due.',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'content' => [
                                'text' => 'Pay Now',
                                'url' => '#',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => 'If you have already made this payment, please disregard this notice. Thank you!',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['customer_name', 'invoice_number', 'invoice_amount', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
            [
                'name' => 'Custom Email Template',
                'category' => 'custom',
                'subject' => '{{custom_subject}}',
                'description' => 'A customizable email template',
                'content' => [
                    'sections' => [
                        [
                            'type' => 'header',
                            'content' => [
                                'logo' => '',
                                'text' => '{{header_text}}',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'content' => [
                                'text' => '{{custom_content}}',
                            ],
                        ],
                        [
                            'type' => 'footer',
                            'content' => [
                                'text' => '© {{company_name}}',
                            ],
                        ],
                    ],
                ],
                'variables' => ['custom_subject', 'header_text', 'custom_content', 'company_name'],
                'is_active' => true,
                'is_prebuilt' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            EmailTemplate::updateOrCreate(
                ['name' => $templateData['name'], 'category' => $templateData['category']],
                $templateData
            );
        }
    }
}
