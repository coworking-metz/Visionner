<?php
include __DIR__ . '/lib/main.inc.php';

$id = $_GET['id'] ?? false;
$slug = $_GET['slug'] ?? false;
$ecran = getEcran($slug);
if ($ecran) {
	$slides = $ecran['slides'];
} else {
	$slide = getSlide($id);
	if($slide) {
		$slides = [$slide];
		$ecran = [];
		$ecran['name'] = $slide['name'];
	} else {
    http_response_code(404);
    echo "Écran non trouvé";
    exit;
	}
}
$cssFile = 'assets/slides.css';
$cssPath = __DIR__ . '/' . $cssFile;
$cssUrl  = BASE_URL . '/' . $cssFile . '?v=' . date('Y-m-d-H-i-s',filemtime($cssPath));

$fontsFile = 'assets/fonts.css';
$fontsPath = __DIR__ . '/' . $fontsFile;
$fontsUrl  = BASE_URL . '/' . $fontsFile . '?v=' . date('Y-m-d-H-i-s',filemtime($fontsPath));

$jsFile = 'assets/slides.js';
$jsPath = __DIR__ . '/' . $jsFile;
$jsUrl  = BASE_URL . '/' . $jsFile . '?v=' . date('Y-m-d-H-i-s',filemtime($jsPath));

$audiojsFile = 'assets/audio.js';
$audiojsPath = __DIR__ . '/' . $audiojsFile;
$audiojsUrl  = BASE_URL . '/' . $audiojsFile . '?v=' . date('Y-m-d-H-i-s',filemtime($audiojsPath));

$websocketjsFile = 'assets/websocket.js';
$websocketjsPath = __DIR__ . '/' . $websocketjsFile;
$websocketjsUrl  = BASE_URL . '/' . $websocketjsFile . '?v=' . date('Y-m-d-H-i-s',filemtime($websocketjsPath));
cacheHeaders();
//print_r($slide);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ecran['name']) ?></title>
    <link rel="stylesheet" href="<?= $cssUrl ?>">
    <link rel="stylesheet" href="<?= $fontsUrl?>">
    <script>
        window.ECRAN_PLAYLIST = <?= json_encode(array_filter(array_map('trim', explode("\n", ($ecran['playlist_on']??false) ? $ecran['playlist'] : '')))) ?>;
        window.ECRAN_VOLUME = <?= intval($ecran['playlist_volume'] ?? 50) ?>;
        window.ECRAN_ID = "<?= $ecran['id'] ?? false ?>";
    </script>

    <script src="<?= $websocketjsUrl ?>" defer></script>
    <script src="<?= $audiojsUrl ?>" defer></script>
    <script src="<?= $jsUrl ?>" defer></script>
</head>

