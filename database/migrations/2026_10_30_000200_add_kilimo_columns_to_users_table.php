<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 30)->nullable()->index()->after('phone');
            // Scoping: a user may be tied to one warehouse, dealer or transporter (Kilimo).
            $table->unsignedBigInteger('warehouse_id')->nullable()->index()->after('role');
            $table->unsignedBigInteger('agro_dealer_id')->nullable()->index()->after('warehouse_id');
            $table->unsignedBigInteger('transporter_id')->nullable()->index()->after('agro_dealer_id');
            $table->string('locale', 5)->default('en')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'warehouse_id', 'agro_dealer_id', 'transporter_id', 'locale']);
        });
    }
};
