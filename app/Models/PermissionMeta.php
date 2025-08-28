<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission;

class PermissionMeta extends Model
{
    protected $fillable = [
        'permission_name',
        'description', 
        'category',
        'is_core',
        'created_by_user_id',
        'last_modified_by',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_core' => 'boolean'
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_name', 'name');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    public function scopeDynamic($query)
    {
        return $query->where('is_core', false);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}