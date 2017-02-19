#!/usr/bin/env bash

cd /lfs

if [[ -f "LFSP.exe" ]] ; then
    echo "Start Pereulok"
    wine LFSP.exe
else
    echo "Start regular LFS"
    wine DCon.exe
fi

# Wait till LFS is exit
/scripts/waitonprocess.sh wineserver

echo "Done"

