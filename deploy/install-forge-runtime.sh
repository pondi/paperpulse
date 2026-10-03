#!/bin/sh
set -eu

apt-get update
apt-get install -y php8.4-cli php8.4-fpm php8.4-pgsql php8.4-zip php8.4-mbstring php8.4-xml php8.4-curl php8.4-bcmath php8.4-intl php8.4-gd php8.4-imagick imagemagick ghostscript
sh deploy/install-office-runtime.sh

# Workers rasterize PDFs; keep ImageMagick PDF writing and other disabled coders blocked.
for policy in /etc/ImageMagick-*/policy.xml; do
    sed -i 's/rights="none" pattern="PDF"/rights="read" pattern="PDF"/' "$policy"
done
