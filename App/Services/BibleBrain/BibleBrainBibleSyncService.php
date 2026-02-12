<?php
declare(strict_types=1);

namespace App\Services\BibleBrain;

use App\Repositories\BibleBrainBibleRepository;
use App\Repositories\BibleBrainLanguageRepository;
use App\Services\Web\BibleBrainConnectionService;
use App\Services\LoggerService;
use DateTime;
use Throwable;

/**
 * BibleBrainBibleSyncService
 *
 * Run once per month (cron). Adds new BibleBrain *text* filesets to the local
 * `bibles` table and updates missing fields for existing records.
 *
 * Safety goals:
 * - Hard stop if already run this month (no accidental re-sync loops)
 * - No destructive "clearCheckedBBBibles" reset
 * - Handles BOTH fileset shapes seen in BibleBrain responses:
 *   A) $entry['filesets']['dbp-prod'][] with keys: id/type/size/(volume?)
 *   B) $entry['filesets'][] flat array with keys: id/set_type_code/set_size_code/meta[]
 */
class BibleBrainBibleSyncService
{
    private BibleBrainBibleRepository $repository;
    private BibleBrainLanguageRepository $languageRepository;
    private string $logFile;

    public function __construct(
        BibleBrainBibleRepository $repository,
        BibleBrainLanguageRepository $languageRepository
    ) {
        $this->repository = $repository;
        $this->languageRepository = $languageRepository;
        $root = dirname(__DIR__, 3); 
        // App/Services/BibleBrain -> App/Services -> App -> (root is one more) 
        // Depending on your tree you may want dirname(__DIR__, 4)
        $this->logFile = $root  . '/data/cron/last_biblebrain_bible_sync.txt';
    }

