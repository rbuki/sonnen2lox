<?php
/**
 * write.php - QueryString-Schreib-API (UNAUTHENTIFIZIERT)
 * Liegt in webfrontend/html/ -> ohne LoxBerry-Login erreichbar.
 * Schutz: enabled-Flag + IPv4-Whitelist (konfiguriert im Tab "Schreiben").
 *
 * Benötigt KEINEN Miniserver -> bindet bewusst NICHT init.php ein.
 */

require_once "loxberry_system.php";
require_once "loxberry_json.php";

// Sprache absolut laden (html/ liegt nicht im Include-Pfad)
$L = LBSystem::readlanguage(LBPTEMPLATEDIR . "/lang/language.ini");

header('Content-Type: application/json; charset=utf-8');

$writeConfigFile = "$lbpdatadir/write-config.json";
$settingsFile    = "$lbpdatadir/plugin-settings.json";

// ---------- Antwort-Helfer ----------
function respond($httpCode, $data) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------- Konfiguration laden ----------
if (!file_exists($writeConfigFile)) {
    respond(403, array('status' => 'forbidden', 'error' => $L['FORM3.API_NOT_CONFIGURED'] ?? 'not configured'));
}
$writeCfg = new LBJSON($writeConfigFile);
$enabled   = is_enabled($writeCfg->enabled ?? false);
$whitelist = (isset($writeCfg->slave->ip_whitelist) && is_array($writeCfg->slave->ip_whitelist))
                ? $writeCfg->slave->ip_whitelist : array();
$trustProxy = is_enabled($writeCfg->trust_proxy ?? false);

if (!$enabled) {
    respond(403, array('status' => 'forbidden', 'error' => $L['FORM3.API_DISABLED'] ?? 'disabled'));
}

// ---------- Sender-IP ----------
// Default: echte TCP-Quell-IP (REMOTE_ADDR) - vom Client nicht fälschbar.
// X-Forwarded-For wird NUR ausgewertet, wenn trust_proxy aktiv ist
// (ausschließlich sinnvoll, wenn der LoxBerry hinter einem vertrauenswürdigen
//  Reverse-Proxy läuft, der diesen Header selbst setzt).
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$xffSource = false;
if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $remoteIp = trim($xff[0]);
    $xffSource = true;
}

// ---------- IPv4-Whitelist (inkl. CIDR) ----------
function ip_in_whitelist($ip, $whitelist) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    $ipLong = ip2long($ip);
    foreach ($whitelist as $entry) {
        $entry = trim((string)$entry);
        if ($entry === '') continue;
        if (strpos($entry, '/') !== false) {
            list($subnet, $bits) = explode('/', $entry, 2);
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
            $bits = (int)$bits;
            if ($bits < 0 || $bits > 32) continue;
            $mask = ($bits === 0) ? 0 : (-1 << (32 - $bits));
            $subnetLong = ip2long($subnet) & $mask;   // Netzwerk-Adresse
            if (($ipLong & $mask) === $subnetLong) return true;
        } elseif ($ip === $entry) {
            return true;
        }
    }
    return false;
}

if (!ip_in_whitelist($remoteIp, $whitelist)) {
    respond(403, array(
        'status' => 'forbidden',
        'error'  => $L['FORM3.API_IP_NOT_LISTED'] ?? 'not in whitelist',
        'ip'     => $remoteIp,
        'source' => $xffSource ? 'x-forwarded-for' : 'remote_addr'
    ));
}

// ---------- sonnenBatterie-Zugang ----------
if (!file_exists($settingsFile)) {
    respond(500, array('status' => 'error', 'error' => $L['FORM3.API_SONNEN_NOT_CONFIGURED'] ?? 'not configured'));
}
$pluginJson = new LBJSON($settingsFile);
$sonnenHost  = $pluginJson->Sonnen->Host  ?? '';
$sonnenToken = $pluginJson->Sonnen->AuthToken ?? '';
if ($sonnenHost === '' || $sonnenToken === '') {
    respond(500, array('status' => 'error', 'error' => $L['FORM3.API_HOST_OR_TOKEN_MISSING'] ?? 'host/token missing'));
}

// ---------- cURL-Helfer ----------
function sonnen_request($method, $path, $host, $token, $postFields = null, $jsonBody = null) {
    $url = "http://{$host}{$path}";
    $ch = curl_init($url);
    $headers = array("Auth-Token: {$token}");
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));
    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
        $headers[] = 'Content-Type: application/json';
    } elseif ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return array($resp, $code, $err);
}

// ---------- Action-Dispatch ----------
$action = $_GET['action'] ?? '';
$okMin = 200; $okMax = 299;

