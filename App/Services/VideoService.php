<?php

namespace App\Services;

use App\Services\TwigService;
use App\Configuration\Config;
use App\Models\Bible\PassageReferenceModel;
use App\Interfaces\ArclightVideoInterface;
use App\Helpers\TimeHelper;

class VideoService {

    protected $twigService;

    // Fixed constructor (correct spelling and type hinting)
    public function __construct(TwigService $twigService) {
        $this->twigService = $twigService;
    }

    // Function to get video twig
    public function getVideoBlockIframe(array $translation) {
        $videoInfo = $this->computeParameters ($translation);
        $videoInfo['url'] = Config::get('api.jvideo_player') . $translation['videoCode'];
        $template = 'videoIframe.twig';

        // Ensure TwigService s a render method
        $output = $this->twigService->render($template, $videoInfo);
      
    }

    public function getUrlFromPassageReference(
        PassageReferenceModel $passageReferenceInfo, 
        string $languageCodeJF){
        
        if ($passageReferenceInfo->getVideoSource() == 'arclight'){
            return self::getArclightUrl($passageReferenceInfo, $languageCodeJF);
        }

    }

    public static function getArclightUrl(
        ArclightVideoInterface $videoInfo, 
        string $languageCodeJF
    ): ?string {
     
        if (!$languageCodeJF || 
            $videoInfo->getVideoSource() !== 'arclight' ||
            $videoInfo->getVideoPrefix() === '' ) {
            return null;
        }

        $url = Config::get('api.jvideo_player');
        $url .= $videoInfo->getVideoPrefix();
        $url .= $languageCodeJF;
        $url .= $videoInfo->getVideoCode();
        $url .= $videoInfo->getVideoSegment();

        if ($videoInfo->getEndTime()) {
            $startSeconds = TimeHelper::convertToSeconds($videoInfo->getStartTime());
            $url .= '&start=' . $startSeconds;
            $endSeconds = TimeHelper::convertToSeconds($videoInfo->getEndTime());
            $url .= '&end=' . $endSeconds;
        }

        $url .= '&playerStyle=default';
        return $url;
    }


    protected function computeParameters(array $translation){
        $videoInfo = [];
        $videoInfo['videoCode'] = $translation['videoCode'] ?? '';
        $videoInfo['url'] =  $videoInfo['videoCode'];
        $videoInfo['videoSegment'] =  $translation['videoSegment'];
        $videoInfo['startTime'] = TimeHelper::convertToSeconds($translation['startTime'] ?? '0:00');
        $videoInfo['endTime'] = TimeHelper::convertToSeconds($translation['endTime'] ?? '0:00');
        return $videoInfo;
    }

}    
