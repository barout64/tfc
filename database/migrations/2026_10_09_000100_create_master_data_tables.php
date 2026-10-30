<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['region_id', 'name']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // e.g. 2026/27
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 30); // planting, top_dressing, compound, lime, other
            $table->unsignedSmallInteger('package_kg')->default(50);
            $table->string('nutrients')->nullable(); // e.g. 18-46-0
            $table->string('color', 7)->default('#16a34a');
            $table->unsignedInteger('low_stock_bags')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Price per bag. region_id null = national price.
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('market_price', 12, 2);
            $table->decimal('subsidy_price', 12, 2); // price the farmer pays
            $table->date('effective_from');
            $table->timestamps();
            $table->index(['product_id', 'season_id', 'region_id']);
        });

        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_sw')->nullable();
            $table->timestamps();
        });

        // Agronomic recommendation: bags of product per acre for a crop.
        Schema::create('crop_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30); // planting, top_dressing
            $table->decimal('bags_per_acre', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['crop_id', 'product_id', 'stage']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('agro_dealers', function (Blueprint $table) {
            $table->id();
            $table->string('dealer_number', 30)->unique();
            $table->string('business_name');
            $table->string('owner_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('tfra_license_no', 50)->nullable();
            $table->date('license_expiry')->nullable();
            $table->foreignId('region_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ward')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, suspended, rejected
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_no', 50)->nullable()->unique();
            $table->foreignId('region_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chairperson')->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedInteger('members_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cooperatives', 'agro_dealers', 'suppliers', 'crop_recommendations', 'crops',
            'product_prices', 'products', 'seasons', 'districts', 'regions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
