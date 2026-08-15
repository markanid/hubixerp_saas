using System.Drawing.Printing;

namespace HubixPrintAgent;

internal static class PrinterCatalog
{
    public static string[] Installed() => PrinterSettings.InstalledPrinters.Cast<string>().OrderBy(name => name).ToArray();

    public static string? Default()
    {
        var settings = new PrinterSettings();
        return settings.IsValid ? settings.PrinterName : null;
    }
}
