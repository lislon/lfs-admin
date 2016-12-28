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
echo "Wait till LFS ends"
/scripts/waitonprocess.sh wineserver | tee -a /lfs/wait.log
echo "Done"
#
## Wait 5 seconds to check if server started
#c=5
#while [[ ! -f "deb.log" && "$c" -gt 0 ]] ; do
#    c=$((c - 1))
#    sleep 1
#done
#
#if [[ -f "deb.log" ]] ;
#else
#    echo "Serve not started. Can't find deb.log file. Sleep for debugging"
#    sleep 3600
#fi
