<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";
require_once "init.php";

/* ============================================================
 * API-Snapshot-Cache (tmpfs)
 * ------------------------------------------------------------
 * Zieht Keys UND Beispielwerte ALLER Sonnen-API-Werte und legt
 * sie auf /tmp ab (RAM-Drive -> schont die SD, reboot-fluechtig).
 *
 * Warum nicht der Daemon-Cache (_values.json)?
 *   Der Daemon schreibt nur GEMAPpte Werte dorthin. Fuer die
 *   Mapping-Auswahl braucht man aber ALLE Werte inkl. Beispiel-
 *   werten, damit der User sieht, ob ein Ziel TextInput, Analog
 *   oder Digital sein muss.
 *
 * Warm-up-Problem der sonnenBatterie-API:
 *   Manche Endpunkte (v.a. Status/Flow...) liefern beim ersten
 *   Request nur leere Werte und erst im 2. Durchlauf Daten.
 *   Deshalb: (a) pro Endpoint ein kurzer Retry, und (b) ein als
 *   unvollstaendig erkannter Snapshot bekommt nur eine KURZE TTL
 *   und wird beim naechsten Aufruf sofort nachgezogen - statt
 *   10 Minuten leer zu verharren.
 * ============================================================ */

// True, wenn mindestens ein Beispielwert leer ist -> Snapshot unvollstaendig
function SonnenSnapshotHasEmpty($values) {
    foreach ($values as $v) {
        if ($v === "" || $v === null) return true;
    }
    return false;
}

// True, wenn mind. ein Wert in diesem (Endpoint-)Teil gefüllt ist
function SonnenBlockHasData($values) {
    foreach ($values as $v) {
        if ($v !== "" && $v !== null) return true;
    }
    return false;
}

