<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imports: vessel from supplier to port, then offloaded into a warehouse.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('vessel_name')->nullable();
            $table->string('port', 50)->default('Dar es Salaam');
            $table->decimal('ordered_tonnes', 12, 2);
            $table->decimal('unit_cost_per_tonne', 12, 2)->nullable();
            $table->date('eta')->nullable();
            $table->date('arrived_on')->nullable();
            $table->string('status', 20)->default('ordered')->index(); // ordered, in_transit, at_port, clearing, offloading, completed, cancelled
            $table->foreignId('receiving_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->unsignedInteger('bags_received')->default(0);
            $table->unsignedInteger('bags_damaged')->default(0);
            $table->string('batch_number', 60)->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transporters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin', 30)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->decimal('rate_per_tonne_km', 10, 2)->default(0);
            $table->decimal('loss_penalty_per_bag', 10, 2)->default(0);
            $table->date('contract_expires_on')->nullable();
            $table->string('status', 20)->default('active'); // active, suspended
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporter_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number', 30)->unique();
            $table->string('type', 20)->default('truck'); // truck, trailer, wagon
            $table->decimal('capacity_tonnes', 8, 2)->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('license_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A trip that moves stock from one warehouse to another.
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('transporter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft')->index(); // draft, approved, in_transit, delivered, cancelled
            $table->unsignedInteger('distance_km')->default(0);
            $table->date('planned_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('tracking_token', 40)->unique(); // QR code / driver link
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('pod_photo')->nullable();
            $table->text('pod_notes')->nullable();
            $table->decimal('transport_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained();
            $table->unsignedInteger('bags_sent');
            $table->unsignedInteger('bags_received')->nullable();
            $table->unsignedInteger('bags_damaged')->default(0);
            $table->timestamps();
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30); // approved, loaded, departed, checkpoint, delay, breakdown, arrived, delivered
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
        });

        Schema::create('transporter_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporter_id')->constrained();
            $table->foreignId('dispatch_id')->unique()->constrained();
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('loss_deduction', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->string('status', 20)->default('pending'); // pending, approved, paid
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['transporter_payments', 'tracking_events', 'dispatch_items', 'dispatches', 'drivers', 'vehicles', 'transporters', 'shipments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
