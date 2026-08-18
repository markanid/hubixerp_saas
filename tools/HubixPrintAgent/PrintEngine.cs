using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace HubixPrintAgent;

internal sealed class PrintEngine
{
    private readonly WebView2 _webView;
    private readonly SemaphoreSlim _printLock = new(1, 1);

    public PrintEngine(WebView2 webView) => _webView = webView;

    public async Task InitializeAsync(CancellationToken cancellationToken)
    {
        await _webView.EnsureCoreWebView2Async().WaitAsync(TimeSpan.FromSeconds(30), cancellationToken);
        var settings = _webView.CoreWebView2.Settings;
        settings.AreDevToolsEnabled = false;
        settings.AreDefaultContextMenusEnabled = false;
    }

    public async Task PrintAsync(PrintJob job, string agentToken, CancellationToken cancellationToken)
    {
        await _printLock.WaitAsync(cancellationToken);
        try
        {
            await InitializeAsync(cancellationToken);
            var core = _webView.CoreWebView2;

            var navigation = new TaskCompletionSource(TaskCreationOptions.RunContinuationsAsynchronously);
            void NavigationCompleted(object? sender, CoreWebView2NavigationCompletedEventArgs args)
            {
                if (args.IsSuccess) navigation.TrySetResult();
                else navigation.TrySetException(new InvalidOperationException($"Invoice rendering failed: {args.WebErrorStatus}."));
            }

            core.NavigationCompleted += NavigationCompleted;
            try
            {
                var request = core.Environment.CreateWebResourceRequest(
                    job.RenderUrl,
                    "GET",
                    Stream.Null,
                    $"Authorization: Bearer {agentToken}\r\nX-Print-Claim: {job.ClaimToken}\r\n");
                core.NavigateWithWebResourceRequest(request);
                await navigation.Task.WaitAsync(TimeSpan.FromSeconds(30), cancellationToken);
            }
            finally
            {
                core.NavigationCompleted -= NavigationCompleted;
            }

            const string readinessScript = """
                (async () => {
                    const timeout = new Promise(resolve => window.setTimeout(resolve, 5000));
                    const fonts = document.fonts && document.fonts.ready
                        ? document.fonts.ready.catch(() => undefined)
                        : Promise.resolve();
                    const images = Promise.all(Array.from(document.images).map(image => image.complete
                        ? Promise.resolve()
                        : new Promise(resolve => {
                            image.addEventListener('load', resolve, { once: true });
                            image.addEventListener('error', resolve, { once: true });
                        })));
                    await Promise.race([Promise.all([fonts, images]), timeout]);
                    return true;
                })()
                """;
            await core.ExecuteScriptAsync(readinessScript).WaitAsync(TimeSpan.FromSeconds(7), cancellationToken);

            var settings = core.Environment.CreatePrintSettings();
            settings.PrinterName = job.PrinterName;
            settings.Orientation = string.Equals(job.Settings.Orientation, "landscape", StringComparison.OrdinalIgnoreCase)
                ? CoreWebView2PrintOrientation.Landscape
                : CoreWebView2PrintOrientation.Portrait;
            settings.ScaleFactor = Math.Clamp(job.Settings.Scale / 100d, 0.5d, 1.5d);
            settings.MarginTop = settings.MarginBottom = settings.MarginLeft = settings.MarginRight = Math.Max(0, job.Settings.MarginMm) / 25.4d;
            settings.ShouldPrintBackgrounds = true;
            settings.ShouldPrintHeaderAndFooter = false;
            settings.Copies = Math.Clamp(job.Copies, 1, 20);

            if (string.Equals(job.Settings.PaperSize, "A5", StringComparison.OrdinalIgnoreCase))
            {
                settings.PageWidth = 5.83;
                settings.PageHeight = 8.27;
            }
            else
            {
                settings.PageWidth = 8.27;
                settings.PageHeight = 11.69;
            }

            var result = await core.PrintAsync(settings);
            if (result != CoreWebView2PrintStatus.Succeeded)
            {
                throw new InvalidOperationException($"Windows printing failed: {result}.");
            }
        }
        finally
        {
            _printLock.Release();
        }
    }
}
