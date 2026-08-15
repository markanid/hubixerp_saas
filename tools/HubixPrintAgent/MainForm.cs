using Microsoft.Web.WebView2.WinForms;
using Microsoft.Win32;

namespace HubixPrintAgent;

internal sealed class MainForm : Form
{
    private readonly TextBox _serverUrl = new();
    private readonly TextBox _agentName = new();
    private readonly TextBox _pairingCode = new();
    private readonly CheckBox _startWithWindows = new();
    private readonly Label _status = new();
    private readonly ListBox _printers = new();
    private readonly Button _pairButton = new();
    private readonly Button _saveButton = new();
    private readonly NotifyIcon _tray = new();
    private readonly WebView2 _webView = new();
    private AgentSettings _settings;
    private readonly AgentApiClient _api;
    private readonly PrintEngine _printer;
    private CancellationTokenSource? _runnerCancellation;
    private bool _exitRequested;
    private string? _lastError;

    public MainForm()
    {
        _settings = SettingsStore.Load();
        _api = new AgentApiClient(_settings);
        _printer = new PrintEngine(_webView);

        Text = "Hubix Local Print Agent";
        Width = 640;
        Height = 600;
        MinimumSize = new Size(560, 500);
        StartPosition = FormStartPosition.CenterScreen;
        Icon = SystemIcons.Application;
        BuildUi();
        LoadSettingsIntoUi();
        ConfigureTray();

        Shown += (_, _) =>
        {
            RefreshPrinters();
            if (_settings.IsPaired)
            {
                StartRunner();
                Hide();
                _tray.ShowBalloonTip(2500, "Hubix Print Agent", "Running securely in the notification area.", ToolTipIcon.Info);
            }
            else
            {
                SetStatus("Not paired. Create a code in HubixERP Company Settings, then enter it here.", true);
            }
        };
        FormClosing += OnFormClosing;
    }

