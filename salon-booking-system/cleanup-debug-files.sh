#!/bin/bash

echo "=== Salon Booking System - Debug Files Cleanup ==="
echo ""

# Check we're in the right directory
if [ ! -f "salon.php" ]; then
    echo "ERROR: Not in plugin root directory!"
    echo "Please cd to the salon-booking-system directory first."
    exit 1
fi

echo "Current directory: $(pwd)"
echo ""

# List files to be deleted
echo "Files to be deleted:"
echo ""

FILES_TO_DELETE=(
    "js/admin/reports-dashboard-debug.js"
    "verify-dashboard.php"
    "tools/verify-dashboard.php"
)

for file in "${FILES_TO_DELETE[@]}"; do
    if [ -f "$file" ]; then
        echo "  - $file (EXISTS)"
    else
        echo "  - $file (NOT FOUND)"
    fi
done

echo ""
read -p "Proceed with deletion? (y/N) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cleanup cancelled."
    exit 0
fi

# Delete files
echo ""
echo "Deleting files..."

for file in "${FILES_TO_DELETE[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Deleted: $file"
    fi
done

echo ""
echo "=== Cleanup Complete ==="
echo ""
echo "Next steps:"
echo "1. Review bitbucket-pipelines.yml and remove excessive debug output"
echo "2. Test the pipeline with simplified logging"
echo "3. Commit the changes"
echo ""
echo "Files deleted: ${#FILES_TO_DELETE[@]}"

