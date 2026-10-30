<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20);
            $table->string('category', 30); // overpricing, stock_out, quality, dealer_conduct, delivery, other
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('channel', 20)->default('web'); // web, ussd, sms, call
            $table->string('status', 20)->default('open')->index(); // open, in_progress, resolved, closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->text('message');
            $table->string('direction', 10)->default('out'); // out, in
            $table->string('status', 20)->default('queued'); // queued, sent, failed, logged
            $table->string('category', 30)->nullable(); // voucher, receipt, alert, advisory, otp
            $table->foreignId('farmer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('phone', 20);
            $table->text('last_input')->nullable();
            $table->timestamps();
        });

        Schema::create('advisories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('sms_text', 320)->nullable();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sms_recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Season distribution target per region & product (tonnes).
        Schema::create('distribution_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('target_tonnes', 12, 2);
            $table->timestamps();
            $table->unique(['season_id', 'region_id', 'product_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'distribution_targets', 'advisories', 'ussd_sessions', 'sms_messages', 'complaints'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
