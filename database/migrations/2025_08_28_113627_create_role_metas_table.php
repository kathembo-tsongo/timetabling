<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->unique();
            $table->string('role_name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_core')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('last_modified_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_modified_by')->references('id')->on('users')->onDelete('set null');

            $table->index('is_core');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_metas');
    }
};
