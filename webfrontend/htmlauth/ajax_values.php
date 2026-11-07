<?php
require_once "loxberry_system.php";
require_once "loxberry_json.php";

header("Content-Type: application/json; charset=utf-8");

$names = $_GET['names'] ?? "";
$wanted = $names === "" ? array() : explode(",", $names);

$cacheFile = "/tmp/{$lbpplugindir}_values.json";
$out = array("timestamp" => 0, "values" => new stdClass());

if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cache)) {
        $out["timestamp"] = $cache["timestamp"] ?? 0;
        $vals = $cache["values"] ?? array();
        foreach ($wanted as $n) {
            if (array_key_exists($n, $vals)) {
                $out["values"]->{"$n"} = $vals[$n];
            }
        }
    }
}

echo json_encode($out);
?>