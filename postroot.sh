#!/bin/bash
PLUGIN="REPLACELBPPLUGINDIR"
su loxberry -c "bash $LBPBIN/$PLUGIN/sonnen2lox_daemon.sh start"
exit 0