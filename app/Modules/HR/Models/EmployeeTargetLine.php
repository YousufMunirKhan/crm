<?php

namespace App\Modules\HR\Models;

use App\Modules\CRM\Models\Product;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTargetLine extends Model
{
    use HasAuditLog;

    public const TYPE_PRODUCT = 'product';

    public const TYPE_CATEGORY = 'category';

    protected $fillable = [
        'employee_target_id',
        'line_type',
        'product_id',
        'category_name',
        'target_quantity',
    ];

    public function employeeTarget(): BelongsTo
    {
        return $this->belongsTo(EmployeeTarget::class, 'employee_target_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
