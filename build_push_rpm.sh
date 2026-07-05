#!/bin/bash
# ============================================================
#  Treya Wireless - Auto-Build & Push RPM to GitHub
#  Run as: bash build_push_rpm.sh
# ============================================================
set -e

echo "======================================================"
echo " Treya Wireless - Building and Pushing RPM"
echo "======================================================"

# 1. Run local build script to create the new RPM package
echo "Running build_rpm.sh..."
bash build_rpm.sh

# 2. Add modified files and the built RPM to Git stage
echo "Staging files in Git..."
git add build_rpm.sh
git add build_push_rpm.sh
git add treya-wireless-web-7.0.28-release1.el9.noarch.rpm

# 3. Commit changes (if any)
echo "Committing changes..."
git commit -m "Update treya-wireless-web RPM with latest customizations" || echo "No changes to commit"

# 4. Push to remote repository (will use cached credentials / token)
echo "Pushing commits to GitHub..."
git push origin main

echo "======================================================"
echo " RPM successfully built and pushed to Git!"
echo "======================================================"
