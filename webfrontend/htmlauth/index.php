<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";
require_once "init.php";

global $ms,$L,$pluginJson,$pluginName,$pluginTitle;
$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(1);
$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
LBWeb::lbheader($template_title, $helplink, $helptemplate, true);

// API-URL für Schritt 2
$apiUrl = "http://" . htmlspecialchars($sonnenHostName) . "/dash/software-integration/json-api";

// Screenshot (sprachabhängig, aus language_xx.ini: IMG_API_ENABLE)
$imgApi = $L['FORM0.IMG_API_ENABLE'] ?? "";
$imgApiPath = "./res/" . $imgApi;
$showImg = $imgApi !== "" && @file_exists(__DIR__ . "/res/" . $imgApi);
?>
<div class="lb-content">
<div style="max-width:820px;">

<p class="intro-text"><?=$L['FORM0.DESCRIPTION'];?></p>
<p class="intro-text"><?=$L['FORM0.DESCRIPTION1'];?></p>

<div class="lb-card prep-card">
  <h4 class="prep-headline"><?=$L['FORM0.PREP_TITLE'];?> – <?=$L['FORM0.PREP_INTRO'];?></h4>

  <?php if ($showImg): ?>
  <div class="prep-screenshot">
    <img src="<?=htmlspecialchars($imgApiPath);?>" alt="Sonnen API aktivieren"
        class="prep-thumb" onclick="document.getElementById('imgModal').showModal();">
  </div>

  <dialog class="lb-modal" id="imgModal">
    <button type="button" class="lb-modal-close" onclick="document.getElementById('imgModal').close();" aria-label="Schliessen">&times;</button>
    <div class="lb-modal-content">
      <img src="<?=htmlspecialchars($imgApiPath);?>" alt="Sonnen API aktivieren"
          style="max-width:100%; height:auto; cursor:zoom-out;"
          onclick="document.getElementById('imgModal').close();">
    </div>
  </dialog>
  <?php endif; ?>

  <ol class="prep-steps">
    <li><a href="settings.php"><?=$L['FORM0.PREP_STEP1'];?></a></li>
    <li><?=sprintf($L['FORM0.PREP_STEP2'], '<a href="'.$apiUrl.'" target="_blank" rel="noopener">'.$apiUrl.'</a>'); ?></li>
    <li><a href="settings.php"><?=$L['FORM0.PREP_STEP3'];?></a></li>
    <li><?=$L['FORM0.PREP_STEP4'];?> <a href="<?=$L['FORM0.PREP_DOCURL'];?>" target="_blank" rel="noopener"><?=$L['FORM0.PREP_DOCURL_TEXT'];?></a></li>
    <li><?=$L['FORM0.PREP_STEP5'];?></li>
  </ol>

  <div class="prep-note"><?=$L['FORM0.PREP_NOTE'];?></div>
</div>

</div>
</div>
<?php LBWeb::lbfooter(); ?>