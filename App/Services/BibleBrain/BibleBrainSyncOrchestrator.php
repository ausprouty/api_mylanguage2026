<?php

// App/Services/BibleBrain/BibleBrainSyncOrchestrator.php
namespace App\Services\BibleBrain;

use App\Services\LoggerService;

final class BibleBrainSyncOrchestrator
{
    public function __construct(
        private BibleBrainLanguageSyncService $langSync,
        private BibleBrainBibleSyncService $bibleSync,
        private LoggerService $log
    ) {}

    public function syncAllOncePerMonth(): void
    {
        $this->log->info('BibleBrain sync: starting');
        $langs = $this->langSync->syncOncePerMonth();
        $bibles = $this->bibleSync->syncOncePerMonth();
        $this->log->info('BibleBrain sync: done', ['langs' => $langs, 'bibles' => $bibles]);
    }
}
