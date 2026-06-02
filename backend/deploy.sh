#!/bin/bash

# Define the site root directory on aaPanel
SITE_DIR="/www/wwwroot/photobooth"

echo "============================================="
echo "   STARTING AUTOMATED PHOTOBOOTH DEPLOY      "
echo "============================================="

# Check if directory exists
if [ ! -d "$SITE_DIR" ]; then
    echo "ERROR: Directory $SITE_DIR does not exist."
    exit 1
fi

cd "$SITE_DIR" || exit

# Pull latest changes from Github
echo "--> 1. Pulling latest code from GitHub..."
git pull origin main

# Copy backend files to root
if [ -d "backend" ]; then
    echo "--> 2. Deploying backend PHP files to root..."
    cp -r backend/* .
    echo "   Backend files deployed successfully."
else
    echo "--> 2. Warning: 'backend' directory not found. Skipping file copy."
fi

# Ensure storage and frame directories exist
echo "--> 3. Creating required folders (uploads & frames)..."
mkdir -p uploads
mkdir -p frames

# Set correct permissions for aaPanel Nginx/Apache user (www)
echo "--> 4. Setting permissions (chmod 755 & chown www:www)..."
chmod -R 755 uploads frames
chown -R www:www uploads frames

echo "============================================="
echo "   DEPLOYMENT COMPLETED SUCCESSFULLY!        "
echo "============================================="
