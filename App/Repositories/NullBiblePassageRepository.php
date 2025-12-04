<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Null-object repository to satisfy DI for controllers that expect a repository.
 * Replace with a real concrete when ready.
 */
final class NullBiblePassageRepository implements PassageRepository
{
    // If you have an interface, implement it here. If not, leave empty —
    // PHP-DI only needs the class to exist for resolution during smoke tests.
}
