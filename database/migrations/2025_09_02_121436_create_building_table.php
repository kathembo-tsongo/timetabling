<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('building', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert some default buildings
        $buildings = [
            ['name' => 'Main Building', 'code' => 'MAIN', 'description' => 'Main administrative building'],
            ['name' => 'Science Building', 'code' => 'SCI', 'description' => 'Science laboratories and classrooms'],
            ['name' => 'Arts Building', 'code' => 'ARTS', 'description' => 'Arts and humanities classrooms'],
            ['name' => 'Engineering Building', 'code' => 'ENG', 'description' => 'Engineering labs and workshops'],
            ['name' => 'Library Building', 'code' => 'LIB', 'description' => 'Library and study areas'],
        ];

        foreach ($buildings as $building) {
            DB::table('building')->insert(array_merge($building, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};