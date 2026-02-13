<?php

namespace App\Services\Database;

use PDO;
use PDOException;
use Exception;
use InvalidArgumentException;
use App\Services\LoggerService;
use App\Configuration\Config;

/**
 * DatabaseService
 *
 * Thin wrapper around PDO with:
 * - Config-driven connection (via App\Configuration\Config)
 * - Safe nullable $dbConnection so closeConnection() can null it
 * - A canonical getPdo() that ALWAYS returns a live PDO or throws
 * - Helper query/transaction methods
 *
 * Expected config shape (from .env.local.php / .env.remote.php):
 *
 * return [
 *   'databases' => [
 *     'standard' => [
 *       'DB_HOST'      => '127.0.0.1',
 *       'DB_PORT'      => 3306,
 *       'DB_DATABASE'  => 'dbname',
 *       'DB_USERNAME'  => 'user',
 *       'DB_PASSWORD'  => 'pass',
 *       'DB_CHARSET'   => 'utf8mb4',
 *       'DB_COLLATION' => 'utf8mb4_unicode_ci',
 *       'PREFIX'       => '',
 *     ],
 *   ],
 * ];
 */
class DatabaseService
{
    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private int $port;
    private string $charset;
    private string $collation;
    private string $prefix = '';

    /**
     * Allow null so we can safely "close" by assigning null.
     * getPdo() will ensure this is non-null (or throw).
     */
    private ?PDO $dbConnection = null;

    /**
     * @param string $configType Which config profile to load (e.g. 'standard').
     *                           Resolved via Config::get("databases.$configType").
     *                           Config chooses .env.* based on APP_ENV/host.
     * @throws Exception if required config keys are missing.
     */
    public function __construct(string $configType = 'standard')
    {
        // Pull credentials from Config (.env.remote.php for cron with APP_ENV=remote)
        $databaseConfig = Config::get("databases.$configType");

        // Assign with safe defaults for optional items
        $this->host      = $databaseConfig['DB_HOST']      ?? 'localhost';
        $this->username  = $databaseConfig['DB_USERNAME']  ?? '';
        $this->password  = $databaseConfig['DB_PASSWORD']  ?? '';
        $this->database  = $databaseConfig['DB_DATABASE']  ?? '';
        $this->port      = (int)($databaseConfig['DB_PORT'] ?? 3306);
        $this->charset   = $databaseConfig['DB_CHARSET']   ?? 'utf8mb4';
        $this->collation = $databaseConfig['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
        $this->prefix    = $databaseConfig['PREFIX']       ?? '';

        // Attempt connection immediately; callers can catch PDOException.
        $this->connect();
    }

    /**
     * Establishes the PDO connection and sets attributes.
     * Throws PDOException on failure.
     */
    private function connect(): void
    {
        // Normalize/defaults
        $host = $this->host ?: '127.0.0.1';
        if (strcasecmp($host, 'localhost') === 0) {
            $host = '127.0.0.1';
        }
        $port = (int) ($this->port ?: 3306);
        $db   = (string) $this->database;
        $user = (string) $this->username;
        $pass = (string) $this->password;

        $charset   = $this->charset   ?: 'utf8mb4';
        $collation = $this->collation ?: 'utf8mb4_unicode_ci';

        // DSN sets charset (no separate SET NAMES)
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $db,
            $charset
        );

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $opts);

            // Force UTC and connection collation (no duplicate attr sets)
            $pdo->exec(
                "SET time_zone = '+00:00', " .
                "collation_connection = '{$collation}'"
            );

