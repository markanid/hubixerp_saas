<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\app\Models\Banking;
use Modules\Settings\app\Models\User;
use Tests\TestCase;

class BankingEditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('banking', function (Blueprint $table) {
            $table->id('bk_id');
            $table->string('bk_bank', 50);
            $table->string('bk_account', 50);
            $table->string('bk_branch', 20);
            $table->string('bk_ifsc', 20);
            $table->decimal('bk_opbalance', 10, 2);
            $table->decimal('bk_clbalance', 10, 2);
            $table->string('bk_status')->default('1');
            $table->timestamps();
        });
        Schema::create('daily_bank', function (Blueprint $table) {
            $table->id('db_id');
            $table->date('db_date');
            $table->string('financial_year', 9)->nullable();
            $table->unsignedBigInteger('db_bank');
            $table->decimal('db_amount', 10, 2);
            $table->string('status')->default('1');
            $table->timestamps();
        });

        Schema::create('ledgerbook', function (Blueprint $table) {
            $table->id('lb_id');
            $table->unsignedBigInteger('lb_paymode');
            $table->unsignedBigInteger('lb_payee')->nullable();
            $table->string('lb_type');
        });
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable();
        });

        $user = new User(['user_name' => 'Bank Editor', 'user_role' => 'Administrator']);
        $user->id = 1;
        $this->actingAs($user);
    }

    public function test_zero_values_load_and_bank_name_edit_saves_with_existing_transactions(): void
    {
        $bank = Banking::create([
            'bk_bank' => 'Cash',
            'bk_account' => '0',
            'bk_branch' => '0',
            'bk_ifsc' => '0',
            'bk_opbalance' => 0,
            'bk_clbalance' => 125,
        ]);
        DB::table('ledgerbook')->insert(['lb_paymode' => $bank->bk_id, 'lb_type' => 'sp']);

        $response = $this->get(route('banks.edit', $bank->bk_id))->assertOk();
        $fields = $this->formFields($response->getContent());

        foreach (['bk_account', 'bk_branch', 'bk_ifsc', 'bk_opbalance'] as $field) {
            $this->assertSame('0', $fields[$field]);
        }

        $fields['bk_bank'] = 'Cash Counter';
        $this->post(route('banks.update'), $fields)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banks.show', $bank->bk_id));

        $this->assertDatabaseHas('banking', [
            'bk_id' => $bank->bk_id,
            'bk_bank' => 'Cash Counter',
            'bk_account' => '0',
            'bk_ifsc' => '0',
            'bk_opbalance' => 0,
            'bk_clbalance' => 125,
        ]);
        $this->assertDatabaseCount('banking', 1);
        $this->assertDatabaseCount('ledgerbook', 1);
    }

    public function test_validation_failure_preserves_entered_details_for_correction(): void
    {
        $bank = Banking::create($this->bankDetails() + ['bk_clbalance' => 100]);
        $editUrl = route('banks.edit', $bank->bk_id);
        $attempt = [
            'bk_id' => $bank->bk_id,
            'bk_bank' => 'Updated Bank',
            'bk_account' => '000987654321',
            'bk_branch' => '',
            'bk_ifsc' => 'TEST0000002',
            'bk_opbalance' => '25.50',
        ];

        $this->from($editUrl)->post(route('banks.update'), $attempt)
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('bk_branch');

        $response = $this->get($editUrl)->assertOk();
        $fields = $this->formFields($response->getContent());
        foreach ($attempt as $field => $value) {
            $this->assertSame((string) $value, $fields[$field]);
        }
        $this->assertDatabaseHas('banking', ['bk_id' => $bank->bk_id] + $this->bankDetails());

        $fields['bk_branch'] = 'Updated Branch';
        $this->post(route('banks.update'), $fields)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banks.show', $bank->bk_id));
        $this->assertDatabaseHas('banking', [
            'bk_id' => $bank->bk_id,
            'bk_bank' => 'Updated Bank',
            'bk_account' => '000987654321',
            'bk_branch' => 'Updated Branch',
            'bk_ifsc' => 'TEST0000002',
            'bk_opbalance' => 25.50,
            'bk_clbalance' => 25.50,
        ]);
    }

    public function test_created_bank_details_reload_and_updates_persist(): void
    {
        $this->post(route('banks.update'), $this->bankDetails())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $bank = Banking::sole();
        $response = $this->get(route('banks.edit', $bank->bk_id))->assertOk();
        $fields = $this->formFields($response->getContent());
        foreach ($this->bankDetails() as $field => $value) {
            $this->assertSame((string) $value, $fields[$field]);
        }

        $fields['bk_bank'] = 'Renamed Bank';
        $fields['bk_account'] = '000123456780';
        $fields['bk_ifsc'] = 'TEST0000003';
        $this->post(route('banks.update'), $fields)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banks.show', $bank->bk_id));

        $response = $this->get(route('banks.edit', $bank->bk_id))->assertOk();
        $reloaded = $this->formFields($response->getContent());
        foreach (['bk_bank', 'bk_account', 'bk_branch', 'bk_ifsc', 'bk_opbalance'] as $field) {
            $this->assertSame($fields[$field], $reloaded[$field]);
        }
        $this->assertDatabaseCount('banking', 1);
    }

    public function test_opening_balance_cannot_be_changed_after_transactions_exist(): void
    {
        $bank = Banking::create($this->bankDetails() + ['bk_clbalance' => 125]);
        DB::table('ledgerbook')->insert(['lb_paymode' => $bank->bk_id, 'lb_type' => 'sp']);

        $this->from(route('banks.edit', $bank->bk_id))
            ->post(route('banks.update'), array_replace($this->bankDetails(), [
                'bk_id' => $bank->bk_id,
                'bk_opbalance' => 200,
            ]))
            ->assertSessionHasErrors('bk_opbalance');

        $this->assertDatabaseHas('banking', [
            'bk_id' => $bank->bk_id,
            'bk_opbalance' => 100,
            'bk_clbalance' => 125,
        ]);

        $response = $this->get(route('banks.edit', $bank->bk_id))->assertOk();
        $fields = $this->formFields($response->getContent());
        $this->assertSame('100', $fields['bk_opbalance']);
        $fields['bk_bank'] = 'Renamed Bank';
        $this->post(route('banks.update'), $fields)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banks.show', $bank->bk_id));
        $this->assertDatabaseHas('banking', [
            'bk_id' => $bank->bk_id,
            'bk_bank' => 'Renamed Bank',
            'bk_opbalance' => 100,
            'bk_clbalance' => 125,
        ]);
    }

    private function bankDetails(): array
    {
        return [
            'bk_bank' => 'Test Bank',
            'bk_account' => '000123456789',
            'bk_branch' => 'Main Branch',
            'bk_ifsc' => 'TEST0000001',
            'bk_opbalance' => '100',
        ];
    }

    private function formFields(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $fields = [];
        foreach ((new DOMXPath($document))->query('//form[@id="addProduct"]//input[@name]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return $fields;
    }
}
