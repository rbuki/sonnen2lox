<?php

function buildNavbar($activePanelNo) {

    global $navbar;
    global $L;
    global $pluginName;

    $navbar[1]['Name'] = $L['BASIC.FORM0'];
    $navbar[1]['URL'] = 'index.php';

    $navbar[2]['Name'] = $L['BASIC.FORM2'];
    $navbar[2]['URL'] = 'settings.php';
    $navbar[2]['Notify_Package'] = $pluginName;
    $navbar[2]['Notify_Name'] = 'crashes';

    $navbar[3]['Name'] = $L['BASIC.FORM1'];
    $navbar[3]['URL'] = 'config.php';

    $navbar[4]['Name'] = $L['BASIC.FORM3'];
    $navbar[4]['URL'] = 'write.php';

    $navbar[$activePanelNo]['active'] = True;
}


function LoxGetLatestLoxAPP3JsonAsArray($transport, $remoteHost, $remotePort, $usr, $pwd, $pluginName) {
    $curlInit = curl_init();
    $curlHeaders = [ "Authorization: Basic " . base64_encode("$usr:$pwd") ];
    curl_setopt($curlInit, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curlInit, CURLOPT_TIMEOUT_MS, 500);

    curl_setopt($curlInit, CURLOPT_URL, "$transport://$remoteHost:$remotePort/jdev/sps/LoxAPPversion3");
    $remoteLa3j = curl_exec($curlInit);

    # Miniserver nicht erreichbar -> sofort false
    if ($remoteLa3j === false) {
        return false;
    }

    $remoteDateTime = json_decode($remoteLa3j);
    if (!isset($remoteDateTime->LL->value)) {
        return false;
    }
    $currentMiniserverEpoch = strtotime($remoteDateTime->LL->value);

    $updateNow = false;
    if (!(file_exists("/tmp/tmp-{$pluginName}.LoxAPP3.json"))) {
        $updateNow = true;
    } else {
        $localCachedApp3Json = json_decode(file_get_contents("/tmp/tmp-{$pluginName}.LoxAPP3.json"), true);
        $localCachedEpoch = strtotime($localCachedApp3Json['lastModified'] ?? "");
        if ($localCachedEpoch != $currentMiniserverEpoch) {
            $updateNow = true;
        } else {
            return $localCachedApp3Json;
        }
    }

    if ($updateNow) {
        curl_setopt($curlInit, CURLOPT_URL, "$transport://$remoteHost:$remotePort/data/LoxAPP3.json");
        $newFile = curl_exec($curlInit);
        if ($newFile === false) {
            return false;
        }
        file_put_contents("/tmp/tmp-{$pluginName}.LoxAPP3.json", $newFile);
    }
    curl_close($ch);
    return json_decode($newFile, true);
}

function SonnenEnumContentUrl($url,$token,$prefix,$offsetSeconds) {

    $SonnenValues = array();

    $headers = [ "Auth-Token: $token", "Content-Type: application/json"];

    $curlInit = curl_init();
    curl_setopt($curlInit, CURLOPT_URL, $url);
    curl_setopt($curlInit, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curlInit, CURLOPT_TIMEOUT_MS, 500);
    $output = curl_exec($curlInit);
    curl_close($ch);
    $json = json_decode($output, true);

    foreach($json as $key => $val) {
        SonnenOutputRecurseData($key,$val,$prefix,0,$SonnenValues,$offsetSeconds);
    }

    return $SonnenValues;
}

function SonnenOutputRecurseData($currentKey,$currentVal,$currentPrefix,$x,&$SonnenValues,$offsetSeconds) {
    if ($x === 100) {
        //kill recursion if someting went wrong
        return;
    } else {
        $x = $x + 1;
    }

    if (is_array($currentVal)) {
        foreach($currentVal as $key => $val) {
            SonnenOutputRecurseData($key,$val,"{$currentPrefix}/{$currentKey}",$x,$SonnenValues,$offsetSeconds);
        }
    } else {
        if ($currentVal === null || (is_string($currentVal) && trim($currentVal) === '')) {
            $currentVal = 0;
        }
        $SonnenValues += ["{$currentPrefix}/{$currentKey}" => "$currentVal"];

        // Timestamp: LoxEpoch-Varianten erzeugen (konsistent zu functions_do.php)
        if (strtolower($currentKey) == "timestamp" && is_string($currentVal) && $currentVal !== "") {
            $dt = new DateTime($currentVal, new DateTimeZone(date_default_timezone_get()));
            if ($dt) {
                $unixEpoch = $dt->getTimestamp();
                $offset    = $dt->getOffset();

                $loxEpochLocal = (new DateTime('2009-01-01 00:00:00'))->getTimestamp();
                $loxLocal = $unixEpoch - $loxEpochLocal;
                $loxUtc   = $loxLocal - $offset;

                $SonnenValues += ["{$currentPrefix}/{$currentKey}_LoxEpoch_LocalTime" => $loxLocal];
                $SonnenValues += ["{$currentPrefix}/{$currentKey}_LoxEpoch_UTC" => $loxUtc];
            }
        }
    }
}

?>