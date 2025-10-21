<?php
namespace App\Renderers;

class HtmlRenderer implements RendererInterface {
    public function render(string $content): string {
        return "<html><body>{$content}</body></html>";
    }
}
