<?php

global $L; 
$L = LBSystem::readlanguage("language.ini");

function enumContentUrl($url, $headers, $mappings, $prefix, &$loxValuesToSend, $log = null) {

    $ch = curl_init();
    $log && $log->DEB("URL: " . $url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
    $output = curl_exec($ch);
    $curlErr = ($output === false) ? curl_error($ch) : '';
    curl_close($ch);
    if ($output === false) {
        $log && $log->WARN("Curl-Fehler: " . $curlErr);
    }

    $json = json_decode($output, true);
    if (!is_array($json)) {
        $log && $log->WARN("Ungültige JSON-Antwort von $url");
        return;
    }

    foreach ($json as $key => $val) {
        sendOrRecurseData($key, $val, $prefix, 0, $mappings, $loxValuesToSend, $log);
    }
}

function sendOrRecurseData($currentKey, $currentVal, $currentPrefix, $x, $mappings, &$loxValuesToSend, $log = null) {
    if ($x === 100) {
        return;
    } else {
        $x = $x + 1;
    }

    if (is_array($currentVal)) {
        foreach ($currentVal as $key => $val) {
            sendOrRecurseData($key, $val, "{$currentPrefix}/{$currentKey}", $x, $mappings, $loxValuesToSend, $log);
        }
    } else {
        if ($currentVal === null || (is_string($currentVal) && trim($currentVal) === '')) {
            $currentVal = 0;
        }
        $mappingName = "{$currentPrefix}/{$currentKey}";
        $loxUid = $mappings->$mappingName->LoxoneControlUUID ?? null;
        if ($loxUid && strlen($loxUid) == 35) {
            $loxValuesToSend[] = array("uid" => $loxUid, "value" => $currentVal, "name" => $mappingName);
            $log->DEB("$mappingName -> $loxUid = $currentVal");
        }

        // Timestamp: LoxEpoch-Varianten erzeugen und senden
        if (strtolower($currentKey) === "timestamp" && is_string($currentVal) && $currentVal !== "") {
            $dt = new DateTime($currentVal, new DateTimeZone(date_default_timezone_get()));
            if ($dt) {
                $unixEpoch = $dt->getTimestamp();
                $offset    = $dt->getOffset();   // echter Offset inkl. Sommerzeit

                $loxEpochLocal = (new DateTime('2009-01-01 00:00:00'))->getTimestamp(); // 1.1.2009 00:00 Lokalzeit
                $loxLocal = $unixEpoch - $loxEpochLocal;        // Loxone LocalTime
                $loxUtc   = $loxLocal - $offset;                // Loxone UTC

                $epName = "{$mappingName}_LoxEpoch_LocalTime";
                $epUid = $mappings->$epName->LoxoneControlUUID ?? null;
                if ($epUid && strlen($epUid) == 35) {
                    $loxValuesToSend[] = array("uid" => $epUid, "value" => $loxLocal, "name" => $epName);
                    $log->DEB("$epName -> $epUid = $loxLocal");
                }

                $epName = "{$mappingName}_LoxEpoch_UTC";
                $epUid = $mappings->$epName->LoxoneControlUUID ?? null;
                if ($epUid && strlen($epUid) == 35) {
                    $loxValuesToSend[] = array("uid" => $epUid, "value" => $loxUtc, "name" => $epName);
                    $log->DEB("$epName -> $epUid = $loxUtc");
                }
            }
        }
    }
}

function LoxSetMsValues($transport, $remoteHost, $remotePort, $usr, $pwd, $ControlAndValue, $uSleepBetweenSend = 20000, $log = null, $sendState = null, $valuesCache = null) {
    global $L;

    $curlInit = curl_init();
    curl_setopt($curlInit, CURLOPT_HTTPHEADER, [ "Authorization: Basic " . base64_encode("$usr:$pwd") ]);
    curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curlInit, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($curlInit, CURLOPT_TIMEOUT_MS, 500);

    $baseUrl = "$transport://$remoteHost:$remotePort/jdev/sps/io";
    $msReachable = true;
    $now = date("c");
    $errors = array();

    foreach ($ControlAndValue as $item) {
        $path = "{$item['uid']}/{$item['value']}";
        curl_setopt($curlInit, CURLOPT_URL, "$baseUrl/$path");
        $retVal = curl_exec($curlInit);
        if ($retVal === false) {
            $log && $log->WARN(($L['ERRORS.MS_UNREACHABLE'] ?? "Miniserver nicht erreichbar") . ": " . curl_error($curlInit));
            $msReachable = false;
            foreach ($ControlAndValue as $rest) {
                $errors[$rest['name']] = $now;
            }
            break;
        }

        $code = json_decode($retVal)->LL->Code ?? null;
        if ($code != "200") {
            $log && $log->WARN(($L['ERRORS.SEND_FAILED'] ?? "Senden fehlgeschlagen") . ": " . $path . " -> " . trim($retVal));
            $errors[$item['name']] = $now;
        }
        usleep($uSleepBetweenSend);
    }

    curl_close($curlInit);   // EINMAL, nach der Schleife

    if ($sendState) {
        foreach ($ControlAndValue as $item) {
            $name = $item['name'];
            if (isset($errors[$name])) {
                $sendState->slave->$name = (object) array("lastError" => $errors[$name]);
            } else {
                if (isset($sendState->slave->$name)) {
                    unset($sendState->slave->$name);
                }
            }
        }
        foreach ($sendState->slave as $name => $state) {
            if (is_object($state) && isset($state->pending)) {
                unset($sendState->slave->$name);
            }
        }
        $sendState->write();
    }
    if ($valuesCache) {
        if (!isset($valuesCache->slave->values)) {
            $valuesCache->slave->values = new stdClass();
        }
        foreach ($ControlAndValue as $item) {
            $valuesCache->slave->values->{$item['name']} = $item['value'];
        }
        $valuesCache->slave->timestamp = time();
        $valuesCache->write();
    }

    return $msReachable;
}

?>