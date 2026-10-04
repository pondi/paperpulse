#!/bin/sh
set -eu

apt-get update
apt-get install -y php8.5-cli php8.5-fpm php8.5-pgsql php8.5-zip php8.5-mbstring php8.5-xml php8.5-curl php8.5-bcmath php8.5-intl php8.5-gd php8.5-imagick imagemagick ghostscript
sh deploy/install-office-runtime.sh

# Workers rasterize PDFs; keep ImageMagick PDF writing and other disabled coders blocked.
for policy in /etc/ImageMagick-*/policy.xml; do
    sed -i 's/rights="none" pattern="PDF"/rights="read" pattern="PDF"/' "$policy"
done
