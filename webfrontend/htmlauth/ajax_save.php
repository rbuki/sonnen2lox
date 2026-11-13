<?php
require_once "loxberry_system.php";
require_once "loxberry_json.php";

$L = LBSystem::readlanguage("language.ini");
header("Content-Type: application/json; charset=utf-8");
$response = array("ok" => false, "msg" => "");

$settingsFile = "$lbpdatadir/plugin-settings.json";
if (!file_exists($settingsFile)) {
    @mkdir(dirname($settingsFile), 0775, true);
    file_put_contents($settingsFile, json_encode(array(
        "Sonnen"    => array("Host" => "sonnen.local", "AuthToken" => ""),
        "SendToLox" => array("Activated" => false, "PauseBetweenRunAsUsleep" => 2500000),
    ), JSON_PRETTY_PRINT));
}
$pluginJson    = new LBJSON($settingsFile);
$sonnenapiJson = new LBJSON("$lbpconfigdir/sonnenapi.json");

$field = $_POST['field'] ?? "";
$value = $_POST['value'] ?? "";

switch ($field) {

    case "host":
        $value = trim($value);
        if ($value === "") { $response["msg"] = $L['ERRORS.HOST_EMPTY']; break; }
        $t   = $sonnenapiJson->Sonnen->Transport ?? "http";
        $tok = $pluginJson->Sonnen->AuthToken;
        $ch = curl_init("{$t}://{$value}/api/v2/status");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Auth-Token: {$tok}", "Content-Type: application/json"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT_MS     => 1000,
        ]);
        $out  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($out === false)    $response["msg"] = sprintf($L['ERRORS.HOST_UNREACHABLE'], $err);
        elseif ($code !== 200) $response["msg"] = sprintf($L['ERRORS.HOST_BAD_HTTP'], $code);
        else { $pluginJson->Sonnen->Host = $value; $pluginJson->write(); $response["ok"] = true; }
        break;

    case "token":
        $value = trim($value);
        if ($value === "") { $response["msg"] = $L['ERRORS.TOKEN_EMPTY']; break; }
        $pluginJson->Sonnen->AuthToken = $value;
        $pluginJson->write();
        $response["ok"] = true;
        break;

    case "pause":
        $s = (float) str_replace(',', '.', $value);
        $s = max(1, min(60, $s));
        $pluginJson->SendToLox->PauseBetweenRunAsUsleep = (int) round($s * 1000000);
        $pluginJson->write();
        $response["ok"] = true;
        $response["clamped"] = number_format($s, 1, '.', '');
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