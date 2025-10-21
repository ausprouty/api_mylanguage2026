<?php
namespace App\Infra;

use App\Contracts\Templates\TemplatesRootProvider;
use App\Configuration\Config;

final class ConfigTemplatesRootProvider implements TemplatesRootProvider
{
    public function getTemplatesRoot(): string
    {
        $dir = Config::getDir('resources.templates'); // …/Resources/templates
        if (!$dir || !is_dir($dir)) {
            throw new \RuntimeException("Templates root not found: " . ($dir ?? '(null)'));
        }
        return rtrim($dir, "\\/"); // normalize trailing slash
    }
}