<body>

    <audio id="player" controls></audio>
    <div id="screen">

    <?php foreach ($slides as $slide) {
        if ($id && $slide['id'] != $id) continue; ?>
            <div id="slide-<?= $slide['id'] ?>" class="slide" data-duration="<?= intval($slide['duration'] ?? 10) ?>" style="--_background-color: <?= htmlspecialchars($slide['meta']['backgroundColor'] ?: 'black') ?>;--_color: <?= htmlspecialchars($slide['meta']['color'] ?? '') ?>;--_background-fit: <?= htmlspecialchars($slide['meta']['fit'] ?? '') ?>;--_background-opacity: <?= htmlspecialchars($slide['meta']['opacity'] ?? '') ?>;">


                <?php if ($slide['type'] === 'url') { ?>
                    <iframe src="<?= htmlspecialchars($slide['meta']['url']) ?>"></iframe>

                <?php } elseif ($slide['type'] === 'image') { ?>
                    <div class="slide-body">
                        <?php if (!empty($slide['meta']['image'])) { ?>
                            <img src="<?= htmlspecialchars($slide['meta']['image']) ?>" class="slide-img">
                        <?php } ?>
					</div>
                    <?php } elseif ($slide['type'] === 'default') { ?>
                        <div class="slide-body">
                            <?php if ($slide['meta']['url']) { ?>
                                <div class="qr">
                                    <img src="https://tools.coworking-metz.fr/qr/?url=<?= urlencode($slide['meta']['url']) ?>">
                                </div>
                            <?php } ?>
                            <?php if (!empty($slide['meta']['image'])) { ?>
                                <img src="<?= htmlspecialchars($slide['meta']['image']) ?>" class="slide-img">
                            <?php } ?>
                            <div class="slide-content">
                                <div>
                                    <?php if (!empty($slide['meta']['imagePrincipale'])) { ?>
                                        <img class="imagePrincipale" src="<?= $slide['meta']['imagePrincipale'] ?>">
                                    <?php } else if (!empty($slide['meta']['emojiPrincipal'])) { ?>
                                        <h2><?= htmlspecialchars($slide['meta']['emojiPrincipal']) ?></h2>
                                    <?php } else if (!empty($slide['meta']['titre'])) { ?>
                                        <h3><?= htmlspecialchars($slide['meta']['titre']) ?></h3>
                                    <?php } ?>

                                    <?php if (!empty($slide['meta']['texte'])) { ?>
                                        <div class="slide-text">
                                            <?= $slide['meta']['texte'] ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                    <?php } ?>
				</div>

    <?php } ?>
</div>
</body>

</html>


<script>

function detectClient() {
    const ua = navigator.userAgent;
    const data = { browser: null, browserVersion: null, os: null, osVersion: null };

    if (navigator.userAgentData) {
        data.browser = navigator.userAgentData.brands?.[0]?.brand;
        data.browserVersion = navigator.userAgentData.brands?.[0]?.version;
        data.os = navigator.userAgentData.platform;
        return data;
    }

    if (/Windows NT/.test(ua)) {
        data.os = "Windows";
        data.osVersion = ua.match(/Windows NT ([\d.]+)/)?.[1];
        return fillBrowser(ua, data);
    }

    if (/Mac OS X/.test(ua)) {
        data.os = "macOS";
        data.osVersion = ua.match(/Mac OS X ([\d_]+)/)?.[1]?.replace(/_/g, ".");
        return fillBrowser(ua, data);
    }

    if (/Android/.test(ua)) {
        data.os = "Android";
        data.osVersion = ua.match(/Android ([\d.]+)/)?.[1];
        return fillBrowser(ua, data);
    }

    if (/iPhone OS/.test(ua)) {
        data.os = "iOS";
        data.osVersion = ua.match(/iPhone OS ([\d_]+)/)?.[1]?.replace(/_/g, ".");
        return fillBrowser(ua, data);
    }

    if (/Linux/.test(ua)) {
        data.os = "Linux";
        return fillBrowser(ua, data);
    }

    return fillBrowser(ua, data);
}

function fillBrowser(ua, data) {
    if (/Edg\//.test(ua)) {
        data.browser = "Edge";
        data.browserVersion = ua.match(/Edg\/([\d.]+)/)?.[1];
        return data;
    }

    if (/Chrome\//.test(ua)) {
        data.browser = "Chrome";
        data.browserVersion = ua.match(/Chrome\/([\d.]+)/)?.[1];
        return data;
    }

    if (/Safari\//.test(ua) && /Version\//.test(ua)) {
        data.browser = "Safari";
        data.browserVersion = ua.match(/Version\/([\d.]+)/)?.[1];
        return data;
    }

    if (/Firefox\//.test(ua)) {
        data.browser = "Firefox";
        data.browserVersion = ua.match(/Firefox\/([\d.]+)/)?.[1];
        return data;
    }

    if (/MSIE /.test(ua)) {
        data.browser = "IE";
        data.browserVersion = ua.match(/MSIE ([\d.]+)/)?.[1];
        return data;
    }

    if (/Trident\//.test(ua)) {
        data.browser = "IE";
        data.browserVersion = ua.match(/rv:([\d.]+)/)?.[1];
        return data;
    }

    data.browser = "Unknown";
    return data;
}

/*const info = detectClient();
const pre = document.createElement('pre');
pre.textContent = JSON.stringify(info, null, 4);
document.body.innerHTML = '';
document.body.appendChild(pre);
*/

</script>
