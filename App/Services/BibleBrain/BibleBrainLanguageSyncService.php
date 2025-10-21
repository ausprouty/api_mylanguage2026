<?php
// App/Services/BibleBrain/BibleBrainLanguageSyncService.php
namespace App\Services\BibleBrain;

use App\Repositories\LanguageRepository;
use App\Services\Web\BibleBrainConnectionService;
use App\Services\LoggerService;
use DateInterval;
use DateTimeImmutable;

final class BibleBrainLanguageSyncService
{
    public function __construct(
        private LanguageRepository $langRepo,
        private BibleBrainConnectionService $bb,
        private LoggerService $log
    ) {}

    /** @return int number of languages checked */
    public function syncOncePerMonth(): int
    {
        $dueLanguages = $this->langRepo->findDueForBibleBrainCheck(
            olderThan: (new DateTimeImmutable())->sub(new DateInterval('P6M'))
        );

        $checked = 0;
        foreach ($dueLanguages as $lang) {
            try {
                $bbData = $this->bb->fetchLanguagesForIsoOrHl(
                    $lang->languageCodeIso,
                    $lang->languageCodeHL
                );
                // Map/merge/update local language rows as needed...
                $this->langRepo->markCheckedBBBibles($lang->id, new DateTimeImmutable());
                $checked++;
            } catch (\Throwable $e) {
                $this->log->error('Language sync failed', [
                    'lang' => $lang->languageCodeHL,
                    'err'  => $e->getMessage(),
                ]);
                // Decide: mark attempted vs leave unchanged
            }
        }
        return $checked;
    }
}
