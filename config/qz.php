<?php

return [
    'script_url' => env('QZ_TRAY_SCRIPT_URL', 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.6/qz-tray.js'),
    'certificate_path' => env('QZ_TRAY_CERTIFICATE_PATH', storage_path('app/qz/digital-certificate.txt')),
    'private_key_path' => env('QZ_TRAY_PRIVATE_KEY_PATH', storage_path('app/qz/private-key.pem')),
    'private_key_passphrase' => env('QZ_TRAY_PRIVATE_KEY_PASSPHRASE'),
    'signature_algorithm' => env('QZ_TRAY_SIGNATURE_ALGORITHM', 'SHA512'),
];
