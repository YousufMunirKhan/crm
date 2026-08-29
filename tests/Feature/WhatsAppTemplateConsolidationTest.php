<?php

namespace Tests\Feature;

use App\Models\WhatsAppTemplate;
use App\Modules\Communication\Models\WhatsAppTemplate as ModuleWhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two independent models were mapped to whatsapp_templates with different
 * fillable lists, and only one applied SoftDeletes - so reads through the
 * module class returned deleted templates and deletes were permanent.
 */
class WhatsAppTemplateConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_classes_share_one_set_of_rules(): void
    {
        $this->assertInstanceOf(WhatsAppTemplate::class, new ModuleWhatsAppTemplate());
        $this->assertSame(
            (new WhatsAppTemplate())->getFillable(),
            (new ModuleWhatsAppTemplate())->getFillable()
        );
    }

    public function test_the_module_class_now_soft_deletes(): void
    {
        $template = ModuleWhatsAppTemplate::create([
            'name' => 'order_update',
            'message' => 'Your order has shipped.',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'is_active' => true,
        ]);

        $template->delete();

        $this->assertSoftDeleted('whatsapp_templates', ['id' => $template->id]);
    }

    public function test_a_deleted_template_is_hidden_from_both_classes(): void
    {
        $template = WhatsAppTemplate::create([
            'name' => 'promo',
            'message' => 'Spring offer inside.',
            'category' => 'MARKETING',
            'status' => 'APPROVED',
            'is_active' => true,
        ]);

        $template->delete();

        $this->assertNull(WhatsAppTemplate::find($template->id));
        $this->assertNull(ModuleWhatsAppTemplate::find($template->id));
    }

    public function test_meta_fields_are_writable_from_either_class(): void
    {
        // The App\Models class previously could not set meta_template_id,
        // language or status at all.
        $template = WhatsAppTemplate::create([
            'name' => 'shipping_update',
            'message' => 'Hello {{1}}',
            'meta_template_id' => '123456789',
            'language' => 'en_GB',
            'status' => 'PENDING',
            'components_json' => ['body' => 'Hello {{1}}'],
        ]);

        $this->assertSame('123456789', $template->fresh()->meta_template_id);
        $this->assertSame(['body' => 'Hello {{1}}'], $template->fresh()->components_json);
    }

    public function test_approved_scope_works_from_the_module_class(): void
    {
        WhatsAppTemplate::create(['name' => 'a', 'message' => 'A', 'status' => 'APPROVED', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'b', 'message' => 'B', 'status' => 'PENDING', 'is_active' => true]);

        $this->assertSame(1, ModuleWhatsAppTemplate::approved()->count());
        $this->assertSame(1, ModuleWhatsAppTemplate::pending()->count());
    }
}
