# Package Structure

This document explains the structure and architecture of the Composer Cleanup package.

## Directory Structure

```
composer-cleanup/
├── src/                          # Source code
│   ├── Cleaner.php              # Main cleanup logic
│   └── Config.php               # Configuration management
├── tests/                        # Test files
│   └── ComposerCleanerTest.php  # Unit tests
├── composer.json                 # Package configuration
├── composer-cleanup.example.json # Example configuration
├── phpunit.xml                   # PHPUnit configuration
├── install.sh                    # Installation script
├── README.md                     # Main documentation
├── LICENSE                       # Apache 2.0 license
└── .gitignore                    # Git ignore rules
```

## Core Components

### 1. Cleaner

- **Purpose**: Main cleanup logic and orchestration
- **Responsibilities**:
  - Scans Laravel application files for used classes and namespaces
  - Uses PHP Parser to analyze PHP files and extract usage patterns
  - Identifies unused packages by comparing used classes with package autoload configurations
  - Removes unused packages from vendor directory
  - Handles error reporting and logging

### 2. Config

- **Purpose**: Manages configuration settings and file loading
- **Responsibilities**:
  - Loads configuration from `composer-cleanup.json`
  - Provides default configuration values
  - Validates JSON configuration files
  - Manages scan directories, exclusion lists, and operation modes
  - Controls dry-run and verbose modes

## Key Features

### Static Analysis

The package uses PHP Parser to perform comprehensive static analysis of PHP files, detecting:

- `use` statements (imported namespaces)
- `new` expressions (instantiated classes)
- Static method calls and property access
- Class constant access
- Type hints in function parameters and return types
- Class inheritance (extends/implements)
- Trait usage
- Instanceof checks
- Catch block exception types
- Function calls (for global functions)

### Safety Mechanisms

- **Dry Run Mode**: Preview changes without applying them (enabled by default)
- **Exclusion Lists**: Protect important packages from being removed
- **Laravel Framework Protection**: Never removes Laravel core packages
- **Plugin Protection**: Never removes Composer plugins
- **Configuration Validation**: Validates JSON configuration files
- **Verbose Logging**: Detailed output for debugging

### Configuration

Users can customize behavior through:

- `composer-cleanup.json` configuration file
- Default configuration with sensible Laravel-specific settings
- Environment-specific customization

## Integration Points

### Composer Scripts

- **cleanup**: Manual execution of the cleanup process
- **test**: Runs PHPUnit tests

### Laravel Integration

- Scans standard Laravel directories: `app`, `config`, `database`, `resources`, `routes`, `tests`
- Excludes Laravel-specific directories: `storage`, `bootstrap/cache`
- Protects Laravel framework packages by default
- Optimized for Laravel application structure

## Dependencies

### Required

- `php`: >= 8.2
- `nikic/php-parser`: For PHP code parsing and AST analysis
- `symfony/finder`: For efficient file system operations

### Development

- `phpunit/phpunit`: For testing
- `composer/composer`: For development dependencies

## Usage Patterns

### As Composer Script

```bash
composer require kytoonlabs/composer-cleanup --dev
composer cleanup  # Runs the cleanup process
```

### Manual Installation

```bash
git clone https://github.com/kytoonlabs/composer-cleanup.git
cd composer-cleanup
composer install
./install.sh
```

## Error Handling

The package includes comprehensive error handling:

- Graceful handling of PHP parsing errors
- Detailed error reporting in verbose mode
- Safe fallbacks for missing files/directories
- JSON configuration validation
- Clear error messages for configuration issues

## Performance Considerations

- Uses efficient file system operations with Symfony Finder
- Implements AST parsing for accurate class detection
- Minimizes memory usage during large scans
- Provides progress reporting for long operations
- Optimized for Laravel application structure

## Configuration Defaults

The package provides sensible defaults for Laravel applications:

- **Scan directories**: `app`, `config`, `database`, `resources`, `routes`, `tests`
- **Exclude directories**: `vendor`, `node_modules`, `storage`, `bootstrap/cache`
- **Exclude packages**: Laravel framework packages and common Laravel packages
- **Exclude package types**: `composer-plugin`, `metapackage`, `library`
- **Dry run**: `true` (for safety)
- **Verbose**: `false`

## Security Considerations

- Runs in dry-run mode by default to prevent accidental package removal
- Validates all configuration input
- Never removes Composer plugins or metapackages
- Protects Laravel framework packages by default
- Requires explicit configuration to enable actual package removal
