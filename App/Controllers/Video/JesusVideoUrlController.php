<?php

namespace App\Controllers\Video;

use App\Repositories\JVideoContentRepository;
use App\Models\Video\JesusVideoSegmentModel;
use App\Services\Database\DatabaseService;
use App\Services\VideoService;
use App\Responses\JsonResponse;
use Exception;

class JesusVideoUrlController
{
    private JVideoContentRepository $videoRepository;

    public function __construct(JVideoContentRepository $videoRepository)
    {
        $this->videoRepository = $videoRepository;
    }

    public function webFetchJesusVideoUrls($args)
    {
        try {
            $languageCodeJF = $args['languageCodeJF'];
            $output = $this->getAllJesusVideoUrls($languageCodeJF);
            JsonResponse::success($output);
        } catch (Exception $e) {
            // Handle any unexpected errors
            JsonResponse::error($e->getMessage());
        }
    }

    public function getAllJesusVideoUrls($languageCodeJF): array
    {
        $videos = $this->videoRepository->getAllJesusVideoSegments(); // Fetch data from repository
        $videoList = [];
        foreach ($videos as $videoData) {
            $videoModel = new JesusVideoSegmentModel(); // Instantiate the model
            $videoModel->populateFromArray($videoData); // Populate with data
            $url = VideoService::getArclightUrl($videoModel, $languageCodeJF);
            $videoList[] = [
                'index' => $videoModel->getId(),
                'url' => $url
            ];
        }
        return $videoList;
    }
}
