using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace HubixPrintAgent;

internal sealed class PrintEngine
{
    private readonly WebView2 _webView;
    private readonly SemaphoreSlim _printLock = new(1, 1);

    public PrintEngine(WebView2 webView) => _webView = webView;

    public async Task PrintAsync(PrintJob job, string agentToken, CancellationToken cancellationToken)
    {
        await _printLock.WaitAsync(cancellationToken);
        try
        {
            await _webView.EnsureCoreWebView2Async();
            var core = _webView.CoreWebView2;
            core.Settings.AreDevToolsEnabled = false;
            core.Settings.AreDefaultContextMenusEnabled = false;

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

            await core.ExecuteScriptAsync("(async()=>{if(document.fonts&&document.fonts.ready){await document.fonts.ready;}await Promise.all(Array.from(document.images).map(i=>i.complete?Promise.resolve():new Promise(r=>{i.onload=r;i.onerror=r;})));return true;})()");

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
