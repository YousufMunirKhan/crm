<?php

namespace App\Policies;

/**
 * Categories are read wherever products are picked, so sales staff need to see
 * them. Editing stays with managers - renaming a category moves every product
 * under it and shifts what target attainment reports against.
 */
class ProductCategoryPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent'];
}
