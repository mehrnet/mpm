<?php

/**
 * MPM - Mehr's Package Manager
 *
 * A minimal PHP universal package manager and command interpreter
 * that provides:
 * - Built-in commands (ls, cat, rm, mkdir, cp, echo, env, pkg)
 * - FILE_PATH-based command discovery
 * - API key authentication
 * - Plain text responses with HTTP status codes or CLI exit codes
 * - Package management with dependency resolution and checksum verification
 *
 * Usage (HTTP): GET /mpm.php/API_KEY/COMMAND/ARG0/ARG1/...
 * Usage (CLI):  php mpm.php COMMAND ARG0 ARG1 ...
 *
 * Commands are resolved via FILE_PATH patterns: path/to/[name]/handler.php
 * Handlers return strings or throw \RuntimeException for errors
 *
 * @requires PHP 7.0+
 */

declare(strict_types=1);

// ============================================================================
// Configuration
// ============================================================================

const APP_VERSION = '0.1.2';

const BUILTIN_COMMANDS = [
    'ls' => 'handleLs',
    'cat' => 'handleCat',
    'rm' => 'handleRm',
    'mkdir' => 'handleMkdir',
    'cp' => 'handleCp',
    'echo' => 'handleEcho',
    'env' => 'handleEnv',
    'pkg' => 'handlePkg',
];

const INITIAL_CONFIG = [
    'FILE_REPOS' => [
        'main' => [
            'https://raw.githubusercontent.com/mehrnet/mpm-repo/refs/heads/main/main',
        ],
    ],
    'FILE_PATH' => [
        'app/packages/[name]/handler.php',
        'bin/[name].php',
    ],
];

// ============================================================================
// Directories
// ============================================================================

const DIR_CONFIG  = '.config';
const DIR_CACHE   = '.cache';

// ============================================================================
// Configuration Files
// ============================================================================

const FILE_KEY       = DIR_CONFIG . '/key';
const FILE_REPOS     = DIR_CONFIG . '/repos.json';
const FILE_PACKAGES  = DIR_CONFIG . '/packages.json';
const FILE_PATH      = DIR_CONFIG . '/path.json';

// ============================================================================
// Cache Files
// ============================================================================

const FILE_LOCK = DIR_CACHE . '/mpm.lock';

// ============================================================================
// Settings
// ============================================================================

const LOCK_TIMEOUT = 60; // seconds
const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

// ============================================================================
// Messages
// ============================================================================

const MSG_PKG_LOCKED = "Another pkg operation is in progress. "
    . "If you want to proceed: remove " . FILE_LOCK . " or try `pkg unlock`";
const MSG_PKG_UNLOCK = "";

// ============================================================================
// Runtime Detection
// ============================================================================

/**
 * Detect if running in CLI mode
 *
 * @return bool
 */
function isCli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

// ============================================================================
// Compatibility Check
// ============================================================================

const MIN_PHP_VERSION = '7.0.0';

const REQUIRED_FUNCTIONS = [
    'json_encode',
    'json_decode',
    'file_get_contents',
    'file_put_contents',
    'hash_equals',
    'hash_file',
    'mkdir',
    'is_dir',
    'file_exists',
];

const REQUIRED_CLASSES = [
    'ZipArchive',
];

/**
 * Run compatibility checks and return array of errors
 *
 * @return array{php_version: ?string, functions: string[],
 *               classes: string[], permissions: ?string}
 */
function checkCompatibility(): array
{
    $errors = [
        'php_version' => null,
        'functions' => [],
        'classes' => [],
        'permissions' => null,
    ];

    // Check PHP version
    if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
        $errors['php_version'] = PHP_VERSION;
    }

    // Check required functions
    foreach (REQUIRED_FUNCTIONS as $func) {
        if (!function_exists($func)) {
            $errors['functions'][] = $func;
        }
    }

    // Check required classes
    foreach (REQUIRED_CLASSES as $class) {
        if (!class_exists($class)) {
            $errors['classes'][] = $class;
        }
    }

    // Check write permissions
    $testFile = __DIR__ . '/.mpm_write_test_' . getmypid();
    $canWrite = @file_put_contents($testFile, 'test') !== false;
    if ($canWrite) {
        @unlink($testFile);
    } else {
        $errors['permissions'] = __DIR__;
    }

    return $errors;
}

/**
 * Check if there are any compatibility errors
 *
 * @param array $errors Result from checkCompatibility()
 * @return bool
 */
function hasCompatibilityErrors(array $errors): bool
{
    return $errors['php_version'] !== null
        || !empty($errors['functions'])
        || !empty($errors['classes'])
        || $errors['permissions'] !== null;
}

/**
 * Render compatibility error page and exit
 *
 * @param array $errors Result from checkCompatibility()
 * @return void
 */
