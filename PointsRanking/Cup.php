<?php
/**
 * Cup.php - Main UI page for the Puchar Polski multi-round classification.
 *
 * Cup settings (edition, round, diploma name), the round snapshot, CSV
 * import/export of the other rounds, the combined classification and the
 * baraż recording form. Available only while the `pp` preset is active.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');
require_once('Fun_Cup.php');
require_once('CupCalc.php');

pl_points_ensure_tables();

$tourId = $_SESSION['TourId'];
$activePresetKey = pl_points_get_tournament_preset($tourId);

// The cup exists only for Puchar Polski: no other preset is shot as four rounds.
if ($activePresetKey !== PL_CUP_PRESET_KEY) {
    $PAGE_TITLE = 'Puchar Polski - klasyfikacja';
    include('Common/Templates/head.php');
    echo '<table class="Tabella"><tr><th class="Title">Puchar Polski - klasyfikacja</th></tr></table>';
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
    echo 'Klasyfikacja generalna dotyczy wyłącznie Pucharu Polski. Wybierz klasyfikację punktową '
        . '"' . htmlspecialchars(PL_POINTS_PRESETS[PL_CUP_PRESET_KEY]['name']) . '" na stronie '
        . '<a href="PointsRanking.php">Klasyfikacja punktowa</a>, aby korzystać z tej strony.';
    echo '</div>';
    include('Common/Templates/tail.php');
    exit;
}

pl_cup_ensure_tables();

$config = pl_cup_get_config($tourId);
$messages = [];
$errors = [];

// --- Export (must run before any page output) ------------------------------

if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // is_scalar, not just isset: intval() takes an array without complaining.
    $exportRound = isset($_GET['Round']) && is_scalar($_GET['Round']) ? intval($_GET['Round']) : $config['Round'];
    if ($exportRound < 1 || $exportRound > PL_CUP_ROUNDS) {
        die('Nieprawidłowy numer rundy do eksportu.');
    }
    $rows = pl_cup_load_rounds($config['Edition'], $exportRound);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="puchar_polski_' . intval($config['Edition']) . '_runda_' . $exportRound . '.csv"');
    echo pl_cup_csv_write($rows);
    exit;
}

// --- Actions ---------------------------------------------------------------

/** intval() silently accepts an array (a crafted Field[]=x request), so scalars only. */
function pl_cup_post_int($field)
{
    return isset($_POST[$field]) && is_scalar($_POST[$field]) ? intval($_POST[$field]) : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveConfig'])) {
    $diplomaName = (isset($_POST['DiplomaName']) && is_string($_POST['DiplomaName'])) ? trim($_POST['DiplomaName']) : '';
    pl_cup_set_config($tourId, pl_cup_post_int('Edition'), pl_cup_post_int('Round'), $diplomaName);
    $config = pl_cup_get_config($tourId);
    $messages[] = 'Ustawienia Pucharu Polski zapisane.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snapshot'])) {
    if ($config['Round'] < 1) {
        $errors[] = 'Najpierw wskaż, którą rundą Pucharu Polski są te zawody.';
    } else {
        $snapshot = pl_cup_build_snapshot($tourId);
        if (!empty($snapshot['errors'])) {
            $errors = array_merge($errors, $snapshot['errors']);
        } else {
            $storeError = pl_cup_store_round($config['Edition'], $config['Round'], $snapshot['rows']);
            if ($storeError !== '') {
                $errors[] = $storeError;
            } else {
                $messages[] = 'Zapisano rundę ' . $config['Round'] . ' (' . count($snapshot['rows']) . ' wierszy).';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $importRound = pl_cup_post_int('ImportRound');
    if ($importRound < 1 || $importRound > PL_CUP_ROUNDS) {
        $errors[] = 'Wybierz numer rundy do zaimportowania.';
    } elseif (!isset($_FILES['CsvFile']) || !is_uploaded_file($_FILES['CsvFile']['tmp_name'] ?? '')) {
        $errors[] = 'Wybierz plik CSV.';
    } else {
        $parsed = pl_cup_csv_parse(file_get_contents($_FILES['CsvFile']['tmp_name']), pl_cup_valid_categories($tourId));
        if (!empty($parsed['errors'])) {
            // Atomic: a single bad line leaves the stored round untouched.
            $errors = array_merge($errors, $parsed['errors']);
        } else {
            $storeError = pl_cup_store_round($config['Edition'], $importRound, $parsed['rows']);
            if ($storeError !== '') {
                $errors[] = $storeError;
            } else {
                $messages[] = 'Zaimportowano rundę ' . $importRound . ' (' . count($parsed['rows']) . ' wierszy).';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveBarrage'])) {
    $orders = isset($_POST['BarrageOrder']) && is_array($_POST['BarrageOrder']) ? $_POST['BarrageOrder'] : [];
    $saved = 0;
    foreach ($orders as $key => $order) {
        $parts = explode('|', (string) $key);
        if (count($parts) !== 3 || !is_scalar($order)) {
            continue;
        }
        pl_cup_set_barrage($config['Edition'], $parts[0], $parts[1], $parts[2], intval($order));
        $saved++;
    }
    if ($saved > 0) {
        $messages[] = 'Zapisano wynik barażu.';
    }
}

// --- Data ------------------------------------------------------------------

$storedRounds = pl_cup_stored_rounds($config['Edition']);
$roundRows = pl_cup_load_rounds($config['Edition']);
$barrages = pl_cup_load_barrages($config['Edition']);
// A baraż settles the cup, not a standing after round 2 (D6a) - gated on the
// final round being stored, not on this tournament being it.
$barrageAllowed = in_array(PL_CUP_ROUNDS, $storedRounds, true);
$classifications = pl_cup_build_classifications($roundRows, $barrages, pl_cup_category_meta($tourId), $barrageAllowed);

$staleCount = 0;
if ($config['Round'] >= 1 && in_array($config['Round'], $storedRounds, true)) {
    $snapshot = pl_cup_build_snapshot($tourId);
    $staleCount = pl_cup_diff_snapshot($snapshot['rows'], pl_cup_load_rounds($config['Edition'], $config['Round']));
}

// --- Rendering -------------------------------------------------------------

function pl_cup_render_notice($text, $color, $border)
{
    echo '<div style="background:' . $color . ';border:1px solid ' . $border . ';padding:10px;margin:10px 0;border-radius:4px;">' . $text . '</div>';
}

function pl_cup_mark_label($row)
{
    if (!empty($row['barrage_resolved'])) {
        return 'rozstrzygnięte barażem';
    }
    if ($row['tie_mark'] === '') {
        return '';
    }
    return PL_CUP_MARK_LABELS[$row['tie_mark']] ?? '';
}

function pl_cup_render_classification(array $classification, $isMixed)
{
    if (empty($classification['sections'])) {
        return;
    }
    $colCount = 4 + PL_CUP_ROUNDS + 2;

    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="' . $colCount . '">' . htmlspecialchars($classification['label']) . '</th></tr>';

    foreach ($classification['sections'] as $section) {
        echo '<tr><td colspan="' . $colCount . '" style="padding:6px;background:#e9ecef;font-weight:bold;">' . htmlspecialchars($section['label']) . '</td></tr>';
        echo '<tr><th>Miejsce</th><th>' . ($isMixed ? 'Klub' : 'Zawodnik') . '</th><th>' . ($isMixed ? 'Skład' : 'Klub') . '</th>'
            . '<th>' . ($isMixed ? 'Kod klubu' : 'Nr licencji') . '</th>';
        for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
            echo '<th>R' . $round . '</th>';
        }
        echo '<th>Suma</th><th>Uwagi</th></tr>';

        foreach ($section['rows'] as $row) {
            echo '<tr>';
            echo '<td style="text-align:center;">' . intval($row['rank']) . '</td>';
            echo '<td>' . htmlspecialchars($isMixed ? $row['club_name'] : $row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($isMixed ? $row['name'] : $row['club_name']) . '</td>';
            echo '<td style="text-align:center;">' . htmlspecialchars($row['identity']) . '</td>';
            for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
                $points = $row['rounds'][$round] ?? null;
                $title = $points === null ? '' : ' title="Miejsce: ' . intval($row['places'][$round] ?? 0)
                    . ', kwalifikacje: ' . intval($row['quals'][$round] ?? 0) . '"';
                echo '<td style="text-align:center;"' . $title . '>' . ($points === null ? '' : intval($points)) . '</td>';
            }
            echo '<td style="text-align:center;font-weight:bold;">' . intval($row['total']) . '</td>';
            echo '<td style="text-align:center;color:#a00;">' . htmlspecialchars(pl_cup_mark_label($row)) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table><br>';
}

/** Tied groups the regulation prescribes a baraż for; the judge decides on the line. */
function pl_cup_render_barrage_form(array $classifications, array $barrages)
{
    $groups = [];
    foreach ($classifications as $classKey => $classification) {
        foreach ($classification['sections'] as $section) {
            if ($section['terminator'] !== 'BARRAGE') {
                continue;
            }
            foreach ($section['rows'] as $row) {
                if ($row['tie_group'] >= 0) {
                    $groups[$classKey . '|' . $section['category'] . '|' . $row['tie_group']]['label'] = $section['label'];
                    $groups[$classKey . '|' . $section['category'] . '|' . $row['tie_group']]['rows'][] = $row;
                }
            }
        }
    }
    if (empty($groups)) {
        return;
    }

    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="4">Baraż - kolejność ustalona na strzelnicy</th></tr>';
    echo '<tr><td colspan="4" style="padding:8px;">';
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="saveBarrage" value="1">';
    echo '<table class="Tabella" style="width:100%;">';
    echo '<tr><th>Kategoria</th><th>Zawodnik / klub</th><th>Suma</th><th>Kolejność (1 = zwycięzca, 0 = brak)</th></tr>';
    foreach ($groups as $group) {
        foreach ($group['rows'] as $row) {
            $key = $row['classification'] . '|' . $row['category'] . '|' . $row['identity'];
            $current = $barrages[$key] ?? 0;
            echo '<tr>';
            echo '<td>' . htmlspecialchars($group['label']) . '</td>';
            echo '<td>' . htmlspecialchars($row['name'] !== '' ? $row['name'] : $row['club_name']) . '</td>';
            echo '<td style="text-align:center;">' . intval($row['total']) . '</td>';
            echo '<td style="text-align:center;"><input type="number" min="0" max="99" style="width:60px;" name="BarrageOrder['
                . htmlspecialchars($key) . ']" value="' . intval($current) . '"></td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    echo '<br><input type="submit" value="Zapisz wynik barażu">';
    echo '</form>';
    echo '</td></tr></table><br>';
}

$PAGE_TITLE = 'Puchar Polski - klasyfikacja';
include('Common/Templates/head.php');

foreach ($errors as $error) {
    pl_cup_render_notice(htmlspecialchars($error), '#f8d7da', '#dc3545');
}
foreach ($messages as $message) {
    pl_cup_render_notice(htmlspecialchars($message), '#d4edda', '#28a745');
}

// Settings
$editions = array_unique(array_merge([pl_cup_default_edition(), $config['Edition']], range(pl_cup_default_edition() - 2, pl_cup_default_edition() + 1)));
sort($editions);

echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Puchar Polski - ustawienia</th></tr>';
echo '<tr><td colspan="2" style="padding:8px;">';
echo '<form method="post" action="">';
echo '<input type="hidden" name="saveConfig" value="1">';
echo 'Edycja (rok): <select name="Edition">';
foreach ($editions as $edition) {
    echo '<option value="' . intval($edition) . '"' . ($edition == $config['Edition'] ? ' selected' : '') . '>' . intval($edition) . '</option>';
}
echo '</select> &nbsp; Te zawody to runda: <select name="Round">';
echo '<option value="0"' . ($config['Round'] === 0 ? ' selected' : '') . '>-- nie ustawiono --</option>';
for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
    echo '<option value="' . $round . '"' . ($config['Round'] === $round ? ' selected' : '') . '>' . $round . '</option>';
}
echo '</select> &nbsp; Nazwa na dyplomach: <input type="text" name="DiplomaName" size="40" value="'
    . htmlspecialchars($config['DiplomaName']) . '" placeholder="np. Puchar Polski 2026">';
echo ' <input type="submit" value="Zapisz">';
echo '</form>';
echo '</td></tr>';
echo '</table><br>';

// Rounds
echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Rundy edycji ' . intval($config['Edition']) . '</th></tr>';
echo '<tr><td style="padding:8px;" colspan="2">';
echo 'Zapisane rundy: ' . (empty($storedRounds) ? 'brak' : htmlspecialchars(implode(', ', $storedRounds)));
echo '</td></tr>';
echo '<tr><td style="padding:8px;" colspan="2">';
echo '<form method="post" action="" style="display:inline;">';
echo '<input type="hidden" name="snapshot" value="1">';
echo '<input type="submit" value="Zapisz bieżącą rundę"' . ($config['Round'] < 1 ? ' disabled' : '') . '>';
echo '</form>';
if ($config['Round'] >= 1 && in_array($config['Round'], $storedRounds, true)) {
    echo ' &nbsp; <a href="Cup.php?action=export&amp;Round=' . intval($config['Round']) . '">Eksportuj rundę ' . intval($config['Round']) . ' (CSV)</a>';
}
echo '</td></tr>';
echo '<tr><td style="padding:8px;" colspan="2">';
echo '<form method="post" action="" enctype="multipart/form-data">';
echo '<input type="hidden" name="import" value="1">';
echo 'Import rundy: <select name="ImportRound">';
for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
    echo '<option value="' . $round . '">' . $round . '</option>';
}
echo '</select> <input type="file" name="CsvFile" accept=".csv,text/csv"> <input type="submit" value="Importuj CSV">';
echo '</form>';
echo '</td></tr>';
echo '</table><br>';

if ($staleCount > 0) {
    pl_cup_render_notice(
        'Zapisana runda ' . intval($config['Round']) . ' różni się od bieżących wyników w ' . intval($staleCount)
        . ' wierszach. Zapisz rundę ponownie, aby klasyfikacja uwzględniała aktualne wyniki.',
        '#fff3cd',
        '#ffc107'
    );
}

if (empty($roundRows)) {
    pl_cup_render_notice('Brak zapisanych rund dla tej edycji - zapisz bieżącą rundę lub zaimportuj wcześniejsze.', '#fff3cd', '#ffc107');
    include('Common/Templates/tail.php');
    exit;
}

echo '<div style="padding:8px 0;">';
echo '<a href="PrnCupRanking.php" target="_blank" style="font-weight:bold;">Generuj PDF klasyfikacji</a>';
echo ' &nbsp; <a href="PrnCupDipl.php?Class=ind" target="_blank">Dyplomy - indywidualne</a>';
echo ' &nbsp; <a href="PrnCupDipl.php?Class=mix" target="_blank">Dyplomy - miksty</a>';
echo '</div>';

pl_cup_render_classification($classifications['ind'], false);
pl_cup_render_classification($classifications['mix'], true);

if ($barrageAllowed) {
    pl_cup_render_barrage_form($classifications, $barrages);
}

include('Common/Templates/tail.php');
