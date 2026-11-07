<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php"; //needs loxberry 2.0.2

require_once "functions_posthandler.php";
require_once "functions.php";

//some global data
$ms = LBSystem::get_miniservers(); //need to run before all the other LBSystem:: 

$L = LBSystem::readlanguage("language.ini");

$myplugin = LBSystem::plugindata();
$pluginTitle = $myplugin['PLUGINDB_TITLE'];
$pluginName    = $lbpplugindir;

# --- Statische API-Definition (config, wird beim Update überschrieben) ---
$sonnenapiJson = new LBJSON("$lbpconfigdir/sonnenapi.json");

# --- Benutzereinstellungen (data, Update-fest) ---
$settingsFile = "$lbpdatadir/plugin-settings.json";
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(array(
        "Sonnen"    => array("Host" => "sonnen.local", "AuthToken" => ""),
        "SendToLox" => array("Activated" => true, "PauseBetweenRunAsUsleep" => 2500000),
    ), JSON_PRETTY_PRINT));
}
$pluginJson = new LBJSON($settingsFile);

# --- Mappings (data, Update-fest) ---
$mappingsFile = "$lbpdatadir/mappings.json";
if (!file_exists($mappingsFile)) {
    file_put_contents($mappingsFile, "{}");
}

# --- Statefile auf tmpfs (/tmp) ---
$sendStateFile = "/tmp/{$lbpplugindir}_sendstate.json";
if (!file_exists($sendStateFile)) {
    file_put_contents($sendStateFile, "{}");
}
$sendStateJson = new LBJSON($sendStateFile);

$debug = ($_GET['debug'] == "1") ? TRUE : FALSE;

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

# Werte aus den getrennten Quellen
$sonnenHostName  = $pluginJson->Sonnen->Host;
$sonnenToken     = $pluginJson->Sonnen->AuthToken;
$sonnenTransport = $sonnenapiJson->Sonnen->Transport ?? "http";

$loxApp3json = LoxGetLatestLoxAPP3JsonAsArray($msTransport, $msIp, $msPort, $msUsr, $msPass, $pluginName);

if ($loxApp3json === false) {
    # Header holen, damit die Fehlerseite sauber aussieht
    $L = LBSystem::readlanguage("language.ini");
    LBWeb::lbheader($pluginTitle, $L['BASIC.HELPLINK'] ?? "", "pluginhelp.php");
    echo "<h2>" . ($L['ERRORS.MS_UNREACHABLE'] ?? "Miniserver nicht erreichbar") . "</h2>";
    echo "<p>Verbindung zu <strong>{$msTransport}://{$msIp}:{$msPort}</strong> fehlgeschlagen.</p>";
    echo "<p>Bitte prüfen: Ist der Miniserver eingeschaltet und unter dieser IP erreichbar?</p>";
    LBWeb::lbfooter();
    exit;
}


?>