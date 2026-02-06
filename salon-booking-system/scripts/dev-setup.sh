#!/bin/bash

# Salon Booking System - Development Setup Script

echo "Setting up Salon Booking System development environment..."

# Check if we're in the right directory
if [ ! -f "salon.php" ]; then
    echo "Error: Please run this script from the plugin root directory"
    exit 1
fi

# Install PHP dependencies
echo "Installing PHP dependencies..."
if command -v composer &> /dev/null; then
    # Check Composer version
    COMPOSER_VERSION=$(composer --version | grep -o '[0-9]\+\.[0-9]\+' | head -1)
    echo "Composer version: $COMPOSER_VERSION"
    
    # Try to install with current composer.json
    if ! composer install --no-dev --dry-run &> /dev/null; then
        echo "Current composer.json has compatibility issues. Using simplified version..."
        cp composer-simple.json composer.json
    fi
    
    composer install
else
    echo "Composer not found. Please install Composer first."
    exit 1
fi

# Install Node.js dependencies
echo "Installing Node.js dependencies..."
if command -v npm &> /dev/null; then
    npm install
else
    echo "npm not found. Please install Node.js first."
    exit 1
fi

# Set up PHP CodeSniffer
echo "Setting up PHP CodeSniffer..."
if [ -f "vendor/bin/phpcs" ]; then
    vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
    vendor/bin/phpcs --config-set default_standard WordPress
    echo "PHP CodeSniffer configured for WordPress standards"
fi

# Create necessary directories
echo "Creating build directories..."
mkdir -p build/tmp
mkdir -p build/releases

# Set up environment file
if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    cat > .env << EOF
# Development Environment Variables
WP_DEBUG=true
WP_DEBUG_LOG=true

# Transifex Configuration (optional)
# TRANSIFEX_USER=your_username
# TRANSIFEX_PASSWORD=your_password
# POT_BUG_REPORT=your_email
# POT_TEAM=your_team
EOF
    echo ".env file created. Please configure as needed."
fi

echo "Development environment setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your WordPress development site"
echo "2. Install the plugin in your WordPress site"
echo "3. Run 'npm run compile:sass' to compile assets"
echo "4. Run 'composer run test' to check code quality"
