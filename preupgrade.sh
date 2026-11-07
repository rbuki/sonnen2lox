#!/bin/sh

echo "<INFO> Creating temporary folders for upgrading"

ARGV1=$1
ARGV3=$3
ARGV5=$5

mkdir -p /tmp/$ARGV1\_upgrade/data

echo "<INFO> Backing up existing data"
cp -p -v -r $ARGV5/data/plugins/$ARGV3/ /tmp/$ARGV1\_upgrade/data

exit 0