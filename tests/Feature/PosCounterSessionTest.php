<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Settings\app\Models\User;
use Tests\TestCase;

class PosCounterSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('user_role')->default('Salesman');
            $table->timestamps();
        });
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('company')->nullable();
            $table->string('inventory_mode')->default('standard');
        });
        Schema::create('pos_counters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_counter_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('financial_year', 9);
            $table->string('counter_code', 50);
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('closing_note')->nullable();
            $table->timestamps();
        });
        Schema::create('pos_held_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_session_id');
            $table->unsignedBigInteger('user_id');
            $table->string('hold_no');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->json('cart');
            $table->json('payments')->nullable();
            $table->decimal('amount_payable', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product');
            $table->string('product_image')->nullable();
            $table->string('hsn_code')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('mrp', 15, 2)->default(0);
            $table->string('unit')->default('No.s');
            $table->decimal('uqty', 15, 2)->default(1);
            $table->decimal('gst', 8, 2)->default(0);
            $table->decimal('minquantity', 15, 2)->default(0);
            $table->decimal('maxquantity', 15, 2)->default(0);
            $table->unsignedTinyInteger('typeid')->default(1);
            $table->boolean('is_batch_managed')->default(false);
            $table->string('status')->default('1');
        });
        Schema::create('stock', function (Blueprint $table) {
            $table->id('stock_id');
            $table->string('stock_product_id');
            $table->decimal('stock_qty', 15, 2)->default(0);
        });
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->decimal('available_quantity', 15, 2)->default(0);
        });

        DB::table('company')->insert(['company' => 'Test Company']);
    }

    public function test_user_can_open_an_assigned_counter_once(): void
    {
        $user = User::create([
            'user_name' => 'Cashier',
            'email' => 'cashier@example.test',
            'password' => bcrypt('password'),
            'user_role' => 'Salesman',
        ]);
        $counterId = DB::table('pos_counters')->insertGetId([
            'name' => 'COUNTER-1',
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('pos.sessions.open'), [
                'pos_counter_id' => $counterId,
                'opening_cash' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('session.counter_code', 'COUNTER-1');

        $this->actingAs($user)
            ->postJson(route('pos.sessions.open'), [
                'pos_counter_id' => $counterId,
                'opening_cash' => 500,
            ])
            ->assertOk()
            ->assertJsonPath('session.counter_code', 'COUNTER-1');
    }

    public function test_counter_cannot_be_opened_when_another_session_is_active(): void
    {
        $first = User::create([
            'user_name' => 'Cashier One',
            'email' => 'one@example.test',
            'password' => bcrypt('password'),
            'user_role' => 'Salesman',
        ]);
        $second = User::create([
            'user_name' => 'Cashier Two',
            'email' => 'two@example.test',
            'password' => bcrypt('password'),
            'user_role' => 'Salesman',
        ]);
        $counterId = DB::table('pos_counters')->insertGetId([
            'name' => 'SHARED',
            'user_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($first)->postJson(route('pos.sessions.open'), [
            'pos_counter_id' => $counterId,
            'opening_cash' => 100,
        ])->assertCreated();

        $this->actingAs($second)->postJson(route('pos.sessions.open'), [
            'pos_counter_id' => $counterId,
            'opening_cash' => 100,
        ])->assertStatus(422);
    }

    public function test_held_bill_can_be_recalled_with_current_product_details(): void
    {
        $user = User::create([
            'user_name' => 'Recall Cashier',
            'email' => 'recall@example.test',
            'password' => bcrypt('password'),
            'user_role' => 'Salesman',
        ]);
        $sessionId = DB::table('pos_sessions')->insertGetId([
            'user_id' => $user->id,
            'financial_year' => '2026-2027',
            'counter_code' => 'COUNTER-1',
            'opening_cash' => 0,
            'expected_cash' => 0,
            'opened_at' => now(),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product')->insert([
            'product_code' => 'PRD_001',
            'product' => 'Recall Product',
            'price' => 125,
            'unit' => 'No.s',
            'uqty' => 1,
            'status' => '1',
        ]);
        DB::table('stock')->insert([
            'stock_product_id' => 'PRD_001',
            'stock_qty' => 8,
        ]);
        $holdId = DB::table('pos_held_bills')->insertGetId([
            'pos_session_id' => $sessionId,
            'user_id' => $user->id,
            'hold_no' => 'H-TEST-1',
            'customer_name' => 'Walk-in',
            'cart' => json_encode([
                'sa_type' => '1',
                'sale_items' => [[
                    'product_id' => 'PRD_001',
                    'quantity' => 2,
                    'unit' => 'No.s',
                    'unit_price' => 125,
                    'discount_amount' => 5,
                ]],
            ]),
            'payments' => json_encode([]),
            'amount_payable' => 245,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('pos.holds.show', $holdId))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Recall Product')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.raw_stock', 8);
    }
}
