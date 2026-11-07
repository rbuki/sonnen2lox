#!/bin/sh

echo "<INFO> Restore data folder"

ARGV1=$1
ARGV3=$3
ARGV5=$5

mkdir -p $ARGV5/data/plugins/$ARGV3
cp -p -v -r /tmp/$ARGV1\_upgrade/data/$ARGV3/* $ARGV5/data/plugins/$ARGV3/ 2>/dev/null

echo "<INFO> Remove temporary folders"
rm -r /tmp/$ARGV1\_upgrade

exit 0