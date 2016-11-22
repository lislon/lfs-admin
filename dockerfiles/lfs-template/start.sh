#!/usr/bin/env bash

cd /lfs

if [[ -f "test.txt" ]] ; then
    echo "running test"
    tail -f test.txt
    exit
fi

if [[ -f "LFSP.exe" ]] ; then
    echo "Start Pereulok"
    wine LFSP.exe
else
    echo "Start regular LFS"
    wine DCon.exe
fi

sleep 1

echo "Done. Read logs..."
tail -f log.log
