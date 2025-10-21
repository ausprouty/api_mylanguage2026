<?php
// import Bibles
    $r->addGroup($basePath . 'api/import/bible', function (RouteCollector $group) {
        $group->addRoute('GET', '/externalId', 'App/API/Imports/updateBibleExternalId.php');
        $group->addRoute('GET', '/languages', 'App/API/Imports/addHLCodeToBible.php');
        $group->addRoute('GET', '/bookNames/languages', 'App/API/Imports/addHLCodeToBibleBookNames.php');
    });

    $r->addGroup($basePath . 'api/import/biblebrain', function (RouteCollector $group) {
        $group->addRoute('GET', '/setup', 'App/API/Imports/clearBibleBrainCheckDate.php');
        $group->addRoute('GET', '/bibles', 'App/API/Imports/addBibleBrainBibles.php');
        $group->addRoute('GET', '/languages', 'App/API/Imports/addBibleBrainLanguages.php');
        $group->addRoute('GET', '/language/details', 'App/API/Imports/updateBibleBrainLanguageDetails.php');
    });

    $r->addGroup($basePath . 'api/import/biblegateway', function (RouteCollector $group) {
        $group->addRoute('GET', '/bibles', 'App/API/Imports/addBibleGatewayBibles.php');
    });

    //import countries
    $r->addGroup($basePath . 'api/import/country', function (RouteCollector $group) {
        $group->addRoute('GET', '/languages/africa', 'App/API/Imports/importLanguagesAfrica.php');
        $group->addRoute('GET', '/languages/jproject', 'App/API/Imports/importLanguagesJProject.php');
        $group->addRoute('GET', '/languages/jproject2', 'App/API/Imports/importLanguagesJProject2.php');
        $group->addRoute('GET', '/names', 'App/API/Imports/checkCountryNames.php');
        $group->addRoute('GET', '/names/language', 'App/API/Imports/addCountryNamesToLanguage.php');
        $group->addRoute('GET', '/names/language2', 'App/API/Imports/addCountryNamesToLanguage2.php');
    });
    //import videos

    $r->addGroup($basePath . 'api/import/video', function (RouteCollector $group) {
        $group->addRoute('GET', '/segments', 'App/API/Imports/importJesusVideoSegments.php');
        $group->addRoute('GET', '/segments/clean', 'App/API/Imports/JFSegmentsClean.php');
        $group->addRoute('GET', '/languages', 'App/API/Imports/videoLanguageCodesForJF.php');
        $group->addRoute('GET', '/jvideo/endTime', 'App/API/Imports/addJVideoSegmentEndTime.php');
    });
    // other imports
    $r->addGroup($basePath . 'api/import', function (RouteCollector $group) {
        $group->addRoute('GET', '/dbs/database', 'App/API/Imports/UpdateDbsLanguages.php');
        $group->addRoute('GET', '/india', 'App/API/Imports/importIndiaVideos.php');
        $group->addRoute('GET', '/leadership/database', 'App/API/Imports/importLeadershipStudies.php');
        $group->addRoute('GET', '/life', 'App/API/Imports/importLifePrinciples.php');
        $group->addRoute('GET', '/lumo', 'App/API/Imports/importLumoVideos.php');
        $group->addRoute('GET', '/lumo/clean', 'App/API/Imports/LumoClean.php');
        $group->addRoute('GET', '/lumo/segments', 'App/API/Imports/LumoSegmentsAdd.php');
        $group->addRoute('GET', '/tracts', 'App/API/Imports/bilingualTractsVerify.php');
    });
