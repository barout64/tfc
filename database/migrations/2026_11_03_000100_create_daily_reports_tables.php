<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily Report (Taarifa ya Mauzo ya Kila Siku): each station (kituo) submits what it sold each day —
     * one report per station per day, with one line per product sold. Totals are read from the lines, never
     * stored separately, so they can never drift from what was actually entered.
     */
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('location', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (! Schema::hasColumn('users', 'station_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('station_id')->nullable()->after('is_support')->constrained()->nullOnDelete();
            });
        }

        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'report_date']);
        });

        Schema::create('daily_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->string('product', 60);
            $table->string('unit', 20)->default('Bags');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_lines');
        Schema::dropIfExists('daily_reports');
        if (Schema::hasColumn('users', 'station_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('station_id'));
        }
        Schema::dropIfExists('stations');
    }
};
