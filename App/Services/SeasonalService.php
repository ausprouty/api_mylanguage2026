<?php

declare(strict_types=1);

namespace App\Services;

use App\Configuration\Config;
use App\Services\LoggerService;
use DateTimeImmutable;
use DateTimeZone;

final class SeasonalService
{
    private DateTimeZone $tz;

    public function __construct(string $timezone = 'Australia/Melbourne')
    {
        $this->tz = new DateTimeZone($timezone);
    }

    public function getActiveSeasonForSite(
        string $site,
        string $languageCodeGoogle
    ): ?array {
        $site = $this->normalizeSite($site);
        if ($site === '') {
            return null;
        }

        $lang = $this->normalizeLang($languageCodeGoogle);

        $collection = $this->loadSiteCollection($site);
        //LoggerService::logInfo('SeasonalService', ['collection'=>$collection]);
        if (!$collection) {
            return null;
        }

        $seasons = $collection['seasonalGreetings'] ?? null;
        //LoggerService::logInfo('SeasonalService', ['seasons'=>$seasons]);
        if (!is_array($seasons) || count($seasons) === 0) {
            return null;
        }

        $today = new DateTimeImmutable('now', $this->tz);
        //LoggerService::logInfo('SeasonalService', ['today'=>$today]);

        $active = [];
        foreach ($seasons as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (!$this->isActiveOnDate($s, $today)) {
                continue;
            }
            $active[] = $s;
        }
         LoggerService::logInfo('SeasonalService', ['active'=>$active]);


        if (count($active) === 0) {
            return null;
        }

        // Overlap rule: prefer primary, then later start date.
        usort($active, function (array $a, array $b): int {
            $pa = $this->priorityRank($a);
            $pb = $this->priorityRank($b);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $sa = (string) ($a['starts'] ?? '');
            $sb = (string) ($b['starts'] ?? '');
            return strcmp($sb, $sa);
        });

        return $this->translateSeason($active[0], $lang);
    }

    private function loadSiteCollection(string $site): ?array
    {
        $base = rtrim((string) Config::getDir('resources.templates'), "\\/");
        $file = $base
            . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'seasonalContent'
            . DIRECTORY_SEPARATOR . $site . '.json';
       // LoggerService::logInfo('SeasonalService', ['file'=> $file]);
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
   
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return null;
        }
        //LoggerService::logInfo('SeasonalService', ['data'=> $data]);
        return $data;
    }

    private function translateSeason(array $season, string $lang): array
    {
        $texts = $season['textsByLanguageCodeGoogle'] ?? [];
        $texts = is_array($texts) ? $texts : [];
        LoggerService::logInfo('SeasonalService', [
            'texts'=> $texts,
            'lang' =>$lang]);

        $t = null;

        if (isset($texts[$lang]) && is_array($texts[$lang])) {
            $t = $texts[$lang];
        } elseif (isset($texts['en']) && is_array($texts['en'])) {
            $t = $texts['en'];
        } else {
            foreach ($texts as $maybe) {
                if (is_array($maybe)) {
                    $t = $maybe;
                    break;
                }
            }
        }
        LoggerService::logInfo('SeasonalService', ['t'=> $t]);
        $title = is_array($t) ? (string) ($t['title'] ?? '') : '';
        $paras = is_array($t) ? ($t['paragraphs'] ?? []) : [];
        if (!is_array($paras)) {
            $paras = [];
        }

        return [
            'key' => (string) ($season['key'] ?? ''),
            'starts' => (string) ($season['starts'] ?? ''),
            'ends' => (string) ($season['ends'] ?? ''),
            'image' => (string) ($season['image'] ?? ''),
            'title' => $title,
            'paragraphs' => array_values(
                array_filter(
                    array_map('strval', $paras),
                    static function (string $p): bool {
                        return trim($p) !== '';
                    }
                )
            ),
        ];
    }

    private function isActiveOnDate(
        array $season,
        DateTimeImmutable $today
    ): bool {
        $startRaw = isset($season['starts']) ? (string) $season['starts'] : '';
        $endRaw = isset($season['ends']) ? (string) $season['ends'] : '';
        if ($startRaw === '' || $endRaw === '') {
            return false;
        }

        $start = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $startRaw . ' 00:00:00',
            $this->tz
        );
        $end = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $endRaw . ' 23:59:59',
            $this->tz
        );

        if (!$start || !$end) {
            return false;
        }

        $active = ($today >= $start) && ($today <= $end);
       // LoggerService::logInfo('SeasonalService', [
        //    'startRaw' => $startRaw,
        //    'endRaw' => $endRaw,
        //   'active' => $active]);
        return $active;
    }

    private function priorityRank(array $season): int
    {
        $p = strtolower((string) ($season['priority'] ?? 'secondary'));
        if ($p === 'primary') {
            return 0;
        }
        return 1;
    }

    private function normalizeSite(string $site): string
    {
        $site = strtolower(trim($site));
        if ($site === '') {
            return '';
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $site)) {
            return '';
        }
        return $site;
    }

    private function normalizeLang(string $lang): string
    {
        $lang = trim($lang);
        if ($lang === '') {
            return 'en';
        }

        // Accept "en", "hi", "ta", "zh-CN", etc.
        if (!preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $lang)) {
            return 'en';
        }

        $parts = explode('-', $lang);
        if (count($parts) === 2) {
            return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
        }

        return strtolower($lang);
    }
}