    private void BuildUi()
    {
        var root = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(18), ColumnCount = 1, RowCount = 9 };
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));

        var title = new Label { Text = "Hubix Local Print Agent", Font = new Font(Font.FontFamily, 16, FontStyle.Bold), AutoSize = true, Margin = new Padding(0, 0, 0, 12) };
        root.Controls.Add(title);
        root.Controls.Add(Field("HubixERP server URL", _serverUrl));
        root.Controls.Add(Field("Agent name", _agentName));
        root.Controls.Add(Field("One-time pairing code", _pairingCode));

        _pairingCode.CharacterCasing = CharacterCasing.Upper;
        _pairingCode.MaxLength = 20;
        _startWithWindows.Text = "Start automatically when I sign in to Windows";
        _startWithWindows.AutoSize = true;
        _startWithWindows.Margin = new Padding(0, 8, 0, 8);
        root.Controls.Add(_startWithWindows);

        var printerLabel = new Label { Text = "Printers reported to HubixERP", AutoSize = true, Font = new Font(Font, FontStyle.Bold), Margin = new Padding(0, 6, 0, 4) };
        root.Controls.Add(printerLabel);
        _printers.Dock = DockStyle.Fill;
        root.Controls.Add(_printers);

        var actions = new FlowLayoutPanel { AutoSize = true, Dock = DockStyle.Fill, FlowDirection = FlowDirection.LeftToRight, Margin = new Padding(0, 12, 0, 8) };
        _pairButton.Text = "Pair Agent";
        _pairButton.AutoSize = true;
        _pairButton.Click += PairClicked;
        _saveButton.Text = "Save Settings";
        _saveButton.AutoSize = true;
        _saveButton.Click += SaveClicked;
        var refresh = new Button { Text = "Refresh Printers", AutoSize = true };
        refresh.Click += (_, _) => RefreshPrinters();
        actions.Controls.Add(_pairButton);
        actions.Controls.Add(_saveButton);
        actions.Controls.Add(refresh);
        root.Controls.Add(actions);

        _status.AutoSize = true;
        _status.MaximumSize = new Size(570, 0);
        root.Controls.Add(_status);

        _webView.Size = new Size(2, 2);
        _webView.Location = new Point(-100, -100);
        Controls.Add(_webView);
        Controls.Add(root);
    }

    private static Control Field(string label, TextBox textBox)
    {
        var panel = new TableLayoutPanel { Dock = DockStyle.Top, AutoSize = true, ColumnCount = 1, Margin = new Padding(0, 0, 0, 8) };
        panel.Controls.Add(new Label { Text = label, AutoSize = true, Font = new Font(SystemFonts.DefaultFont, FontStyle.Bold) });
        textBox.Dock = DockStyle.Top;
        panel.Controls.Add(textBox);
        return panel;
    }

    private void ConfigureTray()
    {
        var menu = new ContextMenuStrip();
        menu.Items.Add("Open", null, (_, _) => ShowWindow());
        menu.Items.Add("Exit", null, (_, _) => ExitAgent());
        _tray.Icon = SystemIcons.Application;
        _tray.Text = "Hubix Local Print Agent";
        _tray.Visible = true;
        _tray.ContextMenuStrip = menu;
        _tray.DoubleClick += (_, _) => ShowWindow();
    }

    private void LoadSettingsIntoUi()
    {
        _serverUrl.Text = _settings.ServerUrl;
        _agentName.Text = _settings.AgentName;
        _startWithWindows.Checked = _settings.StartWithWindows;
        _pairButton.Text = _settings.IsPaired ? "Pair Again" : "Pair Agent";
    }

    private async void PairClicked(object? sender, EventArgs e)
    {
        if (!TryReadUi(out var updated) || string.IsNullOrWhiteSpace(_pairingCode.Text))
        {
            MessageBox.Show("Enter the server URL, agent name, and one-time pairing code.", Text, MessageBoxButtons.OK, MessageBoxIcon.Warning);
            return;
        }

        SetBusy(true);
        try
        {
            _settings = updated;
            _api.UpdateSettings(_settings);
            var paired = await _api.PairAsync(_pairingCode.Text, CancellationToken.None);
            _settings.AgentId = paired.AgentId;
            _settings.AgentName = paired.AgentName;
            _settings.Token = paired.Token;
            SettingsStore.Save(_settings);
            ApplyStartupSetting(_settings.StartWithWindows);
            _api.UpdateSettings(_settings);
            _agentName.Text = _settings.AgentName;
            _serverUrl.Text = _settings.ServerUrl;
            _pairingCode.Clear();
            _pairButton.Text = "Pair Again";
            SetStatus($"Paired as {_settings.AgentName}. Waiting for print jobs.", false);
            StartRunner();
        }
        catch (Exception ex)
        {
            SetStatus(ex.Message, true);
        }
        finally
        {
            SetBusy(false);
        }
    }

    private void SaveClicked(object? sender, EventArgs e)
    {
        if (!TryReadUi(out var updated)) return;
        updated.Token = _settings.Token;
        updated.AgentId = _settings.AgentId;
        _settings = updated;
        SettingsStore.Save(_settings);
        ApplyStartupSetting(_settings.StartWithWindows);
        _api.UpdateSettings(_settings);
        SetStatus("Settings saved.", false);
        if (_settings.IsPaired) StartRunner();
    }

    private bool TryReadUi(out AgentSettings settings)
    {
        settings = new AgentSettings();
        if (!Uri.TryCreate(_serverUrl.Text.Trim(), UriKind.Absolute, out var uri) || (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps))
        {
            MessageBox.Show("Enter a valid HubixERP URL beginning with http:// or https://.", Text, MessageBoxButtons.OK, MessageBoxIcon.Warning);
            return false;
        }
        settings.ServerUrl = uri.ToString().TrimEnd('/');
        settings.AgentName = string.IsNullOrWhiteSpace(_agentName.Text) ? Environment.MachineName : _agentName.Text.Trim();
        settings.StartWithWindows = _startWithWindows.Checked;
        return true;
    }

    private void StartRunner()
    {
        _runnerCancellation?.Cancel();
        _runnerCancellation?.Dispose();
        _runnerCancellation = new CancellationTokenSource();
        _ = RunAsync(_runnerCancellation.Token);
    }

    private async Task RunAsync(CancellationToken cancellationToken)
    {
        var nextHeartbeat = DateTimeOffset.MinValue;
        while (!cancellationToken.IsCancellationRequested && _settings.IsPaired)
        {
            try
            {
                if (DateTimeOffset.UtcNow >= nextHeartbeat)
                {
                    await _api.HeartbeatAsync(PrinterCatalog.Installed(), PrinterCatalog.Default(), _lastError, cancellationToken);
                    nextHeartbeat = DateTimeOffset.UtcNow.AddSeconds(30);
                    _lastError = null;
                    SetStatus("Online. Waiting for print jobs.", false);
                }

                var job = await _api.NextJobAsync(cancellationToken);
                if (job is not null) await ProcessJobAsync(job, cancellationToken);
            }
            catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested) { break; }
            catch (Exception ex)
            {
                _lastError = ex.Message;
                SetStatus($"Offline/error: {ex.Message}", true);
            }

            try { await Task.Delay(TimeSpan.FromSeconds(3), cancellationToken); }
            catch (OperationCanceledException) { break; }
        }
    }

    private async Task ProcessJobAsync(PrintJob job, CancellationToken cancellationToken)
    {
        SetStatus($"Printing job {job.JobId} on {job.PrinterName}...", false);
        try
        {
            await _api.UpdateJobStatusAsync(job, "printing", null, cancellationToken);
            await _printer.PrintAsync(job, _settings.Token!, cancellationToken);
            await _api.UpdateJobStatusAsync(job, "printed", null, cancellationToken);
            SetStatus($"Printed job {job.JobId} successfully.", false);
        }
        catch (Exception ex)
        {
            _lastError = ex.Message;
            try { await _api.UpdateJobStatusAsync(job, "failed", ex.Message, cancellationToken); } catch { }
            SetStatus($"Print failed: {ex.Message}", true);
        }
    }

    private void RefreshPrinters()
    {
        _printers.Items.Clear();
        _printers.Items.AddRange(PrinterCatalog.Installed());
        var defaultPrinter = PrinterCatalog.Default();
        if (defaultPrinter is not null)
        {
            var index = _printers.Items.IndexOf(defaultPrinter);
            if (index >= 0) _printers.SelectedIndex = index;
        }
    }

    private static void ApplyStartupSetting(bool enabled)
    {
        using var key = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Run", writable: true);
        if (enabled) key?.SetValue("HubixPrintAgent", $"\"{Application.ExecutablePath}\" --background");
        else key?.DeleteValue("HubixPrintAgent", throwOnMissingValue: false);
    }

    private void SetBusy(bool busy)
    {
        _pairButton.Enabled = !busy;
        _saveButton.Enabled = !busy;
        UseWaitCursor = busy;
    }

    private void SetStatus(string message, bool error)
    {
        if (InvokeRequired) { BeginInvoke(() => SetStatus(message, error)); return; }
        _status.Text = message;
        _status.ForeColor = error ? Color.Firebrick : Color.DarkGreen;
        _tray.Text = message.Length > 63 ? message[..63] : message;
    }

    private void ShowWindow()
    {
        Show();
        WindowState = FormWindowState.Normal;
        Activate();
    }

    private void OnFormClosing(object? sender, FormClosingEventArgs e)
    {
        if (_exitRequested) return;
        e.Cancel = true;
        Hide();
    }

    private void ExitAgent()
    {
        _exitRequested = true;
        _runnerCancellation?.Cancel();
        _tray.Visible = false;
        Close();
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            _runnerCancellation?.Cancel();
            _runnerCancellation?.Dispose();
            _api.Dispose();
            _tray.Dispose();
            _webView.Dispose();
        }
        base.Dispose(disposing);
    }
}
