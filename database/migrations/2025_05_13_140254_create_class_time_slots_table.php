<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('class_time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('day'); // Day of the week
            $table->time('start_time'); // Start time of the slot
            $table->time('end_time'); // End time of the slot
            $table->string('status')->default('physical'); // Status: physical or online
            $table->timestamps(); // Created at and updated at timestamps
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_time_slots');
    }
};
