# Usage Reference

Complete command reference and API documentation for MPM - Mehr's Package Manager.

## Table of Contents

- [Request Formats](#request-formats)
- [Built-in Commands](#built-in-commands)
- [Package Manager](#package-manager)
- [Response Protocol](#response-protocol)
- [Configuration Files](#configuration-files)
- [Custom Commands](#custom-commands)
- [Architecture](#architecture)
- [Performance](#performance)
- [Troubleshooting](#troubleshooting)

---

## Request Formats

### HTTP Mode

**Format:**
```
GET /shell.php/API_KEY/COMMAND/ARG0/ARG1/ARG2/...
```

**Examples:**
```bash
# Get API key
KEY=$(cat .config/key)

# List files
curl "http://localhost/shell.php/$KEY/ls"

# Read file
curl "http://localhost/shell.php/$KEY/cat/README.md"

# Echo with multiple arguments
curl "http://localhost/shell.php/$KEY/echo/hello/world"

# Package management
curl "http://localhost/shell.php/$KEY/pkg/search/database"
curl "http://localhost/shell.php/$KEY/pkg/add/users"
```

**Response:**
- Success: HTTP 200 with plain text response
- Error: HTTP 4xx/5xx with error message

### CLI Mode

**Format:**
```bash
php shell.php COMMAND ARG0 ARG1 ARG2 ...
```

**Examples:**
```bash
# List files
php shell.php ls

# Read file
php shell.php cat README.md

# Echo with multiple arguments
php shell.php echo hello world

# Package management
php shell.php pkg search database
php shell.php pkg add users
```

**Response:**
- Success: Output to STDOUT, exit code 0
- Error: Output to STDERR with "Error: " prefix, exit code 1

---

## Built-in Commands

### ls [path]

List directory contents.

**Arguments:**
- `path` (optional): Directory to list (default: current directory)

**Examples:**
```bash
# CLI
php shell.php ls
php shell.php ls app
php shell.php ls app/packages

# HTTP
curl "http://localhost/shell.php/$KEY/ls"
curl "http://localhost/shell.php/$KEY/ls/app"
```

**Output:**
Space-separated filenames (excludes `.` and `..`)

**Errors:**
- 404: Directory not found
- 400: Cannot read directory
- 403: Path validation failed (absolute path or `..`)

---

### cat <file>

Read file contents.

**Arguments:**
- `file` (required): Path to file

**Examples:**
```bash
# CLI
php shell.php cat README.md
php shell.php cat .config/key

# HTTP
curl "http://localhost/shell.php/$KEY/cat/README.md"
```

**Output:**
File contents (preserves formatting and newlines)

**Errors:**
- 400: Missing file argument or not a file
- 404: File not found
- 403: Path validation failed

---

### rm <file>

Delete file.

**Arguments:**
- `file` (required): Path to file

**Examples:**
```bash
# CLI
php shell.php rm temp.txt
php shell.php rm logs/old.log

# HTTP
curl "http://localhost/shell.php/$KEY/rm/temp.txt"
```

**Output:**
Empty string on success (POSIX behavior)

**Errors:**
- 400: Missing file argument, not a file, or cannot delete
- 404: File not found
- 403: Path validation failed

---

### mkdir <path>

Create directory (recursive).

**Arguments:**
- `path` (required): Directory path to create

**Examples:**
```bash
# CLI
php shell.php mkdir uploads
php shell.php mkdir app/data/cache

# HTTP
curl "http://localhost/shell.php/$KEY/mkdir/uploads"
```

**Output:**
Empty string on success

**Errors:**
- 400: Missing path argument, already exists, or cannot create
- 403: Path validation failed

---

### cp <src> <dst>

Copy file.

**Arguments:**
- `src` (required): Source file path
- `dst` (required): Destination file path

**Examples:**
```bash
# CLI
php shell.php cp config.json config.backup.json
php shell.php cp README.md docs/README.md

# HTTP
curl "http://localhost/shell.php/$KEY/cp/config.json/config.backup.json"
```

**Output:**
Empty string on success

**Errors:**
- 400: Missing arguments, source not a file, or cannot copy
- 404: Source file not found
- 403: Path validation failed

---

### echo <text>...

Echo text arguments.

**Arguments:**
- `text...` (required): One or more text arguments

**Examples:**
```bash
# CLI
php shell.php echo hello
php shell.php echo hello world "from shell"

# HTTP
curl "http://localhost/shell.php/$KEY/echo/hello"
curl "http://localhost/shell.php/$KEY/echo/hello/world"
```

**Output:**
Space-separated arguments

---

### env [action] [name]

Manage environment variables.

**Actions:**
- `list` (default): List all environment variables
- `get <name>`: Get specific variable value

**Examples:**
```bash
# CLI
php shell.php env list
php shell.php env get PATH
php shell.php env get HOME

# HTTP
curl "http://localhost/shell.php/$KEY/env/list"
curl "http://localhost/shell.php/$KEY/env/get/PATH"
```

**Output:**
- `list`: One variable per line (`KEY=value`)
- `get`: Variable value only

**Errors:**
- 404: Variable not found or unknown action
- 400: Missing variable name for `get`

---

## Package Manager

### pkg add [PACKAGE...]

Install packages with automatic dependency resolution.

**Arguments:**
- `PACKAGE...` (required): One or more package names

**Examples:**
```bash
# CLI
php shell.php pkg add users
php shell.php pkg add users auth database

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/add/users"
curl "http://localhost/shell.php/$KEY/pkg/add/users/auth"
```

**Process:**
1. Fetch repository database
2. Resolve dependencies (DFS topological sort)
3. Download all packages with checksum verification
4. Extract all packages to project root
5. Register packages atomically

**Output:**
```
Packages to install: dependency1, dependency2, users

Downloading packages...
All packages downloaded and verified

Extracting packages...
Extracted dependency1 (1.0.0)
Extracted dependency2 (2.0.0)
Extracted users (3.0.0)

Registering packages...

Successfully installed 3 package(s): dependency1, dependency2, users
```

**Errors:**
- 404: Package not found
- 400: Circular dependency detected
- 503: All mirrors failed or lock held

---

### pkg del <PACKAGE>

Remove package (fails if other packages depend on it).

**Arguments:**
- `PACKAGE` (required): Package name

**Examples:**
```bash
# CLI
php shell.php pkg del users

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/del/users"
```

**Output:**
```
Removed package: users (version 1.0.0) - 15 files deleted, 3 empty directories removed
```

**Errors:**
- 404: Package not installed
- 400: Package required by other packages
- 503: Lock held

---

### pkg upgrade [PACKAGE]

Upgrade all packages or specific package to latest version.

**Arguments:**
- `PACKAGE` (optional): Package name (if omitted, upgrades all)

**Examples:**
```bash
# CLI
php shell.php pkg upgrade           # Upgrade all
php shell.php pkg upgrade users     # Upgrade specific

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/upgrade"
curl "http://localhost/shell.php/$KEY/pkg/upgrade/users"
```

**Output:**
```
Will upgrade users from 1.0.0 to 1.1.0

Downloading package updates...
All package updates downloaded and verified

Extracting package updates...
Extracted users upgrade to 1.1.0

Registering upgrades...

Successfully upgraded 1 package(s): users
```

---

### pkg update

Refresh repository cache (fetch latest package database).

**Examples:**
```bash
# CLI
php shell.php pkg update

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/update"
```

**Output:**
```
Repository cache refreshed - 42 packages available
```

---

### pkg list [FILTER]

List installed packages.

**Arguments:**
- `FILTER` (optional): Filter by package name (case-insensitive)

**Examples:**
```bash
# CLI
php shell.php pkg list
php shell.php pkg list auth

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/list"
curl "http://localhost/shell.php/$KEY/pkg/list/auth"
```

**Output:**
```
Installed packages:

  users                 v1.0.0      installed: 2025-01-15
  auth                  v2.0.0      installed: 2025-01-15 (depends: users)
  database              v1.5.0      installed: 2025-01-15 (depends: users)
```

---

### pkg search <KEYWORD>

Search available packages.

**Arguments:**
- `KEYWORD` (required): Search term

**Examples:**
```bash
# CLI
php shell.php pkg search database

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/search/database"
```

**Output:**
```
Found 3 packages:

  mysql-driver          v1.0.0      MySQL Database Driver [installed]
    Provides MySQL connectivity for applications

  postgres-driver       v1.0.0      PostgreSQL Driver
    PostgreSQL database connector

  database-tools        v2.0.0      Database Management Tools
    Common database utilities and helpers
```

---

### pkg info <PACKAGE>

Show detailed package information.

**Arguments:**
- `PACKAGE` (required): Package name

**Examples:**
```bash
# CLI
php shell.php pkg info users

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/info/users"
```

**Output:**
```
Package: User Management System
ID: users
Latest: 1.0.0
Description: Complete user management with authentication
Author: Mehrnet Team
License: MIT

Installed: v1.0.0 (on 2025-01-15T10:30:00Z)
Dependencies: none

Available versions:
  v1.0.0 - released: 2025-01-15
  v0.9.0 - released: 2025-01-01 (requires: auth-lib)
```

**Errors:**
- 404: Package not found in repository

---

### pkg unlock

Force remove lock file (for manual recovery).

**Examples:**
```bash
# CLI
php shell.php pkg unlock

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/unlock"
```

**Output:**
Empty string on success

**When to use:**
If a package operation crashes, the lock file may remain. This command removes it.

---

### pkg help

Show package manager help.

**Examples:**
```bash
# CLI
php shell.php pkg help

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/help"
```

---

### pkg version

Show package manager version.

**Examples:**
```bash
# CLI
php shell.php pkg version

# HTTP
curl "http://localhost/shell.php/$KEY/pkg/version"
```

---

## Response Protocol

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request (invalid arguments, operational errors) |
| 403 | Forbidden (invalid API key, path validation failed) |
| 404 | Not Found (command, package, or file not found) |
| 503 | Service Unavailable (all mirrors failed, lock held) |

### CLI Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (any type) |

### POSIX Semantics

- Commands with output return data
- Commands without output return empty string (but still exit 0/200)
- Errors throw exceptions with appropriate codes

---

## Configuration Files

### .config/key

Plain text file containing 64-character hex API key.

**Format:**
```
abc123def456789...  (64 hex characters)
```

**Generation:**
```php
$key = bin2hex(random_bytes(32));
```

**Security:**
- Auto-generated on first run
- Used for HTTP authentication
- Auto-loaded for CLI mode
- Timing-safe comparison via `hash_equals()`

---

### .config/repos.json

Repository mirrors configuration.

**Format:**
```json
{
  "main": [
    "https://primary-mirror.com/packages",
    "https://secondary-mirror.com/packages"
  ],
  "extra": [
    "https://extra-repo.com/packages"
  ]
}
```

**Mirror Selection:**
- Mirrors tried sequentially in array order
- No backoff delay on failure
- First successful mirror wins

---

### .config/packages.json

Installed packages registry.

**Format:**
```json
{
  "createdAt": "2025-01-15T10:00:00Z",
  "updatedAt": "2025-01-15T10:30:00Z",
  "packages": {
    "users": {
      "version": "1.0.0",
      "installed_at": "2025-01-15T10:30:00Z",
      "dependencies": [],
      "files": [
        "./app/packages/users/handler.php",
        "./app/packages/users/module.php"
      ],
      "download_url": "https://mirror.com/users-1.0.0.zip",
      "download_time": "2025-01-15T10:29:00Z",
      "checksum": "sha256:abc123...",
      "repository": "main"
    }
  }
}
```

**Metadata:**
- `createdAt`: Registry creation time
- `updatedAt`: Last modification time
- `files`: Array of installed file paths (for removal)
- `download_url`: Source mirror used
- `checksum`: Verified checksum

---

### .config/path.json

Command handler discovery patterns.

**Format:**
```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php"
]
```

**Pattern Matching:**
- `[name]` replaced with command name
- Patterns searched in array order
- First match wins

**Example:**
Command `users` searches:
1. `app/packages/users/handler.php`
2. `bin/users.php`

---

## Custom Commands

### Handler Format

Handlers are PHP files returning a callable:

```php
<?php
return function(array $args): string {
    // Process arguments
    // Return response or throw exception
    return "output";
};
```

### Argument Processing

**HTTP Mode:**
```
GET /shell.php/KEY/mycommand/action/arg1/arg2
                      ↓        ↓      ↓    ↓
                  command   args[0] [1]  [2]
```

**CLI Mode:**
```bash
php shell.php mycommand action arg1 arg2
               ↓        ↓      ↓    ↓
           command   args[0] [1]  [2]
```

**Handler receives:**
```php
function(array $args): string {
    $action = $args[0];  // 'action'
    $arg1 = $args[1];    // 'arg1'
    $arg2 = $args[2];    // 'arg2'
}
```

### Error Handling

Throw exceptions with HTTP status codes:

```php
// Bad request
throw new \RuntimeException('Invalid input', 400);

// Not found
throw new \RuntimeException('Item not found', 404);

// Forbidden
throw new \RuntimeException('Permission denied', 403);
```

**Note:** CLI mode converts all error codes to exit code 1.

### Example Handler

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            // Read data
            $data = json_decode(
                file_get_contents('app/data/items.json'),
                true
            );
            return json_encode($data);

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID required', 400);
            }
            // Fetch item
            return "Item: $id";

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Name required', 400);
            }
            // Create item
            return "Created: $name";

        case 'help':
            return "Actions: list, get <id>, create <name>";

        default:
            throw new \RuntimeException("Unknown action: $action", 404);
    }
};
```

---

## Architecture

### File Structure

```
.
├── shell.php              # Main executable (2300 lines)
├── .config/               # Configuration (auto-created)
│   ├── key                # API key
│   ├── repos.json         # Repository mirrors
│   ├── packages.json      # Installed packages
│   └── path.json          # Handler patterns
├── .cache/                # Cache (auto-created)
│   ├── shell.lock         # Lock file
│   └── *.zip              # Downloaded packages
└── app/packages/          # Installed packages
    └── [package]/
        └── handler.php
```

### Execution Flow

```
1. Runtime Detection (HTTP/CLI)
   ↓
2. Initialize Config Files
   ↓
3. Parse Request (PATH_INFO or argv)
   ↓
4. Validate API Key
   ↓
5. Execute Command
   ├─ Built-in Command
   ├─ Package Manager
   └─ Custom Handler
   ↓
6. Send Response (HTTP headers or STDOUT/STDERR)
```

### Package Manager Flow

**Installation:**
```
1. Resolve Dependencies (DFS topological sort)
   ↓
2. Download All Packages (with checksum verification)
   ↓
3. Extract All Packages (with conflict resolution)
   ↓
4. Register in .config/packages.json (atomic)
```

**Key Features:**
- **Dependency Resolution:** O(V + E) complexity
- **Circular Detection:** O(1) set lookups
- **Checksum Verification:** SHA256 with timing-safe comparison
- **Mirror Failover:** Sequential retry, no backoff
- **Atomic Operations:** All-or-nothing installations
- **Conflict Resolution:** Existing files renamed with `-2`, `-3`, etc.

### Security Model

1. **Authentication:**
   - 64-character random API key
   - `hash_equals()` timing-safe comparison
   - Auto-loaded in CLI mode

2. **Path Validation:**
   - Blocks absolute paths (`/etc/passwd`)
   - Blocks directory traversal (`../../../`)
   - Applied to all file operations

3. **Package Security:**
   - SHA256 checksum verification
   - Zip traversal detection
   - HTTPS mirror enforcement

---

## Performance

### Benchmarks

- **Application execution:** <1ms (measured via profiling)
- **Built-in server overhead:** 1000ms+ (use proper web server in production)
- **Package download:** Depends on network and mirror speed
- **Dependency resolution:** O(V + E) where V = packages, E = dependencies

### Optimization Tips

1. **Use production web server** (Nginx, Apache)
2. **Enable PHP opcode cache** (OPcache)
3. **Use CDN for package mirrors**
4. **Minimize custom handler complexity**
5. **Cache package database locally**

---

## Troubleshooting

### "Invalid API key"

**HTTP 403**

**Cause:** Missing or incorrect API key

**Solution:**
```bash
# Check key
cat .config/key

# Regenerate key (delete and run again)
rm .config/key
php shell.php ls
```

---

### "API key not found" (CLI)

**Exit code 1**

**Cause:** `.config/key` file doesn't exist

**Solution:**
```bash
# Initialize shell
php shell.php ls
```

---

### "Command not found"

**HTTP 404 / Exit code 1**

**Cause:** Handler file not found

**Solution:**
```bash
# Check handler patterns
cat .config/path.json

# Verify handler exists
ls app/packages/[command]/handler.php
ls bin/[command].php
```

---

### "Another pkg operation is in progress"

**HTTP 503 / Exit code 1**

**Cause:** Lock file exists

**Solution:**
```bash
# Wait for operation to complete, or force unlock
php shell.php pkg unlock

# Or manually remove
rm .cache/shell.lock
```

---

### "Package not found"

**HTTP 404 / Exit code 1**

**Cause:** Package not in repository

**Solution:**
```bash
# Refresh repository cache
php shell.php pkg update

# Search for package
php shell.php pkg search keyword
```

---

### "Circular dependency"

**HTTP 400 / Exit code 1**

**Cause:** Package dependency cycle (A → B → C → A)

**Solution:**
Review package dependencies and remove the cycle. This is a package repository issue.

---

### "Cannot remove - required by"

**HTTP 400 / Exit code 1**

**Cause:** Other packages depend on the target package

**Solution:**
```bash
# Remove dependent packages first
php shell.php pkg del dependent-package
php shell.php pkg del target-package
```

---

### Permission Errors

**Cause:** File system permissions

**Solution:**
```bash
# Fix permissions
chmod 755 .
chmod 644 shell.php
chmod 755 .config
chmod 644 .config/*
chmod 755 .cache
```

---

## See Also

- **[Installation Guide](INSTALL.md)** - Setup and configuration
- **[Package Development](PACKAGES.md)** - Creating packages
- **[GitHub Repository](https://github.com/mehrnet/mpm)** - Source code

---

For questions or issues, please visit: https://github.com/mehrnet/mpm/issues
