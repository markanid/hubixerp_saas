<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Settings\app\Models\User;
use Tests\TestCase;

class SaleRouteSecurityTest extends TestCase
{
    public function test_sale_cancellation_uses_delete_and_the_incomplete_api_resource_is_absent(): void
    {
        $destroyRoute = Route::getRoutes()->getByName('sales.destroy');

        $this->assertNotNull($destroyRoute);
        $this->assertSame(['DELETE'], $destroyRoute->methods());
        $this->assertNull(Route::getRoutes()->getByName('api.sale.store'));
    }

    public function test_sale_roles_are_separated_between_operations_and_cancellation(): void
    {
        $salesman = new User(['user_role' => 'Salesman']);
        $administrator = new User(['user_role' => 'Administrator']);

        $this->assertTrue(Gate::forUser($salesman)->allows('sale.create'));
        $this->assertFalse(Gate::forUser($salesman)->allows('sale.cancel'));
        $this->assertTrue(Gate::forUser($administrator)->allows('sale.cancel'));
        $this->assertTrue(Gate::forUser($administrator)->allows('sale.settings'));
    }

    public function test_print_agent_management_requires_administrator_settings_permission(): void
    {
        $route = Route::getRoutes()->getByName('print-agents.store');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('can:sale.settings', $route->gatherMiddleware());
    }
}
