<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Settings\app\Models\LocalPrintJob;
use Modules\Settings\app\Models\PrintAgent;
use Modules\Settings\app\Models\PrintAgentPrinterMapping;
use Modules\Settings\app\Models\PrintSetting;
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
            $table->string('status');
            $table->unsignedSmallInteger('copies')->default(1);
            $table->unsignedBigInteger('requested_by')->nullable();
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
            $table->string('printer_name')->nullable();
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
}
