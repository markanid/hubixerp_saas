<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gst_state_codes')) {
            Schema::create('gst_state_codes', function (Blueprint $table) {
                $table->string('state_code', 2)->primary();
                $table->string('state_name', 100);
                $table->string('alpha_code', 6)->nullable();
            });
        }

        DB::table('gst_state_codes')->upsert($this->gstStates(), ['state_code'], ['state_name']);

        if (!Schema::hasTable('sale_settings')) {
            Schema::create('sale_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->boolean('value')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sale_voucher_sequences')) {
            Schema::create('sale_voucher_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 9);
                $table->enum('sale_type', ['0', '1', '2']);
                $table->unsignedBigInteger('last_number')->default(0);
                $table->unique(['financial_year', 'sale_type'], 'sale_voucher_sequence_unique');
            });
        }

        if (Schema::hasTable('sales')) {
            if (!Schema::hasColumn('sales', 'sa_eway_bill_no')) {
                Schema::table('sales', fn (Blueprint $table) => $table->string('sa_eway_bill_no')->nullable());
            }
            if (!Schema::hasColumn('sales', 'sa_remark')) {
                Schema::table('sales', fn (Blueprint $table) => $table->text('sa_remark')->nullable());
            }
            if (!Schema::hasColumn('sales', 'sa_state_code')) {
                Schema::table('sales', fn (Blueprint $table) => $table->string('sa_state_code', 2)->nullable()->index());
            }
            if (!Schema::hasColumn('sales', 'sa_is_igst')) {
                Schema::table('sales', fn (Blueprint $table) => $table->boolean('sa_is_igst')->default(false));
            }
        }

        if (Schema::hasTable('sale_details') && !Schema::hasColumn('sale_details', 'manufacturing_date')) {
            Schema::table('sale_details', function (Blueprint $table) {
                $table->string('manufacturing_date', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_details') && Schema::hasColumn('sale_details', 'manufacturing_date')) {
            Schema::table('sale_details', fn (Blueprint $table) => $table->dropColumn('manufacturing_date'));
        }

        if (Schema::hasTable('sales')) {
            $columns = collect(['sa_eway_bill_no', 'sa_remark', 'sa_state_code', 'sa_is_igst'])
                ->filter(fn (string $column) => Schema::hasColumn('sales', $column))
                ->all();

            if ($columns !== []) {
                Schema::table('sales', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        Schema::dropIfExists('sale_voucher_sequences');
        Schema::dropIfExists('sale_settings');
        Schema::dropIfExists('gst_state_codes');
    }

    private function gstStates(): array
    {
        return [
            ['state_code' => '01', 'state_name' => 'Jammu and Kashmir'],
            ['state_code' => '02', 'state_name' => 'Himachal Pradesh'],
            ['state_code' => '03', 'state_name' => 'Punjab'],
            ['state_code' => '04', 'state_name' => 'Chandigarh'],
            ['state_code' => '05', 'state_name' => 'Uttarakhand'],
            ['state_code' => '06', 'state_name' => 'Haryana'],
            ['state_code' => '07', 'state_name' => 'Delhi'],
            ['state_code' => '08', 'state_name' => 'Rajasthan'],
            ['state_code' => '09', 'state_name' => 'Uttar Pradesh'],
            ['state_code' => '10', 'state_name' => 'Bihar'],
            ['state_code' => '11', 'state_name' => 'Sikkim'],
            ['state_code' => '12', 'state_name' => 'Arunachal Pradesh'],
            ['state_code' => '13', 'state_name' => 'Nagaland'],
            ['state_code' => '14', 'state_name' => 'Manipur'],
            ['state_code' => '15', 'state_name' => 'Mizoram'],
            ['state_code' => '16', 'state_name' => 'Tripura'],
            ['state_code' => '17', 'state_name' => 'Meghalaya'],
            ['state_code' => '18', 'state_name' => 'Assam'],
            ['state_code' => '19', 'state_name' => 'West Bengal'],
            ['state_code' => '20', 'state_name' => 'Jharkhand'],
            ['state_code' => '21', 'state_name' => 'Odisha'],
            ['state_code' => '22', 'state_name' => 'Chhattisgarh'],
            ['state_code' => '23', 'state_name' => 'Madhya Pradesh'],
            ['state_code' => '24', 'state_name' => 'Gujarat'],
            ['state_code' => '26', 'state_name' => 'Dadra and Nagar Haveli and Daman and Diu'],
            ['state_code' => '27', 'state_name' => 'Maharashtra'],
            ['state_code' => '29', 'state_name' => 'Karnataka'],
            ['state_code' => '30', 'state_name' => 'Goa'],
            ['state_code' => '31', 'state_name' => 'Lakshadweep'],
            ['state_code' => '32', 'state_name' => 'Kerala'],
            ['state_code' => '33', 'state_name' => 'Tamil Nadu'],
            ['state_code' => '34', 'state_name' => 'Puducherry'],
            ['state_code' => '35', 'state_name' => 'Andaman and Nicobar Islands'],
            ['state_code' => '36', 'state_name' => 'Telangana'],
            ['state_code' => '37', 'state_name' => 'Andhra Pradesh'],
            ['state_code' => '38', 'state_name' => 'Ladakh'],
            ['state_code' => '97', 'state_name' => 'Other Territory'],
            ['state_code' => '99', 'state_name' => 'Other Country'],
        ];
    }
};
