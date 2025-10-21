<?php

namespace App\Services;

class YouTubePlaylistService
{
    protected $apiKey;
    protected $playlistId;
    protected $apiUrl = 'https://www.googleapis.com/youtube/v3/playlistItems';

    public function __construct(string $apiKey, string $playlistId)
    {
        $this->apiKey = $apiKey;
        $this->playlistId = $playlistId;
    }

    public function fetchVideos(int $maxResults = 50): array
    {
        $url = $this->apiUrl . '?' . http_build_query([
            'part' => 'snippet',
            'playlistId' => $this->playlistId,
            'maxResults' => $maxResults,
            'key' => $this->apiKey
        ]);

        $response = file_get_contents($url);
        if (!$response) {
            throw new \Exception("Unable to retrieve data from YouTube API.");
        }

        $data = json_decode($response, true);

        $videos = [];
        foreach ($data['items'] as $item) {
            $videoId = $item['snippet']['resourceId']['videoId'];
            $title = $item['snippet']['title'];
            $videos[] = [
                'title' => $title,
                'url' => "https://www.youtube.com/watch?v=$videoId"
            ];
        }

        return $videos;
    }
}
