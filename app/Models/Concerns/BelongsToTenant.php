<?php
namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Auto-filter ALL queries by current tenant
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->has('tenant')) {
                $query->where(
                    (new static)->getTable() . '.tenant_id',
                    tenant()->id
                );
            }
        });

        // Auto-set tenant_id when creating new records
        static::creating(function ($model) {
            if (app()->has('tenant') && empty($model->tenant_id)) {
                $model->tenant_id = tenant()->id;
            }
        });
    }

    // Escape hatch — query across all tenants (super admin only)
    public static function allTenants(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
