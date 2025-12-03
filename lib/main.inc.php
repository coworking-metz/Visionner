<?php
include __DIR__ . '/config.inc.php';


function cacheHeaders()
{
    header("Cache-Control: public, max-age=3600, s-maxage=3600");
}
/**
 * Basic Supabase request helper
 */
function supabase_request($endpoint, $method = 'GET', $params = [], $body = [])
{
    $url = SB_URL . $endpoint;

    if ($method === 'GET' && $params) $url .= '?' . http_build_query($params);

    $headers = [
        "apikey: " . SB_KEY,
        "Authorization: Bearer " . SB_KEY,
        "Accept: application/json",
        "Content-Type: application/json",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);

    return json_decode($response, true);
}

/**
 * Fetch ecran by slug
 */
function getEcran($slug)
{
    $data = supabase_request(
        'ecrans',
        'GET',
        [
            'select' => '*',
            'trash'  => 'eq.false'
        ]
    );

    if (!$data) return null;

    foreach ($data as $ecran) {
        if ($ecran['slug'] === $slug) return enrichir_ecran($ecran);
    }
    return null;
}

/**
 * Slide Eligibility tests -------------------------------------
 */
function isInTimeRange($item)
{
    if (empty($item['display_times'])) return true;

    $ranges = json_decode($item['display_times'], true);
    if (!$ranges || !is_array($ranges)) return true;

    $now = new DateTime("now");
    $weekNumber = intval($now->format("W"));      // ex: 49
    $weekday    = strtolower($now->format("l"));  // monday, tuesday...
    $currentUnix = time();

    foreach ($ranges as $range) {

        // 1) Filtre semaine (even/odd)
        if (!empty($range["weekNumberIs"])) {
            if ($range["weekNumberIs"] === "even" && $weekNumber % 2 !== 0) return false;
            if ($range["weekNumberIs"] === "odd"  && $weekNumber % 2 !== 1) return false;
        }

        // 2) Filtre jours
        if (!empty($range["days"]) && is_array($range["days"])) {
            $lowerDays = array_map('strtolower', $range["days"]);
            if (!in_array($weekday, $lowerDays)) return false;
        }

        // 3) Filtre plage horaire
        if (!empty($range["start"]) && !empty($range["end"])) {
            $start = strtotime($now->format("Y-m-d") . " " . $range["start"]);
            $end   = strtotime($now->format("Y-m-d") . " " . $range["end"]);

            if (!($currentUnix >= $start && $currentUnix <= $end)) return false;
        }
    }

    return true;
}


function hasPriority($slide)
{
    return isset($slide['priority']) && intval($slide['priority']) > 0;
}

function isAlways($slide)
{
    return isset($slide['always']) && $slide['always'] == true;
}

/**
 * Sort slides like Vue version
 */
function sortSlidesByIds($slides, $ids)
{
    $map = [];
    foreach ($slides as $slide) $map[$slide['id']] = $slide;

    $sorted = [];
    foreach ($ids as $id) if (isset($map[$id])) $sorted[] = $map[$id];

    foreach ($slides as $slide) if (!in_array($slide['id'], $ids)) $sorted[] = $slide;

    return $sorted;
}

/**
 * Enrich screen with filtered slides
 */
function enrichir_ecran($ecran)
{
    // get links screen↔slides
    $liens = supabase_request(
        'liens_ecrans_slides',
        'GET',
        ['select' => '*']
    );
    if (!$liens) {
        $ecran['slides'] = [];
        return $ecran;
    }

    // extract slide ids linked to this screen
    $slideIds = [];
    foreach ($liens as $l) {
        if ($l['ecran_id'] == $ecran['id']) $slideIds[] = $l['slide_id'];
    }

    $slides = getSlidesByIds($slideIds);
    if (!$slides) {
        $ecran['slides'] = [];
        return $ecran;
    }

    // filter active slides
    $activeSlides = array_filter($slides, fn($s) => !empty($s['active']));

    // optional sort order if screen defines it
    if (!empty($ecran['slideSort'])) {
        $idsSort = array_map('intval', $ecran['slideSort']);
        $activeSlides = sortSlidesByIds($activeSlides, $idsSort);
    }

    $now = time();
    $eligible = [];

    foreach ($activeSlides as $slide) {

        // if (!in_array($slide['id'], $slideIds)) continue;
        if (!isInTimeRange($slide)) continue;

        // publication
        if (!empty($slide['publication'])) {
            $pub = strtotime($slide['publication']);
            if ($pub > $now) continue;
        }

        // expiration
        if (!empty($slide['expiration'])) {
            $exp = strtotime($slide['expiration']);
            if ($exp < $now) continue;
        }

        $eligible[] = $slide;
    }

    // final priority filter
    $withPriority = array_filter($eligible, fn($s) => hasPriority($s));

    if ($withPriority) {
        $eligible = array_filter(
            $eligible,
            fn($s) => hasPriority($s) || isAlways($s)
        );
    }

    $ecran['slides'] = array_values($eligible);
    return $ecran;
}

/**
 * Fetch slides by IDs
 */
function getSlidesByIds($ids)
{
    if (!$ids || !is_array($ids)) return [];

    $idList = implode(',', $ids);

    return supabase_request(
        'slides',
        'GET',
        [
            'select' => '*',
            'id'     => 'in.(' . $idList . ')'
        ]
    );
}