function renderCompatibilityError(array $errors)
{
    if (isCli()) {
        echo "MPM Compatibility Error\n";
        echo "=======================\n\n";

        if ($errors['php_version'] !== null) {
            echo "✗ PHP Version: {$errors['php_version']} "
                . "(requires " . MIN_PHP_VERSION . "+)\n";
        }

        foreach ($errors['functions'] as $func) {
            echo "✗ Missing function: {$func}()\n";
        }

        foreach ($errors['classes'] as $class) {
            echo "✗ Missing class: {$class}\n";
        }

        if ($errors['permissions'] !== null) {
            echo "✗ No write permission: {$errors['permissions']}\n";
        }

        exit(1);
    }

    http_response_code(500);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPM - Compatibility Error</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI',
                Roboto, sans-serif;
            background: #0d0d0d;
            color: #e5e5e5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            width: 100%;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 32px;
        }
        h1 {
            color: #f34e3f;
            font-size: 1.5em;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #888;
            margin-bottom: 24px;
        }
        .check {
            padding: 12px;
            margin: 8px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .check-fail {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .check-pass {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }
        .icon { margin-right: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Compatibility Error</h1>
        <p class="subtitle">MPM cannot run on this server.</p>

        <?php if ($errors['php_version'] !== null) : ?>
        <div class="check check-fail">
            <span class="icon">✗</span>
            PHP <?php echo htmlspecialchars($errors['php_version']); ?>
            (requires <?php echo MIN_PHP_VERSION; ?>+)
        </div>
        <?php else : ?>
        <div class="check check-pass">
            <span class="icon">✓</span>
            PHP <?php echo PHP_VERSION; ?>
        </div>
        <?php endif; ?>

        <?php foreach (REQUIRED_FUNCTIONS as $func) : ?>
            <?php if (in_array($func, $errors['functions'])) : ?>
        <div class="check check-fail">
            <span class="icon">✗</span>
                <?php echo htmlspecialchars($func); ?>()
        </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach (REQUIRED_CLASSES as $class) : ?>
            <?php if (in_array($class, $errors['classes'])) : ?>
        <div class="check check-fail">
            <span class="icon">✗</span>
                <?php echo htmlspecialchars($class); ?> (ext-zip)
        </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($errors['permissions'] !== null) : ?>
        <div class="check check-fail">
            <span class="icon">✗</span>
            Write permission:
            <?php echo htmlspecialchars($errors['permissions']); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    exit(1);
}

/**
 * Run compatibility check and abort if errors found
 */
function ensureCompatibility()
{
    $errors = checkCompatibility();
    if (hasCompatibilityErrors($errors)) {
        renderCompatibilityError($errors);
    }
}

/**
 * Generate a random key
 *
 * @param int $length Length of the key
 * @return string Random alphanumeric key
 */
function generateKey(int $length = 64): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $key;
}

// ============================================================================
// Response Helper
// ============================================================================

/**
 * Send error response and abort request
 *
 * @param string $message Response message
 * @param int $statusCode HTTP status code (default: 400)
 * @return void
 */
function abort(string $message, int $statusCode = 400)
{
    http_response_code($statusCode);
    echo $message;
    exit(1);
}

// ============================================================================
// Request Lifecycle
// ============================================================================

/**
 * Ensure parent directory exists for a given file path
 */
function ensureParentDir(string $filePath)
{
    $dir = dirname($filePath);
    if ($dir !== '.' && !is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

/**
 * Ensure FILE_KEY file exists (first run)
 */
function ensureKeyFile()
{
    if (!file_exists(FILE_KEY)) {
        ensureParentDir(FILE_KEY);
        $key = generateKey(64);
        file_put_contents(FILE_KEY, $key);

        // Show different output based on mode
        if (!isCli()) {
            renderWelcome($key, checkCompatibility());
            exit(0);
        } else {
            // CLI mode: just print key to STDOUT
            echo "API key generated: $key\n";
            echo "Saved to: " . FILE_KEY . "\n";
            exit(0);
        }
    }
}


/**
 * Get lock timeout based on server's max_execution_time
 * Falls back to LOCK_TIMEOUT (60s) if unlimited
 */
function pkgGetLockTimeout(): int
{
    $maxExecTime = (int)ini_get('max_execution_time');

    // If unlimited (0), use default 60 seconds
    if ($maxExecTime === 0) {
        return LOCK_TIMEOUT;
    }

    return $maxExecTime;
}

/**
 * Check if package lock file exists and is still valid
 * Returns true if locked, false if not locked or expired
 */
function pkgIsLocked(): bool
{
    if (!file_exists(FILE_LOCK)) {
        return false;
    }

    $lockData = json_decode(file_get_contents(FILE_LOCK), true);
    $lockExpiration = $lockData['expires_at'] ?? 0;

    // If lock has expired, remove it
    if (time() > $lockExpiration) {
        @unlink(FILE_LOCK);
        return false;
    }

    // Lock is still valid
    return true;
}

/**
 * Acquire lock for package operations
 * Returns true if lock acquired, false if already locked
 */
function pkgAcquireLock(): bool
{
    if (pkgIsLocked()) {
        return false;
    }

    // Create new lock file with expiration
    ensureParentDir(FILE_LOCK);
    $timeout = pkgGetLockTimeout();
    $lockData = [
        'pid' => getmypid(),
        'created_at' => time(),
        'expires_at' => time() + $timeout,
        'timeout_seconds' => $timeout
    ];

    $content = json_encode($lockData, JSON_PRETTY_PRINT);
    file_put_contents(FILE_LOCK, $content);
    return true;
}

/**
 * Release lock after package operations complete
 */
function pkgReleaseLock()
{
    @unlink(FILE_LOCK);
}

/**
 * Force remove lock file (for manual recovery)
 */
function pkgUnlock(): string
{
    if (file_exists(FILE_LOCK)) {
        @unlink(FILE_LOCK);
    }
    return MSG_PKG_UNLOCK;
}

/**
 * Ensure FILE_REPOS file exists (first run)
 * Uses INITIAL_CONFIG['FILE_REPOS'] as default if file doesn't exist
 */
function ensureReposFile()
{
    if (!file_exists(FILE_REPOS)) {
        ensureParentDir(FILE_REPOS);
        $content = json_encode(
            INITIAL_CONFIG['FILE_REPOS'],
            JSON_FLAGS
        );
        file_put_contents(FILE_REPOS, $content);
    }
}

/**
 * Ensure FILE_PACKAGES file exists (first run)
 */
function ensurePackagesFile()
{
    if (!file_exists(FILE_PACKAGES)) {
        ensureParentDir(FILE_PACKAGES);
        $now = date('c');
        $packages = [
            'createdAt' => $now,
            'updatedAt' => $now,
            'packages' => [],
        ];
        $content = json_encode($packages, JSON_FLAGS);
        file_put_contents(FILE_PACKAGES, $content);
    }
}

/**
 * Ensure FILE_PATH file exists (first run)
 * Uses INITIAL_CONFIG['FILE_PATH'] as default if file doesn't exist
 */
function ensurePathFile()
{
    if (!file_exists(FILE_PATH)) {
        ensureParentDir(FILE_PATH);
        $content = json_encode(
            INITIAL_CONFIG['FILE_PATH'],
            JSON_FLAGS
        );
        file_put_contents(FILE_PATH, $content);
    }
}

/**
 * Ensure secure permissions on .config/ and .cache/ directories
 * Directories: 0700 (owner only)
 * Files: 0600 (owner only)
 */
function ensureSecurePermissions()
{
    $dirs = [DIR_CONFIG, DIR_CACHE];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        // Set directory permission to 0700
        @chmod($dir, 0700);

        // Set all files in directory to 0600
        $files = @scandir($dir);
        if ($files === false) {
            continue;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_file($path)) {
                @chmod($path, 0600);
            } elseif (is_dir($path)) {
                @chmod($path, 0700);
            }
        }
    }
}

/**
 * Ensure .htaccess exists with security rules to block hidden files/directories
 * Protects .config/, .cache/, and any path starting with .
 */
function ensureHtaccess()
{
    $htaccessFile = '.htaccess';
    $securityRule = <<<'HTACCESS'
# MPM Security: Block access to hidden files and directories
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule (^|/)\.(?!well-known/) - [F,L]
</IfModule>

# Fallback for servers without mod_rewrite
<FilesMatch "^\.">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;

    $marker = '# MPM Security:';

    if (file_exists($htaccessFile)) {
        $content = file_get_contents($htaccessFile);
        if (strpos($content, $marker) !== false) {
            return;
        }
        $content = $securityRule . "\n\n" . $content;
        file_put_contents($htaccessFile, $content);
    } else {
        file_put_contents($htaccessFile, $securityRule . "\n");
    }
}

/**
 * Load repositories configuration from FILE_REPOS
 */
function loadReposConfig(): array
{
    ensureReposFile();

    $content = file_get_contents(FILE_REPOS);
    $repos = json_decode($content, true);

    if (!is_array($repos) || empty($repos)) {
        throw new \RuntimeException('Invalid repositories configuration');
    }

    return $repos;
}

/**
 * Load packages configuration from FILE_PACKAGES
 */
function loadPackagesConfig(): array
{
    if (!file_exists(FILE_PACKAGES)) {
        ensurePackagesFile();
    }

    $content = file_get_contents(FILE_PACKAGES);
    $config = json_decode($content, true);

    if (!$config) {
        throw new \RuntimeException('Invalid packages configuration');
    }

    return $config;
}

/**
 * Save packages configuration to FILE_PACKAGES
 */
function savePackagesConfig(array $config)
{
    $config['updatedAt'] = date('c');
    $content = json_encode($config, JSON_FLAGS);
    file_put_contents(FILE_PACKAGES, $content);
}

/**
 * Parse PATH_INFO into [key, command, args]
 *
 * Path format: /mpm.php/API_KEY/COMMAND/ARG0/ARG1/...
 *
 * @return array{key: ?string, command: ?string, args: string[]}
 */
function parsePath(): array
{
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $parts = array_filter(explode('/', trim($pathInfo, '/')));
    $parts = array_values($parts);

    return [
        'key' => $parts[0] ?? null,
        'command' => $parts[1] ?? null,
        'args' => array_slice($parts, 2),
    ];
}

/**
 * Parse CLI arguments into [key, command, args]
 *
 * Format: php mpm.php COMMAND ARG0 ARG1 ...
 * Key is auto-loaded from FILE_KEY
 *
 * @return array{key: ?string, command: ?string, args: string[]}
 */
function parseCliArgs(): array
{
    global $argv;

    // $argv[0] is script name, rest are arguments
    $args = array_slice($argv, 1);

    // Auto-load key from FILE_KEY
    $key = null;
    if (file_exists(FILE_KEY)) {
        $key = trim(file_get_contents(FILE_KEY));
    }

    return [
        'key' => $key,
        'command' => $args[0] ?? null,
        'args' => array_slice($args, 1),
    ];
}

/**
 * Validate API key
 *
 * @param ?string $providedKey
 * @return bool
 */
function validateKey($providedKey): bool
{
    if (!$providedKey) {
        return false;
    }

    if (!file_exists(FILE_KEY)) {
        return false;
    }

    $storedKey = trim(file_get_contents(FILE_KEY));
    return hash_equals($storedKey, $providedKey);
}

/**
 * Validate API key for CLI mode
 *
 * In CLI mode, key is auto-loaded from FILE_KEY.
 * Just verify the key was loaded successfully.
 *
 * @param ?string $providedKey
 * @return bool
 */
function validateCliKey($providedKey): bool
{
    // In CLI mode, just ensure key file exists and was loaded
    return $providedKey !== null && file_exists(FILE_KEY);
}

/**
 * Validate file path to prevent directory traversal and absolute access
 *
 * @param string $path File or directory path
 * @throws RuntimeException If path is invalid
 */
function validatePath(string $path)
{
    // Prevent absolute paths
    if (str_starts_with($path, '/')) {
        throw new \RuntimeException(
            'Permission denied',
            403
        );
    }

    // Prevent directory traversal (defense in depth - web server may not
    // always normalize)
    if (strpos($path, '..') !== false) {
        throw new \RuntimeException(
            'Permission denied',
            403
        );
    }
}

/**
 * Execute the requested command
 *
 * @param string $command
 * @param string[] $args
 * @return string Response content
 * @throws RuntimeException On command not found or execution error
 */
function executeCommand(string $command, array $args): string
{
    // Check if built-in command
    if (isset(BUILTIN_COMMANDS[$command])) {
        $handler = BUILTIN_COMMANDS[$command];
        return $handler($args);
    }

    // Load external package handler
    return loadExternalPackageHandler($command, $args);
}

/**
 * Load and execute external package handler
 *
 * Searches through FILE_PATH patterns to find the command handler
 *
 * @param string $command
 * @param string[] $args
 * @return string Response content
 * @throws RuntimeException On command not found or handler error
 */
function loadExternalPackageHandler(string $command, array $args): string
{
    $patterns = loadPathPatterns();

    foreach ($patterns as $pattern) {
        $handlerPath = str_replace('[name]', $command, $pattern);

        if (file_exists($handlerPath)) {
            try {
                $handler = require $handlerPath;

                if (!is_callable($handler)) {
                    throw new \RuntimeException(
                        "Handler for '$command' is not callable"
                    );
                }

                return $handler($args);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Error executing handler: {$e->getMessage()}"
                );
            }
        }
    }

    throw new \RuntimeException("Command '$command' not found");
}

/**
 * Load command handler patterns from FILE_PATH
 *
 * @return string[]
 */
function loadPathPatterns(): array
{
    ensurePathFile();

    $content = file_get_contents(FILE_PATH);
    $patterns = json_decode($content, true);

    if (!is_array($patterns) || empty($patterns)) {
        return INITIAL_CONFIG['FILE_PATH'];
    }

    return $patterns;
}

/**
 * Send response as plain text with appropriate headers
 *
 * @param string $content Response content
 * @param int $statusCode HTTP status code
 */
function sendResponse(string $content, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
}

/**
 * Send CLI response to STDOUT
 *
 * @param string $content Response content
 * @param int $exitCode Exit code (0 for success)
 */
