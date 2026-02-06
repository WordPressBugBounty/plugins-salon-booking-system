#!/bin/bash
# Test build process locally to diagnose pipeline issues

echo "=========================================="
echo "LOCAL BUILD TEST"
echo "=========================================="
echo ""

# Get version
export PLUGIN_VERSION=$(egrep -o "Version:\s+(.*)" salon.php | awk '{ print $2 }')
echo "Plugin Version: $PLUGIN_VERSION"
echo ""

# Check build script
echo "Checking build script..."
if [ -f plugin-build ]; then
    echo "✅ plugin-build exists"
    ls -lh plugin-build
else
    echo "❌ plugin-build NOT FOUND"
    exit 1
fi
echo ""

# Check if executable
if [ -x plugin-build ]; then
    echo "✅ plugin-build is executable"
else
    echo "⚠️ plugin-build is not executable, making it executable..."
    chmod +x plugin-build
fi
echo ""

# Check build directory
echo "Checking build directory..."
if [ -d build ]; then
    echo "✅ build/ directory exists"
    ls -la build/ | head -20
else
    echo "❌ build/ directory NOT FOUND"
    exit 1
fi
echo ""

# Check config.php
echo "Checking config.php..."
if [ -f build/config.php ]; then
    echo "✅ build/config.php exists"
    echo "Content:"
    cat build/config.php
else
    echo "❌ build/config.php NOT FOUND"
    exit 1
fi
echo ""

# Enter build directory
echo "Entering build directory..."
cd build
echo "Current directory: $(pwd)"
echo ""

# Check filter files
echo "Checking filter files..."
ls -1 filter* 2>/dev/null || echo "No filter files found"
echo ""

# Create releases directory
echo "Creating releases directory..."
mkdir -p releases
ls -ld releases/
echo ""

# Test a single build
echo "=========================================="
echo "TESTING PRO BUILD"
echo "=========================================="
echo ""
echo "Running: php ../plugin-build pay $PLUGIN_VERSION"
echo ""

php ../plugin-build pay $PLUGIN_VERSION 2>&1

echo ""
echo "=========================================="
echo "BUILD COMPLETE - CHECKING OUTPUT"
echo "=========================================="
echo ""

# Check releases directory
if [ -d releases ]; then
    echo "Contents of releases/:"
    ls -lh releases/
    echo ""
    
    # Check for zip files
    if ls releases/*.zip 1> /dev/null 2>&1; then
        echo "✅ ZIP FILES CREATED:"
        ls -lh releases/*.zip
    else
        echo "❌ NO ZIP FILES FOUND"
    fi
else
    echo "❌ releases/ directory disappeared"
fi

