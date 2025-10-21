<?php

namespace App\Controllers\Language;
use App\Configuration\Config;

class TranslationController {

    public static function TranslateText($text, $destinationLanguage, $sourceLanguage = 'eng'){

        $subscriptionKey = Config::get('YOUR_AZURE_SUBSCRIPTION_KEY');
        writeLogDebug('translation-10', $subscriptionKey);
        $endpoint = "https://api.cognitive.microsofttranslator.com";
        $path = "/translate?api-version=3.0";

        $url = $endpoint . $path;

        $headers = array(
            "Ocp-Apim-Subscription-Key: $subscriptionKey",
            "Content-type: application/json",
        );

        $data = array(
            array('text' => 'Good Morning'),
        );

        $body = json_encode($data);

        // Use Guzzle or another HTTP client to make the request
        // Make sure to handle errors and exceptions appropriately in a production environment

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        writeLogDebug('translation-34', $response);
        curl_close($ch);

        // Decode the JSON response
        $result = json_decode($response, true);

        // Get the translated text
        $translatedText = $result[0]['translations'][0]['text'];

        // Output the translated text
        return $translatedText;
    }
}