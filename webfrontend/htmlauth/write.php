<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";
require_once "init.php";

global $pluginTitle,$L,$lbpdatadir;
$template_title=$pluginTitle; $helplink=$L['BASIC.HELPLINK']; $helptemplate="pluginhelp.php";
buildNavbar(4);
$htmlhead="<link rel='stylesheet' href='./res/pluginmain.css'>";

$writeConfigFile="$lbpdatadir/write-config.json";
if(!file_exists($writeConfigFile)){
    file_put_contents($writeConfigFile, json_encode(array('enabled'=>false,'trust_proxy'=>false,'ip_whitelist'=>array()), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
$writeCfg=new LBJSON($writeConfigFile);
$enabled=is_enabled($writeCfg->enabled ?? false);
$trustProxy=is_enabled($writeCfg->trust_proxy ?? false);
$whitelist=(isset($writeCfg->slave->ip_whitelist)&&is_array($writeCfg->slave->ip_whitelist))?$writeCfg->slave->ip_whitelist:array();

$lbIp=LBSystem::get_localip();
$writeUrl="http://".$lbIp."/plugins/{$lbpplugindir}/write.php";
$writeBase=$writeUrl;   // Basis ohne QueryString -> für Copy-Buttons
$browserIp=$_SERVER['REMOTE_ADDR'] ?? '';
$ajaxWriteFile="LBPDATA/".basename($lbpdatadir)."/write-config.json";

$miniservers=LBSystem::get_miniservers(); $msList=array();
if(is_array($miniservers)){foreach($miniservers as $n=>$m){$ip=$m['IPAddress']??'';if($ip==='')continue;$msList[]=array('nr'=>$n,'name'=>$m['Name']??'','ip'=>$ip);}}

LBWeb::lbheader($template_title,$helplink,$helptemplate,true);
?>
<div class="lb-content">
<div style="max-width:1100px;">

<div id="save-status" style="display:none; padding:0.8em 1em; margin:0.5em 0; border-radius:4px;"></div>

<!-- ====== CARD 1: Schreib-API aktivieren ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM3.TITLE'];?></h4>
  <p class="intro-text"><?=$L['FORM3.DESCRIPTION'];?></p>

  <div class="toggle-row">
    <label class="lb-toggle"><input type="checkbox" id="enabled" <?= $enabled?"checked":""; ?>>
      <span class="lb-toggle-slider"></span></label>
    <span class="toggle-label"><?=$L['FORM3.ENABLED'];?></span>
  </div>

  <div class="note-box" style="margin-top:8px;"><?=$L['FORM3.SECURITY_NOTE'];?></div>
</div>

<!-- ====== CARD 2: IP-Whitelist + X-Forwarded-For ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM3.WHITELIST'];?></h4>

  <div class="lb-form-row">
    <div class="lb-form-field">
      <div class="ipChips" id="ipChips">
        <?php if($browserIp): ?><button type="button" class="ipChip" data-ip="<?=htmlspecialchars($browserIp);?>">+ <?=$L['FORM3.CHIP_BROWSER'];?> <code><?=htmlspecialchars($browserIp);?></code></button><?php endif; ?>
        <?php foreach($msList as $m): ?><button type="button" class="ipChip" data-ip="<?=htmlspecialchars($m['ip']);?>">+ <?=htmlspecialchars($m['name']);?> <code><?=htmlspecialchars($m['ip']);?></code></button><?php endforeach; ?>
      </div>
      <textarea class="lb-textarea" id="ip_list" rows="6" style="font-family:monospace;"><?=htmlspecialchars(implode("\n",$whitelist));?></textarea>
      <div class="note-box info" style="margin-top:8px;"><?=$L['FORM3.WHITELIST_HINT'];?></div>
    </div>
  </div>

  <div class="toggle-row">
      <label class="lb-toggle"><input type="checkbox" id="trust_proxy" <?= $trustProxy?"checked":""; ?>>
        <span class="lb-toggle-slider"></span></label>
      <span class="toggle-label"><?=$L['FORM3.TRUST_PROXY'];?></span>
    </div>

    <div class="note-box" style="margin-top:8px;"><?=$L['FORM3.TRUST_PROXY_HINT'];?></div>
  </div>

<!-- ====== CARD 3: Verfügbare QueryStrings ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM3.ENDPOINT_TITLE'];?></h4>

  <div class="endpoint-box">
    <span class="endpoint-label"><?=$L['FORM3.ENDPOINT'];?></span>
    <button type="button" class="endpoint-copy lb-btn lb-btn-sm" onclick="copyEndpoint()" title="URL kopieren">
      <i class="pi pi-copy"></i>
    </button>
    <code class="endpoint-url" id="endpointUrl"><?=htmlspecialchars($writeUrl);?></code>
  </div>

  <table class="lb-table query-table">
    <colgroup>
      <col style="width:16%;">
      <col style="width:46%;">
      <col style="width:38%;">
    </colgroup>
    <thead><tr><th><?=$L['FORM3.TH_ACTION'];?></th><th><?=$L['FORM3.TH_QUERY'];?></th><th><?=$L['FORM3.TH_DESC'];?></th></tr></thead>
    <tbody>
      <tr>
        <td><?=$L['FORM3.A_USOC'];?></td>
        <td>
          <div class="qs-copy">
            <button type="button" class="qs-copy-btn" data-url="<?=htmlspecialchars($writeBase);?>?action=usoc&amp;value=20" title="URL kopieren"><i class="pi pi-copy"></i></button>
            <code>?action=usoc&amp;value=20</code>
          </div>
        </td>
        <td><?=str_replace(' · ', '<br>', htmlspecialchars($L['FORM3.D_USOC']));?></td>
      </tr>
      <tr>
        <td><?=$L['FORM3.A_MODE'];?></td>
        <td>
          <div class="qs-copy">
            <button type="button" class="qs-copy-btn" data-url="<?=htmlspecialchars($writeBase);?>?action=operatingmode&amp;mode=2" title="URL kopieren"><i class="pi pi-copy"></i></button>
            <code>?action=operatingmode&amp;mode=2</code>
          </div>
        </td>
        <td><?=str_replace(' · ', '<br>', htmlspecialchars($L['FORM3.D_MODE']));?>
          <div class="note-box" style="margin-top:12px;"><?=$L['FORM3.D_MODE_Note'];?></div>
        </td>
      </tr>
      <tr>
        <td><?=$L['FORM3.A_SETPOINT'];?></td>
        <td>
          <div class="qs-copy">
            <button type="button" class="qs-copy-btn" data-url="<?=htmlspecialchars($writeBase);?>?action=setpoint&amp;direction=charge&amp;watts=2500" title="URL kopieren"><i class="pi pi-copy"></i></button>
            <code>?action=setpoint&amp;direction=charge|discharge&amp;watts=2500</code>
          </div>
        </td>
        <td><?=str_replace(' · ', '<br>', htmlspecialchars($L['FORM3.D_SETPOINT']));?></td>
      </tr>
      <tr>
        <td><?=$L['FORM3.A_STANDBY'];?></td>
        <td>
          <div class="qs-copy">
            <button type="button" class="qs-copy-btn" data-url="<?=htmlspecialchars($writeBase);?>?action=standby&amp;enabled=true" title="URL kopieren"><i class="pi pi-copy"></i></button>
            <code>?action=standby&amp;enabled=true|false</code>
          </div>
        </td>
        <td><?=str_replace(' · ', '<br>', htmlspecialchars($L['FORM3.D_STANDBY']));?></td>
      </tr>
    </tbody>
  </table>

<!-- </div>  -->

<!-- ====== CARD 4: Sonnen API Dokumentation ====== -->
<br>
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM3.API_DOC_TITLE'];?></h4>
  <p><a class="green-link" href="http://<?=htmlspecialchars($sonnenHostName);?>/api/doc.html" target="_blank" rel="noopener"><?=$L['FORM3.API_DOC_LINK'];?></a></p>
</div></div>

</div>
</div>

<!-- ====== CARD: Beispiele ====== -->
<div class="lb-card settings-card">
  <h4 class="settings-card-headline"><?=$L['FORM3.EXAMPLES_TITLE'];?></h4>

  <div class="example-item">
    <div class="example-comment"># <?=$L['FORM3.EX_SETPOINT_CHARGE'];?></div>
    <code class="example-curl">curl "<?=$writeUrl;?>?action=setpoint&amp;direction=charge&amp;watts=2500"</code>
  </div>

  <div class="example-item">
    <div class="example-comment"># <?=$L['FORM3.EX_SETPOINT_DISCHARGE'];?></div>
    <code class="example-curl">curl "<?=$writeUrl;?>?action=setpoint&amp;direction=discharge&amp;watts=3000"</code>
  </div>

  <div class="example-item">
    <div class="example-comment"># <?=$L['FORM3.EX_MODE'];?></div>
    <code class="example-curl">curl "<?=$writeUrl;?>?action=operatingmode&amp;mode=2"</code>
  </div>

  <div class="example-item">
    <div class="example-comment"># <?=$L['FORM3.EX_USOC'];?></div>
    <code class="example-curl">curl "<?=$writeUrl;?>?action=usoc&amp;value=20"</code>
  </div>

  <div class="example-item">
    <div class="example-comment"># <?=$L['FORM3.EX_STANDBY'];?></div>
    <code class="example-curl">curl "<?=$writeUrl;?>?action=standby&amp;enabled=true"</code>
  </div>
</div>

<script>
var LBL_SAVED      = <?=json_encode($L['FORM3.SAVED']);?>;
var LBL_SAVE_ERROR = <?=json_encode($L['FORM3.SAVE_ERROR']);?>;

function showStatus(m, e){
    var $s = $("#save-status");
    $s.text(m).removeClass("ok err").addClass(e ? "err" : "ok");
    clearTimeout($s.data("t"));
    $s.data("t", setTimeout(function(){ $s.removeClass("ok err"); }, 2000));
}

function save(field, value) {
    var fd = new FormData();
    fd.append("field", field); fd.append("value", value);
    fetch("ajax_write_save.php", { method: "POST", body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.ok) {
            showStatus(LBL_SAVED, false);
            if (field === "ip_whitelist" && Array.isArray(res.whitelist)) $("#ip_list").val(res.whitelist.join("\n"));
            if (res.msg) showStatus(res.msg, true);
        } else {
            showStatus(res.msg || LBL_SAVE_ERROR, true);
        }
      })
      .catch(function(e){ showStatus(e.message, true); });
}

function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){ showStatus('✓ URL kopiert', false); });
    } else {
        // Fallback für http / ältere Browser
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); showStatus('✓ URL kopiert', false); } catch(e) {}
        document.body.removeChild(ta);
    }
}

// Endpoint-URL oben im Rahmen
function copyEndpoint() {
    copyText(document.getElementById('endpointUrl').textContent);
}

// QueryString-Zeilen in der Tabelle (delegiert -> robust)
$(document).on('click', '.qs-copy-btn', function(){
    copyText(this.getAttribute('data-url'));
});

$("#enabled").on("change", function(){ save("enabled", this.checked ? "true" : "false"); });
$("#trust_proxy").on("change", function(){ save("trust_proxy", this.checked ? "true" : "false"); });
$("#ip_list").on("blur", function(){ save("ip_whitelist", this.value); });

$("#ipChips .ipChip").on("click", function(){
    var ip = this.getAttribute("data-ip"); var ta = $("#ip_list");
    var lines = ta.val().split(/\r\n|\r|\n/).map(function(l){return l.trim();}).filter(function(l){return l!=="";});
    if (lines.indexOf(ip) === -1) { lines.push(ip); ta.val(lines.join("\n")); save("ip_whitelist", ta.val()); }
});
</script>
<?php LBWeb::lbfooter(); ?>