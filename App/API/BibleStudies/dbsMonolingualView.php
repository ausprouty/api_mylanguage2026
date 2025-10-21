<?php
/* First we will see if we have the view of the study you want.
   If not, we will create it
   Then store it
   Then send you the text you need
*/

use App\Services\Database\DatabaseService;
use App\Controllers\BibleStudy\Monolingual\MonolingualDbsTemplateController as MonolingualDbsTemplateController;
use App\Services\QrCodeGeneratorService;
use App\Traits\DbsFileNamingTrait;
use App\Configuration\Config;
use App\Repositories\LanguageRepository;
use App\Factories\LanguageFactory;

$databaseService = new DatabaseService;
$languageFactory = new LanguageFactory($databaseService);
$languageRepository = new LanguageRepository($databaseService, $languageFactory);


$qrCodeGeneratorService = new QrCodeGeneratorService();
$controller = new MonolingualDbsTemplateController($qrCodeGeneratorService, $lesson, $languageCodeHL);

$fileName = $this->dbsFileNameingTrait->generateFileName($lesson, $languageCodeHL);
$path = Config::getDir('resources.root');
$filePath = $path . $fileName;

//if (!file_exists($filePath)){
$study = new MonolingualDbsTemplateController($lesson, $languageCodeHL1);
$study->setMonolingualTemplate('monolingualDbsView.twig');
$html =  $study->getTemplate();
$study->saveMonolingualView();
//}
$response = file_get_contents($filePath);
