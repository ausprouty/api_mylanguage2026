<?php
declare(strict_types=1);

namespace App\Services\BibleBrain;

use App\Repositories\BibleBrainBibleRepository;
use App\Services\Web\BibleBrainConnectionService;
use App\Services\LoggerService;
use DateTimeImmutable;
use Throwable;

/**
 * Since there are no longer any source of dbt this routine is no longer needed
 * 
 * One-time cleanup to correct externalId values in local `bibles`
 * by comparing against current BibleBrain filesets.
 *
 * Scope:
 *   - Only rows with source='dbt' (DBT/BibleBrain) and text formats
 *   - Idempotent; safe to re-run
 *
 * This is a pure service (no bootstrapping). Run it from a console
 * command / bin script and let DI construct it.
 */
final class BibleBrainBibleCleanupService
{
    private const BATCH_SIZE = 100;

    /** Acceptable BibleBrain text types */
    private const TEXT_TYPES = ['text', 'text_plain', 'text_format', 'text_usx'];

    /** Map local normalized format => acceptable BB types */
    private const FORMAT_ALIASES = [
        'text'       => ['text', 'text_plain'],
        'text_plain' => ['text', 'text_plain'],
        'text_usx'   => ['text_usx'],
        'text_format'=> ['text_format'],
    ];

    public function __construct(
        private BibleBrainBibleRepository $repo,
        private BibleBrainConnectionService $bb,
        private LoggerService $log,
    ) {}

    /**
     * Run cleanup over batches. Returns number of rows updated.
     */
    public function run(): int
    {
        $lastId   = 0;
        $updated  = 0;

        do {
            $batch = $this->repo->getBiblesForCleanup(self::BATCH_SIZE, $lastId);
            if (!$batch) {
                break;
            }

            foreach ($batch as $row) {
                $lastId = max($lastId, (int)($row['bid'] ?? 0));

                try {
                    if ($this->processBible($row)) {
                        $updated++;
                    }
                } catch (Throwable $e) {
                    $this->log->error(
                        'BB-cleanup: failed row',
                        [
                            'bid'  => $row['bid'] ?? null,
                            'iso'  => $row['languageCodeIso'] ?? null,
                            'err'  => $e->getMessage(),
                        ]
                    );
                }
            }
        } while (\count($batch) === self::BATCH_SIZE);

        $this->log->info('BB-cleanup: finished', ['updated' => $updated]);

        return $updated;
    }

    /**
     * Attempt to match a local bible row to a BB fileset and update record.
     */
    private function processBible(array $bible): bool
    {
        $iso = strtoupper((string)($bible['languageCodeIso'] ?? ''));
        if ($iso === '') {
            return false;
        }

        // Fetch once per ISO (the BB client should internally cache per-request)
        $filesets = $this->bb->fetchBiblesByIso($iso); // ← add this method on the client

        if (!$filesets) {
            return false;
        }

        foreach ($filesets as $fs) {
            if (!$this->isCandidateFileset($fs)) {
                continue;
            }
            if ($this->matchesLocalBible($bible, $fs)) {
                $externalId = (string)$fs['id'];

                // single write method (atomic-ish)
                $ok = $this->repo->updateExternalIdAndVerified(
                    (int)$bible['bid'],
                    $externalId,
                    new DateTimeImmutable()
                );

                if ($ok) {
                    $this->log->info(
                        'BB-cleanup: updated',
                        [
                            'bid' => $bible['bid'],
                            'old' => $bible['externalId'] ?? null,
                            'new' => $externalId,
                        ]
                    );
                }
                return (bool)$ok;
            }
        }

        return false;
    }

    /** Basic shape & type guard for a BB fileset record */
    private function isCandidateFileset(array $fs): bool
    {
        if (!isset($fs['id'], $fs['type'], $fs['size'])) {
            return false;
        }
        return \in_array((string)$fs['type'], self::TEXT_TYPES, true);
    }

    /**
     * Matching heuristic:
     *  - externalId prefix equality (first 7 chars) to BB id prefix
     *  - collectionCode == fileset['size']
     *  - format matches via alias table
     */
    private function matchesLocalBible(array $bible, array $fs): bool
    {
        $localExt = (string)($bible['externalId'] ?? '');
        $localFmt = (string)($bible['format'] ?? '');
        $localCol = (string)($bible['collectionCode'] ?? '');

        if ($localExt === '' || $localCol === '') {
            return false;
        }

        $prefixLocal = substr($localExt, 0, 7);
        $prefixFs    = substr((string)$fs['id'], 0, 7);

        $acceptable = self::FORMAT_ALIASES[$localFmt] ?? [$localFmt];
        $formatOk   = \in_array((string)$fs['type'], $acceptable, true);

        return ($prefixLocal === $prefixFs)
            && ($localCol === (string)$fs['size'])
            && $formatOk;
    }
}
