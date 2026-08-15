<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_counters')) {
            Schema::create('pos_counters', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pos_sessions') && !Schema::hasColumn('pos_sessions', 'pos_counter_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->foreignId('pos_counter_id')->nullable()->after('id')->constrained('pos_counters')->nullOnDelete();
            });
        }

        if (Schema::hasTable('pos_sessions') && Schema::hasTable('pos_counters')) {
            $names = DB::table('pos_sessions')
                ->whereNotNull('counter_code')
                ->distinct()
                ->pluck('counter_code');

            foreach ($names as $name) {
                DB::table('pos_counters')->updateOrInsert(
                    ['name' => $name],
                    ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            DB::table('pos_sessions')
                ->join('pos_counters', 'pos_sessions.counter_code', '=', 'pos_counters.name')
                ->whereNull('pos_sessions.pos_counter_id')
                ->update(['pos_sessions.pos_counter_id' => DB::raw('pos_counters.id')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_sessions') && Schema::hasColumn('pos_sessions', 'pos_counter_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pos_counter_id');
            });
        }

        Schema::dropIfExists('pos_counters');
    }
};
