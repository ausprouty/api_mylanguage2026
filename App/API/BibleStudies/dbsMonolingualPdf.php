<?php
/* First we will see if we have the view of the study you want.
   If not, we will create it
   Then store it
   Then send you the text you need
*/

use App\Controller\ReturnDataController as ReturnDataController;
use App\Controller\BibleStudy\Monolingual\MonolingualDbsTemplateController as MonolingualDbsTemplateController;
use App\Controllers\PdfController as PdfController;

$fileName = MonolingualDbsTemplateController::findFileNamePdf($lesson, $languageCodeHL1);
$path = MonolingualDbsTemplateController::getPathPdf();
$filePath = $path . $fileName;
//if (!file_exists($filePath)){
$study = new MonolingualDbsTemplateController($lesson, $languageCodeHL1);
$study->setMonolingualTemplate('monolingualDbsPdf.twig');
$html =  $study->getTemplate();
$styleSheet = 'dbs.css';
$mpdf = new PdfController($languageCodeHL1);
$mpdf->writePdfToComputer($html, $styleSheet, $filePath);
//}
$url =  MonolingualDbsTemplateController::getUrlPdf();
$response['url'] = $url . $fileName;
$response['name'] = $fileName;
ReturnDataController::returnData($response);
