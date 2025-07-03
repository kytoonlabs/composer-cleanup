# Contributing to Composer Cleanup

Thank you for your interest in contributing to Composer Cleanup! This document provides guidelines and information for contributors.

## Development Setup

1. Clone the repository:

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

## Running Tests

```bash
composer test
```

## Code Style

- Follow PSR-12 coding standards
- Use type hints for all method parameters and return types
- Add PHPDoc comments for public methods
- Keep methods focused and single-purpose

## Project Structure

- `src/` - Main source code
  - `Cleaner.php` - Core cleanup logic
  - `Config.php` - Configuration management
- `tests/` - Test files
- `composer-cleanup.example.json` - Example configuration

## Key Classes

### Cleaner

The main class that handles the cleanup process. It's designed as a static class that can be called from Composer scripts.

### Config

Manages configuration loading and validation. Provides sensible defaults for Laravel applications.

## Adding Features

1. Create a feature branch from `main`
2. Implement your changes
3. Add tests for new functionality
4. Update documentation if needed
5. Submit a pull request

## Testing

- Write unit tests for new functionality
- Test with different Laravel application structures
- Ensure dry-run mode works correctly
- Test configuration validation

## Configuration

The package uses a `composer-cleanup.json` configuration file. When adding new configuration options:

1. Update the `Config` class with new properties
2. Add validation if needed
3. Update the example configuration file
4. Update documentation

## Safety Considerations

- Always test in dry-run mode first
- Never remove Composer plugins or metapackages
- Protect Laravel framework packages by default
- Validate all configuration input

## Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Update documentation
6. Ensure all tests pass
7. Submit a pull request with a clear description

## Issues

When reporting issues, please include:

- PHP version
- Composer version
- Laravel version (if applicable)
- Configuration file contents
- Error messages
- Steps to reproduce

## License

By contributing to this project, you agree that your contributions will be licensed under the Apache 2.0 license.
