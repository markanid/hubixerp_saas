<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Contacts\app\Models\Customer;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer')) {
            return;
        }

        $values = [
            'customer' => Customer::WALK_IN_NAME,
            'phone' => Customer::WALK_IN_PHONE,
            'status' => '1',
        ];

        if (Schema::hasColumn('customer', 'op_balance')) {
            $values['op_balance'] = 0;
        }

        DB::table('customer')->updateOrInsert(
            ['phone' => Customer::WALK_IN_PHONE],
            $values
        );
    }

    public function down(): void
    {
        //
    }
};
