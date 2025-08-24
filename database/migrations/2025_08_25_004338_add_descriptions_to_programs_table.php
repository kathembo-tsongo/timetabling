<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('duration_years');
            $table->string('contact_email')->nullable()->after('description');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->integer('sort_order')->default(0)->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['description', 'contact_email', 'contact_phone', 'sort_order']);
        });
    }
};
