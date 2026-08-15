<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hubix Print Agent Test</title>
    @include('partials.print-settings-style', ['printSetting' => $printSetting])
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .test-page { max-width: 720px; margin: 35mm auto; text-align: center; }
        .check { font-size: 64px; color: #198754; }
        table { margin: 20px auto; border-collapse: collapse; text-align: left; }
        td { border: 1px solid #999; padding: 8px 12px; }
    </style>
</head>
<body>
    <main class="test-page">
        <div class="check">&#10003;</div>
        <h1>Hubix Local Print Agent</h1>
        <p>The secure local printing connection is working.</p>
        <table>
            <tr><td>Agent</td><td>{{ $agent->name }}</td></tr>
            <tr><td>Computer</td><td>{{ $agent->machine_name ?: 'Unknown' }}</td></tr>
            <tr><td>Job</td><td>{{ $job->uuid }}</td></tr>
            <tr><td>Time</td><td>{{ now()->format('Y-m-d H:i:s') }}</td></tr>
        </table>
    </main>
</body>
</html>
