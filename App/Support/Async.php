<?php
declare(strict_types=1);

namespace App\Support;

final class Async
{
    /**
     * Launch a PHP script detached from the current request.
     *
     * @param string      $script   Absolute or relative path to the PHP script.
     * @param array<int,string> $args     Positional CLI args (already without flags).
     * @param string|null $logFile  If provided, append stdout/stderr here.
     * @param string|null $cwd      Working directory (defaults to dirname($script)).
     * @return int|null   Best-effort PID (Windows via PowerShell, Unix via sh). Null if unknown.
     */
    public static function php(
        string $script,
        array $args = [],
        ?string $logFile = null,
        ?string $cwd = null
    ): ?int {
        // 0) Resolve paths
        $php = self::detectPhpBinary();
        $scriptPath = self::makeAbsolute($script, $cwd);

        if (!is_file($scriptPath)) {
            throw new \RuntimeException("Async: script not found: {$scriptPath}");
        }

        if ($cwd === null) {
            $cwd = \dirname($scriptPath);
        }
        if (!is_dir($cwd)) {
            throw new \RuntimeException("Async: working dir missing: {$cwd}");
        }

        // Ensure log directory exists
        if ($logFile) {
            $logDir = \dirname($logFile);
            if (!is_dir($logDir) && !@mkdir($logDir, 0777, true)) {
                throw new \RuntimeException("Async: cannot create log dir: {$logDir}");
            }
        }

        // 1) Build arg string with OS-appropriate quoting
        $isWindows = \stripos(PHP_OS_FAMILY, 'Windows') === 0;
        $q = $isWindows ? [self::class, 'qWin'] : [self::class, 'qPosix'];

        $cmdPhp    = $q($php);
        $cmdScript = $q($scriptPath);
        $cmdArgs   = \implode(' ', \array_map($q, $args));
        $cmdCore   = \trim("{$cmdPhp} {$cmdScript} {$cmdArgs}");

        // 2) Logging redirection
        $redir = '';
        if ($logFile) {
            $log = $isWindows ? self::qWin($logFile) : self::qPosix($logFile);
            // append both stdout & stderr
            $redir = " >> {$log} 2>&1";
        } else {
            if (!$isWindows) {
                $redir = ' >/dev/null 2>&1';
            }
        }

        // 3) Assemble a detached command by OS and try to grab a PID
        $pid = null;

        if ($isWindows) {
            // Prefer PowerShell to get a PID; fall back to 'start'.
            $ps = self::findPowerShell();
            if ($ps !== null) {
                // Start-Process returns an object; -PassThru gives us the PID.
                // NOTE: PowerShell quoting is its own world; we pass one -FilePath and one -ArgumentList string.
                $psCmd = self::buildPowerShellStartProcess($php, $scriptPath, $args, $logFile);
                $full  = $ps . ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ' . self::qWin($psCmd);

                self::execDetached($full, $cwd);
                // Try to read the PID that Start-Process echoed to the log (only if we redirected).
                // If no log file, we can’t easily capture the PID in a fully detached context, so return null.
                $pid = null;
            } else {
                // cmd.exe /c start "" /B <command> >> log 2>&1
                $full = 'cmd /c start "" /B ' . $cmdCore . $redir;
                self::execDetached($full, $cwd);
            }
        } else {
            // Use sh with nohup and echo $! to get PID
            $full = "nohup {$cmdCore}{$redir} & echo $!";
            $pid  = self::execGetPidPosix($full, $cwd);
        }

        // For dev observability, write a one-line launch record
        $line = \sprintf('[Async] cwd=%s cmd=%s', $cwd, $cmdCore . $redir);
        if ($logFile) {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
        } else {
            error_log($line);
        }

        return $pid;
    }

    // --------------------- internals ---------------------

