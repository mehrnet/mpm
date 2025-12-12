# Installation & Setup Guide

Complete guide for installing, configuring, and getting started with MPM - Mehr's Package Manager.

## Table of Contents

- [Installation](#installation)
- [First Run](#first-run)
- [Configuration](#configuration)
- [Getting Started](#getting-started)
- [Package Development](#package-development)
- [Repository Setup](#repository-setup)
- [Deployment](#deployment)

---

## Installation

### Quick Install

```bash
# Download shell.php
wget https://raw.githubusercontent.com/mehrnet/mpm/main/shell.php

# Set permissions
chmod 644 shell.php

# Test installation
php shell.php echo "Hello, World!"
```

### Manual Install

1. Download `shell.php` from the repository
2. Place it in your project root or web server directory
3. Ensure PHP 8.1+ is installed
4. Verify `ZipArchive` extension is enabled (for package management)

### Requirements

- **PHP**: 8.1 or higher
- **Extensions**: `ZipArchive` (for package management)
- **Permissions**: Read/write access to project directory

## First Run

On first execution, MPM automatically generates configuration files:

```bash
# Run any command to initialize
php shell.php ls
```

This creates:

```
.config/
├── key              # API key (64 hex characters)
├── repos.json       # Repository mirrors configuration
├── packages.json    # Installed packages registry
└── path.json        # Command handler patterns
```

### API Key

Your API key is displayed on first HTTP access or saved to `.config/key` on first CLI run:

```bash
# View your API key
cat .config/key
```

**Important:** Keep your API key secure. Anyone with access can execute commands.

## Configuration

### Repository Mirrors

Edit `.config/repos.json` to customize package repositories:

```json
{
  "main": [
    "https://repo.example.com",
    "https://mirror1.example.com",
    "https://mirror2.example.com"
  ],
  "extra": [
    "https://extra-repo.example.com"
  ]
}
```

Mirrors are tried sequentially on failure with no backoff delay.

### Command Handler Patterns

Edit `.config/path.json` to customize where the shell looks for custom commands:

```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php",
  "custom/handlers/[name].php"
]
```

The `[name]` placeholder is replaced with the command name.

### Environment-Specific Configuration

For different environments, you can:

1. **Use different config directories:**
   ```php
   // Modify constants in shell.php (not recommended)
   const DIR_CONFIG = '.config';
   ```

2. **Symlink configuration:**
   ```bash
   ln -s .config.production .config
   ```

3. **Environment variables:**
   Access via `php shell.php env list`

## Getting Started

### CLI Mode (Recommended for Development)

```bash
# List files
php shell.php ls

# Read file contents
php shell.php cat shell.php | head -20

# Create directory
php shell.php mkdir tmp

# Copy files
php shell.php cp shell.php shell.backup.php

# Package management
php shell.php pkg search database
php shell.php pkg add users
php shell.php pkg list
php shell.php pkg upgrade
```

### HTTP Mode (Production)

1. **Start PHP development server (testing):**
   ```bash
   php -S localhost:8000
   ```

2. **Get your API key:**
   ```bash
   KEY=$(cat .config/key)
   ```

3. **Execute commands:**
   ```bash
   # List files
   curl "http://localhost:8000/shell.php/$KEY/ls"

   # Read file
   curl "http://localhost:8000/shell.php/$KEY/cat/README.md"

   # Package management
   curl "http://localhost:8000/shell.php/$KEY/pkg/list"
   curl "http://localhost:8000/shell.php/$KEY/pkg/add/users"
   ```

### Production Web Server

For production, use a proper web server:

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/shell;
    index shell.php;

    location / {
        try_files $uri $uri/ /shell.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index shell.php;
        include fastcgi_params;
    }
}
```

**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ shell.php/$1 [L,QSA]
```

## Package Development

### Creating a Package

1. **Create package structure:**
   ```
   mypackage/
   ├── handler.php       # Required: Entry point
   ├── package.json      # Required: Metadata
   ├── lib/             # Optional: Library code
   └── data/            # Optional: Data files
   ```

2. **Write handler.php:**
   ```php
   <?php
   return function(array $args): string {
       $action = $args[0] ?? 'help';

       switch ($action) {
           case 'list':
               return "Item 1\nItem 2\nItem 3";

           case 'create':
               $name = $args[1] ?? null;
               if (!$name) {
                   throw new \RuntimeException('Name required', 400);
               }
               return "Created: $name";

           default:
               return "Actions: list, create";
       }
   };
   ```

3. **Create package.json:**
   ```json
   {
       "id": "mypackage",
       "name": "My Package",
       "version": "1.0.0",
       "description": "Package description",
       "author": "Your Name",
       "license": "MIT",
       "dependencies": []
   }
   ```

### Package Metadata Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Lowercase, no spaces, used in URLs |
| `name` | string | Yes | Human-readable name |
| `version` | string | Yes | Semantic versioning (X.Y.Z) |
| `description` | string | Yes | Brief description |
| `author` | string | No | Package author |
| `license` | string | No | License identifier (MIT, Apache-2.0, etc.) |
| `dependencies` | array | No | Array of package IDs |

### Testing Your Package

**CLI Mode:**
```bash
# Install manually
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# Test commands
php shell.php mypackage list
php shell.php mypackage create item-name
```

**HTTP Mode:**
```bash
KEY=$(cat .config/key)
curl "http://localhost:8000/shell.php/$KEY/mypackage/list"
curl "http://localhost:8000/shell.php/$KEY/mypackage/create/item-name"
```

### Best Practices

1. **Input Validation:**
   ```php
   $id = $args[0] ?? null;
   if (!$id || !is_numeric($id)) {
       throw new \RuntimeException('Invalid ID', 400);
   }
   ```

2. **Error Handling:**
   ```php
   try {
       $data = json_decode(file_get_contents('data.json'), true);
       if (!$data) {
           throw new \RuntimeException('Invalid JSON', 400);
       }
   } catch (\Throwable $e) {
       throw new \RuntimeException("Error: {$e->getMessage()}", 400);
   }
   ```

3. **Security:**
   ```php
   // BAD: Direct use of user input
   $file = $args[0];
   return file_get_contents($file);  // Vulnerable to path traversal

   // GOOD: Validate and restrict paths
   $file = basename($args[0] ?? '');  // Remove path components
   $path = "app/data/$file";
   if (!file_exists($path)) {
       throw new \RuntimeException('File not found', 404);
   }
   return file_get_contents($path);
   ```

## Repository Setup

### Creating a Package Repository

1. **Create repository structure:**
   ```
   my-repo/
   └── main/
       ├── database.json
       ├── mypackage-1.0.0.zip
       └── otherapkg-1.0.0.zip
   ```

2. **Build package ZIP:**
   ```bash
   cd mypackage
   zip -r ../my-repo/main/mypackage-1.0.0.zip .
   ```

3. **Calculate checksum:**
   ```bash
   sha256sum my-repo/main/mypackage-1.0.0.zip
   # Output: abc123def456...  mypackage-1.0.0.zip
   ```

4. **Create database.json:**
   ```json
   {
     "version": 1,
     "packages": {
       "mypackage": {
         "name": "My Package",
         "description": "Package description",
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
         "latest": "1.0.0",
         "author": "Your Name",
         "license": "MIT"
       }
     }
   }
   ```

5. **Host the repository:**
   - Upload to GitHub: `https://github.com/user/repo/raw/main/main/`
   - Use CDN or static hosting
   - Ensure HTTPS is enabled

6. **Configure shell to use your repository:**
   Edit `.config/repos.json`:
   ```json
   {
     "main": ["https://github.com/user/repo/raw/main/main"]
   }
   ```

### GitHub Hosting Example

```bash
# Create repository
mkdir -p my-packages/main
cd my-packages

# Add packages
cp /path/to/mypackage-1.0.0.zip main/
cat > main/database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# Push to GitHub
git init
git add .
git commit -m "Add packages"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# Users configure: https://github.com/user/my-packages/raw/main/main
```

## Deployment

### Development

```bash
# Run PHP built-in server
php -S localhost:8000

# Test commands
php shell.php pkg list
curl "http://localhost:8000/shell.php/$(cat .config/key)/pkg/list"
```

### Staging/Production

1. **Use proper web server** (Nginx, Apache, Caddy)
2. **Enable HTTPS** for API key security
3. **Set restrictive permissions:**
   ```bash
   chmod 644 shell.php
   chmod 700 .config
   chmod 600 .config/key
   ```

4. **Configure PHP-FPM** for better performance
5. **Set up monitoring** and logging
6. **Regular backups** of `.config/` directory

### Docker Deployment

```dockerfile
FROM php:8.1-fpm

# Install extensions
RUN docker-php-ext-install zip

# Copy shell
COPY shell.php /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
```

### Security Hardening

1. **Restrict API key access:**
   ```bash
   chmod 600 .config/key
   chown www-data:www-data .config/key
   ```

2. **Use environment-specific keys**
3. **Enable HTTPS only** in production
4. **Rate limiting** at web server level
5. **Monitor failed auth attempts**
6. **Regular security updates**

## Troubleshooting

### Common Issues

**"Permission denied" errors:**
```bash
# Fix permissions
chmod 755 .
chmod 644 shell.php
chmod -R 755 .config
```

**"ZipArchive not found":**
```bash
# Install extension
sudo apt-get install php-zip  # Debian/Ubuntu
sudo yum install php-zip       # CentOS/RHEL
```

**"API key not found" (CLI mode):**
```bash
# Generate key manually
php shell.php ls  # This creates .config/key
```

**Lock file issues:**
```bash
# Force unlock if stuck
php shell.php pkg unlock
```

## Next Steps

- Read the [Usage Reference](USAGE.md) for complete command documentation
- See [Package Development](PACKAGES.md) for advanced package creation
- Check out [example packages](https://github.com/mehrnet/mpm-packages)

---

For questions or issues, please visit: https://github.com/mehrnet/mpm
