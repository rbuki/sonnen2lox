#!/usr/bin/env php
<?php
require_once "loxberry_system.php";
require_once "loxberry_json.php";
require_once "loxberry_log.php";
require_once __DIR__ . "/init.php";

global $ms, $pluginJson, $generalJson, $mappingsJson, $sonnenapiJson, $sendStateJson, $valuesCacheJson;
global $msTransport, $msIp, $msPort, $msUsr, $msPass, $lbpplugindir;

$stopFile = "/tmp/{$lbpplugindir}_stop";
@unlink($stopFile);   # beim Start sauber machen

# --- Log-Rotation (wie bisher) ---
$logFile  = "$lbplogdir/daemon.log";
$maxBytes = 2 * 1024 * 1024;
$maxFiles = 3;
if (file_exists($logFile) && filesize($logFile) > $maxBytes) {
    for ($i = $maxFiles - 1; $i >= 1; $i--) {
        $src = $logFile . ($i == 1 ? "" : "." . ($i - 1));
        $dst = $logFile . "." . $i;
        if (file_exists($src)) { rename($src, $dst); }
    }
}

$log = LBLog::newLog(array(
    "name"     => "daemon",
    "filename" => $logFile,
    "append"   => 1
));
$log->LOGSTART("sonnen2lox daemon gestartet");

$IDLE = 500000;   # 0,5s Poll-Intervall im inaktiven Zustand

while (true) {
    # --- Stop-Datei? → sauber beenden ---
    if (file_exists($stopFile)) {
        $log->INF("Stop-Datei erkannt, Daemon beendet sich.");
        break;
    }

    # --- Activated-Flag JEDE Iteration frisch lesen ---
    $pluginJson = new LBJSON("$lbpdatadir/plugin-settings.json");
    $activated  = $pluginJson->SendToLox->Activated ?? false;

    if (!$activated) { usleep($IDLE); continue; }

    # --- Mappings/Config frisch lesen ---
    $mappingsJson  = new LBJSON("$lbpdatadir/mappings.json");
    $sonnenapiJson = new LBJSON("$lbpconfigdir/sonnenapi.json");


    $pause = $pluginJson->SendToLox->PauseBetweenRunAsUsleep ?? 2500000;
    $pause = max(1000000, min($pause, 59000000));

    # --- 1 Iteration = 1 API-Run + Senden ---
    $apiEndPoints = "";
    foreach ($mappingsJson->slave as $map) {
        $prefix = explode("/", $map->Name)[0];
        if (strpos($apiEndPoints, $prefix) === false) { $apiEndPoints .= "$prefix,"; }
    }
    $apiEndPoints = rtrim($apiEndPoints, ",");

    if ($apiEndPoints !== "") {
        $headers = ["Auth-Token: {$pluginJson->Sonnen->AuthToken}", "Content-Type: application/json"];
        $loxValuesToSend = array();
        foreach (explode(",", $apiEndPoints) as $ep) {
            # --- State aller Mappings dieses Endpoints auf "in Arbeit" setzen ---
            foreach ($mappingsJson->slave as $map) {
                if (explode("/", $map->Name)[0] === $ep) {
                    $sendStateJson->slave->{$map->Name} = (object) array("pending" => true);
                }
            }
            $url = "{$sonnenapiJson->Sonnen->Transport}://{$pluginJson->Sonnen->Host}/{$sonnenapiJson->Sonnen->API->{$ep}->Endpoint}";
            enumContentUrl($url, $headers, $mappingsJson, $ep, $loxValuesToSend, $log);
        }
        $msOk = LoxSetMsValues($msTransport, $msIp, $msPort, $msUsr, $msPass, $loxValuesToSend, 10000, $log, $sendStateJson, $valuesCacheJson);
        if (!$msOk) {
            $log->WARN("Miniserver nicht erreichbar – kurze Pause, dann erneuter Versuch.");
            usleep(2000000);   # 2s Backoff, dann Loop von vorn
            continue;
        }
    }

    usleep($pause);
}

@unlink($stopFile);
$log->LOGEND("sonnen2lox daemon gestoppt");
?>