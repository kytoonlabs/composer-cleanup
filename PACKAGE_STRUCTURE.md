# Package Structure

This document explains the structure and architecture of the Composer Cleanup package.

## Directory Structure

```
composer-cleanup/
├── src/                          # Source code
│   ├── ComposerCleanupPlugin.php # Main Composer plugin class
│   ├── VendorCleaner.php         # Core cleanup logic
│   └── Config.php                # Configuration management
├── bin/                          # Executable scripts
│   └── cleanup.php               # Standalone cleanup script
├── tests/                        # Test files
│   └── VendorCleanerTest.php     # Basic unit tests
├── composer.json                 # Package configuration
├── composer-cleanup.example.json # Example configuration
├── phpunit.xml                   # PHPUnit configuration
├── install.sh                    # Installation script
├── README.md                     # Main documentation
├── LICENSE                       # MIT license
└── .gitignore                    # Git ignore rules
```

## Core Components

### 1. ComposerCleanupPlugin

- **Purpose**: Main entry point for the Composer plugin
- **Responsibilities**:
  - Implements `PluginInterface` and `EventSubscriberInterface`
  - Registers for `PRE_AUTOLOAD_DUMP` events
  - Initializes the cleanup process
  - Handles error reporting

### 2. VendorCleaner

- **Purpose**: Core logic for analyzing and cleaning vendor dependencies
- **Responsibilities**:
  - Scans Laravel application files
  - Parses PHP files using PHP Parser
  - Extracts used classes and namespaces
  - Identifies unused packages
  - Removes unused packages from vendor directory

### 3. Config

- **Purpose**: Manages configuration settings
- **Responsibilities**:
  - Provides default configuration values
  - Allows customization of scan directories
  - Manages exclusion lists
  - Controls dry-run and verbose modes

## Key Features

### Static Analysis

The package uses PHP Parser to perform static analysis of PHP files, detecting:

- `use` statements (imported namespaces)
- `new` expressions (instantiated classes)
- Static method calls
- Class constant access

### Safety Mechanisms

- **Dry Run Mode**: Preview changes without applying them
- **Exclusion Lists**: Protect important packages
- **Laravel Framework Protection**: Never removes Laravel core packages
- **Plugin Protection**: Never removes Composer plugins

### Configuration

Users can customize behavior through:

- `composer-cleanup.json` configuration file
- Environment variables
- Command-line options

## Integration Points

### Composer Events

- **PRE_AUTOLOAD_DUMP**: Runs before autoload files are generated
- **POST_INSTALL_CMD**: Can run after package installation
- **POST_UPDATE_CMD**: Can run after package updates

### Laravel Integration

- Scans standard Laravel directories: `app`, `config`, `database`, `resources`, `routes`, `tests`
- Excludes Laravel-specific directories: `storage`, `bootstrap/cache`
- Protects Laravel framework packages

## Dependencies

### Required

- `composer-plugin-api`: For Composer plugin functionality
- `nikic/php-parser`: For PHP code parsing
- `symfony/finder`: For file system operations

### Development

- `phpunit/phpunit`: For testing
- `composer/composer`: For development dependencies

## Usage Patterns

### As Composer Plugin

```bash
composer require kytoonlabs/composer-cleanup --dev
composer dump-autoload  # Triggers cleanup automatically
```

### Standalone Script

```bash
php bin/cleanup.php
```

### Manual Execution

```bash
composer cleanup
```

## Error Handling

The package includes comprehensive error handling:

- Graceful handling of parsing errors
- Detailed error reporting in verbose mode
- Safe fallbacks for missing files/directories
- Validation of configuration values

## Performance Considerations

- Uses efficient file system operations
- Implements caching for parsed ASTs
- Minimizes memory usage during large scans
- Provides progress reporting for long operations
