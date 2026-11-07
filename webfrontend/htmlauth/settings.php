<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";
require_once "init.php";

global $pluginJson, $sonnenapiJson, $pluginTitle, $L;

$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(2);

# --- Aktuellen Zustand lesen ---
$activated = $pluginJson->SendToLox->Activated ?? false;
$transport = $sonnenapiJson->Sonnen->Transport ?? "http";

$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
$htmlhead .= "<script src='./res/pluginscripts.js'></script>";

LBWeb::lbheader($template_title, $helplink, $helptemplate);
?>
<h2><?=$L['FORM2.TITLE'];?></h2>
<div id="saveError"></div>

<form onsubmit="return false;">
  <table>
    <tr>
      <td><?=$L['FORM2.ACTIVATED'];?> / <?=$L['FORM2.PAUSE'];?></td>
      <td>
        <div class="ctrlRow">
          <input type="checkbox" name="activated" id="activated"
                 data-role="flipswitch" data-on-text="<?=$L['FORM2.ACTIVATED_ON'];?>" data-off-text="<?=$L['FORM2.ACTIVATED_OFF'];?>"
                 <?= $activated ? "checked" : "" ?>>
          <input type="number" name="pause_seconds" id="pause_seconds" data-role="none"
                 step="0.1" min="1" max="60"
                 value="<?=number_format(($pluginJson->SendToLox->PauseBetweenRunAsUsleep ?? 2500000) / 1000000, 1, '.', '');?>">
          <?php
            $daemonPid = null;
            $daemonPidFile = "/tmp/{$pluginName}.pid";
            if (file_exists($daemonPidFile)) {
                $p = (int) trim(file_get_contents($daemonPidFile));
                if ($p > 0 && posix_kill($p, 0)) $daemonPid = $p;
            }
          ?>
          <button type="button" id="restartBtn" data-role="none" title="<?=$L['FORM2.RESTART_DAEMON'];?>"
                  style="display:inline-flex; align-items:center; gap:6px; padding:6px 10px;
                         border:1px solid #808080; border-radius:6px; background:#fff; cursor:pointer;">
            <img src="./res/reset_daemon.png" alt="" style="width:18px; height:18px; display:block;">
            <span id="daemonPid"><?= $daemonPid ? 'PID '.$daemonPid : 'PID –'; ?></span>
          </button>
        </div>
      </td>
    </tr>
    <tr>
      <td><?=$L['FORM2.TRANSPORT'];?> / <?=$L['FORM2.HOST'];?></td>
      <td title="<?=$L['FORM2.TRANSPORT_TIP'];?>">
        <div class="ctrlRow">
          <input type="checkbox" name="transport" id="transport"
                 data-role="flipswitch" data-on-text="https" data-off-text="http"
                 disabled>
          <input type="text" name="sonnen_host" id="sonnen_host" data-role="none"
                 value="<?=htmlspecialchars($sonnenHostName);?>">
        </div>
      </td>
    </tr>
    <tr>
      <td><?=$L['FORM2.TOKEN'];?></td>
      <td>
        <span style="position:relative; display:block; width:100%;">
          <input type="password" id="sonnen_token" name="sonnen_token"
                 value="<?=htmlspecialchars($sonnenToken);?>"
                 style="width:280px; box-sizing:border-box; padding-right:44px;">
          <button type="button" id="pwToggle" title="Token anzeigen/verbergen" aria-label="Token anzeigen/verbergen"
                  style="position:absolute; right:8px; top:50%; transform:translateY(-50%); width:26px; height:26px; padding:0; margin:0; cursor:pointer; color:#555; line-height:0;">
            <svg id="pwEye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px; height:20px; display:block;">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg id="pwEyeOff" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px; height:20px; display:none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </span>
      </td>
    </tr>
  </table>
</form>

<script>
  (function(){
  // Banner aus dem jQuery-Mobile-Seitencontainer herauslösen,
  // sonst bricht position:fixed wegen transform:translate3d zusammen
  var errEl = document.getElementById('saveError');
  if (errEl && errEl.parentNode !== document.body) {
    document.body.appendChild(errEl);
  }

  // ... Rest unverändert (pwToggle, showError, save, ...)
  // --- Token anzeigen/verbergen ---
  var btn = document.getElementById('pwToggle');
  var inp = document.getElementById('sonnen_token');
  var eye = document.getElementById('pwEye');
  var eyeOff = document.getElementById('pwEyeOff');
  if (btn && inp) {
    btn.addEventListener('click', function(){
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      eye.style.display = show ? 'none' : 'block';
      eyeOff.style.display = show ? 'block' : 'none';
    });
  }

  // --- Auto-Save: Erfolg stumm, Fehler auffällig ---
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
    fetch("ajax_save.php", { method: "POST", body: data })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.ok) {
          hideError();
          if (res.clamped) { document.getElementById('pause_seconds').value = res.clamped; }
        } else {
          showError(res.msg || "Fehler beim Speichern");
        }
      })
      .catch(function(e){ showError("Serverfehler: " + e.message); });
  }

  // Text-/Zahlenfelder: beim Verlassen speichern
  ["sonnen_host","sonnen_token","pause_seconds"].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.addEventListener("blur", function(){
      var field = id.replace('sonnen_','').replace('_seconds','');
      save(field, el.value);
    });
  });

  // Flipswitch "activated": sofort beim Umschalten
  var act = document.getElementById('activated');
  if (act) {
      $(act).on("change", function(e, ui){
          var on = (ui && typeof ui.state !== 'undefined') ? ui.state : act.checked;
          save("activated", on ? "true" : "false");
      });
  }

  // --- Daemon neu starten ---
    var rBtn = document.getElementById('restartBtn');
    if (rBtn) {
      rBtn.addEventListener('click', function(){
        rBtn.disabled = true;
        rBtn.style.opacity = '0.5';
        fetch('ajax_restart.php')
          .then(function(r){ return r.json(); })
          .then(function(res){
            var el = document.getElementById('daemonPid');
            if (res.ok) {
              el.textContent = 'PID ' + res.pidAfter;
              hideError();
            } else {
              el.textContent = 'PID ' + (res.pidAfter ? res.pidAfter : '–');
              showError('<?= $L['FORM2.RESTART_ERR'] ?>: rc=' + res.rc
                + ' | script=' + res.script + ' | exists=' + res.scriptExists
                + ' | out=' + (res.output || 'leer'));
            }
          })
          .catch(function(e){ showError('<?= $L['FORM2.RESTART_ERR'] ?>: ' + e.message); })
          .finally(function(){
            rBtn.disabled = false;
            rBtn.style.opacity = '1';
          });
      });
    }

})();
</script>

<?php
LBWeb::lbfooter();
?>