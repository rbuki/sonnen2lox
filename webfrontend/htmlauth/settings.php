<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";
require_once "init.php";

global $pluginJson, $sonnenapiJson, $pluginTitle, $L, $lbpdatadir;

$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(2);

$activated = $pluginJson->SendToLox->Activated ?? false;
$transport = $sonnenapiJson->Sonnen->Transport ?? "http";
$pauseS    = number_format(($pluginJson->SendToLox->PauseBetweenRunAsUsleep ?? 2500000) / 1000000, 1, '.', '');

$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";

LBWeb::lbheader($template_title, $helplink, $helptemplate, true);
?>

<div class="lb-content">
<div id="save-status"></div>
<div style="max-width:640px;">


<form id="mainform" data-ajax="false" onsubmit="return false;">

  <!-- ====== ABSCHNITT 1: Sonnen API ====== -->
  <div class="lb-card settings-card">
    <h4 class="settings-card-headline">Sonnen API</h4>

    <!-- Transport + Host -->
    <div class="lb-form-row">
      <label class="lb-form-label" for="sonnen_host"><?=$L['FORM2.TRANSPORT'];?> / <?=$L['FORM2.HOST'];?></label>
      <div class="lb-form-field">
        <span class="lb-btn-group" style="opacity:.6; pointer-events:none; margin-right:10px;">
          <span class="lb-btn lb-btn-sm"><?=$transport;?></span>
        </span>
        <input class="lb-input" type="text" id="sonnen_host"
               value="<?=htmlspecialchars($sonnenHostName);?>" style="max-width:320px;">
      </div>
      <div class="hint-text"><?=$L['FORM2.TRANSPORT_TIP'];?></div>
    </div>

    <!-- Token -->
    <div class="lb-form-row">
      <label class="lb-form-label" for="sonnen_token"><?=$L['FORM2.TOKEN'];?></label>
      <div class="lb-form-field token-field">
        <input class="lb-input" type="password" id="sonnen_token"
               value="<?=htmlspecialchars($sonnenToken);?>">
        <button type="button" id="pwToggle" aria-label="Token anzeigen">
          <i class="pi pi-eye"></i>
        </button>
      </div>
    </div>

  </div>

  <!-- ====== ABSCHNITT 2: Daemon ====== -->
  <div class="lb-card settings-card">
    <h4 class="settings-card-headline">Daemon</h4>

    <!-- Senden Aktiv/Inaktiv – Toggle links, Label rechts -->
    <div class="toggle-row">
      <label class="lb-toggle">
        <input type="checkbox" id="activated" <?= $activated ? "checked" : "" ?>>
        <span class="lb-toggle-slider"></span>
      </label>
      <span class="toggle-label"><?=$L['FORM2.ACTIVATED'];?></span>
    </div>

    <!-- Pause -->
    <div class="pause-row">
      <span class="pause-label"><?=$L['FORM2.PAUSE'];?></span>
      <input class="lb-input" type="number" id="pause_seconds" step="0.1" min="1" max="60"
             value="<?=$pauseS;?>" style="width:110px;">
      <span style="color:#888;"><?=$L['FORM2.SECONDS'];?></span>
    </div>

    <!-- Daemon Neustart + PID -->
    <div class="lb-form-row daemon-row">
      <div class="lb-form-field" style="display:flex; gap:10px; align-items:center;">
        <?php
          $daemonPid = null;
          $pidFile = "/tmp/{$pluginName}.pid";
          if (file_exists($pidFile)) {
              $p = (int) trim(file_get_contents($pidFile));
              if ($p > 0 && posix_kill($p, 0)) $daemonPid = $p;
          }
        ?>
        <button type="button" id="restartBtn" class="lb-btn lb-btn-icon">
          <i class="pi pi-refresh"></i> <?=$L['FORM2.RESTART_DAEMON'];?>
        </button>
        <span id="daemonPid" style="color:#888;"><?= $daemonPid ? 'PID '.$daemonPid : 'PID –'; ?></span>
      </div>
    </div>

  </div>

</form>

</div>
</div>

<script>
var LBL_SAVED       = <?=json_encode($L['FORM1.SAVED']);?>;
var LBL_RESTART_OK  = <?=json_encode($L['FORM2.RESTART_OK']);?>;
var LBL_RESTART_ERR = <?=json_encode($L['FORM2.RESTART_ERR']);?>;

function showStatus(msg, isError) {
    var $s = $("#save-status");
    $s.text(msg).removeClass("ok err").addClass(isError ? "err" : "ok");
    clearTimeout($s.data("t"));
    $s.data("t", setTimeout(function(){ $s.removeClass("ok err"); }, 2000));
}

function save(field, value) {
    var fd = new FormData();
    fd.append("field", field);
    fd.append("value", value);
    fetch("ajax_save.php", { method: "POST", body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.ok) {
            if (res.clamped) $("#pause_seconds").val(res.clamped);
        } else {
            showStatus(res.msg || LBL_RESTART_ERR, true);
        }
      })
      .catch(function(e){ showStatus(e.message, true); });
}

$("#activated").on("change", function(){
    save("activated", this.checked ? "true" : "false");
});
$("#pause_seconds, #sonnen_token").on("blur", function(){
    var map = { pause_seconds: "pause", sonnen_token: "token" };
    save(map[this.id], this.value);
});
$("#sonnen_host").on("blur", function(){ save("host", this.value); });

$("#restartBtn").on("click", function(){
    var b = this; b.disabled = true; b.style.opacity = ".5";
    fetch("ajax_restart.php").then(function(r){ return r.json(); }).then(function(res){
        if (res.ok) { $("#daemonPid").text("PID " + res.pidAfter); showStatus(LBL_RESTART_OK.replace("%s", res.pidAfter), false); }
        else { $("#daemonPid").text("PID –"); showStatus(LBL_RESTART_ERR, true); }
    }).catch(function(e){ showStatus(e.message, true); })
      .finally(function(){ b.disabled = false; b.style.opacity = "1"; });
});

$("#pwToggle").on("click", function(){
    var inp = $("#sonnen_token"); var show = inp.attr("type") === "password";
    inp.attr("type", show ? "text" : "password");
    $(this).find("i").attr("class", show ? "pi pi-eye-slash" : "pi pi-eye");
});
</script>

<?php LBWeb::lbfooter(); ?>