switch ($action) {

    // ?action=setpoint&direction=charge|discharge&watts=2500
    case 'setpoint':
        $direction = $_GET['direction'] ?? '';
        $watts     = $_GET['watts'] ?? '';
        if (!in_array($direction, array('charge', 'discharge'), true)) {
            respond(400, array('status' => 'error', 'error' => $L['FORM3.API_BAD_DIRECTION'] ?? 'bad direction'));
        }
        if (!ctype_digit((string)$watts) || (int)$watts <= 0 || (int)$watts >= 1000000000) {
            respond(400, array('status' => 'error', 'error' => $L['FORM3.API_BAD_WATTS'] ?? 'bad watts'));
        }
        list($resp, $code, $err) = sonnen_request('POST', "/api/v2/setpoint/{$direction}/{$watts}", $sonnenHost, $sonnenToken);
        if ($err) respond(502, array('status' => 'error', 'error' => $L['FORM3.API_UNREACHABLE'] ?? 'unreachable', 'detail' => $err));
        respond(($code >= $okMin && $code <= $okMax) ? 200 : $code, array(
            'status' => 'ok', 'action' => 'setpoint',
            'direction' => $direction, 'watts' => (int)$watts,
            'http' => $code, 'response' => json_decode($resp, true)
        ));
        break;

    // ?action=operatingmode&mode=2   (1,2,10,11)
    case 'operatingmode':
        $mode = (string)($_GET['mode'] ?? '');
        if (!in_array($mode, array('1', '2', '10', '11'), true)) {
            respond(400, array('status' => 'error', 'error' => $L['FORM3.API_BAD_MODE'] ?? 'bad mode'));
        }
        list($resp, $code, $err) = sonnen_request('PUT', "/api/v2/configurations", $sonnenHost, $sonnenToken,
                                                    array('EM_OperatingMode' => $mode));
        if ($err) respond(502, array('status' => 'error', 'error' => 'sonnenBatterie nicht erreichbar', 'detail' => $err));
        respond(($code >= $okMin && $code <= $okMax) ? 200 : $code, array(
            'status' => 'ok', 'action' => 'operatingmode', 'mode' => $mode,
            'http' => $code, 'response' => json_decode($resp, true)
        ));
        break;

    // ?action=usoc&value=20   (0 oder 5-100)
    case 'usoc':
        $value = (string)($_GET['value'] ?? '');
        if (!ctype_digit($value) || ((int)$value !== 0 && ((int)$value < 5 || (int)$value > 100))) {
            respond(400, array('status' => 'error', 'error' => $L['FORM3.API_BAD_VALUE'] ?? 'bad value'));
        }
        list($resp, $code, $err) = sonnen_request('PUT', "/api/v2/configurations", $sonnenHost, $sonnenToken,
                                                    array('EM_USOC' => $value));
        if ($err) respond(502, array('status' => 'error', 'error' => 'sonnenBatterie nicht erreichbar', 'detail' => $err));
        respond(($code >= $okMin && $code <= $okMax) ? 200 : $code, array(
            'status' => 'ok', 'action' => 'usoc', 'value' => (int)$value,
            'http' => $code, 'response' => json_decode($resp, true)
        ));
        break;

    // ?action=rct&seconds=3600   (Remote Command Timeout, Sekunden)
    case 'rct':
        $seconds = (string)($_GET['seconds'] ?? '');
        if (!ctype_digit($seconds) || (int)$seconds < 0 || (int)$seconds > 86400) {
            respond(400, array('status' => 'error', 'error' => $L['FORM3.API_BAD_RCT'] ?? 'seconds must be 0-86400'));
        }
        list($resp, $code, $err) = sonnen_request('PUT', "/api/v2/configurations", $sonnenHost, $sonnenToken,
                                                    array('EM_RemoteCommandTimeout' => $seconds));
        if ($err) respond(502, array('status' => 'error', 'error' => $L['FORM3.API_UNREACHABLE'] ?? 'unreachable', 'detail' => $err));
        respond(($code >= $okMin && $code <= $okMax) ? 200 : $code, array(
            'status' => 'ok', 'action' => 'rct', 'seconds' => (int)$seconds,
            'http' => $code, 'response' => json_decode($resp, true)
        ));
        break;

    // ?action=standby&enabled=true|false
    case 'standby':
        $enabled = is_enabled($_GET['enabled'] ?? '');
        $bool = $enabled ? true : false;
        list($resp, $code, $err) = sonnen_request('POST', "/api/v2/standby", $sonnenHost, $sonnenToken,
                                                    null, array('enabled' => $bool));
        if ($err) respond(502, array('status' => 'error', 'error' => 'sonnenBatterie nicht erreichbar', 'detail' => $err));
        respond(($code >= $okMin && $code <= $okMax) ? 200 : $code, array(
            'status' => 'ok', 'action' => 'standby', 'enabled' => $bool,
            'http' => $code, 'response' => json_decode($resp, true)
        ));
        break;

        default:
            respond(400, array(
                'status' => 'error',
                'error'  => $L['FORM3.API_BAD_ACTION'] ?? 'bad action',
                'allowed' => array('setpoint', 'rct', 'operatingmode', 'usoc', 'standby')
            ));
}