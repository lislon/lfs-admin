#!/usr/bin/env bash

cd /lfs

echo "Sleep 500"
sleep 500

if [[ -f "LFSP.exe" ]] ; then
    echo "Start Pereulok"
    wine LFSP.exe
else
    echo "Start regular LFS"
    wine DCon.exe
fi

sleep 1
echo "Done. Read logs..."
tail -f deb.log
