namespace HubixPrintAgent;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        ApplicationConfiguration.Initialize();
        using var mutex = new Mutex(true, "Local\\HubixPrintAgent", out var firstInstance);
        if (!firstInstance)
        {
            MessageBox.Show("Hubix Local Print Agent is already running.", "Hubix Print Agent", MessageBoxButtons.OK, MessageBoxIcon.Information);
            return;
        }

        Application.Run(new MainForm());
    }
}