function sendCliResponse(string $content, int $exitCode = 0)
{
    if ($content !== '') {
        fwrite(STDOUT, $content);
        if (substr($content, -1) !== "\n") {
            fwrite(STDOUT, "\n");
        }
    }
    exit($exitCode);
}

/**
 * Send CLI error and exit
 *
 * @param string $message Error message
 * @param int $exitCode Exit code (default: 1)
 * @return void
 */
function abortCli(string $message, int $exitCode = 1)
{
    fwrite(STDERR, "Error: $message\n");
    exit($exitCode);
}

// ============================================================================
// Package Management System
// ============================================================================

/**
 * pkg - Package management command
 *
 * Actions:
 *   add PACKAGE [PACKAGE...]  - Install packages with dependencies
 *   del PACKAGE               - Uninstall package
 *   upgrade [PACKAGE]         - Upgrade all or specific package
 *   update                    - Refresh repository cache
 *   list [FILTER]             - List installed packages
 *   search KEYWORD            - Search available packages
 *   info PACKAGE              - Show package details
 *
 * @param string[] $args
 * @return string
 * @throws RuntimeException|InvalidArgumentException On invalid action or error
 */
function handlePkg(array $args): string
{
    $action = $args[0] ?? 'list';

    switch ($action) {
        case 'add':
        case 'install':
            $packages = array_slice($args, 1);
            if (empty($packages)) {
                $msg = 'Usage: pkg add PACKAGE1 [PACKAGE2 ...]';
                throw new \InvalidArgumentException($msg);
            }
            return pkgAdd($packages);

        case 'del':
        case 'delete':
            $package = $args[1] ?? null;
            if (!$package) {
                throw new \InvalidArgumentException('Usage: pkg del PACKAGE');
            }
            return pkgRemove($package);

        case 'upgrade':
            $package = $args[1] ?? null;
            return pkgUpdate($package);

        case 'update':
            return pkgRefresh();

        case 'list':
            $filter = $args[1] ?? null;
            return pkgList($filter);

        case 'search':
            $keyword = $args[1] ?? null;
            if (!$keyword) {
                $msg = 'Usage: pkg search KEYWORD';
                throw new \InvalidArgumentException($msg);
            }
            return pkgSearch($keyword);

        case 'info':
        case 'show':
            $package = $args[1] ?? null;
            if (!$package) {
                $msg = 'Usage: pkg info PACKAGE';
                throw new \InvalidArgumentException($msg);
            }
            return pkgInfo($package);

        case 'unlock':
            return pkgUnlock();

        case 'help':
            return pkgHelp();

        case 'version':
            return pkgVersion();

        default:
            $msg = "Unknown action: $action\nRun 'pkg help' for available "
                . "actions";
            throw new \RuntimeException($msg);
    }
}

/**
 * Show package manager help
 */
function pkgHelp(): string
{
    return <<<'EOF'
MPM - Mehr's Package Manager

USAGE: pkg <action> [options]

ACTIONS:
  add [PACKAGE ...]       Install packages with dependency resolution
  del <PACKAGE>           Remove a package (fails if dependencies exist)
  upgrade [PACKAGE]       Upgrade all packages or specific package to latest
  update                  Refresh repository cache
  list [FILTER]           List installed packages (optionally filter by name)
  search <KEYWORD>        Search packages by name/description
  info <PACKAGE>          Show package details and available versions
  unlock                  Force remove lock file (for manual recovery)
  help                    Show this help message
  version                 Show package manager version

EXAMPLES:
  pkg add users auth
  pkg search database
  pkg list
  pkg info users
  pkg del users
  pkg upgrade
  pkg unlock
EOF;
}

/**
 * Show package manager version
 */
function pkgVersion(): string
{
    return "MPM - Mehr's Package Manager v" . APP_VERSION;
}

/**
 * Fetch repository database with mirror failover
 */
function pkgFetchDatabase(string $repo = 'main'): array
{
    $repos = loadReposConfig();
    $mirrors = $repos[$repo] ?? [];

    if (empty($mirrors)) {
        throw new \RuntimeException("Repository not found: $repo", 404);
    }

    $lastError = null;
    foreach ($mirrors as $mirror) {
        try {
            $cleanMirror = rtrim($mirror, '/');
            $url = "$cleanMirror/database.json";
            $httpOpts = [
                'timeout' => 30,
                'user_agent' => 'mpm-pkg/1.0',
            ];
            $httpsOpts = $httpOpts + ['verify_peer' => true];
            $context = stream_context_create([
                'http' => $httpOpts,
                'https' => $httpsOpts,
            ]);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $lastError = "Mirror $mirror: connection failed";
                continue;
            }

            $database = json_decode($response, true);
            if (!$database || !isset($database['packages'])) {
                $lastError = "Mirror $mirror: invalid format";
                continue;
            }

            return $database['packages'];
        } catch (\Throwable $e) {
            $lastError = "Mirror $mirror: {$e->getMessage()}";
            continue;
        }
    }

    $msg = "Failed to fetch database from mirrors: $lastError";
    throw new \RuntimeException($msg, 503);
}

/**
 * Download and verify all packages with checksum validation
 *
 * Downloads all packages, verifies checksums, redownloads any with mismatches.
 * Only proceeds if ALL packages are successfully downloaded and verified.
 *
 * @param array $toInstall Package names to install
 * @param array $database Package database
 * @return array Downloaded package metadata (zipFile, url, downloadedAt,
 *               checksum, repository)
 */
function pkgDownloadAllPackages(array $toInstall, array $database): array
{
    @mkdir(DIR_CACHE, 0755, true);
    $repos = loadReposConfig();

    $downloadedPackages = [];
    $failedPackages = [];

    // Phase 1: Download and verify all packages
    foreach ($toInstall as $pkg) {
        $pkgData = $database[$pkg];
        $version = $pkgData['latest'];
        $versionData = $pkgData['versions'][$version];
        $checksum = $versionData['checksum'];
        $repo = 'main'; // TODO: make repo configurable per package

        try {
            $meta = pkgDownloadSinglePackage(
                $pkg,
                $version,
                $checksum,
                $repo,
                $repos
            );
            $downloadedPackages[$pkg] = $meta;
        } catch (\RuntimeException $e) {
            $failedPackages[$pkg] = $e->getMessage();
        }
    }

    // If any packages failed, report all failures and abort
    if (!empty($failedPackages)) {
        $errorMap = array_map(
            function ($pkg, $err) {
                return "$pkg: $err";
            },
            array_keys($failedPackages),
            $failedPackages
        );
        $failures = implode("\n  - ", $errorMap);
        throw new \RuntimeException(
            "Failed to download and verify packages:\n  - $failures",
            503
        );
    }

    return $downloadedPackages;
}

/**
 * Download a single package with strict checksum verification
 *
 * Returns metadata about the download including:
 * - zipFile: path to cached ZIP file
 * - url: download URL used
 * - downloadedAt: ISO 8601 timestamp
 * - checksum: verified checksum
 * - repository: repository name
 *
 * @return array Download metadata (zipFile, url, downloadedAt,
 *               checksum, repository)
 */
function pkgDownloadSinglePackage(
    string $name,
    string $version,
    string $checksum,
    string $repo,
    array $repos
): array {
    $mirrors = $repos[$repo] ?? [];

    if (empty($mirrors)) {
        throw new \RuntimeException("Repository not found: $repo");
    }

    $cacheFile = DIR_CACHE . "/$name-$version.zip";

    // Check cache first - verify it still matches the database checksum
    if (file_exists($cacheFile)) {
        if (pkgVerifyChecksum($cacheFile, $checksum)) {
            return [
                'zipFile' => $cacheFile,
                'url' => 'cached',
                'downloadedAt' => date('c'),
                'checksum' => $checksum,
                'repository' => $repo,
            ];
        }
        // Cached file has wrong checksum - delete it and re-download
        @unlink($cacheFile);
    }

    $lastError = null;

    foreach ($mirrors as $mirror) {
        try {
            $cleanMirror = rtrim($mirror, '/');
            $url = "$cleanMirror/$name-$version.zip";

            $httpOpts = [
                'timeout' => 60,
                'user_agent' => 'mpm-pkg/1.0',
            ];
            $httpsOpts = $httpOpts + ['verify_peer' => true];
            $context = stream_context_create([
                'http' => $httpOpts,
                'https' => $httpsOpts,
            ]);
            $data = @file_get_contents($url, false, $context);

            if ($data === false) {
                $lastError = "Mirror $mirror: download failed";
                continue;
            }

            if (!file_put_contents($cacheFile, $data)) {
                $lastError = "Cannot write cache file";
                continue;
            }

            // STRICT VERIFICATION: Checksum must match exactly
            if (!pkgVerifyChecksum($cacheFile, $checksum)) {
                @unlink($cacheFile);
                $msg = "Checksum mismatch from mirror $mirror - ";
                $msg .= "file corrupted or wrong version";
                $lastError = $msg;
                continue;
            }

            // Success - return metadata
            return [
                'zipFile' => $cacheFile,
                'url' => $url,
                'downloadedAt' => date('c'),
                'checksum' => $checksum,
                'repository' => $repo,
            ];
        } catch (\Throwable $e) {
            $lastError = "Mirror $mirror: {$e->getMessage()}";
            continue;
        }
    }

    $msg = "Failed to download $name-$version with valid checksum: ";
    $msg .= $lastError;
    throw new \RuntimeException($msg);
}

/**
 * Verify package checksum (format: sha256:abc123...)
 *
 * @param string $file Path to file to verify
 * @param string $expected Expected checksum in format "algo:hash"
 * @return bool True if checksum matches
 */