    /**
     * Runs the sync if it has not already run this calendar month.
     */
    public function syncOncePerMonth(): void
    {
        $startedAt = microtime(true);
        LoggerService::logInfo('[BibleBrainSync-001]', 'Sync start.');
        if ($this->hasRunThisMonth()) {
            LoggerService::logInfo(
                '[BibleBrainSync-002]',
                'Sync already performed this month. Skipping. logFile={$this->logFile}"
             );'
            );
            return;
        }
        LoggerService::logInfo(
            '[BibleBrainSync-020]',
            'Going to Sync New Bibles"
            );'
        );

        $this->syncNewBibles();
        LoggerService::logInfo(
            '[BibleBrainSync-067]',
            'Going to Update Last Run Timestamp"
            );'
        );
        $this->updateLastRunTimestamp();

        LoggerService::logInfo(
             '[BibleBrainSync-003]',
            'Sync completed and timestamp updated. duration_ms='
                . (string) (int) ((microtime(true) - $startedAt) * 1000)
        );
    }

    /**
     * Syncs new BibleBrain text filesets into the local DB.
     */
    private function syncNewBibles(): void
    {
        $addedCount = 0;
        $languagesProcessed = 0;
        $maxLanguages = 1;
        while ($language = $this->languageRepository
            ->getNextLanguageForBibleBrainSync()
        ) {
            $iso = (string) ($language['languageCodeIso'] ?? '');
            //TODO: remove this line
            $iso = 'en';
            if ($iso === '') {
                  LoggerService::logInfo(
                    'BibleBrainSync-010',
                    'Skipping language row with empty ISO.'
                );
                continue;
            }
            
            $languagesProcessed++;
            if ($languagesProcessed > $maxLanguages) {
                LoggerService::logInfo(
                    'BibleBrainSync-009',
                    "Stopping as directed after {$maxLanguages} language(s)."
                );
                break;
            }

            $hl = (string) ($language['languageCodeHL'] ?? '');
            $bbid = (string) ($language['languageCodeBibleBrain'] ?? '');
            // BibleBrain generally uses ISO in lowercase.
            $isoLower = strtolower($iso);

            $endpoint = '/api/bibles';
                $query = [
                'media_exclude' => 'audio_drama',
                'language_code' => $isoLower,
            ];

            try {
                LoggerService::logInfo(
                    'BibleBrainSync-011',
                    "Next language: HL={$hl} ISO={$iso} BBID={$bbid}"
                );
                LoggerService::logInfo(
                    'BibleBrainSync-012',
                    "Fetching endpoint={$endpoint}"
                );
                $t0 = microtime(true);
                try{
                    $connection = new BibleBrainConnectionService($endpoint, $query);
                }catch (\Exception $e){
                     // If your WebsiteConnectionService message looks like: "HTTP 404 from ..."
                    if (strpos($e->getMessage(), 'HTTP 404') === 0) {
                        throw new \RuntimeException(
                            "Hard stop: BibleBrain returned 404 for {$endpoint}",
                            0,
                            $e
                        );
                    }
                    // otherwise continue / handle as you already do
                    throw $e;
                }
                LoggerService::logInfo(
                    'BibleBrainSync-012b',[
                        'connectionResponse'=>$connection->getJson()
                    ]
                );
                $json = $connection->getJson() ?? [];
                $entries = $json['data'] ?? [];
                $ms = (int) ((microtime(true) - $t0) * 1000);

                if (!is_array($entries)) {
                    $entries = [];
                }
               LoggerService::logInfo(
                    'BibleBrainSync-013',
                    "Fetched entries=" . (string) count($entries) . " duration_ms={$ms}"
                );
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $addedCount += $this->processEntry($entry, $language);
                }

                // Mark the language as checked whether or not new entries were added
                $this->languageRepository->markLanguageAsChecked($iso);
                LoggerService::logInfo(
                    'BibleBrainSync-014',
                    "Marked checkedBBBibles=CURDATE() for ISO={$isoLower}"
                );

            } catch (\Throwable $e) {
                LoggerService::logInfo(
                    'BibleBrainSync-900',
                    "Failed for ISO {$isoLower}: "
                        . get_class($e)
                        . ' '
                        . $e->getMessage()
                        . ' at '
                        . $e->getFile()
                        . ':'
                );
                // Do not mark as checked if the call failed
            } finally {
                // Always pause between BibleBrain calls to avoid overwhelming them.
                $this->throttleBibleBrain();
            }
        }

        LoggerService::logInfo(
             'BibleBrainSync-020',
            "Sync loop finished. languagesProcessed={$languagesProcessed} totalAdded={$addedCount}"
        );
    }


    /**
     * Processes a single Bible entry from BibleBrain and inserts any new text filesets.
     *
     * Accepts two shapes:
     * 1) filesets['dbp-prod'][] with keys: id, type, size, (volume?), ...
     * 2) filesets[] with keys: id, set_type_code, set_size_code, meta[], ...
     */
    private function processEntry(array $entry, array $language): int
    {
        $added = 0;
        LoggerService::logInfo('BibleBrainSync-processEntry-1',[
            'entry'=>  $entry
        ] );
    
        $filesets = $this->extractTextFilesetsFromEntry($entry);
        LoggerService::logInfo('BibleBrainSync-processEntry=2',[
            'filesets'=>  $filesets
        ] );

        foreach ($filesets as $fs) {
            $id = (string) ($fs['id'] ?? '');
            $type = (string) ($fs['type'] ?? '');
            $size = (string) ($fs['size'] ?? '');

            if ($id === '' || $type === '' || $size === '') {
                LoggerService::logInfo('BibleBrainSync-110', 'Skip fileset: missing id/type/size.');
                continue;
            }

            // Only text
            if (!str_starts_with($type, 'text')) {
                 LoggerService::logInfo(
                    'BibleBrainSync-111',
                    "Skip fileset {$id}: non-text type={$type}"
                );
                continue;
            }

            // Only OT/NT/Complete-ish. Be permissive to avoid silently skipping.
            if (!$this->isCollectionSizeOk($size)) {
                LoggerService::logInfo(
                    'BibleBrainSync-112',
                    "Skip fileset {$id}: size rejected size={$size}"
                );
                continue;
            }

            $volumeName = (string) ($fs['volume'] ?? '');
            if ($volumeName === '') {
                $volumeName = (string) ($entry['name'] ?? '');
            }

            if (!$this->repository->bibleRecordExists($id)) {
                LoggerService::logInfo(
                    'BibleBrainSync-addingBibleRecord',
                    ['id'=> $id]
                );
                $this->repository->insertBibleRecord([
                    'externalId'             => $id,
                    'volumeName'             => $volumeName,
                    'languageCodeIso'        => (string) ($language['languageCodeIso'] ?? ''),
                    'languageCodeHL'         => (string) ($language['languageCodeHL'] ?? ''),
                    'languageEnglish'        => (string) ($entry['language'] ?? ''),
                    'languageName'           => (string) ($entry['autonym'] ?? ''),
                    'languageCodeBibleBrain' => (string) ($entry['language_id'] ?? ''),
                    'bibleBrainReviewed'     => (int) ($entry['reviewed'] ?? 0), // 0/1
                    'source'                 => 'dbt',
                    'format'                 => $type,
                    'collectionCode'         => $size,
                    'text'                   => '1',
                    'audio'                  => '0',
                    'video'                  => '0',
                    'dateVerified'           => date('Y-m-d'),
                ]);

                LoggerService::logInfo(
                    'BibleBrainSync-100',
                    "Inserted new Bible fileset: {$id} ({$type}, {$size})"
                );
                $added++;
            } else {
                LoggerService::logInfo(
                    'BibleBrainSync-updateBibleBrainSyncService',
                    ['id'=> $id,
                    'entry'=> $entry]
                );
                $this->repository->markVerifiedByExternalId($id);
                LoggerService::logInfo(
                    'BibleBrainSync-104',
                    "Existing Record: {$id} ({$type}, {$size})"
                );
            }
        }

        return $added;
    }

    /**
     * Returns a normalized list of filesets where each item contains:
     * - id
     * - type (text_json/text_usx/text_plain/text_format...)
     * - size (NT/OT/C/NTP/OTP/etc.)
     * - volume (best-effort)
     */
    private function extractTextFilesetsFromEntry(array $entry): array
    {
        // Shape A: filesets['dbp-prod'] (older style)
        $filesets = $entry['filesets'] ?? null;

        if (is_array($filesets) && isset($filesets['dbp-prod']) && is_array($filesets['dbp-prod'])) {
            LoggerService::logInfo('BibleBrainSync-120', 'Filesets shape A detected (filesets[dbp-prod]).');
            $out = [];
            foreach ($filesets['dbp-prod'] as $fs) {
                if (!is_array($fs)) {
                    continue;
                }
                $id = (string) ($fs['id'] ?? '');
                $type = (string) ($fs['type'] ?? '');
                $size = (string) ($fs['size'] ?? '');

                if ($id === '' || $type === '' || $size === '') {
                    continue;
                }

                $out[] = [
                    'id'     => $id,
                    'type'   => $type,
                    'size'   => $size,
                    'volume' => (string) ($fs['volume'] ?? ''),
                ];
            }
            LoggerService::logInfo(
                'BibleBrainSync-121',
                'Filesets extracted count=' . (string) count($out)
            );
            return $out;
        }

        // Shape B: filesets[] (newer style you pasted)
        if (is_array($filesets) && $this->isListArray($filesets)) {
            LoggerService::logInfo('BibleBrainSync-122', 'Filesets shape B detected (filesets list).');
            $out = [];
            foreach ($filesets as $fs) {
                if (!is_array($fs)) {
                    continue;
                }

                $id = (string) ($fs['id'] ?? '');
                $type = (string) ($fs['set_type_code'] ?? '');
                $size = (string) ($fs['set_size_code'] ?? '');

                if ($id === '' || $type === '' || $size === '') {
                    continue;
                }

                $volume = $this->filesetMetaValue($fs, 'volume');

                $out[] = [
                    'id'     => $id,
                    'type'   => $type,
                    'size'   => $size,
                    'volume' => $volume,
                ];
            }
            LoggerService::logInfo(
                'BibleBrainSync-123',
                'Filesets extracted count=' . (string) count($out)
            );
            return $out;
        }

        return [];
    }

    private function filesetMetaValue(array $fileset, string $key): string
    {
        $meta = $fileset['meta'] ?? [];
        if (!is_array($meta)) {
            return '';
        }

        foreach ($meta as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (($m['name'] ?? '') === $key) {
                return (string) ($m['description'] ?? '');
            }
        }

        return '';
    }

    private function isCollectionSizeOk(string $size): bool
    {
        // Accept common BibleBrain size codes.
        // Keep permissive to avoid dropping valid text sets.
        // Examples seen: NT, OT, C, etc.
        return preg_match('~^(NT|OT|C)~', $size) === 1;
    }

    private function isListArray(array $arr): bool
    {
        // True if keys are 0..n-1
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /**
     * Checks whether the sync already ran this calendar month.
     */
    private function hasRunThisMonth(): bool
    {
        if (!file_exists($this->logFile)) {
            return false;
        }

        $lastRun = trim((string) file_get_contents($this->logFile));
        $lastDate = DateTime::createFromFormat('Y-m-d', $lastRun);
        $now = new DateTime();

        return $lastDate instanceof DateTime
            && $lastDate->format('Y-m') === $now->format('Y-m');
    }

    /**
     * Updates the last run timestamp to the current date.
     */
    private function updateLastRunTimestamp(): void
    {
        $value = date('Y-m-d');
        $ok = file_put_contents($this->logFile, $value);
        LoggerService::logInfo(
            'BibleBrainSync-030',
            "Updated last-run stamp file={$this->logFile} value={$value} bytesWritten=" . (string) $ok
        );
    }

    private function throttleBibleBrain(int $minMs = 250, int $maxMs = 600): void
    {
        // Small jitter so runs do not look like a bot and to avoid sync spikes.
        $ms = random_int($minMs, $maxMs);
        usleep($ms * 1000);
    }

}
