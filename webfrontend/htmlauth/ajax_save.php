<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once "loxberry_system.php";
require_once "loxberry_json.php";

$L = LBSystem::readlanguage("language.ini");

$pluginJson    = new LBJSON("$lbpdatadir/plugin-settings.json");
$sonnenapiJson = new LBJSON("$lbpconfigdir/sonnenapi.json");

header("Content-Type: application/json; charset=utf-8");
$response = array("ok" => false, "msg" => "");

$field = $_POST['field'] ?? "";
$value = $_POST['value'] ?? "";

switch ($field) {
case "host":
    $value = trim($value);
    if ($value === "") {
        $response["msg"] = $L['ERRORS.HOST_EMPTY'] ?? "Host darf nicht leer sein.";
        break;
    }

    # --- Erreichbarkeitstest gegen Status-Endpunkt ---
    $testTransport = $sonnenapiJson->Sonnen->Transport ?? "http";
    $testToken     = $pluginJson->Sonnen->AuthToken;
    $testUrl       = "{$testTransport}://{$value}/api/v2/status";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Auth-Token: {$testToken}", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
    $testOutput = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr    = curl_error($ch);
    curl_close($ch);
   
    if ($testOutput === false) {
        $response["msg"] = sprintf($L['ERRORS.HOST_UNREACHABLE'] ?? "Host nicht erreichbar: %s", $curlErr);
        break;
    }
    if ($httpCode !== 200) {
        $response["msg"] = sprintf($L['ERRORS.HOST_BAD_HTTP'] ?? "Host antwortet nicht korrekt (HTTP %d).", $httpCode);
        break;
    }

    # --- Test erfolgreich -> speichern ---
    $pluginJson->Sonnen->Host = $value;
    $pluginJson->write();
    $response["ok"] = true;
    break;

    case "token":
        $value = trim($value);
        if ($value === "") {
            $response["msg"] = $L['ERRORS.TOKEN_EMPTY'] ?? "Token darf nicht leer sein.";
            break;
        }
        $pluginJson->Sonnen->AuthToken = $value;
        $pluginJson->write();
        $response["ok"] = true;
        break;

    case "pause":
        $pauseSeconds = (float) str_replace(',', '.', $value);
        if ($pauseSeconds < 1)  { $pauseSeconds = 1; }
        if ($pauseSeconds > 60) { $pauseSeconds = 60; }
        $pluginJson->SendToLox->PauseBetweenRunAsUsleep = (int) round($pauseSeconds * 1000000);
        $pluginJson->write();
        $response["ok"] = true;
        $response["clamped"] = number_format($pauseSeconds, 1, '.', '');
        break;

    case "activated":
        $pluginJson->SendToLox->Activated = ($value === "true");
        $pluginJson->write();
        $response["ok"] = true;
        break;

    default:
        $response["msg"] = "Unknown field.";
}

echo json_encode($response);
?>