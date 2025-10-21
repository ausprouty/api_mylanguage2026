<?php
namespace App\Contracts\Templates;

interface TemplatesRootProvider
{
    public function getTemplatesRoot(): string;
}
