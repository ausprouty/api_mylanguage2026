<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Concerns\ValidatesArgs;
use App\Responses\JsonResponse;
use App\Services\BibleStudy\TextBundleResolver;
use App\Services\LoggerService;
use Exception;

final class StudyTextController
{
    use ValidatesArgs;

    private const KINDS = [
        'common'    => 'commonContent',
        'commoncontent' =>'commonContent',
        'interface' => 'interface',
    ];
    private bool $debugController = false;

    public function __construct(private TextBundleResolver $resolver) {}

    /**
     * Args:
     * - kind: common | interface
     * - subject: study or app code (normId'd)
     * - languageCodeHL
     * - variant (optional)
     */
    public function webFetch(array $args): void
    {
        try {
            $kind = $this->arg($args, 'kind', [$this, 'normId']);
            $subj = $this->arg($args, 'subject', [$this, 'normId']);
            $lang = $this->arg($args, 'languageCodeHL', [$this, 'normId']) ?? 'default';

            if (!isset(self::KINDS[$kind])) {
                JsonResponse::error('Invalid kind. Use: coMmon | interface');
                return;
            }

            $mapped = self::KINDS[$kind];
            $res = $this->resolver->fetch($mapped, $subj, $lang, $var);

            // Prepare headers (ETag MUST be quoted)
            $etagHeader = '"' . $res['etag'] . '"';
            $headers = [
                'ETag'          => $etagHeader,
                'Cache-Control' => 'public, max-age=600',
            ];

            // Conditional GET: If-None-Match may contain multiple ETags or '*'
            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
            if (is_string($ifNoneMatch)) {
                $candidates = array_map('trim', explode(',', $ifNoneMatch));
                if ($ifNoneMatch === '*' || in_array($etagHeader, $candidates, true)) {
                    JsonResponse::notModified($headers); // pass ARRAY, not string
                    return;
                }
            }
            if ($this->debugController){
                LoggerService::logInfoJson('StudyTextController-65', $res['data']['meta']);
            }
            JsonResponse::success($res['data'], $headers, 200);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}
