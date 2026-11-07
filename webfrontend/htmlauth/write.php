<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";

require_once "init.php";

global $pluginTitle, $L;

$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(4);

$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
$htmlhead .= "<script src='./res/pluginscripts.js'></script>";

$writeConfigFile = "$lbpdatadir/write-config.json";

// Datei bei Bedarf anlegen
if (!file_exists($writeConfigFile)) {
    file_put_contents($writeConfigFile, json_encode(array(
        'enabled'      => false,
        'trust_proxy'  => false,
        'ip_whitelist' => array(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$writeCfg   = new LBJSON($writeConfigFile);
$enabled    = is_enabled($writeCfg->enabled ?? false);
$trustProxy = is_enabled($writeCfg->trust_proxy ?? false);
$whitelist  = (isset($writeCfg->slave->ip_whitelist) && is_array($writeCfg->slave->ip_whitelist))
                ? $writeCfg->slave->ip_whitelist : array();

# LoxBerry-eigene IP (für Endpunkt-URL)
$lbIp     = LBSystem::get_localip();
$writeUrl = "http://" . $lbIp . "/plugins/{$lbpplugindir}/write.php";

# Browser-IP (REMOTE_ADDR) als Auswahl-Vorschlag
$browserIp = $_SERVER['REMOTE_ADDR'] ?? '';

# Miniserver-Liste (Name + IP) für Auswahl
$miniservers = LBSystem::get_miniservers();
$msList = array();
if (is_array($miniservers)) {
    foreach ($miniservers as $msnr => $ms) {
        $ip = $ms['IPAddress'] ?? '';
        if ($ip === '') continue;
        $msList[] = array('nr' => $msnr, 'name' => $ms['Name'] ?? '', 'ip' => $ip);
    }
}

LBWeb::lbheader($template_title, $helplink, $helptemplate);
?>

<div id="saveError"></div>

<h2><?=$L['FORM3.TITLE'];?></h2>
<p class="intro-text"><?=$L['FORM3.DESCRIPTION'];?></p>

<form onsubmit="return false;">
  <table>
    <tr>
      <td style="vertical-align:top;"><?=$L['FORM3.ENABLED'];?></td>
      <td>
        <div class="ctrlRow">
          <input type="checkbox" name="enabled" id="enabled"
                 data-role="flipswitch" data-on-text="<?=$L['FORM3.SWITCH_ON'];?>" data-off-text="<?=$L['FORM3.SWITCH_OFF'];?>"
                 <?= $enabled ? "checked" : "" ?>>
          <span class="ctrlHint prep-note" style="margin-top:0;"><?=$L['FORM3.SECURITY_NOTE'];?></span>
        </div>
      </td>
    </tr>
    <tr>
      <td style="vertical-align:top;"><?=$L['FORM3.WHITELIST'];?></td>
      <td>
        <p style="margin:0 0 6px 0;font-size:13px;color:#666;">
          <?=$L['FORM3.WHITELIST_HINT'];?>
        </p>

        <div class="ipChips" id="ipChips">
          <?php if (!empty($browserIp)): ?>
            <button type="button" class="ipChip" data-ip="<?=htmlspecialchars($browserIp);?>" data-role="none">
              + <?=$L['FORM3.CHIP_BROWSER'];?> <code><?=htmlspecialchars($browserIp);?></code>
            </button>
          <?php endif; ?>
          <?php foreach ($msList as $m): ?>
            <button type="button" class="ipChip" data-ip="<?=htmlspecialchars($m['ip']);?>" data-role="none">
              + <?=htmlspecialchars($m['name']);?> <code><?=htmlspecialchars($m['ip']);?></code>
            </button>
          <?php endforeach; ?>
        </div>

        <textarea name="ip_list" id="ip_list" rows="6" data-role="none"
                  style="width:420px;box-sizing:border-box;font-family:monospace;padding:8px;border:1px solid #ccc;border-radius:6px;margin-top:8px;"><?=
            htmlspecialchars(implode("\n", $whitelist));
        ?></textarea>
      </td>
    </tr>
    <tr>
      <td style="vertical-align:top;"><?=$L['FORM3.TRUST_PROXY'];?></td>
      <td>
        <div class="ctrlRow">
          <input type="checkbox" name="trust_proxy" id="trust_proxy"
                 data-role="flipswitch" data-on-text="<?=$L['FORM3.SWITCH_ON'];?>" data-off-text="<?=$L['FORM3.SWITCH_OFF'];?>"
                 <?= $trustProxy ? "checked" : "" ?>>
          <span class="ctrlHint prep-note" style="margin-top:0;"><?=$L['FORM3.TRUST_PROXY_HINT'];?></span>
        </div>
      </td>
    </tr>
  </table>
</form>


<h2><?=$L['FORM3.ENDPOINT_TITLE'];?></h2>
<p><?=$L['FORM3.ENDPOINT'];?> <code><?=htmlspecialchars($writeUrl);?></code></p>

<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <tr style="background:#eee;">
    <th><?=$L['FORM3.TH_ACTION'];?></th>
    <th><?=$L['FORM3.TH_QUERY'];?></th>
    <th><?=$L['FORM3.TH_DESC'];?></th>
  </tr>
  <tr>
    <td><?=$L['FORM3.A_USOC'];?></td>
    <td><code>?action=usoc&amp;value=20</code></td>
    <td><?=$L['FORM3.D_USOC'];?></td>
  </tr>
  <tr>
    <td><?=$L['FORM3.A_MODE'];?></td>
    <td><code>?action=operatingmode&amp;mode=2</code></td>
    <td><?=$L['FORM3.D_MODE'];?></td>
  </tr>
  <tr>
    <td><?=$L['FORM3.A_SETPOINT'];?></td>
    <td><code>?action=setpoint&amp;direction=charge|discharge&amp;watts=2500</code></td>
    <td><?=$L['FORM3.D_SETPOINT'];?></td>
  </tr>
  <tr>
    <td><?=$L['FORM3.A_STANDBY'];?></td>
    <td><code>?action=standby&amp;enabled=true|false</code></td>
    <td><?=$L['FORM3.D_STANDBY'];?></td>
  </tr>
</table>

<h3><?=$L['FORM3.API_DOC_TITLE'];?></h3>
<p>
  <a href="http://<?=htmlspecialchars($sonnenHostName);?>/api/doc.html"
     target="_blank" rel="noopener"><?=$L['FORM3.API_DOC_LINK'];?></a>
</p>

<h3><?=$L['FORM3.EXAMPLES_TITLE'];?></h3>
<pre style="background:#f5f5f5;padding:10px;border-radius:6px;overflow-x:auto;">
<?= "<span class=\"ex-comment\"># " . htmlspecialchars($L['FORM3.EX_SETPOINT_CHARGE']) . "</span>\n" ?>curl "<?=htmlspecialchars($writeUrl);?>?action=setpoint&amp;direction=charge&amp;watts=2500"

<?= "<span class=\"ex-comment\"># " . htmlspecialchars($L['FORM3.EX_SETPOINT_DISCHARGE']) . "</span>\n" ?>curl "<?=htmlspecialchars($writeUrl);?>?action=setpoint&amp;direction=discharge&amp;watts=3000"

<?= "<span class=\"ex-comment\"># " . htmlspecialchars($L['FORM3.EX_MODE']) . "</span>\n" ?>curl "<?=htmlspecialchars($writeUrl);?>?action=operatingmode&amp;mode=2"

<?= "<span class=\"ex-comment\"># " . htmlspecialchars($L['FORM3.EX_USOC']) . "</span>\n" ?>curl "<?=htmlspecialchars($writeUrl);?>?action=usoc&amp;value=20"

<?= "<span class=\"ex-comment\"># " . htmlspecialchars($L['FORM3.EX_STANDBY']) . "</span>\n" ?>curl "<?=htmlspecialchars($writeUrl);?>?action=standby&amp;enabled=true"
</pre>

<script>
(function(){
  var errEl = document.getElementById('saveError');
  if (errEl && errEl.parentNode !== document.body) {
    document.body.appendChild(errEl);
  }

  var LBL_SAVE_ERROR = <?=json_encode($L['FORM3.SAVE_ERROR'] ?? 'Error saving');?>;
  var LBL_SERVER_ERROR = <?=json_encode($L['FORM3.SERVER_ERROR'] ?? 'Server error: %s');?>;

  function showError(text) {
    var el = document.getElementById('saveError');
    el.textContent = text;
    el.className = 'show';
  }
  function hideError() {
    document.getElementById('saveError').className = '';
  }

  function save(field, value) {
    var data = new FormData();
    data.append("field", field);
    data.append("value", value);
    fetch("ajax_write_save.php", { method: "POST", body: data })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.ok) {
          hideError();
          if (field === "ip_whitelist" && Array.isArray(res.whitelist)) {
            var ta = document.getElementById('ip_list');
            if (ta) ta.value = res.whitelist.join("\n");
          }
          if (res.msg) { showError(res.msg); }
        } else {
          showError(res.msg || LBL_SAVE_ERROR);
        }
      })
      .catch(function(e){ showError(LBL_SERVER_ERROR.replace('%s', e.message)); });
  }

  var en = document.getElementById('enabled');
  if (en) {
    $(en).on("change", function(e, ui){
      var on = (ui && typeof ui.state !== 'undefined') ? ui.state : en.checked;
      save("enabled", on ? "true" : "false");
    });
  }

  var tp = document.getElementById('trust_proxy');
  if (tp) {
    $(tp).on("change", function(e, ui){
      var on = (ui && typeof ui.state !== 'undefined') ? ui.state : tp.checked;
      save("trust_proxy", on ? "true" : "false");
    });
  }

  var ta = document.getElementById('ip_list');
  if (ta) {
    ta.addEventListener("blur", function(){
      save("ip_whitelist", ta.value);
    });
  }

  // Chips: IP in Textarea einfügen (ohne Duplikate)
  document.querySelectorAll('#ipChips .ipChip').forEach(function(chip){
    chip.addEventListener('click', function(){
      var ip = this.getAttribute('data-ip');
      var ta = document.getElementById('ip_list');
      var lines = ta.value.split(/\r\n|\r|\n/).map(function(l){ return l.trim(); })
                       .filter(function(l){ return l !== ''; });
      if (lines.indexOf(ip) === -1) {
        lines.push(ip);
        ta.value = lines.join("\n");
        save("ip_whitelist", ta.value);
      }
    });
  });

})();
</script>

<?php
LBWeb::lbfooter();
?>