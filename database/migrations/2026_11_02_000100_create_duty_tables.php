<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uwajibikaji: anyone gives anyone a task with a deadline; the person follows it (seen, on it, submitted) and the
     * one who gave it approves or sends it back. A missed deadline is a warning: the person explains before working
     * on again, the one who gave the task accepts or rejects and keeps the warning or strikes it off. Five warnings: no
     * more explaining, until the administrator clears them.
     */
    public function up(): void
    {
        Schema::create('duty_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('details')->nullable();
            $table->timestamp('due_at');
            $table->string('status', 20)->default('new');
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('missed_at')->nullable();   // the current deadline passed (and was counted); a new deadline clears it
            $table->timestamps();
            $table->index(['status', 'due_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('duty_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('duty_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('reply');   // reply | submission | event
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });

        Schema::create('duty_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('duty_tasks')->cascadeOnDelete();
            $table->timestamp('due_at');                       // the deadline that was missed
            $table->unsignedTinyInteger('number');             // 1..5: which warning this was for the person
            $table->boolean('final')->default(false);          // the fifth: no explaining
            $table->string('status', 12)->default('explain');  // explain | review | kept | removed | cleared
            $table->text('explanation')->nullable();
            $table->timestamp('explained_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 10)->nullable();        // accepted | rejected
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('clear_reason')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_warnings');
        Schema::dropIfExists('duty_messages');
        Schema::dropIfExists('duty_tasks');
    }
};
