<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TFC Meetings, inside the one TFC system: plan meetings (one or a repeating series), invite people, departments or
     * groups, run the agenda live, keep attendance, minutes, decisions and action items. Times stay as Tanzania local
     * text ('YYYY-MM-DD HH:MM'), as they always were.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 10)->nullable()->unique();   // Protocol's letter references, e.g. HR
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title', 120)->nullable();
            $table->string('meeting_role', 20)->default('member');   // admin, organizer, secretary, member
        });

        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('capacity')->default(10);
            $table->string('location')->nullable();
            $table->string('equipment')->nullable();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('meeting_type', 40)->default('Team Meeting');
            $table->string('start_time', 16);
            $table->string('end_time', 16);
            $table->foreignId('room_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $table->string('online_link')->nullable();
            $table->foreignId('organizer_id')->constrained('users');
            $table->string('status', 20)->default('scheduled');   // scheduled, in_progress, completed, cancelled
            $table->unsignedTinyInteger('quorum')->default(50);
            $table->string('series_id', 32)->nullable();
            $table->string('started_at', 19)->nullable();
            $table->string('ended_at', 19)->nullable();
            $table->unsignedBigInteger('current_agenda_id')->nullable();
            $table->string('created_at', 19)->nullable();
            $table->index('start_time');
            $table->index(['series_id', 'start_time']);
            $table->index(['room_id', 'start_time']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rsvp', 20)->default('pending');
            $table->string('attendance', 20)->default('unknown');
            $table->primary(['meeting_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('started_at', 19)->nullable();
            $table->string('ended_at', 19)->nullable();
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->foreignId('meeting_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->string('status', 20)->default('draft');   // draft, submitted, approved
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('updated_at', 19)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approved_at', 19)->nullable();
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
            $table->text('text');
            $table->foreignId('proposer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seconder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('votes_for')->nullable();
            $table->unsignedInteger('votes_against')->nullable();
            $table->unsignedInteger('votes_abstain')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->string('created_at', 19)->nullable();
        });

        Schema::create('meeting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('color', 10)->default('#3b5bdb');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_at', 19)->nullable();
        });

        Schema::create('meeting_group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('meeting_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });

        // Which departments / groups a meeting was addressed to (for display)
        Schema::create('meeting_audience', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);   // department, group
            $table->unsignedBigInteger('ref_id');
            $table->primary(['meeting_id', 'kind', 'ref_id']);
        });

        Schema::create('meeting_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('due_date', 10)->nullable();
            $table->string('status', 20)->default('open');   // open, in_progress, done
            $table->string('created_at', 19)->nullable();
            $table->string('completed_at', 19)->nullable();
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('meeting_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
            $table->string('filename');
            $table->string('stored_name');
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_at', 19)->nullable();
        });

        Schema::create('meeting_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('created_at', 19)->nullable();
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        foreach (['meeting_notifications', 'meeting_attachments', 'meeting_action_items', 'meeting_audience', 'meeting_group_members', 'meeting_groups',
            'meeting_decisions', 'meeting_minutes', 'meeting_agenda_items', 'meeting_participants', 'meetings', 'meeting_rooms'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['job_title', 'meeting_role']);
        });
        Schema::dropIfExists('departments');
    }
};
