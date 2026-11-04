<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TFC Protocol, inside the one TFC system: secretaries publish approved letter templates, everyone writes official
     * documents from them, and the protocol handbook. A template, a document and a rule are one JSON record each,
     * in the shape the Protocol screens work with (a template carries its versions, a document its own frozen copy).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('protocol_role', 20)->default('employee');   // admin, protocol, head, secretary, employee
        });

        Schema::create('protocol_settings', function (Blueprint $table) {
            $table->id();
            $table->json('data');
        });

        foreach (['protocol_templates', 'protocol_documents', 'protocol_rules'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('id', 40)->primary();
                $table->json('data');
            });
        }

        Schema::create('protocol_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('link')->nullable();
            $table->string('at', 30);
            $table->boolean('read')->default(false);
            $table->index(['user_id', 'read']);
        });

        Schema::create('protocol_audit', function (Blueprint $table) {
            $table->id();
            $table->string('at', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('detail')->nullable();
        });

        Schema::create('protocol_uploads', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->string('file')->unique();
            $table->string('name');
            $table->string('type', 40);
            $table->unsignedBigInteger('size');
            $table->foreignId('by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('at', 30);
        });

        Schema::create('protocol_counters', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->unsignedInteger('n')->default(0);
        });

        $at = now()->toIso8601ZuluString('millisecond');
        DB::table('protocol_settings')->insert(['data' => json_encode([
            'orgName' => 'Tanzania Fertilizer Company', 'orgCode' => 'TFC', 'motto' => '', 'address' => '', 'phone' => '', 'email' => '', 'footer' => '',
            'refFormat' => '{ORG}/{DEPT}/{YEAR}/{SEQ}', 'logo' => 'coat-of-arms-320x371.png', 'showDemoLogins' => false,
        ])]);
        DB::table('protocol_uploads')->insert(['id' => 'coat-of-arms', 'file' => 'coat-of-arms-320x371.png', 'name' => 'Coat of arms (logo)', 'type' => 'image/png', 'size' => 0, 'by' => null, 'at' => $at]);
    }

    public function down(): void
    {
        foreach (['protocol_counters', 'protocol_uploads', 'protocol_audit', 'protocol_notifications', 'protocol_rules', 'protocol_documents', 'protocol_templates', 'protocol_settings'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('protocol_role'));
    }
};