    private static function detectPhpBinary(): string
    {
        // 1) Allow explicit override via env (set in .env or Apache SetEnv)
        $env = getenv('APP_PHP_BIN');
        if (is_string($env) && $env !== '') {
            $hit = self::which($env);
            if ($hit !== null) {
                return $hit;
            }
        }

        // 2) Use PHP_BINARY unless it's Apache's httpd.exe/apache.exe
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $bin = PHP_BINARY;
            if (!preg_match('/(?:httpd|apache)\.exe$/i', $bin)) {
                $hit = self::which($bin);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        // 3) Try PHP_BINDIR/php(.exe)
        if (defined('PHP_BINDIR') && PHP_BINDIR) {
            $php = rtrim(PHP_BINDIR, '\\/')
                . DIRECTORY_SEPARATOR
                . (DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php');
            $hit = self::which($php);
            if ($hit !== null) {
                return $hit;
            }
        }

        // 4) Common Windows installs
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach ([
                'C:\\ampp82\\php\\php.exe',
                'C:\\xampp\\php\\php.exe',
                'C:\\Program Files\\PHP\\php.exe',
            ] as $cand) {
                $hit = self::which($cand);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        // 5) Fall back to PATH
        $hit = self::which('php');
        return $hit ?? 'php';
    }


    private static function makeAbsolute(string $path, ?string $cwd): string
    {
        if (self::isAbsolutePath($path)) {
            return $path;
        }
        $base = $cwd ?? \getcwd();
        return rtrim($base ?: '', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private static function isAbsolutePath(string $p): bool
    {
        if (\stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            return (bool)\preg_match('/^[A-Za-z]:[\\\\\\/]/', $p) || \str_starts_with($p, '\\\\');
        }
        return \str_starts_with($p, '/');
    }

    // POSIX quoting
    private static function qPosix(string $s): string
    {
        return "'" . \str_replace("'", "'\\''", $s) . "'";
    }

    // Windows (cmd.exe) quoting
    private static function qWin(string $s): string
    {
        // Double-quote and escape internal quotes/backslashes safely for cmd.exe
        $s = \str_replace(['"', '^', '&', '%'], ['\\"', '^^', '^&', '%%'], $s);
        return '"' . $s . '"';
    }

    private static function findPowerShell(): ?string
    {
        // Prefer pwsh (PowerShell 7), fallback to Windows PowerShell
        $candidates = [
            'C:\\Program Files\\PowerShell\\7\\pwsh.exe',
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            'pwsh', 'powershell',
        ];
        foreach ($candidates as $exe) {
            $which = self::which($exe);
            if ($which !== null) {
                return $which;
            }
        }
        return null;
    }

    private static function buildPowerShellStartProcess(
        string $phpBin,
        string $scriptPath,
        array $args,
        ?string $logFile
    ): string {
        // Build ArgumentList as a single string: "<script>" "<a1>" "<a2>" ...
        $parts = array_merge([$scriptPath], $args);
        $quoted = array_map(
            fn($v) => '"' . str_replace('"', '""', (string)$v) . '"',
            $parts
        );
        $argList = implode(' ', $quoted);

        $redir = '';
        if ($logFile) {
            $redir =
                ' -RedirectStandardOutput "' . str_replace('"', '""', $logFile) . '"'
              . ' -RedirectStandardError  "' . str_replace('"', '""', $logFile) . '" -Append';
        }

        return 'Start-Process -FilePath ' . self::qWin($phpBin)
            . ' -ArgumentList ' . self::qWin($argList)
            . ' -WindowStyle Hidden -NoNewWindow -PassThru' . $redir
            . ' | Select-Object -ExpandProperty Id';
    }

    private static function which(string $exe): ?string
    {
        // Quick-and-dirty PATH probe
        if (self::isAbsolutePath($exe) && is_file($exe)) {
            return $exe;
        }
        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
        foreach ($paths as $p) {
            $full = rtrim($p, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $exe;
            if (is_file($full)) {
                return $full;
            }
        }
        return null;
    }

    private static function execDetached(string $command, string $cwd): void
    {
        // Detach without capturing output
        $spec = [
            0 => ['file', (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null', 'a'],
            2 => ['file', (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null', 'a'],
        ];
        $proc = @proc_open($command, $spec, $pipes, $cwd);
        if (\is_resource($proc)) {
            @proc_close($proc); // immediately return
        } else {
            throw new \RuntimeException("Async: failed to spawn process.");
        }
    }

    private static function execGetPidPosix(string $command, string $cwd): ?int
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'a'],
        ];
        $proc = @proc_open($command, $descriptors, $pipes, $cwd);
        if (!\is_resource($proc)) {
            return null;
        }
        $pidStr = stream_get_contents($pipes[1]);
        @fclose($pipes[1]);
        @proc_close($proc);
        $pid = \is_string($pidStr) ? (int)\trim($pidStr) : 0;
        return $pid > 0 ? $pid : null;
    }
}
