<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which account each TFC person uses in each system (no row = no access), and who signed in / opened what.
     */
    public function up(): void
    {
        Schema::create('system_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('system', 30);
            $table->string('remote_id');
            $table->string('remote_name')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'system']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('system', 30)->nullable();
            $table->string('detail')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('system_links');
    }
};
