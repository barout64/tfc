<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily Report: each line says who bought (agro dealers and farmers pay different prices), and each station
     * keeps its opening and closing stock per product per month. Lines from before this have no buyer.
     */
    public function up(): void
    {
        Schema::table('daily_report_lines', function (Blueprint $table) {
            $table->string('buyer', 20)->nullable()->after('daily_report_id');
        });

        Schema::create('station_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);   // 2026-11
            $table->string('product', 60);
            $table->string('unit', 20)->default('Bags');
            $table->decimal('opening', 12, 2)->nullable();
            $table->decimal('closing', 12, 2)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['station_id', 'month', 'product', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_stocks');
        Schema::table('daily_report_lines', fn (Blueprint $table) => $table->dropColumn('buyer'));
    }
};
