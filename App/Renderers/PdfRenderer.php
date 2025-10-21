<?php

namespace App\Renderers;

class PdfRenderer implements RendererInterface {
    public function render(string $content): string {
        // Assume PdfGenerator is a third-party library or utility you’ve included
        //return PdfGenerator::create($content)->addQrCode()->output();
        return 'pdf';
    }
}
