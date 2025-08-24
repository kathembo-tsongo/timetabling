<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
            $table->text('description')->nullable()->after('is_active');
            $table->string('contact_email')->nullable()->after('description');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->integer('sort_order')->default(0)->after('contact_phone');
        });
    }

    public function down()
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'description', 'contact_email', 'contact_phone', 'sort_order']);
        });
    }
};