<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('print_agents')) {
            return;
        }

        if (!Schema::hasColumn('print_agents', 'browser_link_code_hash')) {
            Schema::table('print_agents', function (Blueprint $table) {
                $table->string('browser_link_code_hash', 64)->nullable()->after('last_error');
            });
        }

        if (!Schema::hasColumn('print_agents', 'browser_link_expires_at')) {
            Schema::table('print_agents', function (Blueprint $table) {
                $table->timestamp('browser_link_expires_at')->nullable()->after('browser_link_code_hash');
            });
        }

        if (!Schema::hasColumn('print_agents', 'browser_binding_token_hash')) {
            Schema::table('print_agents', function (Blueprint $table) {
                $table->string('browser_binding_token_hash', 64)->nullable()->after('browser_link_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('print_agents')) {
            return;
        }

        $columns = collect(['browser_link_code_hash', 'browser_link_expires_at', 'browser_binding_token_hash'])
            ->filter(fn (string $column) => Schema::hasColumn('print_agents', $column))
            ->values()
            ->all();

        if ($columns !== []) {
            Schema::table('print_agents', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
