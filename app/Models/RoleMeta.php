<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleMeta extends Model
{
    protected $fillable = [
        'role_name',
        'description',
        'is_core', 
        'created_by_user_id',
        'last_modified_by',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_core' => 'boolean'
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_name', 'name');
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
}