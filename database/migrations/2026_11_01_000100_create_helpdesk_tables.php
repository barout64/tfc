<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TFC Helpdesk: staff ask for help (printer, computer, internet…); helpdesk support takes each request,
     * answers and fixes it. Every reply and status change is kept on the request.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_support')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_support')->default(false);   // receives and fixes helpdesk requests
            });
        }

        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();             // who asked
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->string('priority', 10)->default('normal');
            $table->string('subject', 150);
            $table->text('description');
            $table->string('location', 120)->nullable();                                   // office / room
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('helpdesk_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default('reply');   // reply | event
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_messages');
        Schema::dropIfExists('helpdesk_tickets');
        if (Schema::hasColumn('users', 'is_support')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_support'));
        }
    }
};
