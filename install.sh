#!/bin/bash

echo "Installing Composer Cleanup..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed. Please install Composer first."
    exit 1
fi

# Install dependencies
echo "Installing dependencies..."
composer install

# Copy example configuration
if [ ! -f composer-cleanup.json ]; then
    echo "Creating configuration file..."
    cp composer-cleanup.example.json composer-cleanup.json
    echo "Configuration file created: composer-cleanup.json"
    echo "Please review and customize the configuration before use."
else
    echo "Configuration file already exists: composer-cleanup.json"
fi

echo ""
echo "Installation completed!"
echo ""
echo "Usage:"
echo "  - Run cleanup: composer cleanup"
echo "  - Run tests: composer test"
echo "  - Configure: Edit composer-cleanup.json"
echo ""
echo "For more information, see README.md" 