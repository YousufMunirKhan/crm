<?php

namespace App\Modules\Communication\Models;

use App\Models\WhatsAppTemplate as BaseWhatsAppTemplate;

/**
 * Meta-synced WhatsApp template.
 *
 * Kept as an alias of App\Models\WhatsAppTemplate so the many existing call
 * sites in the Communication module keep working, while there is only one set
 * of rules for the whatsapp_templates table.
 *
 * Previously these were two unrelated models on the same table with different
 * fillable lists, and only the App\Models one applied SoftDeletes - so reads
 * through this class returned deleted templates and deletes were permanent.
 *
 * @deprecated Prefer App\Models\WhatsAppTemplate directly in new code.
 */
class WhatsAppTemplate extends BaseWhatsAppTemplate
{
    //
}
