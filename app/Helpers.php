<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

if (!function_exists('menuActive')) {
    function menuActive(array $routes, $output = 'menu-open')
    {
        return in_array(Request::route()->getName(), $routes) ? $output : '';
    }
}
if (!function_exists('generateBarcodeImage')) {
    function generateBarcodeImage($barcodeText, &$validatedData)
    {
        $generator = new BarcodeGeneratorPNG();
        $barcodeData = $generator->getBarcode($barcodeText, $generator::TYPE_CODE_128);

        $barcodeImage = imagecreatefromstring($barcodeData);
        $barcodeWidth = imagesx($barcodeImage);
        $barcodeHeight = imagesy($barcodeImage);

        $fontPath = public_path('fonts/Roboto-Bold.ttf'); // Make sure this exists
        $fontSize = 14;
        $textBox = imagettfbbox($fontSize, 0, $fontPath, $barcodeText);
        $textWidth = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);

        $padding = 10;
        $newWidth = max($barcodeWidth, $textWidth) + $padding * 2;
        $newHeight = $barcodeHeight + $textHeight + $padding * 3;

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($newImage, 255, 255, 255);
        $black = imagecolorallocate($newImage, 0, 0, 0);
        imagefill($newImage, 0, 0, $white);

        $barcodeX = ($newWidth - $barcodeWidth) / 2;
        imagecopy($newImage, $barcodeImage, $barcodeX, $padding, 0, 0, $barcodeWidth, $barcodeHeight);

        $textX = ($newWidth - $textWidth) / 2;
        $textY = $barcodeHeight + $padding * 2 + $textHeight;
        imagettftext($newImage, $fontSize, 0, $textX, $textY, $black, $fontPath, $barcodeText);

        ob_start();
        imagepng($newImage);
        $imageData = ob_get_clean();
        $barcodeFilename = 'barcode_' . Str::uuid() . '.png';
        Storage::disk('public')->put("product_logos/barcode_logos/{$barcodeFilename}", $imageData);

        imagedestroy($barcodeImage);
        imagedestroy($newImage);

        $validatedData['bcode_image'] = $barcodeFilename;
        $validatedData['bar_code'] = $barcodeText;
    }
}

if (!function_exists('generateQrCodeImage')) {
    function generateQrCodeImage(string $qrText, array &$validatedData): void
    {
        $qrFilename = 'qr_' . Str::uuid() . '.png';
        $qrImage = QrCode::format('png')->size(200)->generate($qrText);

        Storage::disk('public')->put("product_logos/qrcode_logos/{$qrFilename}", $qrImage);

        $validatedData['qrcode_image'] = $qrFilename;
    }
}

if (!function_exists('numberToWords')) {
    function numberToWords($number)
    {
        if ($number == 0) return "Zero";

        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        ];

        $digits = ["", "Hundred", "Thousand", "Lakh", "Crore"];

        $result = "";
        $place = 0;

        while ($number > 0) {
            if ($place == 1) {
                // handle last two digits before Hundred
                $chunk = $number % 10;
                $number = floor($number / 10);
            } else {
                $chunk = $number % 100;
                $number = floor($number / 100);
            }

            if ($chunk > 0) {
                if ($chunk < 20) {
                    $chunkWords = $words[$chunk];
                } else {
                    $tens = floor($chunk / 10) * 10;
                    $ones = $chunk % 10;
                    $chunkWords = $words[$tens] . ($ones ? " " . $words[$ones] : "");
                }

                $result = $chunkWords . " " . $digits[$place] . " " . $result;
            }

            $place++;
        }

        return trim($result) . " Only";
    }
}