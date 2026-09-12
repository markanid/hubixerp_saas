<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServicePrintHeaderTest extends TestCase
{
    public function test_compact_service_header_only_renders_date_and_invoice_number_metadata(): void
    {
        $html = $this->renderMetadata(true);

        $this->assertStringContainsString('Invoice Date', $html);
        $this->assertStringContainsString('Invoice No', $html);
        $this->assertStringNotContainsString('Invoice Type', $html);
        $this->assertStringNotContainsString('E-way Bill No', $html);
        $this->assertStringNotContainsString('State:', $html);
        $this->assertStringNotContainsString('Code:', $html);
        $this->assertStringNotContainsString('Vehicle', $html);
    }

    public function test_full_service_header_renders_all_invoice_metadata(): void
    {
        $html = $this->renderMetadata(false);

        foreach (['Invoice Type', 'Invoice Date', 'Invoice No', 'E-way Bill No', 'State:', 'Code:', 'Vehicle'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    private function renderMetadata(bool $compact): string
    {
        return view('service::services.partials.print-invoice-metadata', [
            'compactServiceHeader' => $compact,
            'service' => (object) [
                'sv_date' => '2026-09-09',
                'sv_vno' => 'SV-1001',
                'sv_eway_bill_no' => 'EWAY-9',
                'sv_vehicle' => 'ABC-123',
            ],
            'invoiceType' => 'B2B',
            'isVat' => false,
            'showTax' => true,
            'state' => (object) ['state_name' => 'Kerala', 'state_code' => '32'],
        ])->render();
    }
}
