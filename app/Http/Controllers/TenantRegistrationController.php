<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\User;
use Throwable;

class TenantRegistrationController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::query()
            ->with('domains')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('central.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('central.tenants.create', [
            'defaultDomain' => $this->centralHost(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = Str::slug((string) $request->input('tenant_id'));
        $domain = $this->normalizeDomain((string) $request->input('domain'));

        if ($domain === '' && $tenantId !== '') {
            $domain = $tenantId . '.' . $this->centralHost();
        }

        $request->merge([
            'tenant_id' => $tenantId,
            'domain' => $domain,
        ]);

        $validated = $request->validate([
            'tenant_id' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                'unique:tenants,id',
            ],
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?!-)[A-Za-z0-9.-]+(?<!-)$/',
                'unique:domains,domain',
            ],
            'company' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $tenant = Tenant::create([
                'id' => $validated['tenant_id'],
                'company_name' => $validated['company'],
                'status' => 'active',
            ]);

            $tenant->domains()->create([
                'domain' => $validated['domain'],
            ]);

            tenancy()->initialize($tenant);

            Company::query()->create([
                'company' => $validated['company'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ]);

            User::query()->create([
                'user_name' => $validated['owner_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'user_role' => 'Super Admin',
            ]);

            tenancy()->end();
        } catch (Throwable $exception) {
            tenancy()->end();

            report($exception);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Tenant creation failed. Please check database permissions and tenant migrations.');
        }

        return redirect()
            ->route('central.tenants.index')
            ->with('success', 'Tenant created successfully.')
            ->with('tenant_url', $this->tenantUrl($validated['domain'], $request));
    }

    private function centralHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ?: 'localhost';
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim(Str::lower($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;

        return trim($domain);
    }

    private function tenantUrl(string $domain, Request $request): string
    {
        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443], true) ? '' : ':' . $port;

        return $request->getScheme() . '://' . $domain . $portSuffix;
    }
}
