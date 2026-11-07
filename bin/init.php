<?php
require_once "functions_do.php";

$ms = LBSystem::get_miniservers();

# Statische API-Definition
$sonnenapiJson = new LBJSON("$lbpconfigdir/sonnenapi.json");

# Benutzereinstellungen
$settingsFile = "$lbpdatadir/plugin-settings.json";
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(array(
        "Sonnen"    => array("Host" => "sonnen.local", "AuthToken" => ""),
        "SendToLox" => array("Activated" => false, "PauseBetweenRunAsUsleep" => 2500000),
    ), JSON_PRETTY_PRINT));
}
$pluginJson = new LBJSON($settingsFile);

# Mappings
$mappingsFile = "$lbpdatadir/mappings.json";
if (!file_exists($mappingsFile)) { file_put_contents($mappingsFile, "{}"); }
$mappingsJson = new LBJSON($mappingsFile);

# Statefile auf tmpfs
$sendStateFile = "/tmp/{$lbpplugindir}_sendstate.json";
if (!file_exists($sendStateFile)) { file_put_contents($sendStateFile, "{}"); }
$sendStateJson = new LBJSON($sendStateFile);


# Werte-Cache auf tmpfs (für Web-Anzeige)
$valuesCacheFile = "/tmp/{$lbpplugindir}_values.json";
if (!file_exists($valuesCacheFile)) {
    file_put_contents($valuesCacheFile, json_encode(array("values" => new stdClass(), "timestamp" => 0)));
}
$valuesCacheJson = new LBJSON($valuesCacheFile);

$defaultMsNo = "1";
$msUsr = $ms[$defaultMsNo]['Admin_RAW'];
$msPass = $ms[$defaultMsNo]['Pass_RAW'];
$msTransport = $ms[$defaultMsNo]['Transport'];
$msIp = $ms[$defaultMsNo]['IPAddress'];

if ($ms[$defaultMsNo]['PreferHttps'] === "1") {
    $msTransport = "https";
    $msPort = $ms[$defaultMsNo]['PortHttps'];
} else {
    $msTransport = "http";
    $msPort = $ms[$defaultMsNo]['Port'];
}
?>