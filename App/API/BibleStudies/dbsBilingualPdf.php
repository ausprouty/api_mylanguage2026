<?php
/* First we will see if we have the pdf of the study you want.
   If not, we will create it
   Then store it
   Then send you the address of the file you can download
*/

use App\Controller\ReturnDataController;
use App\Controllers\PdfController;
use App\Controllers\BibleStudy\Bilingual\BilingualDbsTemplateController;



$fileName =  BilingualDbsTemplateController::findFileNamePdf($lesson, $languageCodeHL1, $languageCodeHL2);
$path = BilingualDbsTemplateController::getPathPdf();
$filePath = $path . $fileName;
writeLogDebug('dbsBilingualPdf-17', $filePath);
//TODO: eliminate commented
//if (!file_exists($filePath)){
$study = new BilingualDbsTemplateController($languageCodeHL1, $languageCodeHL2, $lesson);
$study->setBilingualTemplate('bilingualDbsPdf.twig');
$html =  $study->getTemplate();
writeLogDebug('dbsBilingualPdf-23', $html);
$styleSheet = 'dbs.css';
$mpdf = new PdfController($languageCodeHL1, $languageCodeHL2);
$mpdf->writePdfToComputer($html, $styleSheet, $filePath);
//}
$url = BilingualDbsTemplateController::getUrlPdf();
$response['url'] = $url . $fileName;
$response['name'] = $fileName;
writeLogDebug('dbsBilingualPdf-29', $response);
ReturnDataController::returnData($response);
