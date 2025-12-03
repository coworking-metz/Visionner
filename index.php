<?php
include __DIR__ . '/lib/main.inc.php';

$id = $_GET['id'] ?? false;
$slug = $_GET['slug'] ?? false;
$ecran = getEcran($slug);

if (!$ecran) {
    http_response_code(404);
    echo "Écran non trouvé";
    exit;
}

$cssFile = 'assets/slides.css';
$cssPath = __DIR__ . '/' . $cssFile;
$cssUrl  = BASE_URL . '/' . $cssFile . '?v=' . filemtime($cssPath);

$jsFile = 'assets/slides.js';
$jsPath = __DIR__ . '/' . $jsFile;
$jsUrl  = BASE_URL . '/' . $jsFile . '?v=' . filemtime($jsPath);

$audiojsFile = 'assets/audio.js';
$audiojsPath = __DIR__ . '/' . $audiojsFile;
$audiojsUrl  = BASE_URL . '/' . $audiojsFile . '?v=' . filemtime($audiojsPath);
cacheHeaders();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ecran['name']) ?></title>
    <link rel="stylesheet" href="<?= $cssUrl ?>">
    <script>
        window.ECRAN_PLAYLIST = <?= json_encode(array_filter(array_map('trim', explode("\n", $ecran['playlist_on'] ? $ecran['playlist'] : '')))) ?>;
        window.ECRAN_VOLUME = <?= intval($ecran['playlist_volume'] ?? 50) ?>;
    </script>

    <script src="<?= $audiojsUrl ?>" defer></script>
    <script src="<?= $jsUrl ?>" defer></script>
    <?= '<!-- ' . json_encode($ecran, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ' -->' ?>
</head>

<body>

    <div id="screen"></div>
    <audio id="player" controls></audio>

    <?php foreach ($ecran['slides'] as $slide) {
        if ($id && $slide['id'] != $id) continue; ?>
        <template id="slide-<?= $slide['id'] ?>" data-duration="<?= intval($slide['duration'] ?? 10) ?>">
            <div id="slide-<?= $slide['id'] ?>" style="--_background-color: <?= htmlspecialchars($slide['meta']['backgroundColor'] ?? '') ?>;--_color: <?= htmlspecialchars($slide['meta']['color'] ?? '') ?>;--_background-opacity: <?= htmlspecialchars($slide['meta']['opacity'] ?? '') ?>;">


                <?php if ($slide['type'] === 'url') { ?>
                    <iframe src="<?= htmlspecialchars($slide['meta']['url']) ?>"></iframe>

                <?php } elseif ($slide['type'] === 'image') { ?>
                    <div class="slide-body">
                        <?php if (!empty($slide['meta']['image'])) { ?>
                            <img src="<?= htmlspecialchars($slide['meta']['image']) ?>" class="slide-img">
                        <?php } ?>
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
                                    <?php if (!empty($slide['meta']['emojiPrincipal'])) { ?>
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
        </template>
    <?php } ?>

</body>

</html>