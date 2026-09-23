# Hubix Local Print Agent

Windows tray agent for silent, printer-specific HubixERP document printing. It uses the installed Microsoft Edge WebView2 Evergreen Runtime and never exposes a general-purpose localhost print endpoint.

## Pairing

1. In HubixERP, open **Company Settings → Print Settings**.
2. Create a pairing code for the billing computer.
3. Extract the entire Windows ZIP, run `HubixPrintAgent.exe`, enter the HubixERP base URL and the one-time code, then click **Pair Agent**. Keep the extracted files together.
4. Wait for the agent to report its installed printers, map each document type, and use **Print Test**.
5. Open the paired Windows agent, click **Generate Browser Link Code**, enter that one-time code beside the same computer in HubixERP, and click **Use on this browser**.
6. Change the required document profiles, including **Barcode / QR Labels**, from **Browser Print** to **Hubix Local Print Agent**.

When **Auto Print** is enabled, opening a print document sends it directly to the mapped printer. Disable **Auto Print** to review the document first and send it only after clicking **Print with Hubix**.

Barcode and QR jobs use the label-roll format and dimensions configured in **Company Settings → Barcode & QR Settings**. The existing custom label-printer layout uses one complete row: `(sticker width × labels across) + (column gap × (labels across − 1))`, by the sticker height. Do not add the feed gap between rows to the page height. The common sticker-roll layout sends one centred sticker per printer page, using its configured width and height. Labels are printed at 100% scale.

Use agent version 1.0.5 or later for labels; it explicitly selects custom media size in WebView2. Configure the same complete-row paper size in the printer driver and select gap/web media for die-cut rolls. Measure the actual stickers before changing these settings. Browser printing also needs 100% scale, zero margins, and no headers or footers.

Deploy the migrations `2026_09_16_000001_add_barcode_label_columns.php` and `2026_09_20_000001_add_common_barcode_label_layout.php` before saving barcode label settings. Existing configurations keep the custom layout and one label across until explicitly updated.

The server controls the idle polling and heartbeat intervals returned during pairing. The agent immediately checks for another job after each completed print so queued documents are not held behind an additional polling delay.

The device token is stored with Windows DPAPI for the current user. The agent must run in that same Windows user session so it can access that user's printers and WebView2 runtime.

The browser link code expires after 10 minutes and can be used once. A successful local print renews the browser-to-computer registration. Application logout does not remove this registration.

## Build

```powershell
dotnet publish .\tools\HubixPrintAgent\HubixPrintAgent.csproj -c Release -r win-x64 --self-contained true
```

The publish folder is under `tools\HubixPrintAgent\bin\Release\net8.0-windows\win-x64\publish`. Distribute that folder as a ZIP because WebView2 includes a small native loader beside the executable.
