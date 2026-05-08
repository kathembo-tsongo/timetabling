<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable;
    protected $fillable = [
        'name', 'slug', 'domain', 'logo',
        'primary_color', 'secondary_color',
        'timezone', 'locale', 'currency',
        'plan', 'is_active', 'settings', 'trial_ends_at',
    ];

    protected $casts = [
        'settings'      => 'array',
        'is_active'     => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function setting($key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function users()    { return $this->hasMany(User::class); }
    public function schools()  { return $this->hasMany(School::class); }
    public function programs() { return $this->hasMany(Program::class); }
    public function semesters(){ return $this->hasMany(Semester::class); }
}
