#!/bin/bash

now=$(date +"%Y%m%d")
DIR="$( cd "$( dirname "$0" )" && pwd )"

while getopts s:l:p: flag
do
    case "${flag}" in
        l) logpath=${OPTARG};;
        f) logfile=${OPTARG};;
        p) platform=${OPTARG};;
    esac
done

tmpdir=$(dirname $(mktemp tmp.XXXXXXXXXX -ut))

if [ -z "$logpath" ]
then
    logpath="$tmpdir/"
fi

if [ -z "$platform" ]
then
    platform="0"
fi

if [ -z "$logfile" ]
then
    logfile="$logpath/tepuy-$platform.log"
fi

case "$(ps x |grep -v grep |grep -c $DIR/index.php)" in

0) echo "Restarting php socket server $(date)" >> "$logfile"
   /usr/bin/php $DIR/index.php > $logpath/socket-$now.log &
   ;;
*) # all ok
   ;;
esac