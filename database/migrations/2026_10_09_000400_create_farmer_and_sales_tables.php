<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('farmer_number', 30)->unique();
            $table->string('nida_number', 30)->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->unique();
            $table->foreignId('region_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ward')->nullable();
            $table->string('village')->nullable();
            $table->decimal('farm_size_acres', 8, 2)->default(1);
            $table->foreignId('main_crop_id')->nullable()->constrained('crops')->nullOnDelete();
            $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status', 20)->default('active')->index(); // pending, active, suspended
            $table->string('registered_via', 20)->default('web'); // web, ussd, dealer, officer, import
            $table->string('preferred_language', 5)->default('sw');
            $table->timestamps();
        });

        // How many subsidised bags of each product a farmer may buy this season.
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('bags_allowed');
            $table->unsignedInteger('bags_used')->default(0);
            $table->timestamps();
            $table->unique(['farmer_id', 'season_id', 'product_id']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('bags');
            $table->string('status', 20)->default('issued')->index(); // issued, redeemed, expired, cancelled
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->foreignId('redeemed_at_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
        });

        // Farmer reserves bags at a sale point before travelling there.
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('bags');
            $table->string('status', 20)->default('pending')->index(); // pending, collected, expired, cancelled
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('warehouse_id')->constrained(); // point of sale
            $table->string('customer_type', 20); // farmer, agro_dealer, cooperative
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agro_dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cooperative_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('total_bags');
            $table->decimal('total_amount', 14, 2);   // amount customer pays
            $table->decimal('subsidy_amount', 14, 2)->default(0); // government share
            $table->string('payment_method', 20)->default('cash');
            $table->string('payment_status', 20)->default('pending')->index(); // pending, paid, partial, credit
            $table->string('control_number', 30)->nullable()->unique();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->index();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('stock_batch_id')->constrained();
            $table->unsignedInteger('bags');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subsidy_per_bag', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20); // cash, mpesa, airtel_money, mixx, halopesa, gepg, bank
            $table->string('reference')->nullable();
            $table->string('payer_phone', 20)->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, failed
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'sale_items', 'sales', 'reservations', 'vouchers', 'entitlements', 'farmers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
