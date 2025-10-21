<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Language\TranslationService;
use App\Responses\JsonResponse;
use App\Http\Concerns\ValidatesArgs;  
use Exception;

/**
 * Controller to handle fetching of translation data.
 */
class TranslationFetchController
{
    use ValidatesArgs;
    /**
     * Required and optional keys per endpoint.
     */
    private const COMMON_REQUIRED = ['study', 'languageCodeHL'];
    private const COMMON_OPTIONAL = ['variant'];

    private const IFACE_REQUIRED  = ['app', 'languageCodeHL'];
    private const IFACE_OPTIONAL  = ['variant'];

    
    public function __construct(private TranslationService $translationService) {}

    

    /**
     * Fetches common content translation for a given study and language.
     *
     * Expected keys in $args:
     * - 'study' (string) - required
     * - 'languageCodeHL' (string) - required
     * - 'variant' (string|null) - optional
     *
     * @param array $args
     * @return void
     */
    public function webFetchCommonContent(array $args): void
    {
        try {
            if ($bad = $this->missingRequired($args, self::COMMON_REQUIRED)) {
                JsonResponse::error(
                    'Missing or invalid required arguments: ' . implode(', ', $bad) . '. ' .
                    $this->expectedKeysMsg(self::COMMON_REQUIRED, self::COMMON_OPTIONAL)
                );
                return;
            }
            $study         = $this->arg($args, 'study', [$this,'normId']);
            $languageCodeHL= $this->arg($args, 'languageCodeHL', [$this,'normId']);
            $variant       = $this->arg($args, 'variant', [$this,'normId']); // optional

            $out = $this->translationService->getTranslatedContent(
                'commonContent', $study, $languageCodeHL, $variant
            );
            JsonResponse::success($out);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage());
        }
    }
    /**
     * Fetches interface translation data for a specific app and language.
     *
     * Expected keys in $args:
     * - 'app' (string) - required
     * - 'languageCodeHL' (string) - required
     * - 'variant' (string|null) - optional
     * @param array $args
     * @return void
     */
    public function webFetchInterface(array $args): void
    {
        try{
         if ($bad = $this->missingRequired($args, self::IFACE_REQUIRED)) {
                JsonResponse::error(
                    'Missing or invalid required arguments: ' . implode(', ', $bad) . '. ' .
                    $this->expectedKeysMsg(self::IFACE_REQUIRED, self::IFACE_OPTIONAL)
                );
                return;
            }
            $app           = $this->arg($args, 'app', [$this,'normId']);
            $languageCodeHL= $this->arg($args, 'languageCodeHL', [$this,'normId']);
            $variant       = $this->arg($args, 'variant', [$this,'normId']); // optional

            $out = $this->translationService->getTranslatedContent(
                'interface', $app, $languageCodeHL, $variant
            );
            JsonResponse::success($out);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}
