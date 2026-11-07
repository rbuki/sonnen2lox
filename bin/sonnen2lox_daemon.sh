#!/bin/bash
# sonnen2lox Daemon-Steuerung. PID + Stop-Datei in /tmp (flüchtig).
# REPLACELBP*-Platzhalter werden bei der Installation ersetzt.

PLUGIN="REPLACELBPPLUGINDIR"
DAEMON_PHP="$LBPBIN/$PLUGIN/sonnen2lox_loop.php"
PIDFILE="/tmp/${PLUGIN}.pid"
STOPFILE="/tmp/${PLUGIN}_stop"
LOGDIR="$LBPLOG/$PLUGIN"

get_pid() {
    pgrep -f "sonnen2lox_loop.php" | head -1
}

is_running() {
    [ -n "$(get_pid)" ]
}

cmd_start() {
    if is_running; then
        echo "sonnen2lox läuft bereits (PID $(get_pid)) -> Restart, um neuen Code zu laden."
        cmd_stop
    fi
    mkdir -p "$LOGDIR"
    rm -f "$STOPFILE"
    nohup /usr/bin/php "$DAEMON_PHP" >> "$LOGDIR/daemon.log" 2>&1 < /dev/null &
    local pid=$!
    echo "$pid" > "$PIDFILE"
    echo "sonnen2lox gestartet (PID $pid)."
}

cmd_stop() {
    if ! is_running; then
        echo "sonnen2lox läuft nicht."
        rm -f "$PIDFILE" "$STOPFILE"
        return 0
    fi
    local pid; pid="$(get_pid)"
    echo "Stoppe sonnen2lox (PID $pid) über Stop-Datei..."
    touch "$STOPFILE"
    # bis 15s auf sauberes Ende warten
    local i=0
    while is_running && [ "$i" -lt 15 ]; do sleep 1; i=$((i+1)); done
    if is_running; then
        echo "Daemon endet nicht sauber -> SIGKILL."
        kill -KILL "$pid" 2>/dev/null
        sleep 1
    fi
    rm -f "$PIDFILE" "$STOPFILE"
    echo "sonnen2lox gestoppt."
}

cmd_status() {
    if is_running; then
        echo "running:$(get_pid)"
        return 0
    else
        echo "stopped"
        return 1
    fi
}

ACTION="${1:-start}"
case "$ACTION" in
    start)   cmd_start ;;
    stop)    cmd_stop ;;
    restart) cmd_stop; cmd_start ;;
    status)  cmd_status ;;
    *)       echo "usage: $0 {start|stop|restart|status}"; exit 2 ;;
esac
exit 0