function pkgVerifyChecksum(string $file, string $expected): bool
{
    if (!file_exists($file)) {
        return false;
    }

    // Parse checksum format "algo:hash" - default to sha256
    $parts = explode(':', $expected, 2);
    if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
        return false; // Invalid checksum format
    }

    [$algo, $expectedHash] = $parts;
    $actualHash = hash_file($algo, $file);

    if ($actualHash === false) {
        return false; // Invalid algorithm or file read error
    }

    return hash_equals($expectedHash, $actualHash);
}

/**
 * Extract ZIP package to root directory with conflict resolution
 *
 * Files are extracted to their paths relative to project root.
 * If a file exists at the target path, the existing file is renamed
 * with a numeric suffix (-2, -3, etc.) to preserve it.
 *
 * @return array{files: string[], warnings: string[]}
 */
function pkgExtract(string $zipFile, string $packageName): array
{
    if (!class_exists('ZipArchive')) {
        $msg = 'PHP ZipArchive extension is required';
        throw new \RuntimeException($msg, 500);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new \RuntimeException("Cannot open archive: $zipFile", 400);
    }

    $files = [];
    $warnings = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);

        // Security: prevent directory traversal
        if (strpos($filename, '..') !== false) {
            $zip->close();
            $msg = "Security: directory traversal detected";
            throw new \RuntimeException($msg, 400);
        }

        // Target path is relative to project root
        $targetPath = './' . $filename;

        // Skip directories
        if (substr($filename, -1) === '/') {
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0755, true);
            }
            continue;
        }

        // Ensure parent directory exists
        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            if (!@mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
                $zip->close();
                $msg = "Cannot create directory: $parentDir";
                throw new \RuntimeException($msg, 400);
            }
        }

        // Handle file conflicts - rename existing file
        if (file_exists($targetPath)) {
            $pathInfo = pathinfo($targetPath);
            $directory = $pathInfo['dirname'];
            $filename_base = $pathInfo['filename'];
            $ext = $pathInfo['extension'] ?? '';
            $extension = $ext ? '.' . $ext : '';

            $suffix = 2;
            $newPath = "$directory/$filename_base-$suffix$extension";

            while (file_exists($newPath)) {
                $suffix++;
                $newPath = "$directory/$filename_base-$suffix$extension";
            }

            if (!@rename($targetPath, $newPath)) {
                $zip->close();
                $msg = "Cannot rename conflicting file: $targetPath";
                throw new \RuntimeException($msg, 400);
            }

            $msg = "WARNING: File conflict at $targetPath - ";
            $msg .= "existing file renamed to $newPath";
            $warnings[] = $msg;
        }

        // Extract file
        $content = $zip->getFromIndex($i);
        if ($content === false) {
            $zip->close();
            throw new \RuntimeException("Cannot extract file: $filename", 400);
        }

        if (!file_put_contents($targetPath, $content)) {
            $zip->close();
            throw new \RuntimeException("Cannot write file: $targetPath", 400);
        }

        $files[] = $targetPath;
    }

    $zip->close();

    return [
        'files' => $files,
        'warnings' => $warnings,
    ];
}

/**
 * Recursively remove directory
 */
