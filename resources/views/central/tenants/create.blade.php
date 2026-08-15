<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Tenant</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1f2937; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 20px; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        form { background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 22px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input { box-sizing: border-box; width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .field { margin-bottom: 16px; }
        .hint { color: #6b7280; font-size: 13px; margin-top: 6px; }
        .error { color: #b91c1c; font-size: 13px; margin-top: 6px; }
        .actions { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 8px; }
        button { background: #0f766e; color: white; border: 0; border-radius: 6px; padding: 11px 16px; font-weight: 700; cursor: pointer; }
        a { color: #0f766e; text-decoration: none; font-weight: 700; }
        .notice { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; background: #fef2f2; border: 1px solid #fecaca; }
        @media (max-width: 680px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <h1>Create Tenant</h1>
    <p>Provision a new isolated ERP database, company profile, and first Super Admin user.</p>

    @if (session('error'))
        <div class="notice">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('central.tenants.store') }}">
        @csrf

        <div class="grid">
            <div class="field">
                <label for="tenant_id">Tenant Slug</label>
                <input id="tenant_id" name="tenant_id" value="{{ old('tenant_id') }}" placeholder="demo-company" required>
                <div class="hint">Lowercase letters, numbers, and hyphens.</div>
                @error('tenant_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="domain">Tenant Domain</label>
                <input id="domain" name="domain" value="{{ old('domain') }}" placeholder="demo-company.{{ $defaultDomain }}">
                <div class="hint">Leave blank to use slug.{{ $defaultDomain }}.</div>
                @error('domain') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field">
            <label for="company">Company Name</label>
            <input id="company" name="company" value="{{ old('company') }}" required>
            @error('company') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="grid">
            <div class="field">
                <label for="owner_name">Owner Name</label>
                <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                @error('owner_name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="email">Owner Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" value="{{ old('phone') }}">
            @error('phone') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="grid">
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('central.tenants.index') }}">Back to tenants</a>
            <button type="submit">Create Tenant</button>
        </div>
    </form>
</main>
</body>
</html>
