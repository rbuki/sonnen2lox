#!/bin/bash

pluginname=REPLACELBPPLUGINDIR

##manual start the Loxone Crashlog Listener if not running (after first install to prevent from forced reboot)
#numberOfScripts=$(ps -fade | grep lcrsmain.sh | grep -v grep -c)
#if [ "$numberOfScripts" -eq "0" ]; then
#	setsid $LBPBIN/$pluginname/lcrsmain.sh >$LBPLOG/$pluginname/scripterror.log 2>&1 < /dev/null &
#fi