            $this->dbConnection = $pdo;
        } catch (\Throwable $e) {
            $safe = sprintf(
                'DB connect failed: %s (dsn=%s user=%s)',
                $e->getMessage(),
                $dsn,
                $user
            );
            if (class_exists(\App\Services\LoggerService::class)) {
                \App\Services\LoggerService::logError('db.connect', ['error' => $safe]);
            } else {
                error_log($safe);
            }
            throw new \RuntimeException($safe, previous: $e);
        }
    }


    /**
     * Returns true if we currently hold an open PDO connection.
     */
    public function isConnected(): bool
    {
        return $this->dbConnection instanceof PDO;
    }

    /**
     * Ensures we have an active connection; reconnects if necessary.
     * You can change this to only throw if you prefer not to reconnect.
     */
    public function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /**
     * Canonical getter: ALWAYS returns a live PDO instance or throws.
     * Prefer this everywhere instead of touching $dbConnection directly.
     *
     * @throws \RuntimeException if not connected and reconnection fails.
     */
    public function getPdo(): PDO
    {
        $this->ensureConnected();

        if (!$this->dbConnection) {
            // Should not happen because ensureConnected() re-connects or throws
            throw new \RuntimeException('Database not connected.');
        }

        return $this->dbConnection;
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->getPdo()->prepare($sql);
    }

    /**
     * Non-throwing getter for rare cases where you want to probe.
     * Returns null if not connected.
     */
    public function tryGetPdo(): ?PDO
    {
        return $this->dbConnection;
    }

    /**
     * @deprecated Use getPdo(). Left for older callers.
     */
    public function pdo(): PDO
    {
        return $this->getPdo();
    }

    /**
     * Executes a SQL query with optional parameters.
     *
     * - Uses getPdo() (ensures connection / throws on failure)
     * - Binds ints, bools, nulls with appropriate PDO types
     * - Supports positional (?) and named (:name) parameters
     *
     * @return \PDOStatement|null Returns statement on success, null on failure
     *                            (and logs details).
     */
    public function executeQuery(
        string $query,
        array $params = []
    ): ?\PDOStatement {
        try {
            $stmt = $this->getPdo()->prepare($query);

            foreach ($params as $key => $value) {
                // Choose correct PDO param type
                $type = match (true) {
                    is_int($value)  => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };

                // Positional params: array index is 0-based; PDO is 1-based
                if (is_int($key)) {
                    $stmt->bindValue($key + 1, $value, $type);
                } else {
                    // Named params: include leading ':' in $key or not, both ok
                    $name = $key[0] === ':' ? $key : (':' . $key);
                    $stmt->bindValue($name, $value, $type);
                }
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            // Log query and params for diagnostics (avoid secrets in production logs)
            $logMessage = print_r(
                ['query' => $query, 'params' => $params, 'error' => $e->getMessage()],
                true
            );
            LoggerService::logError('executeQuery', $logMessage);
            return null;
        }
    }

    /**
     * Fetches all rows as an array of associative arrays.
     * Returns [] on error or no results.
     */
    public function fetchAll(string $query, array $params = []): array
    {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Fetches a single row as an associative array.
     * Returns null on error or if no row.
     */
    public function fetchRow(string $query, array $params = []): ?array
    {
        $stmt = $this->executeQuery($query, $params);
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return ($row === false) ? null : $row;
    }

     
    /**
     * Alias for fetchRow() to support repositories that expect fetchOne().
     * Does not disturb current users.
     */
    public function fetchOne(string $query, array $params = []): ?array
    {
        return $this->fetchRow($query, $params);
    }

    /**
     * Convenience wrapper for executeQuery() that returns a boolean.
     * Useful when callers do not care about the statement.
     * Does not disturb current users.
     */
    public function execute(string $query, array $params = []): bool
    {
        return (bool) $this->executeQuery($query, $params);
    }

    /**
     * Execute a query and THROW on failure.
     *
     * This is the safest option inside transactions, because executeQuery()
     * currently logs + returns null (which would otherwise allow commit()).
     *
     * @throws \RuntimeException
     */
    public function executeOrFail(
        string $query,
        array $params = []
    ): \PDOStatement {
        $stmt = $this->executeQuery($query, $params);
        if (!$stmt) {
            $msg = 'Database query failed (see logs).';
            throw new \RuntimeException($msg);
        }
        return $stmt;
    }


    /**
     * Fetches a single scalar value (first column of first row).
     * Returns null on error or if no row/value.
     */
    public function fetchSingleValue(
        string $query,
        array $params = []
    ): mixed {
        $stmt = $this->executeQuery($query, $params);
        if (!$stmt) {
            return null;
        }
        $value = $stmt->fetchColumn();
        // Normalize false (no column) to null for consistency
        return ($value === false) ? null : $value;
    }

    /**
     * Fetches a single column across all rows.
     * Returns [] on error or no results.
     */
    public function fetchColumn(string $query, array $params = []): array
    {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Last inserted ID for the current connection.
     * Use after an INSERT into a table with AUTO_INCREMENT.
     */
    public function getLastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Closes the database connection (lets PDO be GC'd).
     * Safe because property is ?PDO.
     */
    public function closeConnection(): void
    {
        $this->dbConnection = null;
    }

    /**
     * Begin a new transaction.
     * Leaves autocommit mode and holds locks until commit()/rollBack().
     *
     * @throws PDOException if a transaction is already active or cannot start.
     */
    public function beginTransaction(): void
    {
        $this->getPdo()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @throws PDOException if commit fails or no transaction active.
     */
    public function commit(): void
    {
        $this->getPdo()->commit();
    }

    /**
     * Roll back current transaction.
     * Guarded by inTransaction() so it's safe in catch blocks.
     */
    public function rollBack(): void
    {
        $pdo = $this->tryGetPdo();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    /**
     * True if a transaction is currently active.
     */
    public function inTransaction(): bool
    {
        $pdo = $this->tryGetPdo();
        return $pdo ? $pdo->inTransaction() : false;
    }

    /**
     * Preferred table name helper: prefix + base, quoted with backticks.
     * Validates prefix so only [A-Za-z0-9_] are allowed.
     */
    public function table(string $base): string
    {
        $this->assertSafePrefix();
        return $this->quoteIdent($this->getPrefix() . $base);
    }

    /** Prefix getter for external callers (e.g., building dynamic SQL). */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** Validate once so no weird chars get into identifiers. */
    private function assertSafePrefix(): void
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/', (string)$this->prefix)) {
            throw new \RuntimeException('Invalid DB prefix.');
        }
    }

    /** Quote an identifier safely for MySQL (escapes backticks). */
    private function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
