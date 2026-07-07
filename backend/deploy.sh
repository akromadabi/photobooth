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
echo "--> 1. Fetching and hard-resetting code to match origin/main..."
git fetch origin main
git reset --hard origin/main

# Copy backend files to root (optional fallback, but kept for compatibility)
if [ -d "backend" ]; then
    echo "--> 2. Syncing backend PHP files (excluding uploads)..."
    cp backend/*.php . 2>/dev/null || true
    
    # Sync JSON files selectively without overwriting active production configurations
    for f in backend/*.json; do
        if [ -f "$f" ]; then
            filename=$(basename "$f")
            if [ "$filename" = "settings.json" ] || [ "$filename" = "packages.json" ] || [ "$filename" = "coupons.json" ] || [ "$filename" = "queue.json" ]; then
                if [ ! -f "$filename" ]; then
                    echo "    Copying default $filename (file does not exist)"
                    cp "$f" . 2>/dev/null || true
                else
                    echo "    Skipping $filename (keeping existing production file)"
                fi
            else
                cp "$f" . 2>/dev/null || true
            fi
        fi
    done

    cp backend/*.html . 2>/dev/null || true
    cp backend/*.png . 2>/dev/null || true
    cp backend/*.apk . 2>/dev/null || true
    
    if [ -d "backend/characters" ]; then
        cp -r backend/characters . 2>/dev/null || true
    fi

    # Sync frames directory structure and assets, but preserve config.json if it exists
    if [ -d "backend/frames" ]; then
        mkdir -p frames
        # Copy everything except config.json
        for item in backend/frames/*; do
            if [ -e "$item" ]; then
                name=$(basename "$item")
                if [ "$name" != "config.json" ]; then
                    cp -r "$item" frames/ 2>/dev/null || true
                fi
            fi
        done
        # Handle frames/config.json separately
        if [ ! -f "frames/config.json" ] && [ -f "backend/frames/config.json" ]; then
            echo "    Copying default frames/config.json (file does not exist)"
            cp backend/frames/config.json frames/config.json 2>/dev/null || true
        else
            echo "    Skipping frames/config.json (keeping existing production file)"
        fi
    fi
else
    echo "--> 2. Warning: 'backend' directory not found."
fi

# Ensure storage and frame directories exist in both root and backend subfolder
echo "--> 3. Creating required folders (uploads & frames)..."
mkdir -p uploads
mkdir -p frames
mkdir -p backend/uploads 2>/dev/null || true
mkdir -p backend/frames 2>/dev/null || true

# Set correct permissions for aaPanel Nginx/Apache user (www)
echo "--> 4. Setting permissions (chmod 755 & chown www:www)..."
chmod -R 755 uploads frames 2>/dev/null || true
chown -R www:www uploads frames 2>/dev/null || true

if [ -d "backend" ]; then
    chmod -R 755 backend 2>/dev/null || true
    chown -R www:www backend 2>/dev/null || true
fi

echo "============================================="
echo "   DEPLOYMENT COMPLETED SUCCESSFULLY!        "
echo "============================================="
