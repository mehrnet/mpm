# Package Development Guide

Complete guide for creating, testing, and distributing MPM (Mehr's Package Manager) packages.

<!--html-ignore-->
For installation and setup instructions, see [INSTALL.md](INSTALL.md) or [USAGE.md](USAGE.md).

---

## Table of Contents

- [Quick Start](#quick-start)
- [Package Structure](#package-structure)
- [Handler Implementation](#handler-implementation)
- [Package Metadata](#package-metadata)
- [Testing Packages](#testing-packages)
- [Distribution](#distribution)
- [Best Practices](#best-practices)
- [Examples](#examples)

---
<!--/html-ignore-->

## Quick Start

Create a minimal package in 3 steps:

```bash
# 1. Create package structure
mkdir -p mypackage
cd mypackage

# 2. Create handler
cat > handler.php << 'EOF'
<?php
return function(array $args): string {
    return "Hello from mypackage!";
};
EOF

# 3. Create metadata
cat > package.json << 'EOF'
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "A simple package",
    "author": "Your Name",
    "license": "MIT",
    "dependencies": []
}
EOF

# Test it
cd ..
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/
php mpm.php mypackage
```

---

## Package Structure

### Required Files

```
mypackage/
├── handler.php       # REQUIRED: Entry point
└── package.json      # REQUIRED: Metadata
```

### Optional Structure

```
mypackage/
├── handler.php       # Entry point (required)
├── package.json      # Metadata (required)
├── lib/             # Library code
│   ├── MyClass.php
│   └── helpers.php
├── data/            # Data files
│   └── config.json
├── views/           # Templates
│   └── template.html
└── README.md        # Package documentation
```

### File Extraction

Packages extract to project root preserving paths:

**Package contains:**
```
handler.php
module.php
lib/Database.php
data/schema.json
```

**Extracts to:**
```
./handler.php
./module.php
./lib/Database.php
./data/schema.json
```

For Mehr framework modules, use:
```
app/packages/[name]/handler.php
app/packages/[name]/module.php
```

---

## Handler Implementation

### Basic Handler

```php
<?php
return function(array $args): string {
    // Single action
    return "Hello, World!";
};
```

### Multi-Action Handler

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            return handleList();

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID required', 400);
            }
            return handleGet($id);

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Name required', 400);
            }
            return handleCreate($name);

        case 'delete':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID required', 400);
            }
            return handleDelete($id);

        case 'help':
            return <<<'EOF'
Available actions:
  list              - List all items
  get <id>          - Get item by ID
  create <name>     - Create new item
  delete <id>       - Delete item
EOF;

        default:
            throw new \RuntimeException("Unknown action: $action", 404);
    }
};

function handleList(): string {
    return "item1\nitem2\nitem3";
}

function handleGet(string $id): string {
    // Load from file, database, etc.
    return "Item data for: $id";
}

function handleCreate(string $name): string {
    // Create and persist
    return "Created: $name";
}

function handleDelete(string $id): string {
    // Remove item
    return "Deleted: $id";
}
```

### Argument Processing

Arguments are identical in both HTTP and CLI modes:

**HTTP:**
```
GET /mpm.php/KEY/users/create/alice/alice@example.com
                    ↓      ↓      ↓         ↓
                command args[0] args[1]  args[2]
```

**CLI:**
```bash
php mpm.php users create alice alice@example.com
               ↓      ↓      ↓         ↓
           command args[0] args[1]  args[2]
```

**Handler:**
```php
function(array $args): string {
    $action = $args[0];    // 'create'
    $name = $args[1];      // 'alice'
    $email = $args[2];     // 'alice@example.com'
}
```

### File Operations

Handlers execute in mpm.php directory. Use relative paths:

```php
<?php
return function(array $args): string {
    // Read data file
    $data = json_decode(
        file_get_contents('app/data/items.json'),
        true
    );

    // Modify data
    $data['new_item'] = $args[0] ?? 'default';

    // Write back
    file_put_contents(
        'app/data/items.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );

    return "Updated";
};
```

### Error Handling

```php
<?php
return function(array $args): string {
    $id = $args[0] ?? null;

    // Validation
    if (!$id) {
        throw new \RuntimeException('ID required', 400);
    }

    if (!is_numeric($id)) {
        throw new \RuntimeException('ID must be numeric', 400);
    }

    // File operations
    $file = "app/data/$id.json";
    if (!file_exists($file)) {
        throw new \RuntimeException('Item not found', 404);
    }

    try {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!$data) {
            throw new \RuntimeException('Invalid JSON', 400);
        }

        return json_encode($data);
    } catch (\Throwable $e) {
        throw new \RuntimeException(
            "Failed to read item: {$e->getMessage()}",
            400
        );
    }
};
```

### Response Formats

**Plain Text:**
```php
return "item1\nitem2\nitem3";
```

**JSON:**
```php
return json_encode([
    'status' => 'success',
    'items' => ['item1', 'item2', 'item3']
]);
```

**Empty (POSIX):**
```php
return '';  // Success with no output
```

---

## Package Metadata

### package.json Format

```json
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "Package description",
    "author": "Your Name",
    "license": "MIT",
    "dependencies": ["dependency1", "dependency2"]
}
```

### Field Specifications

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Lowercase, no spaces, used in URLs |
| `name` | string | Yes | Human-readable name |
| `version` | string | Yes | Semantic versioning (X.Y.Z) |
| `description` | string | Yes | Brief description |
| `author` | string | No | Package author |
| `license` | string | No | License identifier (MIT, Apache-2.0, etc.) |
| `dependencies` | array | No | Array of package IDs |

### Dependency Declaration

```json
{
    "id": "auth",
    "dependencies": ["users", "session"]
}
```

The package manager:
- Resolves transitive dependencies automatically
- Detects circular dependencies
- Installs in correct order (dependencies before dependents)
- Skips already-installed packages

---

## Testing Packages

### Manual Testing (CLI)

```bash
# Install package manually
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# Test commands
php mpm.php mypackage
php mpm.php mypackage list
php mpm.php mypackage get 123
php mpm.php mypackage create "Test Item"
```

### Manual Testing (HTTP)

```bash
# Start server
php -S localhost:8000

# Get API key
KEY=$(cat .config/key)

# Test commands
curl "http://localhost:8000/mpm.php/$KEY/mypackage"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/list"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/get/123"
```

### Testing Checklist

- [ ] All actions work correctly
- [ ] Error handling works (invalid input)
- [ ] Required arguments are validated
- [ ] File operations succeed
- [ ] Both CLI and HTTP modes work identically
- [ ] Help action documents all commands
- [ ] Dependencies are declared in package.json

---

## Distribution

### Creating Package ZIP

```bash
cd mypackage
zip -r ../mypackage-1.0.0.zip .

# Verify contents
unzip -l ../mypackage-1.0.0.zip
```

### Calculate Checksum

```bash
sha256sum mypackage-1.0.0.zip
# Output: abc123def456...  mypackage-1.0.0.zip
```

### Automated Build Scripts

For reusable packaging, create shell scripts that automate the build process. This is especially useful for packaging third-party assets like icons, fonts, or JavaScript libraries.

**Basic structure:**

```bash
#!/bin/sh
set -e

PKG="mypackage"
DES="Package description"
VER="1.0.0"

URL="https://example.com/source.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
# Download and extract source
wget -q "$URL" -O src.zip
unzip -q src.zip

# Restructure for your format
mkdir -p "assets/category/${PKG}"
mv source-files/* "assets/category/${PKG}/"

# Create package zip
zip -rq "$OUT" assets

# Calculate metadata
CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

# Move to repository
mv "$OUT" "${SCRIPT_DIR}/../main/"

# Update database.json
DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" \
   --arg name "$PKG" \
   --arg desc "$DES" \
   --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" \
   --argjson size "$SIZE" \
   --arg url "$OUT" \
   --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name,
       description: $desc,
       author: "mpm-bot",
       license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Example: Icon package (heroicons)**

```bash
#!/bin/sh
set -e

PKG="heroicons"
DES="A set of free MIT-licensed high-quality SVG icons for UI development."
VER="2.2.0"

URL="https://github.com/tailwindlabs/heroicons/archive/refs/tags/v${VER}.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
wget -q "$URL" -O src.zip
unzip -q src.zip

mkdir -p "assets/icons/${PKG}"
mv "heroicons-${VER}/src/"* "assets/icons/${PKG}/"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Example: JavaScript library (htmx)**

```bash
#!/bin/sh
set -e

PKG="htmx"
DES="High power tools for HTML."
VER="2.0.4"

URL="https://unpkg.com/htmx.org@${VER}/dist/htmx.min.js"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
mkdir -p "assets/js"
wget -q "$URL" -O "assets/js/htmx.min.js"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "BSD-2-Clause",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Repository structure:**

```
mpm-repo/
├── scripts/
│   ├── heroicons.sh
│   ├── htmx.sh
│   ├── alpinejs.sh
│   └── ...
└── main/
    ├── database.json
    ├── heroicons-2.2.0.zip
    ├── htmx-2.0.4.zip
    └── ...
```

**Build all packages:**

```bash
for script in scripts/*.sh; do ./$script; done
```

### Repository database.json

```json
{
  "version": 1,
  "packages": {
    "mypackage": {
      "name": "My Package",
      "description": "Package description",
      "author": "Your Name",
      "license": "MIT",
      "versions": {
        "1.0.0": {
          "version": "1.0.0",
          "dependencies": [],
          "checksum": "sha256:abc123def456...",
          "size": 2048,
          "download_url": "mypackage-1.0.0.zip",
          "released_at": "2025-01-15"
        }
      },
      "latest": "1.0.0"
    }
  }
}
```

Files are tracked locally during extraction for clean uninstallation.

### GitHub Hosting

```bash
# Create repository structure
mkdir -p my-packages/main
cd my-packages/main

# Add package and database
cp /path/to/mypackage-1.0.0.zip .
cat > database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# Push to GitHub
cd ..
git init
git add .
git commit -m "Add mypackage"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# Users configure:
# "main": ["https://github.com/user/my-packages/raw/main/main"]
```

---

## Best Practices

### Input Validation

```php
// BAD: No validation
$id = $args[0];
return processId($id);

// GOOD: Validate and sanitize
$id = $args[0] ?? null;
if (!$id || !is_numeric($id) || $id < 1) {
    throw new \RuntimeException('Invalid ID', 400);
}
return processId((int)$id);
```

### Security

```php
// BAD: Arbitrary file access
$file = $args[0];
return file_get_contents($file);  // Vulnerable to ../../../etc/passwd

// GOOD: Restrict to safe directory
$file = basename($args[0] ?? '');  // Remove path components
$path = "app/data/$file";

if (!file_exists($path)) {
    throw new \RuntimeException('File not found', 404);
}

return file_get_contents($path);
```

### Error Messages

```php
// BAD: Generic error
throw new \RuntimeException('Error', 400);

// GOOD: Descriptive error
throw new \RuntimeException('User ID must be a positive integer', 400);
```

### Code Organization

```php
// BAD: Everything in handler
return function(array $args): string {
    // 500 lines of code...
};

// GOOD: Use lib/ directory
return function(array $args): string {
    require_once __DIR__ . '/lib/Manager.php';
    $manager = new Manager();
    return $manager->handle($args);
};
```

### Dependencies

```php
// GOOD: Check if Composer libraries available
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Use libraries
} else {
    throw new \RuntimeException('Composer dependencies missing', 500);
}
```

---

## Examples

### Simple Counter

```php
<?php
return function(array $args): string {
    $file = 'app/data/counter.txt';

    if (!file_exists($file)) {
        file_put_contents($file, '0');
    }

    $count = (int)file_get_contents($file);
    $count++;
    file_put_contents($file, (string)$count);

    return "Count: $count";
};
```

### JSON CRUD

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'list';
    $file = 'app/data/items.json';

    // Load data
    $items = [];
    if (file_exists($file)) {
        $items = json_decode(file_get_contents($file), true) ?? [];
    }

    switch ($action) {
        case 'list':
            return json_encode($items);

        case 'add':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Name required', 400);
            }
            $items[] = [
                'id' => count($items) + 1,
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));
            return "Added: $name";

        case 'get':
            $id = (int)($args[1] ?? 0);
            foreach ($items as $item) {
                if ($item['id'] === $id) {
                    return json_encode($item);
                }
            }
            throw new \RuntimeException('Item not found', 404);

        default:
            throw new \RuntimeException('Unknown action', 404);
    }
};
```

### Mehr Framework Module

```
mymodule/
├── app/
│   └── packages/
│       └── mymodule/
│           ├── handler.php       # Shell command handler
│           ├── module.php        # Module implementation
│           ├── manifest.json     # Mehr manifest
│           └── migrations/       # Database migrations
│               └── 001_create_table.php
└── package.json                  # Distribution metadata
```

**handler.php:**
```php
<?php
return function(array $args): string {
    require_once __DIR__ . '/module.php';
    $module = new MyModule();
    return $module->handleCommand($args);
};
```

**package.json:**
```json
{
    "id": "mymodule",
    "name": "My Module",
    "version": "1.0.0",
    "description": "Mehr framework module",
    "dependencies": ["core"]
}
```

---

<!--html-ignore-->
## See Also

- **[Installation Guide](INSTALL.md)** - Setup and repository creation
- **[Usage Reference](USAGE.md)** - Command reference
- **[Example Packages](https://github.com/mehrnet/mpm-packages)** - Working examples

---

For questions or issues, please visit: https://github.com/mehrnet/mpm/issues
<!--/html-ignore-->
