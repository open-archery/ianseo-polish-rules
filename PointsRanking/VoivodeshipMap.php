<?php
/**
 * VoivodeshipMap.php - Club -> voivodeship mapping page.
 *
 * Lists every club present in the current tournament with a dropdown of the
 * 16 Polish voivodeships. Mappings are keyed by Countries.CoCode (D6), so a
 * mapping entered once applies to every tournament that club code appears in.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');

pl_points_ensure_tables();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveMap'])) {
    $submitted = isset($_POST['Voivodeship']) ? $_POST['Voivodeship'] : [];
    foreach ($submitted as $coCode => $voivodeship) {
        pl_points_save_voivodeship($coCode, $voivodeship);
    }
    $message = 'Mapowanie zostało zapisane.';
}

$clubs = pl_points_load_clubs_in_tournament($_SESSION['TourId']);
$currentMap = pl_points_get_voivodeship_map();

$PAGE_TITLE = 'Mapa województw';

include('Common/Templates/head.php');

if (!empty($message)) {
    echo '<div style="background:#d4edda;border:1px solid #28a745;padding:10px;margin:10px 0;border-radius:4px;">';
    echo htmlspecialchars($message);
    echo '</div>';
}

echo '<form method="post" action="">';
echo '<input type="hidden" name="saveMap" value="1">';

echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Mapa województw</th></tr>';

if (empty($clubs)) {
    echo '<tr><td colspan="2"><em>Brak klubów w tym turnieju.</em></td></tr>';
} else {
    echo '<tr><th>Klub</th><th>Województwo</th></tr>';
    foreach ($clubs as $coCode => $coName) {
        $selectedVoivodeship = $currentMap[$coCode] ?? '';
        echo '<tr>';
        echo '<td style="padding:6px;">' . htmlspecialchars($coName) . ' <small>(' . htmlspecialchars($coCode) . ')</small></td>';
        echo '<td style="padding:6px;">';
        echo '<select name="Voivodeship[' . htmlspecialchars($coCode) . ']">';
        echo '<option value=""' . ($selectedVoivodeship === '' ? ' selected' : '') . '>-- Brak --</option>';
        foreach (PL_VOIVODESHIPS as $voivodeship) {
            $selected = ($voivodeship === $selectedVoivodeship) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($voivodeship) . '"' . $selected . '>' . htmlspecialchars($voivodeship) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
    }
}

echo '</table>';

if (!empty($clubs)) {
    echo '<br><div style="text-align:center;padding:10px;">';
    echo '<input type="submit" value="Zapisz mapowanie" style="padding:10px 30px;font-size:14px;font-weight:bold;">';
    echo '</div>';
}

echo '</form>';

include('Common/Templates/tail.php');
