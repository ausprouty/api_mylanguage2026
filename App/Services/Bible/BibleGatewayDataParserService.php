<?php

namespace App\Services\Bible;

class BibleGatewayDataParserService
{
    /**
     * Parse the language name from a line containing the language code and name.
     *
     * Example input line:
     * <option class="lang" value="BG1940">---Български (BG)---</option>
     *
     * @param string $line
     * @return string|null
     */
    public function parseLanguageName(string $line): ?string
    {
        $startPos = strpos($line, '>-') + 2;
        $namePart = substr($line, $startPos);
        $endPos = strpos($namePart, '(') - 1;
        $name = substr($namePart, 0, $endPos);
        $name = str_replace('-', '', $name);
        return trim($name);
    }

    /**
     * Parse the default Bible identifier from a line.
     *
     * @param string $line
     * @return string|null
     */
    public function parseDefaultBible(string $line): ?string
    {
        $startPos = strpos($line, 'value="') + 7;
        $idPart = substr($line, $startPos);
        $endPos = strpos($idPart, '"');
        return substr($idPart, 0, $endPos);
    }

    /**
     * Parse the ISO language code from a line containing the language code and name.
     *
     * @param string $line
     * @return string|null
     */
    public function parseLanguageCodeIso(string $line): ?string
    {
        $startPos = strrpos($line, '(') + 1;
        $isoCodePart = substr($line, $startPos);
        $endPos = strpos($isoCodePart, ')');
        $isoCode = substr($isoCodePart, 0, $endPos);

        return strtolower(trim($isoCode));
    }

    /**
     * Parse the external ID and volume name from a line containing the Bible information.
     *
     * Example line:
     * <option value="BG1940">1940 Bulgarian Bible (BG1940)</option>
     *
     * @param string $line
     * @return array An associative array with keys 'volumeName' and 'externalId'.
     */
    public function parseExternalIdAndVolumeName(string $line): array
    {
        $startPos = strpos($line, '>') + 1;
        $contentPart = substr($line, $startPos);
        $endPos = strpos($contentPart, '<');
        $content = substr($contentPart, 0, $endPos);

        $volumeEndPos = strrpos($content, '(');
        $volumeName = trim(substr($content, 0, $volumeEndPos - 1));

        $externalIdStartPos = $volumeEndPos + 1;
        $externalId = substr($content, $externalIdStartPos, -1);

        return [
            'volumeName' => $volumeName,
            'externalId' => $externalId,
        ];
    }
}
