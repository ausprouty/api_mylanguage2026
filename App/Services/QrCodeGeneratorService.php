<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use App\Configuration\Config;

class QrCodeGeneratorService
{
    private string $url;
    private int $size;
    private string $filePath;
    private string $qrCodeUrl;

    public function confirmCodeExists(string $url, string $fileName, int $size): void
    {
        $this->url = $url;
        $this->size = $size;
        $this->filePath = Config::getDir('resources.qr_codes')  . $fileName;
        $this->qrCodeUrl = Config::getURL('resources.qr_codes')  . $fileName;
        if (!file_exists($this->filePath)){
            print_r('gernating QrCode');
            $this->generateQrCode();
        }
        return;
    }

    public function generateQrCode(): void
    {
        $qrCode = new QrCode($this->url);
        $qrCode->setSize($this->size);
        $qrCode->writeFile($this->filePath);
    }

    public function getQrCodeUrl(): string
    {
        return $this->qrCodeUrl;
    }
}
