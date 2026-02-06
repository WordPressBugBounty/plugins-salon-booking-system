#!/bin/bash
# Deploy Check for Updates Fix to Production
# 
# This script copies the fixed Manager.php to production via SCP
# Adjust the SSH credentials and paths as needed

LOCAL_FILE="/Users/macbookpro/Desktop/Salon Booking System/development/bitbucket/src/SLN/Update/Manager.php"
REMOTE_USER="salonsb"
REMOTE_HOST="salon.salonbooking.it"
REMOTE_PATH="/home/salonsb/web/salon.salonbooking.it/public_html/wp-content/plugins/salon-booking-plugin-pro/src/SLN/Update/Manager.php"

echo "🚀 Deploying Manager.php fix to production..."
echo ""
echo "Local file: $LOCAL_FILE"
echo "Remote: $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"
echo ""

# Backup existing file first
echo "📦 Creating backup of production file..."
ssh $REMOTE_USER@$REMOTE_HOST "cp $REMOTE_PATH ${REMOTE_PATH}.backup-$(date +%Y%m%d-%H%M%S)"

# Upload the fixed file
echo "⬆️  Uploading fixed file..."
scp "$LOCAL_FILE" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS! File deployed to production"
    echo ""
    echo "Next steps:"
    echo "1. Clear caches on production:"
    echo "   ssh $REMOTE_USER@$REMOTE_HOST 'cd /home/salonsb/web/salon.salonbooking.it/public_html && wp transient delete --all'"
    echo ""
    echo "2. Test the 'Check for Updates' button"
    echo "   Should now show: Update from 10.29.6 to 10.30.5"
else
    echo ""
    echo "❌ FAILED to deploy. Please upload manually via FTP."
fi

