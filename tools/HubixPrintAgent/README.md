# Hubix Local Print Agent

Windows tray agent for silent, printer-specific HubixERP document printing. It uses the installed Microsoft Edge WebView2 Evergreen Runtime and never exposes a general-purpose localhost print endpoint.

## Pairing

1. In HubixERP, open **Company Settings → Print Settings**.
2. Create a pairing code for the billing computer.
3. Extract the entire Windows ZIP, run `HubixPrintAgent.exe`, enter the HubixERP base URL and the one-time code, then click **Pair Agent**. Keep the extracted files together.
4. Wait for the agent to report its installed printers, map each document type, and use **Print Test**.
5. Change the required document profiles from **Browser Print** to **Hubix Local Print Agent**.

The device token is stored with Windows DPAPI for the current user. The agent must run in that same Windows user session so it can access that user's printers and WebView2 runtime.

## Build

```powershell
dotnet publish .\tools\HubixPrintAgent\HubixPrintAgent.csproj -c Release -r win-x64 --self-contained true
```

The publish folder is under `tools\HubixPrintAgent\bin\Release\net8.0-windows\win-x64\publish`. Distribute that folder as a ZIP because WebView2 includes a small native loader beside the executable.
