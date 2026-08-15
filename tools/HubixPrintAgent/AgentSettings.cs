using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace HubixPrintAgent;

internal sealed class AgentSettings
{
    public string ServerUrl { get; set; } = "http://localhost";
    public string AgentName { get; set; } = Environment.MachineName;
    public string? AgentId { get; set; }
    public bool StartWithWindows { get; set; } = true;
    public string? Token { get; set; }
    public bool IsPaired => !string.IsNullOrWhiteSpace(Token);
}

internal static class SettingsStore
{
    private static readonly string DirectoryPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Hubix", "PrintAgent");
    private static readonly string SettingsPath = Path.Combine(DirectoryPath, "settings.json");
    private static readonly byte[] Entropy = Encoding.UTF8.GetBytes("HubixERP.LocalPrintAgent.v1");

    public static AgentSettings Load()
    {
        try
        {
            if (!File.Exists(SettingsPath)) return new AgentSettings();
            var persisted = JsonSerializer.Deserialize<PersistedSettings>(File.ReadAllText(SettingsPath)) ?? new PersistedSettings();
            var settings = new AgentSettings
            {
                ServerUrl = persisted.ServerUrl ?? "http://localhost",
                AgentName = persisted.AgentName ?? Environment.MachineName,
                AgentId = persisted.AgentId,
                StartWithWindows = persisted.StartWithWindows,
            };
            if (!string.IsNullOrWhiteSpace(persisted.ProtectedToken))
            {
                var clear = ProtectedData.Unprotect(Convert.FromBase64String(persisted.ProtectedToken), Entropy, DataProtectionScope.CurrentUser);
                settings.Token = Encoding.UTF8.GetString(clear);
            }
            return settings;
        }
        catch
        {
            return new AgentSettings();
        }
    }

    public static void Save(AgentSettings settings)
    {
        Directory.CreateDirectory(DirectoryPath);
        string? protectedToken = null;
        if (!string.IsNullOrWhiteSpace(settings.Token))
        {
            var protectedBytes = ProtectedData.Protect(Encoding.UTF8.GetBytes(settings.Token), Entropy, DataProtectionScope.CurrentUser);
            protectedToken = Convert.ToBase64String(protectedBytes);
        }
        var persisted = new PersistedSettings
        {
            ServerUrl = settings.ServerUrl.TrimEnd('/'),
            AgentName = settings.AgentName,
            AgentId = settings.AgentId,
            StartWithWindows = settings.StartWithWindows,
            ProtectedToken = protectedToken,
        };
        File.WriteAllText(SettingsPath, JsonSerializer.Serialize(persisted, new JsonSerializerOptions { WriteIndented = true }));
    }

    private sealed class PersistedSettings
    {
        public string? ServerUrl { get; set; }
        public string? AgentName { get; set; }
        public string? AgentId { get; set; }
        public bool StartWithWindows { get; set; } = true;
        public string? ProtectedToken { get; set; }
    }
}
