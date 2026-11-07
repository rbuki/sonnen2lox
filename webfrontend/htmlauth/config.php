<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";

require_once "init.php";

global $ms, $L, $pluginJson, $generalJson, $pluginName, $pluginTitle;

$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(3);

$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
$htmlhead .= "<script src='./res/pluginscripts.js'></script>";
LBWeb::lbheader($template_title, $helplink, $helptemplate);

# POST zuerst, damit die Meldung oben erscheint
if (isset($_POST)) {
    echo postHandler($_POST);
}

# Loxone-Controls vorbereiten (nur Info-Typen)
$rooms = array();
foreach (($loxApp3json['rooms']) as $key => $val) { $rooms += [$key => $val['name']]; }
$cats = array();
foreach (($loxApp3json['cats']) as $key => $val) { $cats += [$key => $val['name']]; }

# sonnen-Werte aus allen Endpoints einsammeln (aus sonnenapi.json)
$ApiEndPointUrls = array();
foreach ($sonnenapiJson->Sonnen->API as $epName => $ep) {
    $prefix   = $ep->Prefix ?? $epName;
    $endpoint = $ep->Endpoint ?? "";
    if ($endpoint === "") { continue; }
    $ApiEndPointUrls += ["{$sonnenTransport}://{$sonnenHostName}/{$endpoint}" => $prefix];
}

$sonnenValues = array();
foreach ($ApiEndPointUrls as $apikey => $value) {
    $sonnenValues += SonnenEnumContentUrl($apikey, $sonnenToken, $value, 0);
}
?>

<h2><?=$L['FORM1.EXISTING'];?></h2>

<?php
$mjson = new LBJSON("$lbpdatadir/mappings.json");
$existing = $mjson->slave;

# --- Sendezustand + Daemon-Pause (aus plugin-settings.json) ---
$sendingActive = $pluginJson->SendToLox->Activated ?? false;
$pauseUs = $pluginJson->SendToLox->PauseBetweenRunAsUsleep ?? 2500000;
$pauseMs = max(1000, min((int)($pauseUs / 1000), 59000));   // Poll-Intervall in ms

# --- Letzte gesendete Werte aus Daemon-Cache ---
$valuesCache = array();
$cacheFile = "/tmp/{$lbpplugindir}_values.json";
if (file_exists($cacheFile)) {
    $c = json_decode(file_get_contents($cacheFile), true);
    if (isset($c["values"])) { $valuesCache = $c["values"]; }
}
$sendingActive = $pluginJson->SendToLox->Activated ?? false;


# --- UUID-Zählung: welches Loxone-Control wird mehrfach beschrieben? ---
$uuidCount = array();
foreach ($existing as $entry) {
    $u = $entry->LoxoneControlUUID ?? "";
    if ($u !== "") {
        $uuidCount[$u] = ($uuidCount[$u] ?? 0) + 1;
    }
}

$hasAny = false;
foreach ($existing as $name => $entry) {
    $hasAny = true;
    $uuid = $entry->LoxoneControlUUID ?? "";
    $ctrlName = "—";
    foreach (($loxApp3json['controls']) as $c) {
        if (($c['uuidAction'] ?? "") === $uuid) {
            $ctrlName = $c['name'];
            break;
        }
    }
    $isError   = ($ctrlName === "—");
    $isDup     = !$isError && ($uuidCount[$uuid] ?? 0) > 1;
    $sendWarn  = !$isError && !$isDup && isset($sendStateJson->slave->$name->lastError);
    $warnTime  = $sendWarn ? date("d.m.Y H:i", strtotime($sendStateJson->slave->$name->lastError)) : "";

    $cls = "mapItem mapMapping";
    if ($isError)    { $cls .= " mapError"; }
    elseif ($isDup)  { $cls .= " mapDupWarn"; }
    elseif ($sendWarn) { $cls .= " mapSendWarn"; }
    ?>
      <form method="post" action="config.php" data-role="none"
      onsubmit="return confirm('<?=$L['FORM1.DELETE_CONFIRM'];?>');"
      class="<?= $cls ?>">
      <span class="mi-body">
        <span class="mi-name"><?=htmlspecialchars($name);?></span>
        <span class="mi-arrow">&rarr;</span>
        <span class="mi-ctrl"><?=htmlspecialchars($ctrlName);?></span>
        <?php $ctrlType = $entry->LoxoneControlType ?? ""; ?>
        <?php if ($ctrlType !== ""): ?>
          <span class="mi-ctrl-type"><?=htmlspecialchars($ctrlType);?></span>
        <?php endif; ?>
        <?php if ($sendingActive): ?>
            <span class="mi-val" data-map="<?=htmlspecialchars($name);?>"><?=htmlspecialchars($valuesCache[$name] ?? "—");?></span>
        <?php else: ?>
            <span class="mi-val mi-val-paused"><?=htmlspecialchars($L['FORM1.PAUSED']);?></span>
        <?php endif; ?>
        <?php if ($isError): ?>
          <span class="mi-error"><?=$L['FORM1.ERR_UUID_MISSING'];?>: <span class="mi-error-uuid"><?=htmlspecialchars($uuid);?></span></span>
          <span class="mi-error-hint"><?=$L['FORM1.ERR_UUID_MISSING_HINT'];?></span>
        <?php elseif ($isDup): ?>
          <span class="mi-dup-icon">!</span>
          <span class="mi-dup"><?=$L['FORM1.WARN_DUP'];?></span>
          <span class="mi-dup-hint"><?=$L['FORM1.WARN_DUP_HINT'];?></span>
        <?php elseif ($sendWarn): ?>
          <span class="mi-warn"><?=$L['FORM1.WARN_SEND'];?> (<?=htmlspecialchars($warnTime);?>)</span>
          <span class="mi-warn-hint"><?=$L['FORM1.WARN_SEND_HINT'];?></span>
        <?php endif; ?>
      </span>
      <input type="hidden" name="delete-mapping" value="<?=htmlspecialchars($name);?>">
      <button type="submit" class="mapDelete" data-role="none" data-icon="none"><?=$L['FORM1.DELETE'];?></button>
    </form>
    <?php
}
if (!$hasAny) {
    echo "<p>".$L['FORM1.NO_MAPPINGS']."</p>";
}
?>

