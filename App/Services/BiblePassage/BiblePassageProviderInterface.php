<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;

interface BiblePassageProviderInterface
{
    public function fetchPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel;
}
