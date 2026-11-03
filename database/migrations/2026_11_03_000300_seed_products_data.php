<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The previous migration's product rows silently failed to insert: the "products" table already existed
     * on this database, left over from an earlier, since-reverted attempt to merge TFC Kilimo in — and that
     * table requires a "category" column this app does not otherwise use. This seeds the real products,
     * satisfying that column, without touching the table's structure.
     */
    public function up(): void
    {
        DB::table('products')->insertOrIgnore([
            ['name' => 'DAP (Di-Ammonium Phosphate)', 'code' => 'DAP', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'UREA', 'code' => 'UREA', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CAN (Calcium Ammonium Nitrate)', 'code' => 'CAN', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'NPK 10:18:24 (Tobacco)', 'code' => 'NPK', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sulphate of Ammonia', 'code' => 'SA', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chokaa (Agricultural Lime)', 'code' => 'CHOKAA', 'category' => 'Fertilizer', 'package_kg' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('code', ['DAP', 'UREA', 'CAN', 'NPK', 'SA', 'CHOKAA'])->delete();
    }
};
