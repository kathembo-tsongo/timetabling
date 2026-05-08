<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id', 'invoice_number', 'status', 'plan',
        'amount', 'currency', 'payment_method', 'payment_reference',
        'due_date', 'paid_at', 'notes',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at'  => 'datetime',
        'amount'   => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generateNumber(): string
    {
        $last = static::latest()->first();
        $next = $last ? (intval(substr($last->invoice_number, 4)) + 1) : 1;
        return 'INV-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public function isPaid(): bool    { return $this->status === 'paid'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isOverdue(): bool { return $this->status === 'overdue' || ($this->isPending() && $this->due_date->isPast()); }
}
