<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A simple product catalogue for the Daily Report form, and the real TFC stations and products —
     * taken from the TFC Kilimo system (its warehouses and fertilizer products), so Daily Report starts
     * with the real list instead of an empty one.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('code', 20)->nullable();
                $table->unsignedSmallInteger('package_kg')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        DB::table('products')->insertOrIgnore([
            ['name' => 'DAP (Di-Ammonium Phosphate)', 'code' => 'DAP', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'UREA', 'code' => 'UREA', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CAN (Calcium Ammonium Nitrate)', 'code' => 'CAN', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'NPK 10:18:24 (Tobacco)', 'code' => 'NPK', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sulphate of Ammonia', 'code' => 'SA', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chokaa (Agricultural Lime)', 'code' => 'CHOKAA', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $stations = [
            ['name' => 'Arusha', 'location' => 'Arusha'], ['name' => 'Babati', 'location' => 'Manyara'],
            ['name' => 'Dodoma', 'location' => 'Dodoma'], ['name' => 'Iringa', 'location' => 'Iringa'],
            ['name' => 'Kahama', 'location' => 'Shinyanga'], ['name' => 'Karatu', 'location' => 'Arusha'],
            ['name' => 'Kasulu', 'location' => 'Kigoma'], ['name' => 'Kurasini', 'location' => 'Dar es Salaam'],
            ['name' => 'Makambako', 'location' => 'Njombe'], ['name' => 'Mbeya', 'location' => 'Mbeya'],
            ['name' => 'Morogoro', 'location' => 'Morogoro'], ['name' => 'Moshi', 'location' => 'Kilimanjaro'],
            ['name' => 'Mpanda', 'location' => 'Katavi'], ['name' => 'Mwanza', 'location' => 'Mwanza'],
            ['name' => 'Namtumbo', 'location' => 'Ruvuma'], ['name' => 'Nzela', 'location' => 'Geita'],
            ['name' => 'Pamba', 'location' => 'Dar es Salaam'], ['name' => 'Shinyanga', 'location' => 'Shinyanga'],
            ['name' => 'Songea', 'location' => 'Ruvuma'], ['name' => 'Songwe', 'location' => 'Songwe'],
            ['name' => 'Sumbawanga', 'location' => 'Rukwa'], ['name' => 'Tabora', 'location' => 'Tabora'],
            ['name' => 'Tarime', 'location' => 'Mara'], ['name' => 'Tunduru', 'location' => 'Ruvuma'],
        ];
        DB::table('stations')->insertOrIgnore(array_map(
            fn ($s) => $s + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            $stations,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
