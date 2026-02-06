#!/bin/bash

# Salon Booking System - Development Tools Installation Script
# This script installs development tools without Composer

echo "Installing development tools for Salon Booking System..."

# Check if we're in the right directory
if [ ! -f "salon.php" ]; then
    echo "Error: Please run this script from the plugin root directory"
    exit 1
fi

# Create vendor directory structure
mkdir -p vendor/bin

# Download PHP CodeSniffer
echo "Installing PHP CodeSniffer..."
if [ ! -f "vendor/bin/phpcs" ]; then
    curl -L https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.7.2/phpcs.phar -o vendor/bin/phpcs
    chmod +x vendor/bin/phpcs
fi

# Download PHP Code Beautifier and Fixer
if [ ! -f "vendor/bin/phpcbf" ]; then
    curl -L https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.7.2/phpcbf.phar -o vendor/bin/phpcbf
    chmod +x vendor/bin/phpcbf
fi

# Download WordPress Coding Standards
echo "Installing WordPress Coding Standards..."
if [ ! -d "vendor/wp-coding-standards" ]; then
    git clone https://github.com/WordPress/WordPress-Coding-Standards.git vendor/wp-coding-standards
fi

# Configure PHP CodeSniffer
echo "Configuring PHP CodeSniffer..."
vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards
vendor/bin/phpcs --config-set default_standard WordPress

echo "Development tools installation complete!"
echo ""
echo "Available commands:"
echo "  vendor/bin/phpcs --standard=WordPress .     # Check coding standards"
echo "  vendor/bin/phpcbf --standard=WordPress .   # Fix coding standards"
echo ""
echo "Note: For full Composer support, upgrade to Composer 2"
