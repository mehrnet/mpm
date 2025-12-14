# 🐚 MPM - Mehr's Package Manager

A minimal, single-file PHP universal package manager with built-in command interpreter.

Execute commands and manage packages via HTTP API or CLI with zero dependencies.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)

---

## Features

- **🚀 Zero Dependencies** - Single `mpm.php` file, no external libraries required
- **🌐 Dual Mode** - Use via HTTP API or command-line interface
- **📦 Package Manager** - Install, upgrade, and remove packages with automatic dependency resolution
- **🔒 Secure** - API key authentication with timing-safe comparison
- **🎯 Extensible** - Add custom commands via simple PHP handlers
- **⚡ Fast** - Application code executes in <1ms

## Quick Start

### Installation

```bash
# Download the shell
wget https://raw.githubusercontent.com/mehrnet/mpm/main/mpm.php

# Make it executable
chmod 644 mpm.php

# Run a command (generates API key on first run)
php mpm.php ls
```

### CLI Usage

```bash
# List files
php mpm.php ls

# Read file
php mpm.php cat README.md

# Package management
php mpm.php pkg help
php mpm.php pkg search database
php mpm.php pkg add users
```

### HTTP Usage

```bash
# Get your API key
KEY=$(cat .config/key)

# Execute commands via HTTP
curl "http://your-server/mpm.php/$KEY/ls"
curl "http://your-server/mpm.php/$KEY/pkg/list"
```

## Built-in Commands

| Command | Description |
|---------|-------------|
| `ls [path]` | List directory contents |
| `cat <file>` | Read file contents |
| `rm <file>` | Delete file |
| `mkdir <path>` | Create directory |
| `cp <src> <dst>` | Copy file |
| `echo <text>...` | Echo text |
| `env [get\|list]` | Environment variables |
| `pkg <action>` | Package manager |

## Package Management

```bash
# Search for packages
php mpm.php pkg search auth

# Install packages
php mpm.php pkg add users auth

# List installed packages
php mpm.php pkg list

# Upgrade all packages
php mpm.php pkg upgrade

# Get package info
php mpm.php pkg info users
```

The package manager features:
- **Dependency Resolution** - Automatic transitive dependency installation
- **Checksum Verification** - SHA256 validation for all downloads
- **Mirror Failover** - Automatic retry on mirror failure
- **Atomic Operations** - All-or-nothing installations

## Architecture

```
┌─────────────────────────────────────┐
│         mpm.php (2300 lines)      │
├─────────────────────────────────────┤
│  Runtime Detection (HTTP/CLI)       │
│  ↓                                   │
│  Request Parsing                    │
│  ↓                                   │
│  API Key Validation                 │
│  ↓                                   │
│  Command Execution                  │
│  ├─ Built-in Commands              │
│  ├─ Package Manager                │
│  └─ Custom Handlers                │
│  ↓                                   │
│  Response (HTTP/CLI)                │
└─────────────────────────────────────┘
```

## Documentation

- **[Installation Guide](docs/INSTALL.md)** - Setup, configuration, and package development
- **[Usage Reference](docs/USAGE.md)** - Complete command reference and API documentation
- **[Package Development](docs/PACKAGES.md)** - Creating and distributing packages

## Use Cases

- **Remote Server Management** - Execute commands on servers via HTTP API
- **CI/CD Pipelines** - Automate deployments and tasks
- **Webhook Handlers** - Process webhooks with custom commands
- **Build Systems** - Manage project dependencies and build steps
- **Development Tools** - Quick prototyping and scripting

## Security Features

- **API Key Authentication** - 64-character cryptographically secure key with timing-safe comparison
- **Path Validation** - Prevents directory traversal and absolute path access
- **Command Validation** - Only alphanumeric command names allowed
- **HTTP Security Headers** - X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy
- **Auto .htaccess** - Blocks access to hidden files (.config/, .cache/)
- **Secure Permissions** - Config files set to 0600, directories to 0700
- **Checksum Verification** - SHA256 validation for all package downloads
- **Safe Extraction** - Conflict resolution with file renaming
- **Localhost Init** - First-run key only shown via CLI or localhost

## Requirements

- PHP 7.0 or higher
- `ZipArchive` extension (for package management)

## Configuration

Auto-generated on first run:

```
.config/          # Mode 0700 (owner only)
├── key           # API key (64 chars, mode 0600)
├── repos.json    # Repository mirrors
├── packages.json # Installed packages
└── path.json     # Command handler patterns
.htaccess         # Auto-generated, blocks dotfiles
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) for details

## Links

- **GitHub Repository**: [mehrnet/mpm](https://github.com/mehrnet/mpm)
- **Package Repository**: [mehrnet/mpm-repo](https://github.com/mehrnet/mpm-repo)
- **Documentation**: [Installation](docs/INSTALL.md) | [Usage](docs/USAGE.md) | [Packages](docs/PACKAGES.md)

---

**Made with ❤️ by the Mehrnet team**
