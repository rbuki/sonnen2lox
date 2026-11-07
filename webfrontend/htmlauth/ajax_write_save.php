<?php
require_once "loxberry_system.php";
require_once "loxberry_json.php";

$L = LBSystem::readlanguage("language.ini");

header("Content-Type: application/json; charset=utf-8");
$response = array("ok" => false, "msg" => "");

$writeConfigFile = "$lbpdatadir/write-config.json";

if (!file_exists($writeConfigFile)) {
    file_put_contents($writeConfigFile, json_encode(array(
        'enabled'      => false,
        'trust_proxy'  => false,
        'ip_whitelist' => array(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$writeCfg = new LBJSON($writeConfigFile);

$field = $_POST['field'] ?? "";
$value = $_POST['value'] ?? "";

switch ($field) {

    case "enabled":
        $writeCfg->slave->enabled = ($value === "true");
        $writeCfg->write();
        $response["ok"] = true;
        break;

    case "trust_proxy":
        $writeCfg->slave->trust_proxy = ($value === "true");
        $writeCfg->write();
        $response["ok"] = true;
        break;

    case "ip_whitelist":
        $lines = preg_split('/\r\n|\r|\n/', $value);
        $clean = array();
        $invalid = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (strpos($line, '/') !== false) {
                list($ip, $bits) = explode('/', $line, 2);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && ctype_digit($bits) && (int)$bits >= 0 && (int)$bits <= 32) {
                    $clean[] = $line;
                } else {
                    $invalid[] = $line;
                }
            } elseif (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $clean[] = $line;
            } else {
                $invalid[] = $line;
            }
        }
        $writeCfg->slave->ip_whitelist = $clean;
        $writeCfg->write();
        $response["ok"] = true;
        $response["whitelist"] = $clean;   // bereinigte Liste zurückgeben
        if (!empty($invalid)) {
            $response["msg"] = sprintf($L['FORM3.INVALID_IPS'] ?? 'Invalid entries ignored: %s', implode(', ', $invalid));
        }
        break;

    default:
        $response["msg"] = "Unknown field.";
}

echo json_encode($response);
?>