<?php
/**
 * PointsRanking.php - Main UI page for the PL points-ranking module.
 *
 * Preset selection, then (once a preset is chosen) the calculated reports as
 * HTML tables, a PDF link and diploma buttons for the CLUB/VOIVODESHIP
 * reports the active preset declares.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');

pl_points_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selectPreset'])) {
    // trim() throws a TypeError on a non-string (a crafted PresetKey[]=...
    // request makes $_POST['PresetKey'] an array) — reject without touching
    // the stored preset, rather than letting trim() crash the page.
    if (!isset($_POST['PresetKey']) || is_string($_POST['PresetKey'])) {
        $presetKey = isset($_POST['PresetKey']) ? trim($_POST['PresetKey']) : '';
        // pl_points_set_tournament_preset() itself rejects anything not '' or a real preset key.
        pl_points_set_tournament_preset($_SESSION['TourId'], $presetKey);
    }
}

$activePresetKey = pl_points_get_tournament_preset($_SESSION['TourId']);
$activePreset = ($activePresetKey && isset(PL_POINTS_PRESETS[$activePresetKey])) ? PL_POINTS_PRESETS[$activePresetKey] : null;

$result = $activePreset ? pl_points_calculate($_SESSION['TourId'], $activePreset) : null;

function pl_points_html_row_label(array $row)
{
    return $row['name'] ?? $row['club_name'];
}

function pl_points_render_section_rows(array $rows, bool $isTeam, bool $isMixed)
{
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td style="text-align:center;">' . $row['rank'] . '</td>';
        if ($isMixed) {
            echo '<td>' . htmlspecialchars($row['club_name']) . '</td>';
            echo '<td>' . htmlspecialchars(pl_points_html_row_label($row)) . '</td>';
        } else {
            echo '<td>' . htmlspecialchars(pl_points_html_row_label($row)) . '</td>';
            echo '<td>' . htmlspecialchars($row['club_name']) . '</td>';
            echo '<td style="text-align:center;">' . ($isTeam ? '' : htmlspecialchars($row['code'])) . '</td>';
        }
        echo '<td style="text-align:center;">' . intval($row['place']) . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars(pl_points_format_number($row['points'])) . '</td>';
        echo '</tr>';
    }
}

function pl_points_render_separate_report(array $report)
{
    $subject = $report['subject'] ?? 'IND';
    $isMixed = $subject === 'MIXED';
    $isTeam = $subject !== 'IND';
    $colCount = $isMixed ? 5 : 6;

    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="' . $colCount . '">' . htmlspecialchars($report['label']) . '</th></tr>';
    foreach ($report['sections'] as $section) {
        if (empty(pl_points_scored_rows($section['rows']))) {
            continue;
        }
        echo '<tr><td colspan="' . $colCount . '" style="padding:6px;background:#e9ecef;font-weight:bold;">' . htmlspecialchars($section['label']) . '</td></tr>';
        if ($isMixed) {
            // No license number (a pair has two, not one); club identifies the
            // entry, "Skład" (roster) names the pair.
            echo '<tr><th>Miejsce</th><th>Klub</th><th>Skład</th><th>Miejsce w zawodach</th><th>Punkty</th></tr>';
        } else {
            echo '<tr><th>Miejsce</th><th>' . ($isTeam ? 'Zespół' : 'Zawodnik') . '</th><th>Klub</th><th>Nr licencji</th><th>Miejsce w zawodach</th><th>Punkty</th></tr>';
        }
        pl_points_render_section_rows(pl_points_scored_rows($section['rows']), $isTeam, $isMixed);
    }
    echo '</table><br>';
}

function pl_points_render_combined_report(array $report)
{
    // No overall "Miejsce" column: this table has no single winner-by-total
    // concept (only the per-classification place matters) — unlike Puchar
    // Polski's SEPARATE tables, which already rank within one classification.
    $classKeys = $report['classifications'];
    $colCount = 3 + count($classKeys) * 2 + 1;

    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="' . $colCount . '">' . htmlspecialchars($report['label']) . '</th></tr>';
    foreach ($report['sections'] as $section) {
        echo '<tr><td colspan="' . $colCount . '" style="padding:6px;background:#e9ecef;font-weight:bold;">' . htmlspecialchars($section['label']) . '</td></tr>';
        echo '<tr><th>Zawodnik</th><th>Klub</th><th>Nr licencji</th>';
        foreach ($classKeys as $ck) {
            $short = htmlspecialchars(pl_points_classification_short_label($ck));
            echo '<th>M. ' . $short . '</th><th>Pkt. ' . $short . '</th>';
        }
        echo '<th>Suma</th></tr>';

        foreach ($section['rows'] as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['club_name']) . '</td>';
            echo '<td style="text-align:center;">' . htmlspecialchars($row['code']) . '</td>';
            foreach ($classKeys as $ck) {
                $col = $row['columns'][$ck] ?? ['value' => 0, 'dropped' => false, 'place' => null];
                echo '<td style="text-align:center;">' . ($col['place'] !== null ? intval($col['place']) : '') . '</td>';
                if ($col['value'] <= 0) {
                    echo '<td style="text-align:center;"></td>';
                } elseif ($col['dropped']) {
                    echo '<td style="text-align:center;color:#999;"><span style="text-decoration:line-through;">'
                        . htmlspecialchars(pl_points_format_number($col['value'])) . '</span> 0</td>';
                } else {
                    echo '<td style="text-align:center;">' . htmlspecialchars(pl_points_format_number($col['value'])) . '</td>';
                }
            }
            echo '<td style="text-align:center;font-weight:bold;">' . htmlspecialchars(pl_points_format_number($row['points'])) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table><br>';
}

function pl_points_render_club_report(array $report)
{
    $showVoiv = !empty($report['show_voivodeship']);
    $colCount = $showVoiv ? 4 : 3;

    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="' . $colCount . '">' . htmlspecialchars($report['label']) . '</th></tr>';
    echo '<tr><th>Miejsce</th><th>Klub</th>' . ($showVoiv ? '<th>Województwo</th>' : '') . '<th>Suma</th></tr>';
    foreach ($report['rows'] as $row) {
        echo '<tr>';
        echo '<td style="text-align:center;">' . $row['rank'] . '</td>';
        echo '<td>' . htmlspecialchars($row['club_name']) . '</td>';
        if ($showVoiv) {
            echo '<td>' . htmlspecialchars($row['voivodeship']) . '</td>';
        }
        echo '<td style="text-align:center;font-weight:bold;">' . htmlspecialchars(pl_points_format_number($row['points'])) . '</td>';
        echo '</tr>';
    }
    echo '</table><br>';
}

function pl_points_render_voivodeship_report(array $report)
{
    echo '<table class="Tabella">';
    echo '<tr><th class="Title" colspan="3">' . htmlspecialchars($report['label']) . '</th></tr>';
    echo '<tr><th>Miejsce</th><th>Województwo</th><th>Suma</th></tr>';
    foreach ($report['rows'] as $row) {
        echo '<tr>';
        echo '<td style="text-align:center;">' . $row['rank'] . '</td>';
        echo '<td>' . htmlspecialchars($row['voivodeship']) . '</td>';
        echo '<td style="text-align:center;font-weight:bold;">' . htmlspecialchars(pl_points_format_number($row['points'])) . '</td>';
        echo '</tr>';
    }
    echo '</table><br>';
}

$PAGE_TITLE = 'Klasyfikacja punktowa';

include('Common/Templates/head.php');

echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Klasyfikacja punktowa</th></tr>';
echo '<tr><td colspan="2" style="padding:8px;">';
echo '<form method="post" action="">';
echo '<input type="hidden" name="selectPreset" value="1">';
echo 'Wybierz klasyfikację: ';
echo '<select name="PresetKey">';
echo '<option value=""' . ($activePresetKey ? '' : ' selected') . '>-- Brak --</option>';
foreach (PL_POINTS_PRESETS as $key => $preset) {
    $selected = ($key === $activePresetKey) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($preset['name']) . '</option>';
}
echo '</select> ';
echo '<input type="submit" value="Zastosuj">';
echo '</form>';
echo '</td></tr>';
echo '</table><br>';

if (!$activePreset) {
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
    echo 'Wybierz klasyfikację, aby zobaczyć wyniki.';
    echo '</div>';
    include('Common/Templates/tail.php');
    exit;
}

if (!empty($result['warnings'])) {
    echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
    foreach ($result['warnings'] as $warning) {
        echo '<div>' . htmlspecialchars($warning) . '</div>';
    }
    echo '</div>';
}

echo '<div style="padding:8px 0;">';
echo '<a href="PrnPointsRanking.php" target="_blank" style="font-weight:bold;">Generuj PDF</a>';

$hasClub = (bool) array_filter($activePreset['reports'], fn ($r) => $r['kind'] === 'CLUB');
$hasVoivodeship = (bool) array_filter($activePreset['reports'], fn ($r) => $r['kind'] === 'VOIVODESHIP');
if ($hasClub) {
    echo ' &nbsp; <a href="PrnPointsRankingDipl.php?Report=CLUB" target="_blank">Dyplomy - klasyfikacja klubowa</a>';
}
if ($hasVoivodeship) {
    echo ' &nbsp; <a href="PrnPointsRankingDipl.php?Report=VOIVODESHIP" target="_blank">Dyplomy - klasyfikacja województw</a>';
}
echo '</div>';

foreach ($result['reports'] as $report) {
    switch ($report['kind']) {
        case 'SEPARATE':
            pl_points_render_separate_report($report);
            break;
        case 'COMBINED':
            pl_points_render_combined_report($report);
            break;
        case 'CLUB':
            pl_points_render_club_report($report);
            break;
        case 'VOIVODESHIP':
            pl_points_render_voivodeship_report($report);
            break;
    }
}

include('Common/Templates/tail.php');
