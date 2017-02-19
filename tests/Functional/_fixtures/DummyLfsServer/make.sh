#!/usr/bin/env sh

set -e
make
cd obj
rm -f ../LfsDummyImage.zip
zip -r ../LfsDummyImage.zip DCon.exe
cd ..
make clean
echo "Success"
