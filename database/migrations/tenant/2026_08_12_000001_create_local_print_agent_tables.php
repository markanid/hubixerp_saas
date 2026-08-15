<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('machine_name')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('pairing_code_hash')->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->foreignId('paired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('version', 40)->nullable();
            $table->json('printers')->nullable();
            $table->string('default_printer')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('print_agent_printer_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_agent_id')->constrained('print_agents')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('printer_name');
            $table->timestamps();
            $table->unique(['print_agent_id', 'document_type'], 'print_agent_document_unique');
        });

        Schema::create('local_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('print_agent_id')->constrained('print_agents')->restrictOnDelete();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('copies')->default(1);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('claim_token_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->dateTime('expires_at');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['print_agent_id', 'status', 'expires_at'], 'print_agent_queue_index');
            $table->index(['requested_by', 'created_at'], 'print_requester_index');
        });

        if (Schema::hasTable('print_settings')) {
            DB::table('print_settings')->where('print_method', 'qz')->update(['print_method' => 'browser']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_print_jobs');
        Schema::dropIfExists('print_agent_printer_mappings');
        Schema::dropIfExists('print_agents');
    }
};
