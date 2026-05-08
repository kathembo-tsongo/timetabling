<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_status')->default('trial');  // trial|active|overdue|suspended
            $table->string('payment_method')->nullable();        // mpesa|bank|paypal|card
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->decimal('billing_amount', 10, 2)->default(0);
            $table->string('billing_currency')->default('KES');
            $table->string('billing_cycle')->default('monthly'); // monthly|annual
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('billing_notes')->nullable();
        });

        // Create invoices table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->string('status')->default('pending'); // pending|paid|overdue|cancelled
            $table->string('plan');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('KES');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable(); // M-Pesa code, bank ref etc
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'billing_status', 'payment_method', 'billing_email',
                'billing_phone', 'billing_amount', 'billing_currency',
                'billing_cycle', 'last_payment_at', 'next_billing_at',
                'suspended_at', 'billing_notes'
            ]);
        });
    }
};