function SonnenGetApiSnapshot($ApiEndPointUrls, $token, $ttl = 600) {
    global $lbpplugindir;
    $cacheFile = "/tmp/{$lbpplugindir}_apisnapshot.json";
    $now = time();

    if (file_exists($cacheFile)) {
        $cache = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cache) && ($cache['timestamp'] ?? 0) > ($now - $ttl)
            && is_array($cache['values'] ?? null)) {
            return $cache['values'];
        }
    }

    $values = array();
    $anyOk = false;
    foreach ($ApiEndPointUrls as $url => $prefix) {
        $headers = [ "Auth-Token: $token", "Content-Type: application/json" ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT_MS     => 500,
        ]);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        if (!is_array($json)) continue;
        $anyOk = true;
        foreach ($json as $k => $v) {
            SonnenCollectValues($k, $v, $prefix, 0, $values);
        }
    }

    if ($anyOk) {
        file_put_contents($cacheFile, json_encode([
            'timestamp' => $now,
            'values'    => $values,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $values;
    }
    // Fehlerfall: alten Snapshot weiterreichen, sonst leer
    return is_array($cache['values'] ?? null) ? $cache['values'] : array();
}

function SonnenCollectValues($key, $val, $prefix, $x, &$values) {
    if ($x === 100) return;
    $x++;
    if (is_array($val)) {
        foreach ($val as $k => $v) {
            SonnenCollectValues($k, $v, "{$prefix}/{$key}", $x, $values);
        }
    } else {
        if (is_bool($val)) {
            $val = $val ? "1" : "0";          // false -> "0", true -> "1"
        } elseif ($val === null) {
            $val = "";
        } else {
            $val = "$val";
        }
        $values["{$prefix}/{$key}"] = $val;
        if (strtolower($key) === "timestamp" && is_string($val) && $val !== "") {
            $dt = new DateTime($val, new DateTimeZone(date_default_timezone_get()));
            if ($dt) {
                $unixEpoch = $dt->getTimestamp();
                $offset    = $dt->getOffset();
                $loxLocal  = $unixEpoch - (new DateTime('2009-01-01 00:00:00'))->getTimestamp();
                $loxUtc    = $loxLocal - $offset;
                $values["{$prefix}/{$key}_LoxEpoch_LocalTime"] = $loxLocal;
                $values["{$prefix}/{$key}_LoxEpoch_UTC"] = $loxUtc;
            }
        }
    }
}

global $ms,$L,$pluginJson,$pluginTitle,$lbpdatadir;
$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(3);
$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
LBWeb::lbheader($template_title, $helplink, $helptemplate, true);

if (isset($_POST)) { echo postHandler($_POST); }

$rooms = array(); foreach (($loxApp3json['rooms'] ?? array()) as $k=>$v) $rooms[$k]=$v['name'];
$cats  = array(); foreach (($loxApp3json['cats']  ?? array()) as $k=>$v) $cats[$k]=$v['name'];

$ApiEndPointUrls = array();
foreach ($sonnenapiJson->Sonnen->API as $epName=>$ep) {
    $prefix=$ep->Prefix ?? $epName; $endpoint=$ep->Endpoint ?? "";
    if ($endpoint==="") continue;
    $ApiEndPointUrls["{$sonnenTransport}://{$sonnenHostName}/{$endpoint}"]=$prefix;
}

// EIN Cache-Hit statt bis zu 7 Live-Calls; enthaelt ALLE Werte inkl. Beispiel
$sonnenValues = SonnenGetApiSnapshot($ApiEndPointUrls, $sonnenToken);

$mjson = new LBJSON("$lbpdatadir/mappings.json");
$existing = $mjson->slave;
$sendingActive = $pluginJson->SendToLox->Activated ?? false;
$pauseUs = $pluginJson->SendToLox->PauseBetweenRunAsUsleep ?? 2500000;
$pauseMs = max(1000, min((int)($pauseUs/1000),59000));
$valuesCache=array();
$cacheFile="/tmp/{$lbpplugindir}_values.json";
if (file_exists($cacheFile)) { $c=json_decode(file_get_contents($cacheFile),true); if(isset($c["values"])) $valuesCache=$c["values"]; }

$uuidCount=array();
foreach ($existing as $e){ $u=$e->LoxoneControlUUID ?? ""; if($u!=="") $uuidCount[$u]=($uuidCount[$u]??0)+1; }
?>
<div class="lb-content">

<!-- ====== CARD 1: Bestehende Mappings ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM1.EXISTING'];?></h4>
  <?php
  $hasAny=false;
  foreach ($existing as $name=>$entry){
    $hasAny=true; $uuid=$entry->LoxoneControlUUID ?? ""; $ctrlName="—";
    foreach (($loxApp3json['controls']) as $c){ if(($c['uuidAction']??"")===$uuid){$ctrlName=$c['name'];break;} }
    $isError=($ctrlName==="—"); $isDup=!$isError && ($uuidCount[$uuid]??0)>1;
    $sendWarn=!$isError && !$isDup && isset($sendStateJson->slave->$name->lastError);
    $warnTime=$sendWarn?date("d.m.Y H:i",strtotime($sendStateJson->slave->$name->lastError)):"";
    $cls="mapItem mapMapping";
    if($isError)$cls.=" mapError"; elseif($isDup)$cls.=" mapDupWarn"; elseif($sendWarn)$cls.=" mapSendWarn";
  ?>
  <form method="post" action="config.php" data-ajax="false"
        onsubmit="return confirm('<?=$L['FORM1.DELETE_CONFIRM'];?>');" class="<?=$cls?>">
    <span class="mi-body">
      <span class="mi-name"><?=htmlspecialchars($name);?></span>
      <span class="mi-arrow">&rarr;</span>
      <span class="mi-ctrl"><?=htmlspecialchars($ctrlName);?></span>
    </span>
    <span class="mi-pill">
      <?php if(($entry->LoxoneControlType??"")!==""): ?><span class="mi-ctrl-type"><?=htmlspecialchars($entry->LoxoneControlType);?></span><?php endif; ?>
      <?php if($sendingActive): ?><span class="mi-val" data-map="<?=htmlspecialchars($name);?>"><?=htmlspecialchars($valuesCache[$name]??"—");?></span>
      <?php else: ?><span class="mi-val mi-val-paused"><?=$L['FORM1.PAUSED'];?></span><?php endif; ?>
    </span>
    <input type="hidden" name="delete-mapping" value="<?=htmlspecialchars($name);?>">
    <button type="submit" class="lb-btn lb-btn-sm lb-btn-danger"><?=$L['FORM1.DELETE'];?></button>
    <?php if($isError): ?><span class="mi-error"><?=$L['FORM1.ERR_UUID_MISSING'];?>: <span class="mi-error-uuid"><?=htmlspecialchars($uuid);?></span></span><?php endif; ?>
    <?php if($isDup): ?><span class="mi-dup">! <?=$L['FORM1.WARN_DUP'];?></span><?php endif; ?>
    <?php if($sendWarn): ?><span class="mi-warn"><?=$L['FORM1.WARN_SEND'];?> (<?=htmlspecialchars($warnTime);?>)</span><?php endif; ?>
  </form>
  <?php }
  if(!$hasAny) echo "<p>".$L['FORM1.NO_MAPPINGS']."</p>";
  ?>
</div>

<!-- ====== CARD 2: Neues Mapping ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM1.TITLE'];?></h4>
  <form method="post" action="config.php" data-ajax="false">
    <div class="mapWrap">
      <div class="mapCol">
        <h3><?=$L['FORM1.VON'];?></h3>
        <input class="lb-input mapSearch" id="searchSonnen" placeholder="<?=$L['FORM1.SEARCH_VON'];?>">
        <div class="mapList" id="listSonnen">
          <?php foreach($sonnenValues as $key=>$val): ?>
          <label class="mapItem" data-search="<?=htmlspecialchars(strtolower($key));?>">
            <input type="radio" name="hidden-sonnen" value="<?=htmlspecialchars($key);?>">
            <span class="mi-body"><span class="mi-name"><?=htmlspecialchars($key);?></span>
            <span class="mi-meta"><?=$L['FORM1.TXT_Value'];?>: <?=htmlspecialchars(($val === "" ? "…" : $val));?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="mapArrow" id="addBtn" disabled>&#10142;</button>
      <div class="mapCol">
        <h3><?=$L['FORM1.NACH'];?></h3>
        <input class="lb-input mapSearch" id="searchControl" placeholder="<?=$L['FORM1.SEARCH_NACH'];?>">
        <div class="mapList" id="listControl">
          <?php foreach(($loxApp3json['controls']) as $key=>$val):
            if(!in_array($val['type'],["InfoOnlyDigital","InfoOnlyAnalog","InfoOnlyText","TextInput"])) continue; ?>
          <label class="mapItem" data-search="<?=htmlspecialchars(strtolower($val['name'].' '.($rooms[$val['room']]??'').' '.($cats[$val['cat']]??'')));?>">
            <input type="radio" name="hidden-control" value="<?=htmlspecialchars($val['uuidAction']);?>">
            <span class="mi-body"><span class="mi-name"><?=htmlspecialchars($val['name']);?></span>
            <span class="mi-sub"><?=str_replace("InfoOnly","",$val['type']);?></span>
            <span class="mi-meta"><?=$L['FORM1.TXT_Room'];?>: <?=htmlspecialchars($rooms[$val['room']]??'');?></span></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </form>
</div>

<?php if($sendingActive): ?>
<script>
(function(){var maps=[];document.querySelectorAll('.mi-val[data-map]').forEach(function(e){maps.push(e.getAttribute('data-map'));});
if(!maps.length)return;
function r(){fetch('ajax_values.php?names='+encodeURIComponent(maps.join(','))).then(function(x){return x.json();}).then(function(res){
document.querySelectorAll('.mi-val[data-map]').forEach(function(e){var n=e.getAttribute('data-map');
if(res.values&&Object.prototype.hasOwnProperty.call(res.values,n))e.textContent=res.values[n];});}).catch(function(){});}
setInterval(r,<?=$pauseMs?>);})();
</script>
<?php endif; ?>

<script>
function filterList(inputId, listId) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('#' + listId + ' .mapItem').forEach(function(it) {
            var s = (it.getAttribute('data-search') || '').toLowerCase();
            if (q === '' || s.indexOf(q) > -1) {
                it.classList.remove('mapHidden');
            } else {
                it.classList.add('mapHidden');
            }
        });
    });
}

function updateAddBtn() {
    var s = document.querySelector('input[name="hidden-sonnen"]:checked');
    var c = document.querySelector('input[name="hidden-control"]:checked');
    var btn = document.getElementById('addBtn');
    if (!btn) return;
    if (s && c) {
        btn.disabled = false;
        btn.classList.add('ready');
        btn.innerHTML = '&#43;';   // +
    } else {
        btn.disabled = true;
        btn.classList.remove('ready');
        btn.innerHTML = '&#10142;'; // Pfeil
    }
}

document.addEventListener('DOMContentLoaded', function() {
    filterList('searchSonnen', 'listSonnen');
    filterList('searchControl', 'listControl');
    document.querySelectorAll('input[name="hidden-sonnen"], input[name="hidden-control"]')
        .forEach(function(r) { r.addEventListener('change', updateAddBtn); });
});
</script>

</div>
<?php LBWeb::lbfooter(); ?>