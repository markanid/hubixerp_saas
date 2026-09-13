<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Settings\app\Models\LocalPrintJob;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintAgentPrinterMapping;
use Modules\Settings\app\Models\PrintSetting;
use Modules\Settings\app\Services\PrintDocumentRenderer;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class LocalPrintAgentProtocolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);

        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('machine_name')->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->string('pairing_code_hash')->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->unsignedBigInteger('paired_by')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('version')->nullable();
            $table->json('printers')->nullable();
            $table->string('default_printer')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('browser_link_code_hash', 64)->nullable();
            $table->timestamp('browser_link_expires_at')->nullable();
            $table->string('browser_binding_token_hash', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('print_agent_printer_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('print_agent_id');
            $table->string('document_type');
            $table->string('printer_name');
            $table->timestamps();
        });
        Schema::create('local_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('print_agent_id');
            $table->string('document_type');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status');
            $table->unsignedSmallInteger('copies')->default(1);
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('request_ip')->nullable();
            $table->string('request_user_agent', 500)->nullable();
            $table->string('claim_token_hash')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->dateTime('expires_at');
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->unique();
            $table->string('print_method')->default('local_agent');
            $table->string('paper_size')->default('A4');
            $table->string('orientation')->default('portrait');
            $table->unsignedSmallInteger('scale')->default(100);
            $table->unsignedSmallInteger('margin_mm')->default(10);
            $table->boolean('auto_print')->default(true);
            $table->string('header_mode')->default('full');
            $table->string('printer_name')->nullable();
            $table->timestamps();
        });
        Schema::create('barcode_settings', function (Blueprint $table) {
            $table->id();
            $table->string('context')->unique();
            $table->json('selected_fields')->nullable();
            $table->unsignedSmallInteger('label_width_mm')->default(80);
            $table->unsignedSmallInteger('label_height_mm')->default(40);
            $table->unsignedSmallInteger('label_margin_mm')->default(2);
            $table->boolean('auto_print')->default(true);
            $table->timestamps();
        });
    }

    public function test_agent_token_claims_each_job_once_and_enforces_state_transitions(): void
    {
        $token = 'agent-secret-token';
        $lastSeenAt = now()->startOfSecond();
        $agent = PrintAgent::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Counter 1',
            'token_hash' => hash('sha256', $token),
            'enabled' => true,
            'printers' => ['Receipt Printer'],
            'last_seen_at' => $lastSeenAt,
        ]);
        PrintAgentPrinterMapping::create([
            'print_agent_id' => $agent->id,
            'document_type' => 'sale',
            'printer_name' => 'Receipt Printer',
        ]);
        PrintSetting::create(array_merge(PrintSetting::defaultsFor('sale'), ['print_method' => 'local_agent']));
        $job = LocalPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'print_agent_id' => $agent->id,
            'document_type' => 'sale',
            'document_id' => 55,
            'status' => LocalPrintJob::STATUS_QUEUED,
            'copies' => 1,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken('wrong-token')->getJson(route('api.print-agent.jobs.next'))->assertUnauthorized();

        $claimed = $this->withToken($token)->getJson(route('api.print-agent.jobs.next'))
            ->assertOk()
            ->assertJsonPath('job_id', $job->uuid)
            ->assertJsonPath('printer_name', 'Receipt Printer');
        $claimToken = $claimed->json('claim_token');

        $this->withToken($token)->getJson(route('api.print-agent.jobs.next'))->assertNoContent();
        $this->assertDatabaseHas('local_print_jobs', ['id' => $job->id, 'status' => LocalPrintJob::STATUS_CLAIMED]);

        $headers = ['X-Print-Claim' => $claimToken];
        $this->withToken($token)->postJson(route('api.print-agent.jobs.status', $job), ['status' => 'printing'], $headers)->assertOk();
        $this->withToken($token)->postJson(route('api.print-agent.jobs.status', $job), ['status' => 'printed'], $headers)->assertOk();
        $this->assertDatabaseHas('local_print_jobs', ['id' => $job->id, 'status' => LocalPrintJob::STATUS_PRINTED]);
        $this->assertSame($lastSeenAt->format('Y-m-d H:i:s'), $agent->fresh()->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_local_agent_auto_print_setting_is_exposed_to_the_preview_script(): void
    {
        $manualSetting = new PrintSetting(array_merge(
            PrintSetting::defaultsFor('sale'),
            ['print_method' => 'local_agent', 'auto_print' => false]
        ));
        $automaticSetting = new PrintSetting(array_merge(
            PrintSetting::defaultsFor('sale'),
            ['print_method' => 'local_agent', 'auto_print' => true]
        ));

        $manualHtml = view('partials.local-print-script', [
            'printSetting' => $manualSetting,
            'documentType' => 'sale',
            'documentId' => 55,
        ])->render();
        $automaticHtml = view('partials.local-print-script', [
            'printSetting' => $automaticSetting,
            'documentType' => 'sale',
            'documentId' => 55,
        ])->render();

        $this->assertStringContainsString('Print with Hubix', $manualHtml);
        $this->assertStringContainsString('autoPrint: false', $manualHtml);
        $this->assertStringContainsString('autoPrint: true', $automaticHtml);
    }

    public function test_barcode_job_uses_the_browser_bound_agent_and_custom_label_size(): void
    {
        $token = 'barcode-agent-token';
        $agent = PrintAgent::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Label Counter',
            'version' => '1.0.3',
            'token_hash' => hash('sha256', $token),
            'enabled' => true,
            'printers' => ['Zebra Label Printer'],
            'last_seen_at' => now(),
        ]);
        $browserBinding = $this->createBrowserBinding($agent);
        PrintAgentPrinterMapping::create([
            'print_agent_id' => $agent->id,
            'document_type' => 'barcode',
            'printer_name' => 'Zebra Label Printer',
        ]);
        PrintSetting::create(array_merge(PrintSetting::defaultsFor('barcode'), ['print_method' => 'local_agent']));
        DB::table('barcode_settings')->insert([
            'context' => 'inventory',
            'selected_fields' => '[]',
            'label_width_mm' => 60,
            'label_height_mm' => 30,
            'label_margin_mm' => 2,
            'auto_print' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'source' => 'products',
            'ids' => [12, 18],
            'code_type' => 'barcode',
            'layout' => 'sheet',
        ];
        $renderer = $this->mock(PrintDocumentRenderer::class);
        $renderer->shouldReceive('documentExists')->once()->with('barcode', null, $payload)->andReturnTrue();

        $user = new User();
        $user->forceFill(['id' => 77, 'email' => 'printer@example.test']);
        $created = $this->actingAs($user)
            ->withCredentials()
            ->withCookie(PrintAgent::BROWSER_COOKIE, $browserBinding)
            ->postJson(route('local-print-jobs.store'), [
                'document_type' => 'barcode',
                'document_id' => null,
                'payload' => $payload,
                'copies' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('agent', 'Label Counter')
            ->assertCookie(PrintAgent::BROWSER_COOKIE, $browserBinding);

        $job = LocalPrintJob::query()->where('uuid', $created->json('job_id'))->firstOrFail();
        $this->assertSame($agent->id, $job->print_agent_id);
        $this->assertEquals($payload, $job->payload);

        $this->withToken($token)
            ->getJson(route('api.print-agent.jobs.next'))
            ->assertOk()
            ->assertJsonPath('printer_name', 'Zebra Label Printer')
            ->assertJsonPath('settings.paper_size', 'custom')
            ->assertJsonPath('settings.page_width_mm', 60)
            ->assertJsonPath('settings.page_height_mm', 30);
    }

    public function test_unbound_browser_cannot_queue_a_barcode_job(): void
    {
        PrintSetting::create(array_merge(PrintSetting::defaultsFor('barcode'), ['print_method' => 'local_agent']));
        $payload = [
            'source' => 'products',
            'ids' => [12],
            'code_type' => 'barcode',
            'layout' => 'single',
        ];
        $renderer = $this->mock(PrintDocumentRenderer::class);
        $renderer->shouldReceive('documentExists')->once()->with('barcode', null, $payload)->andReturnTrue();

        $user = new User();
        $user->forceFill(['id' => 78, 'email' => 'remote@example.test']);
        $this->actingAs($user)
            ->postJson(route('local-print-jobs.store'), [
                'document_type' => 'barcode',
                'payload' => $payload,
            ])
            ->assertStatus(409)
            ->assertJsonPath('fallback', 'browser');

        $this->assertDatabaseCount('local_print_jobs', 0);
    }

    public function test_browser_binding_requires_an_online_agents_single_use_local_code(): void
    {
        $token = 'binding-agent-token';
        $agent = PrintAgent::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Computer 1',
            'version' => '1.0.4',
            'token_hash' => hash('sha256', $token),
            'enabled' => true,
            'printers' => ['Computer 1 Printer'],
            'last_seen_at' => now(),
        ]);

        $codeResponse = $this->withToken($token)
            ->postJson(route('api.print-agent.browser-link-code'))
            ->assertOk();
        $code = $codeResponse->json('code');

        $this->assertSame(8, strlen($code));
        $this->assertSame(hash('sha256', $code), $agent->fresh()->browser_link_code_hash);

        Gate::define('sale.settings', fn () => true);
        $user = new User();
        $user->forceFill(['id' => 79, 'email' => 'computer1@example.test']);

        $bindingResponse = $this->actingAs($user)
            ->post(route('print-agents.bind-browser', $agent), ['browser_link_code' => $code])
            ->assertRedirect()
            ->assertCookie(PrintAgent::BROWSER_COOKIE);
        $browserBinding = $bindingResponse->getCookie(PrintAgent::BROWSER_COOKIE)->getValue();
        $this->assertTrue($agent->fresh()->matchesBrowserBinding($browserBinding));
        $this->assertGreaterThan(
            now()->addDays(399)->timestamp,
            $bindingResponse->getCookie(PrintAgent::BROWSER_COOKIE)->getExpiresTime()
        );

        $agent->refresh();
        $this->assertNull($agent->browser_link_code_hash);
        $this->assertNull($agent->browser_link_expires_at);

        $this->actingAs($user)
            ->post(route('print-agents.bind-browser', $agent), ['browser_link_code' => $code])
            ->assertSessionHasErrors('browser_link_code');

        $offlineCode = $this->withToken($token)
            ->postJson(route('api.print-agent.browser-link-code'))
            ->assertOk()
            ->json('code');
        $agent->update(['last_seen_at' => now()->subMinutes(3)]);

        $this->actingAs($user)
            ->post(route('print-agents.bind-browser', $agent), ['browser_link_code' => $offlineCode])
            ->assertSessionHas('error', 'The print agent must be paired, enabled, and online before linking this browser.');
    }

    public function test_application_logout_does_not_remove_the_computer_binding(): void
    {
        $user = new User();
        $user->forceFill(['id' => 81, 'email' => 'persistent-binding@example.test']);

        $this->actingAs($user)
            ->withCredentials()
            ->withCookie(PrintAgent::BROWSER_COOKIE, (string) Str::uuid())
            ->get(route('auth.logout'))
            ->assertRedirect(route('auth.login'))
            ->assertCookieMissing(PrintAgent::BROWSER_COOKIE);
    }

    public function test_two_computers_can_only_claim_jobs_bound_to_their_own_agent(): void
    {
        [$agent1, $token1] = $this->createMappedAgent('Computer 1', 'Printer at Location 1');
        [$agent2, $token2] = $this->createMappedAgent('Computer 2', 'Printer at Location 2');
        $browserBinding2 = $this->createBrowserBinding($agent2);
        PrintSetting::create(array_merge(PrintSetting::defaultsFor('estimation'), ['print_method' => 'local_agent']));

        $renderer = $this->mock(PrintDocumentRenderer::class);
        $renderer->shouldReceive('documentExists')->once()->with('estimation', 501, [])->andReturnTrue();

        $user = new User();
        $user->forceFill(['id' => 80, 'email' => 'location2@example.test']);
        $created = $this->actingAs($user)
            ->withCredentials()
            ->withCookie(PrintAgent::BROWSER_COOKIE, $browserBinding2)
            ->postJson(route('local-print-jobs.store'), [
                'document_type' => 'estimation',
                'document_id' => 501,
            ])
            ->assertCreated()
            ->assertJsonPath('agent', 'Computer 2');

        $job = LocalPrintJob::where('uuid', $created->json('job_id'))->firstOrFail();
        $this->assertSame($agent2->id, $job->print_agent_id);

        $this->withToken($token1)->getJson(route('api.print-agent.jobs.next'))->assertNoContent();
        $this->withToken($token2)
            ->getJson(route('api.print-agent.jobs.next'))
            ->assertOk()
            ->assertJsonPath('printer_name', 'Printer at Location 2');
    }

    public function test_heartbeat_does_not_create_implicit_default_printer_mappings(): void
    {
        $token = 'explicit-mapping-token';
        $agent = PrintAgent::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unconfigured Computer',
            'token_hash' => hash('sha256', $token),
            'enabled' => true,
        ]);

        $this->withToken($token)->postJson(route('api.print-agent.heartbeat'), [
            'machine_name' => 'PC-UNCONFIGURED',
            'version' => '1.0.4',
            'printers' => ['Windows Default Printer'],
            'default_printer' => 'Windows Default Printer',
        ])->assertOk();

        $this->assertDatabaseCount('print_agent_printer_mappings', 0);
        $this->assertSame('Windows Default Printer', $agent->fresh()->default_printer);
    }

    private function createMappedAgent(string $name, string $printer): array
    {
        $token = Str::random(32);
        $agent = PrintAgent::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'version' => '1.0.4',
            'token_hash' => hash('sha256', $token),
            'enabled' => true,
            'printers' => [$printer],
            'last_seen_at' => now(),
        ]);
        PrintAgentPrinterMapping::create([
            'print_agent_id' => $agent->id,
            'document_type' => 'estimation',
            'printer_name' => $printer,
        ]);

        return [$agent, $token];
    }

    private function createBrowserBinding(PrintAgent $agent): string
    {
        $token = bin2hex(random_bytes(32));
        $agent->update(['browser_binding_token_hash' => hash('sha256', $token)]);

        return $agent->uuid.'.'.$token;
    }
}
