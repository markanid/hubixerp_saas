<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Estimation\app\Models\Estimation;
use Tests\TestCase;

class EstimationVoucherCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('estimation', function (Blueprint $table) {
            $table->id('es_id');
            $table->string('es_vno')->unique();
        });
    }

    public function test_first_voucher_starts_at_one(): void
    {
        $this->assertSame($this->prefix().'0001', Estimation::getVoucherCode());
    }

    public function test_voucher_increments_the_highest_numeric_suffix_for_the_current_year(): void
    {
        DB::table('estimation')->insert([
            ['es_vno' => $this->prefix().'0002'],
            ['es_vno' => $this->prefix().'0010'],
            ['es_vno' => $this->prefix().'BAD'],
            ['es_vno' => 'ES00-9999'],
        ]);

        $this->assertSame($this->prefix().'0011', Estimation::getVoucherCode());
    }

    private function prefix(): string
    {
        $year = date('m') >= 4 ? date('y') : (string) ((int) date('y') - 1);

        return 'ES'.$year.'-';
    }
}
