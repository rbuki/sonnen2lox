<?php
require_once "loxberry_system.php";

header("Content-Type: application/json; charset=utf-8");

$plugin = LBPPLUGINDIR;
$pidfile = "/tmp/{$plugin}.pid";
$script  = LBPBINDIR . "/{$plugin}_daemon.sh";

function read_pid($pidfile) {
    if (!file_exists($pidfile)) return null;
    $pid = (int) trim(file_get_contents($pidfile));
    if ($pid > 0 && posix_kill($pid, 0)) return $pid;
    return null;
}

$pidBefore = read_pid($pidfile);

if (!file_exists($script)) {
    echo json_encode(array("ok" => false, "error" => "script", "pidBefore" => $pidBefore, "pidAfter" => null));
    exit;
}

# restart ausführen (Blockiert bis Stop+Start durch ist)
$cmd = "bash " . escapeshellarg($script) . " restart 2>&1";
@exec($cmd, $out, $rc);

usleep(300000);   # kurz warten, bis neue PID geschrieben ist
$pidAfter = read_pid($pidfile);

echo json_encode(array(
    "ok"        => ($rc === 0 && $pidAfter !== null),
    "pidBefore" => $pidBefore,
    "pidAfter"  => $pidAfter,
    "rc"        => $rc,
    "script"    => $script,
    "scriptExists" => file_exists($script),
    "output"    => implode("\n", $out),
));
?>