<h2><?=$L['FORM1.TITLE'];?></h2>

<form method="post" action="config.php">
  <div class="mapWrap">

    <div class="mapCol">
      <h2><?=$L['FORM1.VON'];?></h2>
      <input class="mapSearch" id="searchSonnen" type="text" placeholder="<?=$L['FORM1.SEARCH_VON'];?>">
      <div class="mapList" id="listSonnen">
        <?php foreach ($sonnenValues as $key => $val): ?>
          <label class="mapItem" data-search="<?=htmlspecialchars(strtolower($key));?>">
            <input type="radio" name="hidden-sonnen" value="<?=htmlspecialchars($key);?>" data-role="none">
            <span class="mi-body">
              <span class="mi-name"><?=htmlspecialchars($key);?></span>
              <span class="mi-meta"><?=$L['FORM1.TXT_Value'];?>: <?=htmlspecialchars($val);?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

  <button type="submit" class="mapArrow" id="addBtn" disabled data-role="none">&#10142;</button>

    <div class="mapCol">
      <h2><?=$L['FORM1.NACH'];?></h2>
      <input class="mapSearch" id="searchControl" type="text" placeholder="<?=$L['FORM1.SEARCH_NACH'];?>">
      <div class="mapList" id="listControl">
        <?php foreach (($loxApp3json['controls']) as $key => $val):
            if (!in_array($val['type'], ["InfoOnlyDigital","InfoOnlyAnalog","InfoOnlyText","TextInput"])) continue; ?>
          <label class="mapItem" data-search="<?=htmlspecialchars(strtolower($val['name'].' '.$rooms[$val['room']].' '.$cats[$val['cat']]));?>">
            <input type="radio" name="hidden-control" value="<?=htmlspecialchars($val['uuidAction']);?>" data-role="none">
            <span class="mi-body">
              <span class="mi-name"><?=htmlspecialchars($val['name']);?></span>
              <span class="mi-sub"><?= str_replace("InfoOnly","",$val['type']);?></span>
              <span class="mi-meta"><?=$L['FORM1.TXT_Room'];?>: <?=htmlspecialchars($rooms[$val['room']]);?></span>
              <span class="mi-meta"><?=$L['FORM1.TXT_Cat'];?>: <?=htmlspecialchars($cats[$val['cat']]);?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

</form>

<?php if ($sendingActive): ?>
<script>
(function(){
    var maps = [];
    document.querySelectorAll('.mi-val[data-map]').forEach(function(el){
        maps.push(el.getAttribute('data-map'));
    });
    if (!maps.length) return;

    function refreshValues(){
        fetch('ajax_values.php?names=' + encodeURIComponent(maps.join(',')))
          .then(function(r){ return r.json(); })
          .then(function(res){
            document.querySelectorAll('.mi-val[data-map]').forEach(function(el){
                var n = el.getAttribute('data-map');
                if (res.values && Object.prototype.hasOwnProperty.call(res.values, n)) {
                    el.textContent = res.values[n];
                }
            });
          })
          .catch(function(){});
    }
    setInterval(refreshValues, <?= $pauseMs ?>);
})();
</script>
<?php endif; ?>

<script>
function filterList(inputId, listId) {
  document.getElementById(inputId).addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#'+listId+' .mapItem').forEach(function(it){
      it.style.display = it.getAttribute('data-search').indexOf(q) > -1 ? '' : 'none';
    });
  });
}
filterList('searchSonnen','listSonnen');
filterList('searchControl','listControl');


var addBtn = document.getElementById('addBtn');
function refreshAddBtn() {
    var s = document.querySelector('input[name="hidden-sonnen"]:checked');
    var c = document.querySelector('input[name="hidden-control"]:checked');
    var ready = !!(s && c);
    addBtn.disabled = !ready;
    addBtn.classList.toggle('ready', ready);
    addBtn.innerHTML = ready ? '+' : '&#10142;';
}
document.querySelectorAll('input[name="hidden-sonnen"], input[name="hidden-control"]')
    .forEach(function(r){ r.addEventListener('change', refreshAddBtn); });
refreshAddBtn();

</script>

<?php
function displayControlTypeToReadableName($L, $typeName) {
    return $L['CONTROLS.' . $typeName];
}
LBWeb::lbfooter();
?>