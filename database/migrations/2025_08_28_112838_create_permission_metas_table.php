<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permission_metas', function (Blueprint $table) {
           $table->id();
            $table->string('permission_name')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->boolean('is_core')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('last_modified_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('permission_name')->references('name')->on('permissions')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_modified_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['category', 'is_core']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_metas');
    }
};