function pkgRemoveDirectory(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $files = @scandir($dir);
    if ($files === false) {
        return false;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            pkgRemoveDirectory($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

/**
 * Resolve dependencies for packages using DFS topological sort
 * Uses O(1) set lookups instead of O(n) array searches for efficiency
 */
function pkgResolveDependencies(array $packages, array $database): array
{
    $config = loadPackagesConfig();
    $installed = $config['packages'];

    $toInstall = [];
    $visiting = [];   // Current DFS path (set for O(1) cycle detection)
    $processed = [];  // All processed packages (set for O(1) lookup)

    // Process each requested package
    foreach ($packages as $pkg) {
        if (!isset($processed[$pkg])) {
            pkgVisit(
                $pkg,
                $database,
                $installed,
                $visiting,
                $processed,
                $toInstall
            );
        }
    }

    return $toInstall;
}

/**
 * DFS visit for topological sort (post-order: dependencies before packages)
 * Uses associative arrays as O(1) sets instead of in_array() O(n) searches
 */
function pkgVisit(
    string $package,
    array $database,
    array $installed,
    array &$visiting,   // Current recursion path (for cycle detection)
    array &$processed,  // All processed packages (to avoid reprocessing)
    array &$toInstall   // Result: packages to install in order
) {
    // Validate package exists in database
    if (!isset($database[$package])) {
        throw new \RuntimeException("Package not found: $package", 404);
    }

    // Detect circular dependencies using O(1) set lookup
    if (isset($visiting[$package])) {
        $keys = array_keys($visiting);
        $cycle = implode(' -> ', array_merge($keys, [$package]));
        throw new \RuntimeException("Circular dependency: $cycle", 400);
    }

    // Skip if already processed
    if (isset($processed[$package])) {
        return;
    }

    // Skip if already installed at latest version (optimization)
    if (isset($installed[$package])) {
        $currentVersion = $installed[$package]['version'];
        $latestVersion = $database[$package]['latest'];

        if ($currentVersion === $latestVersion) {
            $processed[$package] = true;
            return;
        }
    }

    // Mark as currently visiting (add to DFS recursion path)
    $visiting[$package] = true;

    // Get latest version info
    $latestVersion = $database[$package]['latest'];
    $versionData = $database[$package]['versions'][$latestVersion];
    $dependencies = $versionData['dependencies'] ?? [];

    // Recursively visit all dependencies first (post-order traversal)
    // Dependencies are added to toInstall before the package itself
    foreach ($dependencies as $dep) {
        pkgVisit(
            $dep,
            $database,
            $installed,
            $visiting,
            $processed,
            $toInstall
        );
    }

    // Remove from current DFS path (backtrack)
    unset($visiting[$package]);

    // Mark as fully processed
    $processed[$package] = true;

    // Add to install list (dependencies already added before us)
    $toInstall[] = $package;
}

/**
 * Install packages with dependency resolution
 * Atomic operation: all operations succeed or none are committed to registry
 */
function pkgAdd(array $packages): string
{
    // Check if another package operation is in progress
    if (pkgIsLocked()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    // Acquire lock for this operation
    if (!pkgAcquireLock()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    try {
        $output = [];

        // Load and validate
        $database = pkgFetchDatabase('main');
        $config = loadPackagesConfig();

        foreach ($packages as $pkg) {
            if (!isset($database[$pkg])) {
                throw new \RuntimeException("Package not found: $pkg", 404);
            }

            if (isset($config['packages'][$pkg])) {
                $current = $config['packages'][$pkg];
                $latest = $database[$pkg]['latest'];

                if ($current['version'] === $latest) {
                    $msg = "Package '$pkg' is already installed ";
                    $msg .= "(version $latest)";
                    $output[] = $msg;
                    continue;
                }

                $msg = "Package '$pkg' will be upgraded from ";
                $msg .= "{$current['version']} to $latest";
                $output[] = $msg;
            }
        }

        // Resolve dependencies
        $toInstall = pkgResolveDependencies($packages, $database);

        if (empty($toInstall)) {
            return implode("\n", $output);
        }

        $output[] = "\nPackages to install: " . implode(', ', $toInstall);
        $output[] = "";

        // Phase 1: Download all packages first (with strict checksum
        // verification and mirror failover)
        $output[] = "Downloading packages...";
        $allDownloads = pkgDownloadAllPackages($toInstall, $database);
        $output[] = "All packages downloaded and verified";
        $output[] = "";

        // Phase 2: Extract all packages (no registry updates yet)
        $output[] = "Extracting packages...";
        $pkgInfoList = [];
        $extractedPackages = [];
        $failedExtractions = [];

        foreach ($toInstall as $pkg) {
            $pkgData = $database[$pkg];
            $version = $pkgData['latest'];
            $versionData = $pkgData['versions'][$version];
            $downloadMeta = $allDownloads[$pkg];

            // Try to extract package
            try {
                $extractResult = pkgExtract(
                    $downloadMeta['zipFile'],
                    $pkg
                );
                $files = $extractResult['files'];
                $warnings = $extractResult['warnings'];

                // Track successfully extracted packages for potential
                // rollback if later extraction fails
                $extractedPackages[$pkg] = $files;

                // Add any conflict warnings to output
                if (!empty($warnings)) {
                    $output[] = implode("\n", $warnings);
                }

                // Store extracted files info for later registration
                // with download metadata
                $pkgInfoList[$pkg] = [
                    'version' => $version,
                    'installed_at' => date('c'),
                    'dependencies' => $versionData['dependencies'] ?? [],
                    'files' => $files,
                    'download_url' => $downloadMeta['url'],
                    'download_time' => $downloadMeta['downloadedAt'],
                    'checksum' => $downloadMeta['checksum'],
                    'repository' => $downloadMeta['repository'],
                ];

                $output[] = "Extracted $pkg ($version)";
            } catch (\RuntimeException $e) {
                $failedExtractions[$pkg] = $e->getMessage();
            }
        }

        // If any extractions failed, rollback all extracted packages
        // and report failures
        if (!empty($failedExtractions)) {
            foreach ($extractedPackages as $pkg => $files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
                $pkgDir = "app/packages/$pkg";
                pkgRemoveDirectory($pkgDir);
            }

            $errorMap = array_map(
                function ($pkg, $err) {
                    return "$pkg: $err";
                },
                array_keys($failedExtractions),
                $failedExtractions
            );
            $failures = implode("\n  - ", $errorMap);
            throw new \RuntimeException(
                "Failed to extract packages:\n  - $failures",
                400
            );
        }

        // Phase 3: Update packages registry atomically
        $output[] = "";
        $output[] = "Registering packages...";

        // Register all packages in FILE_PACKAGES
        foreach ($pkgInfoList as $pkg => $info) {
            $config['packages'][$pkg] = $info;
        }
        savePackagesConfig($config);

        $output[] = "";
        $count = count($pkgInfoList);
        $packages = implode(', ', array_keys($pkgInfoList));
        $output[] = "Successfully installed $count package(s): $packages";

        return implode("\n", $output);
    } finally {
        pkgReleaseLock();
    }
}

/**
 * Remove package
 */
function pkgRemove(string $package): string
{
    // Check if another package operation is in progress
    if (pkgIsLocked()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    // Acquire lock for this operation
    if (!pkgAcquireLock()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    try {
        $config = loadPackagesConfig();

        if (!isset($config['packages'][$package])) {
            $msg = "Package not installed: $package";
            throw new \RuntimeException($msg, 404);
        }

        // Check for reverse dependencies
        $dependents = [];
        foreach ($config['packages'] as $pkg => $data) {
            if (in_array($package, $data['dependencies'] ?? [], true)) {
                $dependents[] = $pkg;
            }
        }

        if (!empty($dependents)) {
            $depList = implode(', ', $dependents);
            $msg = "Cannot remove $package - required by: $depList";
            throw new \RuntimeException($msg, 400);
        }

        $pkgData = $config['packages'][$package];

        // Remove files installed by this package
        $removedCount = 0;
        $directories = [];

        foreach ($pkgData['files'] ?? [] as $file) {
            if (file_exists($file)) {
                @unlink($file);
                $removedCount++;
            }

            // Track all parent directories recursively
            $dir = dirname($file);
            while ($dir !== '.' && $dir !== '/' && $dir !== '') {
                if (!in_array($dir, $directories, true)) {
                    $directories[] = $dir;
                }
                $dir = dirname($dir);
            }
        }

        // Step 1: Extract unique directories from package files, sorted
        // by depth (deepest first)
        usort(
            $directories,
            function ($a, $b) {
                return substr_count($b, '/') - substr_count($a, '/');
            }
        );

        // Step 2: Remove empty directories from deepest to shallowest
        $removedDirCount = 0;
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            // Check if directory is empty (only contains . and ..)
            $handle = @opendir($dir);
            if ($handle) {
                $empty = true;
                while (($item = readdir($handle)) !== false) {
                    if ($item !== '.' && $item !== '..') {
                        $empty = false;
                        break;
                    }
                }
                closedir($handle);

                if ($empty && @rmdir($dir)) {
                    $removedDirCount++;
                }
            }
        }

        // Unregister package
        unset($config['packages'][$package]);
        savePackagesConfig($config);

        $msg = "Removed package: $package ";
        $msg .= "(version {$pkgData['version']}) - ";
        $msg .= "$removedCount files deleted";
        if ($removedDirCount > 0) {
            $msg .= ", $removedDirCount empty directories removed";
        }

        return $msg;
    } finally {
        pkgReleaseLock();
    }
}

/**
 * Refresh repository cache (fetch latest database from mirrors)
 */
function pkgRefresh(): string
{
    // Check if another package operation is in progress
    if (pkgIsLocked()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    // Fetch the latest package database from mirrors
    // (HTTP caching headers are ignored; fresh database always fetched)
    $database = pkgFetchDatabase('main');
    $msg = "Repository cache refreshed - " . count($database);
    $msg .= " packages available";
    return $msg;
}

/**
 * Update (upgrade) packages to latest versions
 * Atomic operation: all upgrades succeed or none are committed to
 * FILE_PACKAGES
 */
function pkgUpdate($package = null): string
{
    // Check if another package operation is in progress
    if (pkgIsLocked()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    // Acquire lock for this operation
    if (!pkgAcquireLock()) {
        throw new \RuntimeException(MSG_PKG_LOCKED, 503);
    }

    try {
        $config = loadPackagesConfig();
        $database = pkgFetchDatabase('main');
        $output = [];

        // Get packages to update
        $toUpdate = [];
        if ($package) {
            if (!isset($config['packages'][$package])) {
                $msg = "Package not installed: $package";
                throw new \RuntimeException($msg, 404);
            }
            $toUpdate = [$package];
        } else {
            $toUpdate = array_keys($config['packages']);
        }

        if (empty($toUpdate)) {
            return "No packages installed";
        }

        // Phase 1: Identify packages needing updates
        $toUpgrade = [];
        foreach ($toUpdate as $pkg) {
            $current = $config['packages'][$pkg];

            if (!isset($database[$pkg])) {
                $output[] = "Package no longer available in repository: $pkg";
                continue;
            }

            $latest = $database[$pkg]['latest'];

            if ($current['version'] === $latest) {
                $output[] = "$pkg is up to date ($latest)";
                continue;
            }

            $msg = "Will upgrade $pkg from {$current['version']} ";
            $msg .= "to $latest";
            $output[] = $msg;
            $toUpgrade[$pkg] = $latest;
        }

        if (empty($toUpgrade)) {
            $output[] = "\nAll packages are up to date";
            return implode("\n", $output);
        }

        // Phase 2: Download all updated packages first (with strict
        // checksum verification and mirror failover)
        $output[] = "";
        $output[] = "Downloading package updates...";
        $allDownloads = pkgDownloadAllPackages(
            array_keys($toUpgrade),
            $database
        );
        $output[] = "All package updates downloaded and verified";
        $output[] = "";

        // Phase 3: Extract all updated packages (don't update config yet)
        $output[] = "Extracting package updates...";
        $pkgUpdateList = [];
        $extractedPackages = [];
        $failedExtractions = [];

        foreach ($toUpgrade as $pkg => $latest) {
            // Extract new version (files are extracted to root with
            // conflict resolution)
            $versionData = $database[$pkg]['versions'][$latest];
            $downloadMeta = $allDownloads[$pkg];

            try {
                $extractResult = pkgExtract(
                    $downloadMeta['zipFile'],
                    $pkg
                );
                $files = $extractResult['files'];
                $warnings = $extractResult['warnings'];

                // Track successfully extracted packages for potential
                // rollback if later extraction fails
                $extractedPackages[$pkg] = $files;

                // Add any conflict warnings to output
                if (!empty($warnings)) {
                    $output[] = implode("\n", $warnings);
                }

                // Store update info for registration with download
                // metadata
                $pkgUpdateList[$pkg] = [
                    'version' => $latest,
                    'installed_at' => date('c'),
                    'dependencies' => $versionData['dependencies'] ?? [],
                    'files' => $files,
                    'download_url' => $downloadMeta['url'],
                    'download_time' => $downloadMeta['downloadedAt'],
                    'checksum' => $downloadMeta['checksum'],
                    'repository' => $downloadMeta['repository'],
                ];

                $output[] = "Extracted $pkg upgrade to $latest";
            } catch (\RuntimeException $e) {
                $failedExtractions[$pkg] = $e->getMessage();
            }
        }

        // If any extractions failed, rollback all extracted packages
        // and report failures
        if (!empty($failedExtractions)) {
            foreach ($extractedPackages as $pkg => $files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
                $pkgDir = "app/packages/$pkg";
                pkgRemoveDirectory($pkgDir);
            }

            $errorMap = array_map(
                function ($pkg, $err) {
                    return "$pkg: $err";
                },
                array_keys($failedExtractions),
                $failedExtractions
            );
            $failures = implode("\n  - ", $errorMap);
            throw new \RuntimeException(
                "Failed to extract package updates:\n  - $failures",
                400
            );
        }

        // Phase 4: Commit all updates to FILE_PACKAGES atomically
        $output[] = "";
        $output[] = "Registering upgrades...";

        // Update all packages in config
        foreach ($pkgUpdateList as $pkg => $info) {
            $config['packages'][$pkg] = $info;
        }
        savePackagesConfig($config);

        $output[] = "";
        $count = count($pkgUpdateList);
        $packages = implode(', ', array_keys($pkgUpdateList));
        $output[] = "Successfully upgraded $count package(s): $packages";

        return implode("\n", $output);
    } finally {
        pkgReleaseLock();
    }
}

/**
 * List installed packages (read-only, no lock needed)
 */
function pkgList($filter = null): string
{
    $config = loadPackagesConfig();
    $installed = $config['packages'];

    if (empty($installed)) {
        return "No packages installed";
    }

    $output = ["Installed packages:", ""];

    foreach ($installed as $pkg => $data) {
        if ($filter && stripos($pkg, $filter) === false) {
            continue;
        }

        $deps = !empty($data['dependencies'])
            ? ' (depends: ' . implode(', ', $data['dependencies']) . ')'
            : '';

        $output[] = sprintf(
            "  %-20s  v%-10s  installed: %s%s",
            $pkg,
            $data['version'],
            substr($data['installed_at'], 0, 10),
            $deps
        );
    }

    return implode("\n", $output);
}

/**
 * Search available packages (read-only, no lock needed)
 */
function pkgSearch(string $keyword): string
{
    $database = pkgFetchDatabase('main');
    $config = loadPackagesConfig();

    $results = [];
    $matchCount = 0;

    foreach ($database as $pkg => $data) {
        $parts = [$pkg, $data['name'], $data['description'] ?? ''];
        $haystack = strtolower(implode(' ', $parts));

        if (stripos($haystack, $keyword) !== false) {
            $matchCount++;
            $isInstalled = isset($config['packages'][$pkg]);
            $installed = $isInstalled ? ' [installed]' : '';
            $description = $data['description'] ?? '';

            $header = sprintf(
                "  %-20s  v%-10s  %s%s",
                $pkg,
                $data['latest'],
                $data['name'],
                $installed
            );
            $results[] = $header;

            if ($description) {
                $results[] = sprintf(
                    "    %s",
                    $description
                );
            }
        }
    }

    if (empty($results)) {
        return "No packages found matching: $keyword";
    }

    $plural = $matchCount === 1 ? 'package' : 'packages';
    array_unshift(
        $results,
        "Found $matchCount $plural:",
        ""
    );
    return implode("\n", $results);
}

/**
 * Show package information (read-only, no lock needed)
 */
function pkgInfo(string $package): string
{
    $database = pkgFetchDatabase('main');
    $config = loadPackagesConfig();

    if (!isset($database[$package])) {
        throw new \RuntimeException("Package not found: $package", 404);
    }

    $data = $database[$package];
    $output = [];

    $output[] = "Package: {$data['name']}";
    $output[] = "ID: $package";
    $output[] = "Latest: {$data['latest']}";
    $output[] = "Description: {$data['description']}";

    if (isset($data['author'])) {
        $output[] = "Author: {$data['author']}";
    }

    if (isset($data['license'])) {
        $output[] = "License: {$data['license']}";
    }

    // Show installed version
    if (isset($config['packages'][$package])) {
        $installed = $config['packages'][$package];
        $output[] = "";
        $ver = $installed['version'];
        $date = $installed['installed_at'];
        $output[] = "Installed: v{$ver} (on {$date})";

        if (!empty($installed['dependencies'])) {
            $deps = implode(', ', $installed['dependencies']);
            $output[] = "Dependencies: {$deps}";
        }
    } else {
        $output[] = "";
        $output[] = "Status: Not installed";
    }

    // Show available versions
    $output[] = "";
    $output[] = "Available versions:";
    foreach ($data['versions'] as $ver => $verData) {
        $deps = !empty($verData['dependencies'])
            ? ' (requires: ' . implode(', ', $verData['dependencies']) . ')'
            : '';
        $output[] = "  v$ver - released: {$verData['released_at']}$deps";
    }

    return implode("\n", $output);
}

// ============================================================================
// Built-in Commands
// ============================================================================

/**
 * ls [path]
 *
 * List files in directory
 *
 * @param string[] $args
 * @return string Space-separated filenames
 * @throws RuntimeException On path validation or read error
 */
function handleLs(array $args): string
{
    $path = $args[0] ?? '.';

    validatePath($path);

    if (!is_dir($path)) {
        throw new \RuntimeException("Not a directory: $path", 404);
    }

    $files = @scandir($path);
    if ($files === false) {
        throw new \RuntimeException("Cannot read directory: $path", 400);
    }

    // Remove . and ..
    $files = array_filter($files, function ($f) {
        return $f !== '.' && $f !== '..';
    });
    $files = array_values($files);

    return implode(' ', $files);
}

/**
 * cat <file>
 *
 * Read file contents
 *
 * @param string[] $args
 * @return string File contents
 * @throws RuntimeException On path validation or read error
 */
function handleCat(array $args): string
{
    $file = $args[0] ?? null;

    if (!$file) {
        throw new \RuntimeException('Usage: cat <file>', 400);
    }

    validatePath($file);

    if (!file_exists($file)) {
        throw new \RuntimeException("File not found: $file", 404);
    }

    if (!is_file($file)) {
        throw new \RuntimeException("Not a file: $file", 400);
    }

    $content = @file_get_contents($file);
    if ($content === false) {
        throw new \RuntimeException("Cannot read file: $file", 400);
    }

    return $content;
}

/**
 * rm <file>
 *
 * Delete file
 *
 * @param string[] $args
 * @return string Empty string on success
 * @throws RuntimeException On path validation or delete error
 */
function handleRm(array $args): string
{
    $file = $args[0] ?? null;

    if (!$file) {
        throw new \RuntimeException('Usage: rm <file>', 400);
    }

    validatePath($file);

    if (!file_exists($file)) {
        throw new \RuntimeException("File not found: $file", 404);
    }

    if (!is_file($file)) {
        throw new \RuntimeException("Not a file (is directory?): $file", 400);
    }

    if (!@unlink($file)) {
        throw new \RuntimeException("Cannot delete file: $file", 400);
    }

    return '';
}

/**
 * mkdir <path>
 *
 * Create directory
 *
 * @param string[] $args
 * @return string Empty string on success
 * @throws RuntimeException On path validation or create error
 */
function handleMkdir(array $args): string
{
    $path = $args[0] ?? null;

    if (!$path) {
        throw new \RuntimeException('Usage: mkdir <path>', 400);
    }

    validatePath($path);

    if (file_exists($path)) {
        throw new \RuntimeException("Already exists: $path", 400);
    }

    if (!@mkdir($path, 0755, true)) {
        throw new \RuntimeException("Cannot create directory: $path", 400);
    }

    return '';
}

/**
 * cp <src> <dst>
 *
 * Copy file
 *
 * @param string[] $args
 * @return string Empty string on success
 * @throws RuntimeException On path validation or copy error
 */
function handleCp(array $args): string
{
    $src = $args[0] ?? null;
    $dst = $args[1] ?? null;

    if (!$src || !$dst) {
        throw new \RuntimeException('Usage: cp <src> <dst>', 400);
    }

    validatePath($src);
    validatePath($dst);

    if (!file_exists($src)) {
        throw new \RuntimeException("Source not found: $src", 404);
    }

    if (!is_file($src)) {
        throw new \RuntimeException("Source is not a file: $src", 400);
    }

    if (!@copy($src, $dst)) {
        throw new \RuntimeException("Cannot copy file: $src → $dst", 400);
    }

    return '';
}

/**
 * echo <text>...
 *
 * Echo text
 *
 * @param string[] $args
 * @return string
 */
function handleEcho(array $args): string
{
    return implode(' ', $args);
}

/**
 * env [get <name> | list]
 *
 * Manage environment variables
 *
 * @param string[] $args
 * @return string Environment variable value or list
 * @throws RuntimeException On invalid action or variable not found
 */
function handleEnv(array $args): string
{
    $action = $args[0] ?? 'list';

    switch ($action) {
        case 'get':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Usage: env get <name>', 400);
            }

            $value = getenv($name);
            if ($value === false) {
                throw new \RuntimeException("Variable not set: $name", 404);
            }

            return $value;

        case 'list':
            $vars = getenv();
            $output = '';
            foreach ($vars as $key => $value) {
                $output .= "$key=$value\n";
            }

            return rtrim($output);

        default:
            throw new \RuntimeException("Unknown env action: $action", 404);
    }
}

// ============================================================================
// Welcome Screen (First Run)
// ============================================================================

function renderWelcome(string $key, array $compat)
{
    $phpOk = $compat['php_version'] === null;
    $zipOk = !in_array('ZipArchive', $compat['classes']);
    $writeOk = $compat['permissions'] === null;
    $funcsOk = empty($compat['functions']);
    $installCmd = "GET /mpm.php/{$key}/pkg/add/web-installer";
    ?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPM - Mehr's Package Manager</title>
    <link rel="icon" type="image/svg+xml" href="<?php
        echo 'data:image/svg+xml,' .
            '%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' .
            '%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%22' .
            '3%201%2018%2022%22%20fill%3D%22none%22%3E%3Cdefs%3E' .
            '%3ClinearGradient%20id%3D%22t%22%20x1%3D%223%22%20y1%3D%22' .
            '1%22%20x2%3D%2221%22%20y2%3D%2211%22%3E%3Cstop%20offset%3D' .
            '%220%22%20stop-color%3D%22%23FFD0C8%22%2F%3E%3Cstop%20' .
            'offset%3D%221%22%20stop-color%3D%22%23FF3D00%22%2F%3E%3C' .
            '%2FlinearGradient%3E%3ClinearGradient%20id%3D%22l%22%20' .
            'x1%3D%223%22%20y1%3D%227%22%20x2%3D%2212%22%20y2%3D%2223' .
            '%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%23' .
            'FF5A3D%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D' .
            '%22%23E63B1F%22%2F%3E%3C%2FlinearGradient%3E' .
            '%3ClinearGradient%20id%3D%22r%22%20x1%3D%2212%22%20y1%3D' .
            '%2212%22%20x2%3D%2221%22%20y2%3D%2223%22%3E%3Cstop%20' .
            'offset%3D%220%22%20stop-color%3D%22%23E63B1F%22%2F%3E' .
            '%3Cstop%20offset%3D%221%22%20stop-color%3D%22%237A1B12%22' .
            '%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Cpolygon%20' .
            'points%3D%223%2C7.3%2011.1%2C12.2%2011.1%2C23%203%2C18.1' .
            '%22%20fill%3D%22url(%23l)%22%2F%3E%3Cpolygon%20points%3D' .
            '%2221%2C7.3%2012.9%2C12.2%2012.9%2C23%2021%2C18.1%22%20' .
            'fill%3D%22url(%23r)%22%2F%3E%3Cpolygon%20points%3D%2212' .
            '%2C1%2020.4%2C6.2%2012%2C10.8%203.6%2C6.2%22%20fill%3D' .
            '%22url(%23t)%22%2F%3E%3C%2Fsvg%3E';
    ?>">
    <style>
        :root {
            --bg-primary: #171717;
            --bg-secondary: #2a1f19;
            --bg-tertiary: #1e2021;
            --text-primary: #e2e8f0;
            --text-secondary: #a89280;
            --text-tertiary: #c4b5a8;
            --text-muted: #6b5a52;
            --border: #3d2a23;
            --border-secondary: #4d3a30;
            --accent: #f34e3f;
            --success: #22c55e;
            --selection-bg: #4a3531;
            --selection-text: #c4b5a8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        *::selection {
            background-color: var(--selection-bg);
            color: var(--selection-text);
        }

        *::-moz-selection {
            background-color: var(--selection-bg);
            color: var(--selection-text);
        }

        html {
            scroll-behavior: smooth;
        }

        html[dir="rtl"] { direction: rtl; }
        html[dir="ltr"] { direction: ltr; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI',
                Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            position: relative;
            max-width: 720px;
            width: 100%;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-text {
            font-size: 1em;
            font-weight: 800;
            color: var(--accent);
            white-space: nowrap;
            line-height: 1;
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .icon svg {
            width: 100%;
            height: 100%;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        .logo .icon svg {
            stroke: none;
            fill: inherit;
        }

        .lang-selector {
            position: relative;
        }

        .lang-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: 1px solid var(--border-secondary);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s;
        }

        .lang-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .lang-btn .icon {
            width: 16px;
            height: 16px;
        }

        .lang-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border-secondary);
            border-radius: 4px;
            z-index: 1000;
            white-space: nowrap;
        }

        html[dir="rtl"] .lang-dropdown {
            right: auto;
            left: 0;
        }

        .lang-dropdown.show {
            display: block;
        }

        .lang-option {
            display: block;
            width: 100%;
            padding: 10px 16px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
            text-align: left;
        }

        html[dir="rtl"] .lang-option {
            text-align: right;
        }

        .lang-option:hover,
        .lang-option.active {
            background-color: rgba(243, 78, 63, 0.1);
            color: var(--accent);
        }

        .subtitle {
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-size: 1em;
            text-align: center;
            line-height: 1.6;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            color: var(--text-primary);
            font-size: 0.9em;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .key-label {
            color: var(--text-secondary);
            font-size: 0.85em;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .key-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-secondary);
            border-radius: 6px;
            padding: 16px;
            margin: 12px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            padding-right: 50px;
        }

        .key-value {
            flex: 1;
            direction: ltr;
            color: #10b981;
            font-weight: bold;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            word-break: break-all;
            font-size: 0.9em;
            line-height: 1.4;
            min-width: 0;
        }

        .copy-btn {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: transparent;
            border: 1px solid var(--border-secondary);
            border-radius: 4px;
            padding: 6px 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85em;
        }

        .copy-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(243, 78, 63, 0.1);
        }

        .copy-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .copy-icon {
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .copy-icon svg {
            width: 100%;
            height: 100%;
            stroke: none;
            fill: currentColor;
        }

        .copy-btn.copied .copy-icon {
            display: none;
        }

        .copy-btn.copied .copied-text {
            color: #10b981;
        }

        .copied-text {
            display: none;
        }

        .copy-btn.copied .copied-text {
            display: inline;
        }

        .compat-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .compat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-secondary);
            border-radius: 4px;
            font-size: 0.85em;
        }

        .compat-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .compat-icon.pass { color: var(--success); }
        .compat-icon.fail { color: var(--accent); }

        .compat-label {
            color: var(--text-tertiary);
        }

        .code-block {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 12px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8em;
            color: #10b981;
            overflow-x: auto;
            word-break: break-all;
            white-space: pre-wrap;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show { display: flex; }

        .modal {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1em;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5em;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .modal-close:hover { color: var(--accent); }

        .modal-body { padding: 16px; }

        .step {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .step:last-child { margin-bottom: 0; }

        .step .code-block {
            margin-top: 8px;
        }

        .step-num {
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85em;
            font-weight: bold;
        }

        .step-content { flex: 1; }

        .step-desc {
            color: var(--text-tertiary);
            font-size: 0.9em;
            margin-bottom: 8px;
        }

        .modal-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 24px;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary:hover { background: #d94435; }

        .links {
            display: flex;
            gap: 12px;
            margin: 24px 0;
            flex-wrap: wrap;
            justify-content: center;
        }

        .link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: transparent;
            border: 1px solid var(--accent);
            border-radius: 4px;
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.2s;
            cursor: pointer;
        }

        .link-btn .icon {
            width: 16px;
            height: 16px;
        }

        .link-btn .icon svg {
            stroke-width: 2.5;
        }

        .link-btn:hover:not(:disabled) {
            background: rgba(243, 78, 63, 0.1);
        }

        .link-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        p {
            line-height: 1.6;
            margin-bottom: 16px;
            color: var(--text-tertiary);
            font-size: 0.95em;
        }

        code {
            background: var(--bg-secondary);
            color: #10b981;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9em;
        }

        .footer-bottom p {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85em;
        }

        @media (max-width: 600px) {
            body { padding: 16px; }
            .container { padding: 24px; }
            h1 { font-size: 1.4em; }
            .header-top { flex-wrap: wrap; gap: 12px; }
            .compat-list { grid-template-columns: 1fr; }
            .links { flex-direction: column; }
            .link-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-top">
            <div class="logo">
                <svg width="24" height="24" viewBox="3 1 18 22"
                    fill="none" aria-hidden="true"
                    shape-rendering="geometricPrecision">
                    <defs>
                        <linearGradient id="pkg_red_top"
                            x1="3" y1="1" x2="21" y2="11">
                            <stop offset="0" stop-color="#FFD0C8"/>
                            <stop offset="1" stop-color="#FF3D00"/>
                        </linearGradient>
                        <linearGradient id="pkg_red_left"
                            x1="3" y1="7" x2="12" y2="23">
                            <stop offset="0" stop-color="#FF5A3D"/>
                            <stop offset="1" stop-color="#E63B1F"/>
                        </linearGradient>
                        <linearGradient id="pkg_red_right"
                            x1="12" y1="12" x2="21" y2="23">
                            <stop offset="0" stop-color="#E63B1F"/>
                            <stop offset="1" stop-color="#7A1B12"/>
                        </linearGradient>
                    </defs>
                    <polygon points="3,7.3 11.1,12.2 11.1,23 3,18.1"
                        fill="url(#pkg_red_left)"/>
                    <polygon points="21,7.3 12.9,12.2 12.9,23 21,18.1"
                        fill="url(#pkg_red_right)"/>
                    <polygon points="12,1 20.4,6.2 12,10.8 3.6,6.2"
                        fill="url(#pkg_red_top)"/>
                </svg>
                <span class="logo-text" data-i18n="logo-text">
                    Mehr's Package Manager
                </span>
            </div>
            <div class="lang-selector">
                <button class="lang-btn" id="lang-btn" data-lang="en"
                    aria-label="Select language"
                    aria-expanded="false" aria-haspopup="true">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10
                                15.3 15.3 0 0 1-4 10
                                15.3 15.3 0 0 1-4-10
                                15.3 15.3 0 0 1 4-10z"/>
                            <path d="M2 12h20"/>
                        </svg>
                    </span>
                    <span>EN</span>
                </button>
                <div class="lang-dropdown" id="lang-dropdown"
                    role="menu" aria-label="Language options">
                    <button class="lang-option" data-lang="en"
                        role="menuitem">English</button>
                    <button class="lang-option" data-lang="fr"
                        role="menuitem">Français</button>
                    <button class="lang-option" data-lang="fa"
                        role="menuitem">فارسی</button>
                </div>
            </div>
        </div>

        <p class="subtitle" data-i18n="subtitle">
            Universal package management for modern PHP applications
        </p>

        <div class="section">
            <div class="key-label" data-i18n="api-key-label">
                API Key (keep it secret!):
            </div>
            <div class="key-section">
                <div class="key-value" id="key-value">
                    <?php echo htmlspecialchars($key); ?>
                </div>
                <button class="copy-btn" id="copy-btn"
                    title="Copy to clipboard"
                    aria-label="Copy API key to clipboard">
                    <span class="copy-icon">
                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13"
                                rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2
                                2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </span>
                    <span class="copied-text">Copied!</span>
                </button>
            </div>
            <p data-i18n="key-saved">Saved in
                <code><?php echo htmlspecialchars(FILE_KEY); ?></code>.
                Automatically generated and uniquely identifies your instance.
            </p>
        </div>

        <div class="section">
            <div class="section-title" data-i18n="compat-title">
                Compatibility Checklist
            </div>
            <div class="compat-list">
                <div class="compat-item">
                    <span class="compat-icon <?php
                        echo $phpOk ? 'pass' : 'fail'; ?>">
                        <?php echo $phpOk ? '✓' : '✗'; ?>
                    </span>
                    <span class="compat-label">
                        PHP <?php echo PHP_VERSION; ?>
                    </span>
                </div>
                <div class="compat-item">
                    <span class="compat-icon <?php
                        echo $zipOk ? 'pass' : 'fail'; ?>">
                        <?php echo $zipOk ? '✓' : '✗'; ?>
                    </span>
                    <span class="compat-label" data-i18n="compat-zip">
                        ZipArchive
                    </span>
                </div>
                <div class="compat-item">
                    <span class="compat-icon <?php
                        echo $writeOk ? 'pass' : 'fail'; ?>">
                        <?php echo $writeOk ? '✓' : '✗'; ?>
                    </span>
                    <span class="compat-label" data-i18n="compat-write">
                        Write Permissions
                    </span>
                </div>
                <div class="compat-item">
                    <span class="compat-icon <?php
                        echo $funcsOk ? 'pass' : 'fail'; ?>">
                        <?php echo $funcsOk ? '✓' : '✗'; ?>
                    </span>
                    <span class="compat-label" data-i18n="compat-funcs">
                        Required Functions
                    </span>
                </div>
            </div>
        </div>

        <div class="links">
            <button class="link-btn" id="install-btn">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0
                            0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </span>
                <span data-i18n="installer">Web Installer</span>
            </button>
            <a href="https://mpm.mehrnet.com" class="link-btn"
                target="_blank" rel="noopener">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4
                            14.5v-10A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </span>
                <span data-i18n="documentation">Documentation</span>
            </a>
        </div>

        <div class="footer-bottom">
            <p>MPM - v<?php echo htmlspecialchars(APP_VERSION); ?></p>
        </div>
    </div>

    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 data-i18n="modal-title">Web Installer</h3>
                <button class="modal-close" id="modal-close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p class="step-desc" data-i18n="step1-desc">
                            Install the web-installer package:
                        </p>
                        <div class="code-block"><?php
                            echo htmlspecialchars($installCmd); ?></div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p class="step-desc" data-i18n="step2-desc">
                            You will be redirected to:
                        </p>
                        <div class="code-block">/install.php</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" id="modal-install">
                    <span data-i18n="modal-install">Install Now</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                "logo-text": "Mehr's Package Manager",
                subtitle:
                    "Universal package management for modern PHP applications",
                "api-key-label": "API Key (keep it secret!):",
                "key-saved": "Saved in <code>" +
                    "<?php echo htmlspecialchars(FILE_KEY); ?></code>. " +
                    "Automatically generated and uniquely identifies " +
                    "your instance.",
                "compat-title": "Compatibility Checklist",
                "compat-zip": "ZipArchive",
                "compat-write": "Write Permissions",
                "compat-funcs": "Required Functions",
                "modal-title": "Web Installer",
                "step1-desc": "Install the web-installer package:",
                "step2-desc": "You will be redirected to:",
                "modal-install": "Install Now",
                documentation: "Documentation",
                installer: "Web Installer"
            },
            fr: {
                "logo-text": "Gestionnaire de Paquets Mehr",
                subtitle: "Gestion universelle des paquets pour les " +
                    "applications PHP modernes",
                "api-key-label": "Clé API (gardez-la secrète !):",
                "key-saved": "Enregistrée dans <code>" +
                    "<?php echo htmlspecialchars(FILE_KEY); ?></code>. " +
                    "Généré automatiquement et identifie de manière " +
                    "unique votre instance.",
                "compat-title": "Liste de Compatibilité",
                "compat-zip": "ZipArchive",
                "compat-write": "Permissions d'Écriture",
                "compat-funcs": "Fonctions Requises",
                "modal-title": "Installeur Web",
                "step1-desc": "Installer le paquet web-installer:",
                "step2-desc": "Vous serez redirigé vers:",
                "modal-install": "Installer Maintenant",
                documentation: "Documentation",
                installer: "Installeur Web"
            },
            fa: {
                "logo-text": "مدیر بسته مهر",
                subtitle: "مدیریت جهانی بسته " +
                    "برای برنامه‌های PHP مدرن",
                "api-key-label": "کلید API " +
                    "(آن را محرمانه نگه دارید!):",
                "key-saved": "در <code>" +
                    "<?php echo htmlspecialchars(FILE_KEY); ?>" +
                    "</code> ذخیره شده است. " +
                    "به طور خودکار تولید شده.",
                "compat-title": "چک‌لیست سازگاری",
                "compat-zip": "ZipArchive",
                "compat-write": "مجوزهای نوشتن",
                "compat-funcs": "توابع مورد نیاز",
                "modal-title": "نصب‌کننده وب",
                "step1-desc": "نصب بسته web-installer:",
                "step2-desc": "هدایت خواهید شد به:",
                "modal-install": "نصب کنید",
                documentation: "مستندات",
                installer: "نصب‌کننده وب"
            }
        };

        function setLanguage(lang) {
            const htmlRoot = document.getElementById('html-root');
            const dir = lang === 'fa' ? 'rtl' : 'ltr';
            htmlRoot.setAttribute('lang', lang);
            htmlRoot.setAttribute('dir', dir);
            localStorage.setItem('mpm-lang', lang);
            updateTranslations(lang);
            updateLangButton(lang);
            var dropdown = document.getElementById('lang-dropdown');
            dropdown.classList.remove('show');
        }

        function updateTranslations(lang) {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (translations[lang][key]) {
                    el.innerHTML = translations[lang][key];
                }
            });
        }

        function updateLangButton(lang) {
            const langLabels = { en: 'EN', fr: 'FR', fa: 'FA' };
            const langBtn = document.getElementById('lang-btn');
            const spanEl = langBtn.querySelector('span:last-child');
            if (spanEl) {
                spanEl.textContent = langLabels[lang];
            }
            document.querySelectorAll('.lang-option').forEach(
                function(opt) {
                    var isActive = opt.getAttribute('data-lang') === lang;
                    opt.classList.toggle('active', isActive);
                }
            );
        }

        const copyBtn = document.getElementById('copy-btn');
        let copyTimeout;

        copyBtn.addEventListener('click', () => {
            const keyValue = document.getElementById('key-value').textContent;
            navigator.clipboard.writeText(keyValue.trim()).then(() => {
                copyBtn.classList.add('copied');
                clearTimeout(copyTimeout);
                copyTimeout = setTimeout(() => {
                    copyBtn.classList.remove('copied');
                }, 2000);
            });
        });

        var modalOverlay = document.getElementById('modal-overlay');
        var modalClose = document.getElementById('modal-close');
        var modalInstall = document.getElementById('modal-install');

        document.getElementById('install-btn').addEventListener(
            'click',
            function() {
                modalOverlay.classList.add('show');
            }
        );

        modalClose.addEventListener('click', function() {
            modalOverlay.classList.remove('show');
        });

        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('show');
            }
        });

        modalInstall.addEventListener('click', function() {
            var url = '/mpm.php/' +
                '<?php echo htmlspecialchars($key); ?>' +
                '/pkg/add/web-installer';
            window.location.href = url;
        });

        document.getElementById('lang-btn').addEventListener(
            'click',
            function() {
                var dd = document.getElementById('lang-dropdown');
                var isOpen = dd.classList.toggle('show');
                var btn = document.getElementById('lang-btn');
                btn.setAttribute('aria-expanded', isOpen);
            }
        );

        document.querySelectorAll('.lang-option').forEach(
            function(btn) {
                btn.addEventListener('click', function(e) {
                    setLanguage(e.target.getAttribute('data-lang'));
                    var langBtn = document.getElementById('lang-btn');
                    langBtn.setAttribute('aria-expanded', 'false');
                });
            }
        );

        document.addEventListener('click', function(e) {
            var selector = document.querySelector('.lang-selector');
            if (!selector.contains(e.target)) {
                var dropdown = document.getElementById('lang-dropdown');
                dropdown.classList.remove('show');
                var langBtn = document.getElementById('lang-btn');
                langBtn.setAttribute('aria-expanded', 'false');
            }
        });

        var savedLang = localStorage.getItem('mpm-lang');
        var initialLang = savedLang || (
            navigator.language.startsWith('fr') ? 'fr' :
            navigator.language.startsWith('fa') ? 'fa' : 'en'
        );
        setLanguage(initialLang);
    </script>
</body>
</html>
    <?php
}

// ============================================================================
// Main Execution
// ============================================================================

// Run compatibility checks before anything else
ensureCompatibility();

// Ensure config directory and all config files exist (first run)
ensureKeyFile();
ensureReposFile();
ensurePackagesFile();
ensurePathFile();
ensureSecurePermissions();
ensureHtaccess();

// Register cleanup for fatal errors - ensures lock is released even if
// script dies unexpectedly (e.g., out of memory, max execution time)
register_shutdown_function(function () {
    if (file_exists(FILE_LOCK)) {
        @unlink(FILE_LOCK);
    }
});

// Detect runtime mode
$isCli = isCli();

// Parse request based on mode
if ($isCli) {
    $parsed = parseCliArgs();
} else {
    $parsed = parsePath();
}

$key = $parsed['key'];
$command = $parsed['command'];
$args = $parsed['args'];

// Validate and execute command
try {
    if (!$command) {
        throw new \RuntimeException('No command specified', 400);
    }

    // Validate key based on mode
    if ($isCli) {
        if (!validateCliKey($key)) {
            throw new \RuntimeException('API key not found', 403);
        }
    } else {
        if (!validateKey($key)) {
            throw new \RuntimeException('Invalid API key', 403);
        }
    }

    $response = executeCommand($command, $args);

    // Send response based on mode
    if ($isCli) {
        sendCliResponse($response, 0);
    } else {
        sendResponse($response, 200);
    }
} catch (\RuntimeException $e) {
    $statusCode = $e->getCode() ?: 400;
    if ($isCli) {
        abortCli($e->getMessage(), 1);
    } else {
        abort($e->getMessage(), $statusCode);
    }
} catch (\InvalidArgumentException $e) {
    $statusCode = $e->getCode() ?: 400;
    if ($isCli) {
        abortCli($e->getMessage(), 1);
    } else {
        abort($e->getMessage(), $statusCode);
    }
} catch (\Exception $e) {
    if ($isCli) {
        abortCli("Internal error: {$e->getMessage()}", 1);
    } else {
        abort("Internal error: {$e->getMessage()}", 500);
    }
}
