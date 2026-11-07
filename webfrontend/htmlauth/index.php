<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_json.php";

require_once "init.php";

global $ms;
global $L;
global $pluginJson;
global $generalJson;
global $pluginName;
global $pluginTitle;

$template_title = $pluginTitle;
$helplink = $L['BASIC.HELPLINK'];
$helptemplate = "pluginhelp.php";
buildNavbar(1);

$htmlhead = "<link rel='stylesheet' href='./res/pluginmain.css'>";
$htmlhead .= "<script src='./res/pluginscripts.js'></script>";

LBWeb::lbheader($template_title, $helplink, $helptemplate);
?>

<h2><?=$L['FORM0.TITLE'];?></h2>

<p class="intro-text">
    <?=$L['FORM0.DESCRIPTION'];?>
</p>

<h3><?=$L['FORM0.PREP_TITLE'];?></h3>

<p class="prep-lead"><?=$L['FORM0.PREP_INTRO'];?></p>

<a href="./res/<?=$L['FORM0.IMG_API_ENABLE'];?>" class="prep-thumb-link" data-lightbox="./res/<?=$L['FORM0.IMG_API_ENABLE'];?>">
    <img src="./res/<?=$L['FORM0.IMG_API_ENABLE'];?>"
         alt="<?=htmlspecialchars($L['FORM0.PREP_INTRO']);?>"
         class="prep-thumb"
         style="width:150px; max-width:150px; height:auto; display:block;">
</a>

<?php
$sonnenApiUrl  = "http://" . $sonnenHostName . "/dash/software-integration/json-api";
$sonnenApiLink = '<a href="' . htmlspecialchars($sonnenApiUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($sonnenApiUrl) . '</a>';
?>
<div class="prep-step">
    <span class="prep-num">1</span>
    <div class="prep-body">
        <p><?=sprintf($L['FORM0.PREP_STEP1'], $sonnenApiLink);?></p>
    </div>
</div>

<div class="prep-step">
    <span class="prep-num">1</span>
    <div class="prep-body">
        <p><a href="settings.php"><?=$L['FORM0.PREP_STEP1'];?></a></p>
    </div>
</div>

<div class="prep-step">
    <span class="prep-num">2</span>
    <div class="prep-body">
        <p><?=sprintf($L['FORM0.PREP_STEP2'], $sonnenApiLink);?></p>
    </div>
</div>

<div class="prep-step">
    <span class="prep-num">3</span>
    <div class="prep-body">
        <p><?=$L['FORM0.PREP_STEP3'];?></p>
    </div>
</div>

<div class="prep-step">
    <span class="prep-num">4</span>
    <div class="prep-body">
        <p><?=$L['FORM0.PREP_STEP4'];?> <a href="<?=$L['FORM0.PREP_DOCURL'];?>" target="_blank" rel="noopener"><?=$L['FORM0.PREP_DOCURL_TEXT'];?></a></p>
    </div>
</div>

<div class="prep-step">
    <span class="prep-num">5</span>
    <div class="prep-body">
        <p><?=$L['FORM0.PREP_STEP5'];?></p>
    </div>
</div>

<p class="prep-note"><?=$L['FORM0.PREP_NOTE'];?></p>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
    <span class="lightbox-close" aria-label="Schliessen">&times;</span>
    <img class="lightbox-img" src="" alt="">
</div>

<script>
(function(){
    var lb = document.getElementById('lightbox');
    var lbImg = lb.querySelector('.lightbox-img');
    var lbClose = lb.querySelector('.lightbox-close');

    // Lightbox aus dem jQuery-Mobile-Seitencontainer herauslösen,
    // sonst bricht position:fixed wegen transform:translate3d zusammen
    if (lb && lb.parentNode !== document.body) {
        document.body.appendChild(lb);
    }

    function open(src){ lbImg.src = src; lb.classList.add('open'); }
    function close(){ lb.classList.remove('open'); lbImg.src = ''; }

    document.querySelectorAll('.prep-thumb-link').forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            open(this.getAttribute('data-lightbox'));
        });
    });
    lb.addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
})();
</script>

<?php
LBWeb::lbfooter();
?>