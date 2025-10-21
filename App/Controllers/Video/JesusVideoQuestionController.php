<?php

namespace App\Controllers\Video;

use App\Configuration\Config;

class JesusVideoQuestionController
{
    private $template;

    public function __construct()
    {
        $this->template = null;
    }

    public function getBilingualTemplate($languageCodeHL1, $languageCodeHL2)
    {
        $template = file_get_contents(Config::getDir('resources.templates') . 'bilingualJesusVideoQuestions.twig');
    }
}
