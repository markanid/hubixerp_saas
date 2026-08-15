<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenants</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1f2937; }
        main { max-width: 1080px; margin: 0 auto; padding: 32px 20px; }
        header { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 28px; }
        a.button { background: #0f766e; color: white; padding: 10px 14px; border-radius: 6px; text-decoration: none; font-weight: 700; }
        .notice { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; background: #ecfdf5; border: 1px solid #a7f3d0; }
        .error { background: #fef2f2; border-color: #fecaca; }
        table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e5e7eb; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-size: 13px; text-transform: uppercase; color: #6b7280; }
        .empty { background: white; padding: 32px; border: 1px solid #e5e7eb; border-radius: 6px; }
        .domain-list { margin: 0; padding-left: 18px; }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Tenants</h1>
            <p>Central SaaS tenant management.</p>
        </div>
        <a class="button" href="{{ route('central.tenants.create') }}">Create Tenant</a>
    </header>

    @if (session('success'))
        <div class="notice">
            {{ session('success') }}
            @if (session('tenant_url'))
                <a href="{{ session('tenant_url') }}">{{ session('tenant_url') }}</a>
            @endif
        </div>
    @endif

    @if (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    @if ($tenants->isEmpty())
        <div class="empty">No tenants yet. Create your first tenant to provision its ERP database.</div>
    @else
        <table>
            <thead>
            <tr>
                <th>Tenant ID</th>
                <th>Domains</th>
                <th>Created</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($tenants as $tenant)
                <tr>
                    <td>{{ $tenant->id }}</td>
                    <td>
                        <ul class="domain-list">
                            @foreach ($tenant->domains as $domain)
                                <li>{{ $domain->domain }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>{{ optional($tenant->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</main>
</body>
</html>
