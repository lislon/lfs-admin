#!/usr/bin/env sh

set -e
make
zip -r LfsDummyImage.zip obj/DCon.exe
make clean
echo "Success"
