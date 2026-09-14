<?php
/**
 * LiveQualificationData.php — AJAX/JSON data feed for LiveQualification.php.
 *
 * Read-only: checkFullACL(..., AclReadOnly, false) 404s a caller lacking
 * even read access instead of returning any tournament data (mirrors
 * Qualification/TargetUpdate_XML.php's guard for this same AJAX context).
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
require_once dirname(__FILE__) . '/Fun_LiveQualification.php';

if (!CheckTourSession()) {
    JsonOut(['error' => 1]);
}
checkFullACL(AclQualification, '', AclReadOnly, false);

$session = (isset($_REQUEST['Session']) && preg_match('/^[0-9]{1,2}$/', $_REQUEST['Session']))
    ? (int) $_REQUEST['Session']
    : 0;
$distance = (isset($_REQUEST['Distance']) && preg_match('/^[1-8]$/', $_REQUEST['Distance']))
    ? (int) $_REQUEST['Distance']
    : 0;

if ($session <= 0 || $distance <= 0) {
    JsonOut(['error' => 1]);
}

$tourId = (int) $_SESSION['TourId'];

// Arrows-per-end for this exact session+distance (qualification rounds only —
// DiType='Q' keeps this from picking up an elimination/final row that shares
// the same session+distance numbering with a different arrow count).
$rsDist = safe_r_sql(
    "SELECT DiArrows FROM DistanceInformation "
    . "WHERE DiTournament = " . StrSafe_DB($tourId, true)
    . " AND DiSession = " . StrSafe_DB($session, true)
    . " AND DiDistance = " . StrSafe_DB($distance, true)
    . " AND DiType = 'Q'"
);
$distRow = safe_fetch($rsDist);
$arrowsPerEnd = $distRow ? (int) $distRow->DiArrows : 0;
safe_free_result($rsDist);

if ($arrowsPerEnd <= 0) {
    JsonOut(['error' => 1]);
}

// $distance is validated above against /^[1-8]$/, so this is safe to splice
// into the column list (there is no parameterized way to select a dynamic
// column name).
$scoreCol = 'QuD' . $distance . 'Score';
$arrowCol = 'QuD' . $distance . 'ArrowString';

$rsQual = safe_r_sql(
    "SELECT En.EnName, En.EnFirstName, Qu.QuTarget, Qu.QuLetter, Qu.QuIrmType, Irm.IrmType, "
    . "Qu.{$scoreCol} AS DistScore, Qu.{$arrowCol} AS DistArrowString "
    . "FROM Qualifications Qu "
    . "INNER JOIN Entries En ON En.EnId = Qu.QuId "
    . "LEFT JOIN IrmTypes Irm ON Irm.IrmId = Qu.QuIrmType "
    . "WHERE En.EnTournament = " . StrSafe_DB($tourId, true)
    . " AND Qu.QuSession = " . StrSafe_DB($session, true)
    . " AND En.EnStatus <= 1 "
    . "ORDER BY Qu.QuTarget ASC, Qu.QuLetter ASC"
);

// QuIrmType != 0 means the entry is DNS/DNF/DSQ/DQB for this round (IrmTypes
// lookup) — not merely behind on pace. Such rows are excluded from the
// peer-lag pool entirely: their (usually zero) arrow count must not count
// toward "current max", and they are never flagged as lacking results —
// their status column already explains why they have no progress.
//
// A nonzero score with zero arrows shot (no IRM status) is a different case:
// it can only happen when the distance was scored through a non-arrow-by-arrow
// path (bulk/manual entry) instead of a live phone sync — a phone sync always
// writes the arrow string alongside the score. Flag it as a data gap rather
// than "behind": it isn't a target falling behind live scoring, it's a target
// this view has no arrow-level visibility into.
$rows = [];
$maxArrows = 0;
while ($r = safe_fetch($rsQual)) {
    $arrowsShot = pl_live_qual_arrows_shot((string) $r->DistArrowString);
    $score = (int) $r->DistScore;
    $status = (int) $r->QuIrmType === 0 ? '' : (string) $r->IrmType;
    $dataGap = ($status === '' && $arrowsShot === 0 && $score > 0);
    if ($status === '' && !$dataGap) {
        $maxArrows = max($maxArrows, $arrowsShot);
    }
    $rows[] = [
        'target' => (string) $r->QuTarget,
        'letter' => (string) $r->QuLetter,
        'name' => trim($r->EnFirstName . ' ' . $r->EnName),
        'score' => $score,
        'arrowsShot' => $arrowsShot,
        'status' => $status,
        'dataGap' => $dataGap,
    ];
}
safe_free_result($rsQual);

$targets = [];
foreach ($rows as $row) {
    $row['isBehind'] = ($row['status'] === '' && !$row['dataGap'])
        ? pl_live_qual_is_behind($row['arrowsShot'], $maxArrows, $arrowsPerEnd)
        : false;
    $targets[$row['target']][] = $row;
}

// A target is "flagged" if any of its rows is behind.
$flaggedTargets = 0;
foreach ($targets as $targetRows) {
    foreach ($targetRows as $row) {
        if ($row['isBehind']) {
            ++$flaggedTargets;
            break;
        }
    }
}

JsonOut([
    'error' => 0,
    'maxArrows' => $maxArrows,
    'totalTargets' => count($targets),
    'flaggedTargets' => $flaggedTargets,
    'targets' => $targets,
]);
