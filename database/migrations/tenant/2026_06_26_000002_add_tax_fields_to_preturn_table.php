<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preturn', function (Blueprint $table) {
            if (!Schema::hasColumn('preturn', 'pr_state_code')) {
                $table->string('pr_state_code', 2)->nullable()->after('pr_vendor')->index();
            }

            if (!Schema::hasColumn('preturn', 'pr_is_igst')) {
                $table->boolean('pr_is_igst')->default(false)->after('pr_state_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preturn', function (Blueprint $table) {
            if (Schema::hasColumn('preturn', 'pr_is_igst')) {
                $table->dropColumn('pr_is_igst');
            }

            if (Schema::hasColumn('preturn', 'pr_state_code')) {
                $table->dropIndex(['pr_state_code']);
                $table->dropColumn('pr_state_code');
            }
        });
    }
};
