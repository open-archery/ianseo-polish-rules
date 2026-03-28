<?php
/**
 * CombinedRanking.php — Cross-tournament combined ranking page.
 *
 * GET  — shows the form (pre-selects tour1/tour2 from query string if present)
 * POST (qf_save=1) — saves 10/X/9 QF counts, PRG redirect back to GET
 * POST (main form) — computes ranking; streams PDF if no unresolved QF ties,
 *                    otherwise re-renders the form with a warning + entry form
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once dirname(__FILE__) . '/Fun_CombinedRanking.php';
require_once dirname(__FILE__) . '/PrnCombinedRanking.php';

pl_combined_ranking_install_qf_table();

$currentTourId = (int)$_SESSION['TourId'];
$tournaments   = pl_combined_ranking_get_tournaments();

// State for the view
$error      = '';
$ties       = [];        // unresolved QF ties (non-empty → show warning)
$qfSaved    = isset($_GET['qf_saved']);
$selectedT1 = isset($_GET['tour1']) ? (int)$_GET['tour1'] : $currentTourId;
$selectedT2 = isset($_GET['tour2']) ? (int)$_GET['tour2'] : 0;

// ─── Handle QF count save (PRG) ───────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['qf_save'])) {
    $t1 = isset($_POST['tour1']) ? (int)$_POST['tour1'] : 0;
    $t2 = isset($_POST['tour2']) ? (int)$_POST['tour2'] : 0;

    foreach (($_POST['qf_arrows'] ?? []) as $tourId => $byCode) {
        $tourId = (int)$tourId;
        if ($tourId <= 0) continue;
        foreach ($byCode as $enCode => $arrows) {
            if ($enCode === '' || !is_numeric($arrows)) continue;
            pl_combined_ranking_save_qf_count($tourId, $enCode, (int)$arrows);
        }
    }

    $redirect = $_SERVER['PHP_SELF']
        . '?tour1=' . $t1
        . ($t2 > 0 ? '&tour2=' . $t2 : '')
        . '&qf_saved=1';
    header('Location: ' . $redirect);
    exit;
}

// ─── Handle main form POST (generate PDF) ─────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t1 = isset($_POST['tour1']) && $_POST['tour1'] !== '' ? (int)$_POST['tour1'] : 0;
    $t2 = isset($_POST['tour2']) && $_POST['tour2'] !== '' ? (int)$_POST['tour2'] : 0;
    $selectedT1 = $t1;
    $selectedT2 = $t2;
    $forcePdf   = !empty($_POST['force_pdf']);

    if ($t1 === 0) {
        $error = 'Należy wybrać co najmniej jeden turniej (Dzień 1).';
    } else {
        $data1 = pl_combined_ranking_load($t1);
        $data2 = ($t2 > 0) ? pl_combined_ranking_load($t2) : [];

        $merged   = pl_combined_ranking_merge($data1, $data2);
        $labels   = pl_combined_ranking_get_div_labels($t1);
        $sections = pl_combined_ranking_compute($merged, $labels);

        $t1Name = '';
        $t2Name = '';
        foreach ($tournaments as $t) {
            if ($t['ToId'] === $t1) $t1Name = $t['ToName'];
            if ($t2 > 0 && $t['ToId'] === $t2) $t2Name = $t['ToName'];
        }

        $qfCounts = pl_combined_ranking_load_qf_counts($t1, $t2);
        $sections = pl_combined_ranking_apply_qf_counts($sections, $qfCounts, $t1, $t2);
        $ties     = pl_combined_ranking_detect_qf_ties($sections, $qfCounts, $t1, $t2, $t1Name, $t2Name);

        if (empty($ties) || $forcePdf) {
            // Build set of licences with unresolved ties for PDF marker
            $unresolvedLicences = [];
            foreach ($ties as $tie) {
                $unresolvedLicences[$tie['licence']] = true;
            }
            pl_combined_ranking_print($sections, $t1Name, $t2Name, $unresolvedLicences);
            exit;
        }
        // Falls through to render form with warning banner
    }
}

// ─── Render the form ──────────────────────────────────────────────────────────

$PAGE_TITLE = 'Ranking łączony';
require_once 'Common/Templates/head.php';
?>

<h2>Ranking łączony</h2>
<p>Wybierz turnieje, z których zostanie wygenerowany ranking łączony. Turniej 2 jest opcjonalny.</p>

<?php if ($error !== ''): ?>
    <p style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($qfSaved): ?>
    <p style="color: green; font-weight: bold;">Dane 10/X/9 zostały zapisane.</p>
<?php endif; ?>

<form method="POST" action="">
    <table>
        <tr>
            <td><strong>Dzień 1 (Turniej 1):</strong></td>
            <td>
                <select name="tour1" style="min-width: 350px;">
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['ToId'] ?>"
                            <?= ($t['ToId'] === $selectedT1) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['ToName']) ?>
                            (<?= htmlspecialchars(substr($t['ToWhenFrom'], 0, 10)) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><strong>Dzień 2 (Turniej 2):</strong></td>
            <td>
                <select name="tour2" style="min-width: 350px;">
                    <option value="">— brak —</option>
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['ToId'] ?>"
                            <?= ($t['ToId'] === $selectedT2) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['ToName']) ?>
                            (<?= htmlspecialchars(substr($t['ToWhenFrom'], 0, 10)) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top: 12px;">
                <input type="submit" value="Generuj PDF">
            </td>
        </tr>
    </table>
</form>

<?php if (!empty($ties)): ?>
<div style="margin-top: 20px; padding: 12px 16px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
    <strong>&#9888; Remis w 1/4 finału (Compound) — wymagane dane 10/X/9</strong>
    <p style="margin: 8px 0 4px;">Poniższe zawodniczki/zawodnicy uzyskali ten sam wynik w 1/4 finału.
    Wprowadź liczbę strzał 10/X/9 z danego pojedynku, aby rozstrzygnąć kolejność miejsc.</p>

    <form method="POST" action="">
        <input type="hidden" name="qf_save" value="1">
        <input type="hidden" name="tour1" value="<?= $selectedT1 ?>">
        <input type="hidden" name="tour2" value="<?= $selectedT2 ?>">

        <table style="border-collapse: collapse; margin-top: 8px;">
            <tr style="background: #e2e2e2; font-weight: bold;">
                <th style="padding: 4px 10px; text-align: left;">Zawodnik</th>
                <th style="padding: 4px 10px; text-align: left;">Turniej</th>
                <th style="padding: 4px 10px; text-align: center;">Miejsce (1/4)</th>
                <th style="padding: 4px 10px; text-align: center;">Liczba 10/X/9</th>
            </tr>
            <?php foreach ($ties as $tie): ?>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 4px 10px;"><?= htmlspecialchars($tie['athlete']) ?></td>
                <td style="padding: 4px 10px;"><?= htmlspecialchars($tie['tourName']) ?></td>
                <td style="padding: 4px 10px; text-align: center;"><?= (int)$tie['place'] ?></td>
                <td style="padding: 4px 10px; text-align: center;">
                    <?php if ($tie['has_count']): ?>
                        <input type="number" min="0" max="30"
                               name="qf_arrows[<?= (int)$tie['tourId'] ?>][<?= htmlspecialchars($tie['licence']) ?>]"
                               value="<?= (int)$tie['count'] ?>"
                               style="width: 60px;">
                    <?php else: ?>
                        <input type="number" min="0" max="30"
                               name="qf_arrows[<?= (int)$tie['tourId'] ?>][<?= htmlspecialchars($tie['licence']) ?>]"
                               placeholder="—"
                               style="width: 60px;">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div style="margin-top: 10px;">
            <input type="submit" value="Zapisz dane 10/X/9">
        </div>
    </form>

    <form method="POST" action="" style="margin-top: 8px;">
        <input type="hidden" name="tour1" value="<?= $selectedT1 ?>">
        <input type="hidden" name="tour2" value="<?= $selectedT2 ?>">
        <input type="hidden" name="force_pdf" value="1">
        <input type="submit" value="Generuj PDF mimo nierozstrzygniętego remisu"
               style="background: #ccc; color: #333;">
    </form>
</div>
<?php endif; ?>

<hr>

<h3>Dyplomy rankingu łączonego</h3>
<p>Wybierz te same turnieje co powyżej. Data na dyplomie jest niezależna od konfiguracji dyplomów.</p>

<form method="POST" action="PrnCombinedRankingDipl.php">
    <table>
        <tr>
            <td><strong>Dzień 1 (Turniej 1):</strong></td>
            <td>
                <select name="tour1" style="min-width: 350px;">
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['ToId'] ?>"
                            <?= ($t['ToId'] === $selectedT1) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['ToName']) ?>
                            (<?= htmlspecialchars(substr($t['ToWhenFrom'], 0, 10)) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><strong>Dzień 2 (Turniej 2):</strong></td>
            <td>
                <select name="tour2" style="min-width: 350px;">
                    <option value="">— brak —</option>
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['ToId'] ?>"
                            <?= ($t['ToId'] === $selectedT2) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['ToName']) ?>
                            (<?= htmlspecialchars(substr($t['ToWhenFrom'], 0, 10)) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top: 12px;">
                <input type="submit" value="Generuj dyplomy">
            </td>
        </tr>
    </table>
</form>

<?php require_once 'Common/Templates/tail.php'; ?>
