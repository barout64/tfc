<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every place stock can sit: port yard, HQ, zonal warehouse, sale point, or an agro-dealer shop.
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 20)->index(); // port, hq, zonal, sale_point, agro_dealer
            $table->foreignId('region_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agro_dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tfra_number', 50)->nullable();
            $table->unsignedInteger('capacity_tonnes')->default(0);
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_public')->default(true); // shown on public sale-point finder
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('batch_number', 60);
            $table->string('source', 20)->default('import'); // import, local, return
            $table->unsignedBigInteger('shipment_id')->nullable()->index();
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable(); // landed cost per bag
            $table->timestamps();
            $table->unique(['product_id', 'batch_number']);
        });

        // Current balance per warehouse + batch, always in whole bags.
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->cascadeOnDelete();
            $table->integer('bags')->default(0);
            $table->integer('reserved_bags')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'stock_batch_id']);
        });

        // Immutable ledger; balances are the running sum of these rows.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('stock_batch_id')->constrained();
            $table->string('type', 30)->index(); // receipt, dispatch_out, transfer_in, sale, loss, adjustment, return
            $table->integer('bags'); // signed
            $table->integer('balance_after');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('counted_on');
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, rejected
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained();
            $table->integer('system_bags');
            $table->integer('counted_bags');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_count_items', 'stock_counts', 'stock_movements', 'stock_balances', 'stock_batches', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
