<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class I18nTranslationsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Fetch translations by Google code (e.g., 'fr', 'zh-CN').
     * Returns rows like:
     *   ['stringId'=>int,'translatedText'=>string]
     */
    public function fetchByStringIdsAndLanguageGoogle(
        array $stringIds,
        string $languageCodeGoogle
    ): array {
        if (empty($stringIds)) {
            return [];
        }

        [$in, $params] = $this->buildInParams($stringIds, 'id');

        $sql = "SELECT stringId, translatedText
                  FROM i18n_translations
                 WHERE stringId IN ($in)
                   AND languageCodeGoogle = :g";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':g', strtolower($languageCodeGoogle));

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }

        $stmt->execute();

        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'stringId'       => (int)$r['stringId'],
                'translatedText' => (string)$r['translatedText'],
            ];
        }

        return $out;
    }

    /**
     * Upsert a Google-language translation.
     */
    public function upsertGoogle(
        int $stringId,
        string $google,
        string $text
    ): void {
        $sql = "INSERT INTO i18n_translations
                    (stringId, languageCodeGoogle, translatedText,
                     createdAt, updatedAt)
                VALUES
                    (:sid, :g, :txt, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    translatedText = VALUES(translatedText),
                    updatedAt = UTC_TIMESTAMP()";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sid', $stringId, PDO::PARAM_INT);
        $stmt->bindValue(':g', strtolower($google));
        $stmt->bindValue(':txt', $text);
        $stmt->execute();
    }

    /**
     * Build a safe IN(...) list with named params.
     *
     * @return array{0:string,1:array<string,int>}
     */
    private function buildInParams(
        array $ids,
        string $prefix = 'id'
    ): array {
        $params = [];
        $ph = [];
        $i = 0;

        foreach ($ids as $id) {
            $key = ':' . $prefix . $i++;
            $ph[] = $key;
            $params[$key] = (int)$id;
        }

        return [implode(',', $ph), $params];
    }
}
