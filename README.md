git s# Composer Cleanup

A Composer plugin that analyzes Laravel applications and removes unused dependencies from the vendor folder. This package provides a manual command to keep your vendor directory clean and reduce deployment size.

## Features

- **Automatic Analysis**: Scans your Laravel application files to detect used classes and namespaces
- **Smart Detection**: Uses PHP Parser to analyze PHP files and extract usage patterns
- **Configurable**: Customize which directories to scan and which packages to exclude
- **Safe Operation**: Supports dry-run mode to preview changes before applying them
- **Laravel Optimized**: Specifically designed for Laravel applications with sensible defaults
- **Comprehensive Detection**: Identifies classes used in use statements, instantiation, static calls, type hints, and more

## Installation

### Via Composer (Recommended)

1. Install the package:

```bash
composer require kytoonlabs/composer-cleanup --dev
```

2. Create a configuration file:

```bash
cp composer-cleanup.example.json composer-cleanup.json
```

3. Customize the configuration file as needed

### Manual Installation

1. Clone this repository:

```bash
git clone https://github.com/kytoonlabs/composer-cleanup.git
cd composer-cleanup
```

2. Install dependencies:

```bash
composer install
```

3. Run the installation script:

```bash
chmod +x install.sh
./install.sh
```

## Usage

### As Composer Script

Run the cleanup command:

```bash
composer cleanup
```

### Manual Execution

If installed manually, you can run:

```bash
composer cleanup
```

This will:

1. Analyze your Laravel application for used classes and namespaces
2. Identify unused vendor packages
3. Remove the unused packages (unless in dry-run mode)
4. Regenerate autoload files

### Configuration

Create a `composer-cleanup.json` file in your project root to customize the behavior:

```json
{
  "scan_directories": [
    "app",
    "config",
    "database",
    "resources",
    "routes",
    "tests"
  ],
  "exclude_directories": [
    "vendor",
    "node_modules",
    "storage",
    "bootstrap/cache"
  ],
  "exclude_packages": [
    "laravel/framework",
    "laravel/tinker",
    "laravel/sanctum",
    "laravel/telescope",
    "laravel/horizon",
    "laravel/nova",
    "laravel/fortify",
    "laravel/jetstream",
    "laravel/breeze",
    "laravel/ui"
  ],
  "exclude_package_types": ["composer-plugin", "metapackage", "library"],
  "dry_run": true,
  "verbose": false
}
```

### Configuration Options

- **scan_directories**: Directories to scan for PHP files (relative to project root)
- **exclude_directories**: Directories to exclude from scanning
- **exclude_packages**: Package names to never remove (supports partial matches)
- **exclude_package_types**: Package types to never remove
- **dry_run**: If true, shows what would be removed without actually removing
- **verbose**: If true, shows detailed error messages during parsing

## How It Works

1. **File Scanning**: The plugin scans all PHP files in your Laravel application directories
2. **AST Analysis**: Uses PHP Parser to create an Abstract Syntax Tree and extract:
   - `use` statements (imported namespaces)
   - `new` expressions (instantiated classes)
   - Static method calls and property access
   - Class constant access
   - Type hints in function parameters and return types
   - Class inheritance (extends/implements)
   - Trait usage
   - Instanceof checks
   - Catch block exception types
3. **Package Analysis**: Examines each installed package's autoload configuration
4. **Usage Detection**: Determines if a package's classes are actually used in your code
5. **Cleanup**: Removes packages that are not referenced in your application

## Safety Features

- **Dry Run Mode**: Test the cleanup without actually removing files (enabled by default)
- **Exclusion Lists**: Protect important packages from being removed
- **Laravel Framework Protection**: Automatically excludes Laravel core packages
- **Plugin Protection**: Never removes Composer plugins
- **Verbose Logging**: Detailed output for debugging
- **Configuration Validation**: Validates JSON configuration files

## Example Output

```
Loading configuration from composer-cleanup.json
Analyzing Laravel application for used classes...
Found 3 potentially unused packages:
  - monolog/monolog
  - symfony/console
  - guzzlehttp/guzzle
Removed unused package: monolog/monolog
Removed unused package: symfony/console
Removed unused package: guzzlehttp/guzzle
Vendor cleanup completed successfully!
```

## Development

### Running Tests

```bash
composer test
```

### Building

```bash
composer install
```

### Project Structure

```
composer-cleanup/
├── src/                          # Source code
│   ├── Cleaner.php              # Main cleanup logic
│   └── Config.php               # Configuration management
├── tests/                        # Test files
│   └── ComposerCleanerTest.php  # Unit tests
├── composer.json                 # Package configuration
├── composer-cleanup.example.json # Example configuration
├── install.sh                    # Installation script
├── README.md                     # Main documentation
└── LICENSE                       # Apache 2.0 license
```

## Requirements

- PHP >= 8.2
- Composer >= 2.0
- Laravel application (for intended use)

## Dependencies

- `nikic/php-parser`: For PHP code parsing and AST analysis
- `symfony/finder`: For efficient file system operations

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

This package is open-sourced software licensed under the [Apache 2.0](LICENSE) license.

## Disclaimer

This tool analyzes static code and may not detect all dynamic usage patterns. Always test your application thoroughly after running the cleanup, especially in production environments. Consider using dry-run mode first to review what would be removed.

**Important**: The tool runs in dry-run mode by default for safety. Set `"dry_run": false` in your configuration to actually remove packages.
