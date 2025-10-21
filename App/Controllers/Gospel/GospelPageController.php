<?php

namespace App\Controllers\Gospel;

use App\Configuration\Config;

class GospelPageController
{

    public function getBilingualPage($page)
    {
        $file = Config::getDir('resources.tracts') . 'bilingualTracts/' . $page;
        $text = file_get_contents($file);
        return $text;
    }
}
