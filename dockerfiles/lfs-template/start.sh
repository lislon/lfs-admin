#!/usr/bin/env bash

cd /lfs

if [[ -f "LFSP.exe" ]] ; then
    echo "Start Pereulok"
    wine LFSP.exe
else
    echo "Start regular LFS"
    wine DCon.exe
fi

c=5
while [[ ! -f "deb.log" && $c -gt 0 ]] ; do
    c=$((c - 1))
    sleep 1
done

if [[ -f "deb.log" ]] ; then
    tail -f deb.log
else
    echo "Serve not started. Can't find deb.log file"
    sleep 3600
fi
