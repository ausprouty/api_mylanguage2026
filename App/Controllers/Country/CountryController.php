<?php

namespace App\Controllers\Country;

use App\Services\Database\DatabaseService;
use PDO as PDO;


Class CountryController extends Country {

    static function getCountryByIsoCode($countryCodeIso){
        //$databaseService = new DatabaseService();
        $query = "SELECT *
                  FROM country_locations 
                  WHERE countryCodeIso = :countryCodeIso";
                  $params = array(':countryCodeIso'=> $countryCodeIso);
        try {
            $results = $databaseService->executeQuery($query, $params);
            $data = $results->fetch(PDO::FETCH_OBJECT);
            return $data;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    static function getCountryByIsoCode3($countryCodeIso3){
        //$databaseService = new DatabaseService();
        $query = "SELECT *
                  FROM country_locations 
                  WHERE countryCodeIso3 = :countryCodeIso3";
                  $params = array(':countryCodeIso3'=> $countryCodeIso3);
        try {
            $results = $databaseService->executeQuery($query, $params);
            $data = $results->fetch(PDO::FETCH_OBJECT);
            return $data;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    protected function updateCountryCodeIso3($countryCodeIso, $countryCodeIso3){
        //$databaseService = new DatabaseService();
        $query = "UPDATE country_locations 
                SET countryCodeIso3 = :countryCodeIso3
                  WHERE countryCodeIso = :countryCodeIso";
                  $params = array(
                    ':countryCodeIso3'=> $countryCodeIso3, 
                    ':countryCodeIso'=> $countryCodeIso)
                ;
        try {
            $results = $databaseService->executeQuery($query, $params);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    protected function updateCountryNamefromCodeIso($countryCodeIso, $countryName){
        //$databaseService = new DatabaseService();
        $query = "UPDATE country_locations 
                SET countryName = :countryName
                  WHERE countryCodeIso = :countryCodeIso";
                  $params = array(
                    ':countryName'=> $countryName, 
                    ':countryCodeIso'=> $countryCodeIso)
                ;
        try {
            $results = $databaseService->executeQuery($query, $params);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }


}