<?php

declare(strict_types=1);

namespace App\Repository;

use App\Services\Database\DatabaseService;

final class NumeralSystemRepository
{
    public function __construct(private DatabaseService $db)
    {
    }

    /**
     * @return array<string,string>|null
     *   Keys: code, description, digit0..digit9,
     *         chapter_sep, range_sep
     */
    public function findOneByCode(string $code): ?array
    {
        $sql = 'SELECT code, description,
                       digit0, digit1, digit2, digit3, digit4,
                       digit5, digit6, digit7, digit8, digit9,
                       chapter_sep, range_sep
                  FROM numeral_systems
                 WHERE code = :code
                 LIMIT 1';

        $row = $this->db->fetchRow($sql, [':code' => $code]);

        return $row === false ? null : $row;
    }
}
