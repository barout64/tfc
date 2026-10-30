<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TFC "Stock position" reports, kept exactly as reported: tonnes per centre plus the money
 * figures (cash sale, subsidy, stock value) and bulk/export sales that the bag ledger cannot hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->index();
            $table->string('title');
            $table->string('source_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_to_stock_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per TFC centre (the "STOCK POSITION" sheet)
        Schema::create('stock_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('region_label');
            $table->string('center_label');
            $table->decimal('distributed_tonnes', 14, 3)->default(0);
            $table->decimal('sold_tonnes', 14, 3)->default(0);
            $table->decimal('balance_tonnes', 14, 3)->default(0);
            $table->decimal('dap_old_stock_tonnes', 14, 3)->default(0);
            $table->decimal('urea_tonnes', 14, 3)->default(0);
            $table->decimal('cash_sale_tzs', 18, 2)->default(0);
            $table->decimal('subsidy_tzs', 18, 2)->default(0);
            $table->decimal('stock_value_tzs', 18, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Bulk and export sales not made at a centre, e.g. DAP to Burundi, NPK to TCJE
        Schema::create('stock_report_bulk_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('buyer')->nullable();
            $table->decimal('quantity_tonnes', 14, 3)->default(0);
            $table->decimal('usd_per_tonne', 12, 2)->nullable();
            $table->decimal('amount_usd', 18, 2)->nullable();
            $table->decimal('amount_tzs', 18, 2)->nullable();
            $table->timestamps();
        });

        // Per-product summary (the "SALES SUMMARY" sheet); amounts may be TZS or USD
        Schema::create('stock_report_product_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->decimal('sold_tonnes', 14, 3)->default(0);
            $table->decimal('cash_sale_amount', 18, 2)->default(0);
            $table->string('cash_sale_currency', 3)->default('TZS');
            $table->decimal('subsidy_tzs', 18, 2)->default(0);
            $table->decimal('balance_tonnes', 14, 3)->default(0);
            $table->decimal('stock_value_amount', 18, 2)->default(0);
            $table->string('stock_value_currency', 3)->default('TZS');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_report_product_summaries', 'stock_report_bulk_sales', 'stock_report_lines', 'stock_reports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
