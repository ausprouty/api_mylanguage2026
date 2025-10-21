<?php

use App\Controllers\ReturnDataController as ReturnDataController;

$options = [
    [
        'videoSegment' => '1-0-0',
        'title' => '1. Who Is God?'
    ],
    
    [
        'videoSegment' => '2-0-0',
        'title' => '2. Who Is Jesus?'
    ],
    [
        'videoSegment' => '3-0-0',
        'title' => '3. Prayer - Talking to God'
    ],
    [
        'videoSegment' => '4-0-0',
        'title' => '4. Living as a Disciple of Jesus'
    ],
    [
        'videoSegment' => '5-0-0',
        'title' => '5. Sharing Your Faith With Others'
    ]
];
ReturnDataController::returnData($options);
