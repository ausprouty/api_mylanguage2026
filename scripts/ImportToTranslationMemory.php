<?php

namespace scripts;

use App\Services\Database\DatabaseService;

class ImportToTranslationMemory{

     protected DatabaseService $db;
     protected String  $filename;

    public function __construct($filename)
    {
        $this->db = new DatabaseService('standard');
        $this->filename = $filename;

    }
     public function run(): void
    {
        //$filename = 'translations.tsv'; // your TSV file
        if (!file_exists($this->filename)) {
        die("File not found: $this->filename\n");
}
        // Read the file into rows
        $lines = file($this->filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Check there are enough lines
        if (count($lines) < 3) {
            die("Not enough data in file.\n");
        }

        // Extract headers
        $hlCodes = explode("\t", $lines[0]);
        $googleCodes = explode("\t", $lines[1]);

        // Map HL code to Google code
        $langMap = [];
        $termsProcessed = 0;
        for ($i = 2; $i < count($hlCodes); $i++) {
            $hl = trim($hlCodes[$i]);
            $google = trim($googleCodes[$i]);
            if ($hl && $google) {
                $langMap[$i] = ['hl' => $hl, 'google' => $google];
            }
        }
        
        // Process each row
        for ($lineNum = 2; $lineNum < count($lines); $lineNum++) {
            $cols = explode("\t", $lines[$lineNum]);
            $key = $cols[0];
            $enSource = $cols[1] ?? '';

            for ($i = 2; $i < count($cols); $i++) {
                if (!isset($langMap[$i])) continue;
                $targetLang = $langMap[$i]['google'];
                $translated = trim($cols[$i]);

                if ($translated !== '' 
                    && $translated !== 'Loading...'
                    && $translated !== '#VALUE!') {
                    $termsProcessed++;
                    $this->db->executeQuery(
                    "INSERT INTO translation_memory (source_text, source_lang, target_lang, translated_text)
                    VALUES (:source_text, 'en', :target_lang, :translated_text)
                    ON DUPLICATE KEY UPDATE translated_text = :translated_text",
                    [
                        ':source_text' => $enSource,
                        ':target_lang' => $targetLang,
                        ':translated_text' => $translated
                    ]
                );
                }
            }
        }

    echo "Imported $termsProcessed";
    }
}
