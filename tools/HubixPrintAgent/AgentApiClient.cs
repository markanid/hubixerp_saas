using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;

namespace HubixPrintAgent;

internal sealed class AgentApiClient : IDisposable
{
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(30) };
    private readonly JsonSerializerOptions _json = new(JsonSerializerDefaults.Web) { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower };
    private AgentSettings _settings;

    public AgentApiClient(AgentSettings settings) => _settings = settings;

    public void UpdateSettings(AgentSettings settings) => _settings = settings;

    public async Task<PairResponse> PairAsync(string code, CancellationToken cancellationToken)
    {
        var payload = new
        {
            pairing_code = code.Trim().ToUpperInvariant(),
            machine_name = Environment.MachineName,
            version = AppVersion,
        };

        using var initialResponse = await SendPairAsync(_settings.ServerUrl, payload, cancellationToken);
        if (initialResponse.StatusCode != System.Net.HttpStatusCode.NotFound || !IsLocalServer(_settings.ServerUrl))
        {
            await EnsureSuccessAsync(initialResponse, cancellationToken, _settings.ServerUrl);
            return (await initialResponse.Content.ReadFromJsonAsync<PairResponse>(_json, cancellationToken))!;
        }

        foreach (var fallbackUrl in LocalFallbackUrls(_settings.ServerUrl))
        {
            using var fallbackResponse = await SendPairAsync(fallbackUrl, payload, cancellationToken);
            if (fallbackResponse.IsSuccessStatusCode)
            {
                _settings.ServerUrl = fallbackUrl;
                return (await fallbackResponse.Content.ReadFromJsonAsync<PairResponse>(_json, cancellationToken))!;
            }

            if (fallbackResponse.StatusCode != System.Net.HttpStatusCode.NotFound)
            {
                await EnsureSuccessAsync(fallbackResponse, cancellationToken, fallbackUrl);
            }
        }

        throw new HttpRequestException(
            $"HubixERP Print Agent API was not found at {_settings.ServerUrl}. Use the exact browser address, including its port (for example http://localhost:8000).",
            null,
            System.Net.HttpStatusCode.NotFound);
    }

    public async Task HeartbeatAsync(string[] printers, string? defaultPrinter, string? lastError, CancellationToken cancellationToken)
    {
        using var request = Authorized(HttpMethod.Post, "heartbeat");
        request.Content = JsonContent.Create(new
        {
            machine_name = Environment.MachineName,
            version = AppVersion,
            printers,
            default_printer = defaultPrinter,
            last_error = lastError,
        }, options: _json);
        using var response = await _http.SendAsync(request, cancellationToken);
        await EnsureSuccessAsync(response, cancellationToken, _settings.ServerUrl);
    }

    public async Task<PrintJob?> NextJobAsync(CancellationToken cancellationToken)
    {
        using var request = Authorized(HttpMethod.Get, "jobs/next");
        using var response = await _http.SendAsync(request, cancellationToken);
        if (response.StatusCode == System.Net.HttpStatusCode.NoContent) return null;
        await EnsureSuccessAsync(response, cancellationToken, _settings.ServerUrl);
        return await response.Content.ReadFromJsonAsync<PrintJob>(_json, cancellationToken);
    }

    public async Task UpdateJobStatusAsync(PrintJob job, string status, string? error, CancellationToken cancellationToken)
    {
        using var request = Authorized(HttpMethod.Post, $"jobs/{job.JobId}/status");
        request.Headers.Add("X-Print-Claim", job.ClaimToken);
        request.Content = JsonContent.Create(new { status, error }, options: _json);
        using var response = await _http.SendAsync(request, cancellationToken);
        await EnsureSuccessAsync(response, cancellationToken, _settings.ServerUrl);
    }

    private HttpRequestMessage Authorized(HttpMethod method, string path)
    {
        var request = new HttpRequestMessage(method, ApiUrl(path));
        request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", _settings.Token);
        request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        return request;
    }

    private string ApiUrl(string path) => $"{_settings.ServerUrl.TrimEnd('/')}/api/print-agent/{path.TrimStart('/')}";

    private Task<HttpResponseMessage> SendPairAsync(string serverUrl, object payload, CancellationToken cancellationToken) =>
        _http.PostAsJsonAsync($"{serverUrl.TrimEnd('/')}/api/print-agent/pair", payload, _json, cancellationToken);

    private static bool IsLocalServer(string serverUrl) =>
        Uri.TryCreate(serverUrl, UriKind.Absolute, out var uri)
        && (uri.Host.Equals("localhost", StringComparison.OrdinalIgnoreCase) || uri.IsLoopback);

    private static IEnumerable<string> LocalFallbackUrls(string serverUrl)
    {
        var uri = new Uri(serverUrl);
        foreach (var port in new[] { 8000, 80 })
        {
            if (uri.Port == port) continue;
            var builder = new UriBuilder(uri) { Port = port };
            yield return builder.Uri.ToString().TrimEnd('/');
        }
    }

    private static async Task EnsureSuccessAsync(HttpResponseMessage response, CancellationToken cancellationToken, string serverUrl)
    {
        if (response.IsSuccessStatusCode) return;
        var message = response.StatusCode == System.Net.HttpStatusCode.NotFound
            ? $"HubixERP Print Agent API was not found at {serverUrl}. Check the URL and port shown in your browser."
            : $"Server returned {(int)response.StatusCode}.";
        try
        {
            var error = await response.Content.ReadFromJsonAsync<ApiError>(cancellationToken: cancellationToken);
            if (!string.IsNullOrWhiteSpace(error?.Message)) message = error.Message;
        }
        catch { }
        throw new HttpRequestException(message, null, response.StatusCode);
    }

    public void Dispose() => _http.Dispose();

    public static string AppVersion => typeof(AgentApiClient).Assembly.GetName().Version?.ToString(3) ?? "1.0.0";
}

internal sealed record PairResponse(string AgentId, string AgentName, string Token, int PollSeconds, int HeartbeatSeconds);
internal sealed record ApiError(string Message);
internal sealed record PrintJob(string JobId, string ClaimToken, string RenderUrl, string PrinterName, int Copies, PrintJobSettings Settings);
internal sealed record PrintJobSettings(string PaperSize, string Orientation, int Scale, int MarginMm);
