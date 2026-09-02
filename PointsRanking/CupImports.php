<?php
/**
 * CupImports.php - What the current Puchar Polski edition is assembled from.
 *
 * One row per import that still feeds the classification: its round, the
 * categories it owns, where the rows came from, in which competition the import
 * was performed and when. Superseded imports are not listed - the history is
 * grouped out of the stored rows themselves, so what is shown is exactly what
 * the ranking uses.
 *
 * Unlike the classification, this view is not narrowed to the categories of the
 * current competition: it is where the whole edition is inspected.
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

if (pl_points_get_tournament_preset($tourId) !== PL_CUP_PRESET_KEY) {
    $PAGE_TITLE = 'Puchar Polski - historia importu';
    include('Common/Templates/head.php');
    echo '<table class="Tabella"><tr><th class="Title">Puchar Polski - historia importu</th></tr></table>';
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
    echo 'Ta strona dotyczy wyłącznie Pucharu Polski.';
    echo '</div>';
    include('Common/Templates/tail.php');
    exit;
}

pl_cup_ensure_tables();

$config = pl_cup_get_config($tourId);
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteImport'])) {
    $round = isset($_POST['Round']) && is_scalar($_POST['Round']) ? intval($_POST['Round']) : 0;
    $source = isset($_POST['Source']) && is_string($_POST['Source']) ? $_POST['Source'] : '';
    $importedAt = isset($_POST['ImportedAt']) && is_string($_POST['ImportedAt']) ? $_POST['ImportedAt'] : '';
    if ($round >= 1 && $round <= PL_CUP_ROUNDS) {
        pl_cup_delete_import($config['Edition'], $round, $source, $importedAt);
        $messages[] = 'Usunięto import rundy ' . $round . ' (' . $source . ').';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteRound'])) {
    $round = isset($_POST['Round']) && is_scalar($_POST['Round']) ? intval($_POST['Round']) : 0;
    if ($round >= 1 && $round <= PL_CUP_ROUNDS) {
        pl_cup_delete_round($config['Edition'], $round);
        $messages[] = 'Usunięto całą rundę ' . $round . '.';
    }
}

$imports = pl_cup_load_imports($config['Edition']);

// Labels for every category the edition holds, not only the ones this
// competition runs - an imported round may cover divisions shot elsewhere.
$divLabels = pl_ranking_div_labels($tourId);

/** Competitions on this installation, for naming the one an import was made in. */
function pl_cup_tournament_names()
{
    $names = [];
    $Rs = safe_r_sql('SELECT ToId, ToCode, ToName FROM Tournament');
    while ($row = safe_fetch($Rs)) {
        $names[intval($row->ToId)] = $row->ToName . ' (' . $row->ToCode . ')';
    }
    safe_free_result($Rs);
    return $names;
}

$tournamentNames = pl_cup_tournament_names();

/**
 * Categories are named from their code, not from this competition's classes: an
 * imported round covers divisions that may not be shot here at all.
 */
function pl_cup_category_label(array $category, array $divLabels)
{
    $code = $category['category'];
    $fallback = $divLabels[$code]['label'] ?? '';
    return pl_cup_series_label($category['classification'], $code, $fallback) . ' [' . $code . ']';
}

$PAGE_TITLE = 'Puchar Polski - historia importu';
include('Common/Templates/head.php');

foreach ($messages as $message) {
    echo '<div style="background:#d4edda;border:1px solid #28a745;padding:10px;margin:10px 0;border-radius:4px;">'
        . htmlspecialchars($message) . '</div>';
}

echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Puchar Polski ' . intval($config['Edition']) . ' - historia importu</th></tr>';
echo '<tr><td colspan="2" style="padding:8px;">';
echo 'Lista pokazuje tylko te importy, które obecnie tworzą klasyfikację - import zastąpiony przez nowszy znika z listy. ';
echo '<a href="Cup.php">Wróć do klasyfikacji</a>';
echo '</td></tr>';
echo '</table><br>';

if (empty($imports)) {
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
    echo 'Brak zapisanych rund dla edycji ' . intval($config['Edition']) . '.';
    echo '</div>';
    include('Common/Templates/tail.php');
    exit;
}

$byRound = [];
foreach ($imports as $import) {
    $byRound[$import['round']][] = $import;
}
ksort($byRound);

foreach ($byRound as $round => $roundImports) {
    $roundRows = array_sum(array_column($roundImports, 'rows'));

    echo '<table class="Tabella">';
    // The whole-round button rides in the title bar and each import's button in
    // its own row: a delete belongs beside the data it removes, not under it.
    echo '<tr><th class="Title" colspan="5">Runda ' . intval($round) . ' - ' . intval($roundRows) . ' wierszy</th>';
    echo '<th class="Title" style="text-align:right;white-space:nowrap;">';
    echo '<form method="post" action="" style="display:inline;" onsubmit="return confirm(\'Usunąć całą rundę '
        . intval($round) . ' (' . intval($roundRows) . ' wierszy)?\');">';
    echo '<input type="hidden" name="deleteRound" value="1">';
    echo '<input type="hidden" name="Round" value="' . intval($round) . '">';
    echo '<input type="submit" value="Usuń całą rundę">';
    echo '</form>';
    echo '</th></tr>';
    echo '<tr><th>Kategorie</th><th>Wierszy</th><th>Źródło</th><th>Zaimportowano w</th><th>Data</th><th></th></tr>';

    foreach ($roundImports as $import) {
        $labels = array_map(fn ($category) => pl_cup_category_label($category, $divLabels), $import['categories']);
        $where = $tournamentNames[$import['import_tournament']]
            ?? ($import['import_tournament'] > 0 ? '#' . $import['import_tournament'] : '-');

        echo '<tr>';
        echo '<td>' . htmlspecialchars(implode(', ', $labels)) . '</td>';
        echo '<td style="text-align:center;">' . intval($import['rows']) . '</td>';
        echo '<td>' . htmlspecialchars($import['source'] !== '' ? $import['source'] : 'nieznane') . '</td>';
        echo '<td>' . htmlspecialchars($where) . '</td>';
        echo '<td style="text-align:center;white-space:nowrap;">'
            . htmlspecialchars($import['imported_at'] !== '' ? $import['imported_at'] : '-') . '</td>';
        echo '<td style="text-align:right;white-space:nowrap;">';
        echo '<form method="post" action="" style="display:inline;" onsubmit="return confirm(\'Usunąć ten import ('
            . htmlspecialchars(addslashes($import['source']), ENT_QUOTES) . ', ' . intval($import['rows']) . ' wierszy)?\');">';
        echo '<input type="hidden" name="deleteImport" value="1">';
        echo '<input type="hidden" name="Round" value="' . intval($import['round']) . '">';
        echo '<input type="hidden" name="Source" value="' . htmlspecialchars($import['source'], ENT_QUOTES) . '">';
        echo '<input type="hidden" name="ImportedAt" value="' . htmlspecialchars($import['imported_at'], ENT_QUOTES) . '">';
        echo '<input type="submit" value="Usuń import">';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</table><br>';
}

include('Common/Templates/tail.php');
