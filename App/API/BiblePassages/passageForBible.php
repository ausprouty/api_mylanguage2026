<?php

use App\Controllers\ReturnDataController;
use App\Controllers\BiblePassage\PassageSelectController;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\BibleRepository;
use App\Services\Database\DatabaseService;
use App\Factories\BibleFactory;
use stdClass;

$databaseService = new DatabaseService();
$bibleRepository = new BibleRepository($databaseService);
$bid = intval($_POST['bid']);
$entry = strip_tags($_POST['entry']);

$factory = new BibleFactory($bibleRepository);
$bibleModel = $factory->createFromBid($bid);
$bibleReference = new PassageReferenceModel();
$bibleReference->setFromEntry($entry);

$passage = new PassageSelectController($bibleReference, $bible);

$response = new stdClass();
$response->url = $passage->getPassageUrl();
$response->text = $passage->getPassageText();
ReturnDataController::returnData($response);
