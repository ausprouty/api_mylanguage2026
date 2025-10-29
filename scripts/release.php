<?php
declare(strict_types=1);

/**
 * Release helper invoked by Composer:
 *   composer run release
 *
 * Steps:
 *  - ensure build script is executable
 *  - git add/commit (skip commit if no changes)
 *  - create annotated tag with timestamp
 *  - push with tags
 *  - run scripts/build-release.sh
 */

$root = realpath(__DIR__ . '/..') ?: '.';
chdir($root);

function run(string $cmd, bool $allowFail = false) : void
{
    passthru($cmd, $code);
    if ($code !== 0 && !$allowFail) {
        fwrite(STDERR, "ERROR: command failed: {$cmd}\n");
        exit($code);
    }
}

function execCapture(string $cmd) : string
{
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        return '';
    }
    return implode("\n", $out);
}

function hasGit() : bool
{
    $v = execCapture('git --version');
    return stripos($v, 'git version') !== false;
}

function ensureCleanIndexOrCommit() : bool
{
    // Are there staged or unstaged changes?
    $porcelain = execCapture('git status --porcelain=1');
    return trim($porcelain) !== '';
}

if (!hasGit()) {
    fwrite(STDERR, "ERROR: git not found in PATH.\n");
    exit(1);
}

$build = $root . '/scripts/build-release.sh';
if (!is_file($build)) {
    fwrite(STDERR, "ERROR: missing {$build}\n");
    exit(1);
}

// 1) Ensure build script is executable (best-effort on Windows/Git Bash)
run(sprintf('chmod +x %s', escapeshellarg($build)), true);

// 2) Stage everything
run('git add -A');

// 3) Commit if there are changes; otherwise continue silently
if (ensureCleanIndexOrCommit()) {
    $msg = 'Queue SELECT: inline numeric LIMIT';
    // Allow empty commit message customization via env if desired
    $envMsg = getenv('RELEASE_COMMIT_MSG');
    if (is_string($envMsg) && $envMsg !== '') {
        $msg = $envMsg;
    }
    run(sprintf('git commit -m %s', escapeshellarg($msg)), true);
}

// 4) Create an annotated tag
$ts = (new DateTimeImmutable('now'))->format('Ymd-His');
$tag = "release-{$ts}";
$tagMsg = 'Release with inline LIMIT';
$envTagMsg = getenv('RELEASE_TAG_MSG');
if (is_string($envTagMsg) && $envTagMsg !== '') {
    $tagMsg = $envTagMsg;
}

// If tag already exists (rare), append a suffix
$existing = execCapture(sprintf('git tag --list %s', escapeshellarg($tag)));
if (trim($existing) === $tag) {
    $tag .= '-1';
}

run(sprintf(
    'git tag -a %s -m %s',
    escapeshellarg($tag),
    escapeshellarg($tagMsg)
));

// 5) Push with tags (respects your current upstream)
// Use --follow-tags so only annotated tags go up
run('git push --follow-tags');

// 6) Run your build script
run(escapeshellcmd($build));

echo "\nDone. Created and pushed tag: {$tag}\n";
