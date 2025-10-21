<?php

use App\Controllers\ReturnDataController as ReturnDataController;
use App\Models\Language\CountryLanguageModel as  CountryLanguageModel;

$data = CountryLanguageModel::getLanguagesWithContentForCountry($countryCode);
$output =  CountryLanguageModel::addLanguageCodeJF($data);
ReturnDataController::returnData($output);