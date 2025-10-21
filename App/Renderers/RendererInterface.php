<?php

namespace App\Renderers;

interface RendererInterface {
    public function render(string $content): string;
}
