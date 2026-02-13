<?php
declare(strict_types=1);

namespace App\Services\Bible;

use App\Repositories\BibleSelectionRepository;

final class BestBibleSelectionService
{
    public function __construct(
        private BibleSelectionRepository $repo
    ) {}

    public function run(): void
    {
        $languages = $this->repo->fetchLanguagesNeedingSelection();

        foreach ($languages as $lang) {
            $hl = (string) ($lang['languageCodeHL'] ?? '');
            if ($hl === '') {
                continue;
            }
            $bibles = $this->repo->fetchBiblesForLanguage($hl);
            if (!$bibles) {
                continue;
            }

            $selection = $this->pickBest($bibles);

            if ($selection['type'] === 'complete') {
                $this->repo->saveSelectionComplete(
                    $hl,
                    (int) $selection['bibleId'],
                    7
                );
            } elseif ($selection['type'] === 'pair') {
                $this->repo->saveSelectionPair(
                    $hl,
                    (int) $selection['otBibleId'],
                    (int) $selection['ntBibleId'],
                    7
                );
            } elseif ($selection['type'] === 'nt_only') {
                $this->repo->saveSelectionComplete(
                    $hl,
                    (int) $selection['bibleId'],
                    5
                );
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $bibles
     * @return array<string,mixed>
     */
    private function pickBest(array $bibles): array
    {
        $byProvider = [
            'bible_brain' => [],
            'word' => [],
            'bible_gateway' => [],
            'others' => [],
            'youversion' => [],
        ];

        foreach ($bibles as $b) {
            $p = strtolower((string) ($b['provider'] ?? 'others'));
            if (!isset($byProvider[$p])) {
                $p = 'others';
            }
            $byProvider[$p][] = $this->enrich($b, $p);
        }

        // 1) BibleBrain: prefer JSON + complete, else OT+NT pair
        $bb = $byProvider['bible_brain'];
        if ($bb) {
            $bestComplete = $this->bestComplete($bb);
            if ($bestComplete) {
                return [
                    'type' => 'complete',
                    'bibleId' => $bestComplete['id'],
                ];
            }

            $pair = $this->bestPair($bb);
            if ($pair) {
                return [
                    'type' => 'pair',
                    'otBibleId' => $pair['ot']['id'],
                    'ntBibleId' => $pair['nt']['id'],
                ];
            }
            
            $bestNt = $this->bestNtOnly($bb);
            if ($bestNt) {
                return [
                    'type' => 'nt_only',
                    'bibleId' => $bestNt['id'],
                ];
            }

        }

        // 2) Word
        $best = $this->bestCompleteOrSingle($byProvider['word']);
        if ($best) {
            return ['type' => 'complete', 'bibleId' => $best['id']];
        }

        // 3) BibleGateway
        $best = $this->bestCompleteOrSingle($byProvider['bible_gateway']);
        if ($best) {
            return ['type' => 'complete', 'bibleId' => $best['id']];
        }

        // 4) Others
        $best = $this->bestCompleteOrSingle($byProvider['others']);
        if ($best) {
            return ['type' => 'complete', 'bibleId' => $best['id']];
        }

        // 5) Last resort: YouVersion
        $best = $this->bestCompleteOrSingle($byProvider['youversion']);
        if ($best) {
            return ['type' => 'complete', 'bibleId' => $best['id']];
        }

        // Nothing usable
        return ['type' => 'none'];
    }

    /**
     * @param array<string,mixed> $b
     * @return array<string,mixed>
     */
    private function enrich(array $b, string $provider): array
    {
        $b['provider_norm'] = $provider;

        $b['has_json'] =
            (bool) ($b['has_json'] ?? false)
            || (isset($b['format']) && strtolower((string) $b['format']) === 'json');

        $cc = strtoupper(trim((string)($b['collectionCode'] ?? '')));
        $b['is_complete'] = ($cc === 'C');
        $b['is_ot']       = ($cc === 'OT');
        $b['is_nt']       = ($cc === 'NT');
        return $b;
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<string,mixed>|null
     */
    private function bestComplete(array $candidates): ?array
    {
        $complete = array_values(array_filter(
            $candidates,
            fn($b) => !empty($b['is_complete'])
        ));

        if (!$complete) {
            return null;
        }

        usort($complete, fn($a, $b) => $this->compare($a, $b));

        return $complete[0];
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<string,mixed>|null
     */
    private function bestCompleteOrSingle(array $candidates): ?array
    {
        if (!$candidates) {
            return null;
        }

        usort($candidates, fn($a, $b) => $this->compare($a, $b));
        return $candidates[0];
    }


    /**
     * BibleBrain NT-only fallback (no OT available).
     *
     * @param array<int,array<string,mixed>> $bb
     * @return array<string,mixed>|null
     */
    private function bestNtOnly(array $bb): ?array
    {
        $nts = array_values(array_filter($bb, fn($b) => !empty($b['is_nt'])));
        if (!$nts) {
            return null;
        }

        usort($nts, fn($a, $b) => $this->compare($a, $b));
        return $nts[0];
    }


    /**
     * @param array<int,array<string,mixed>> $bb
     * @return array{ot:array<string,mixed>, nt:array<string,mixed>}|null
     */
    private function bestPair(array $bb): ?array
    {
        $ots = array_values(array_filter($bb, fn($b) => !empty($b['is_ot'])));
        $nts = array_values(array_filter($bb, fn($b) => !empty($b['is_nt'])));

        if (!$ots || !$nts) {
            return null;
        }

        usort($ots, fn($a, $b) => $this->compare($a, $b));
        usort($nts, fn($a, $b) => $this->compare($a, $b));

        return ['ot' => $ots[0], 'nt' => $nts[0]];
    }

    

    




    private function compare(array $a, array $b): int
    {
        // Lower score is better
        return $this->score($a) <=> $this->score($b);
    }

    private function score(array $b): int
    {
        $provider = (string) ($b['provider_norm'] ?? 'others');

        $base = match ($provider) {
            'bible_brain' => 0,
            'word' => 1000,
            'bible_gateway' => 2000,
            'others' => 3000,
            'youversion' => 4000,
            default => 3000,
        };

        $penalty = 0;

        if ($provider === 'bible_brain' && empty($b['has_json'])) {
            $penalty += 200;
        }

        if (empty($b['is_complete'])) {
            $penalty += 100;
        }

        if (!empty($b['is_ot']) || !empty($b['is_nt'])) {
            $penalty += 150;
        }

        return $base + $penalty;
    }
}
