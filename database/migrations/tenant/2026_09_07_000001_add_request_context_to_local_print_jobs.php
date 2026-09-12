<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->string('request_ip', 45)->nullable()->after('requested_by');
            $table->string('request_user_agent', 500)->nullable()->after('request_ip');
        });
    }

    public function down(): void
    {
        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->dropColumn(['request_ip', 'request_user_agent']);
        });
    }
};
