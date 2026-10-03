#!/bin/sh
set -eu

apt-get update
apt-get install -y libreoffice-writer libreoffice-calc libreoffice-impress bubblewrap fonts-dejavu fonts-liberation
