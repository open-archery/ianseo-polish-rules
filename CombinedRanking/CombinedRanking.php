<?php
/**
 * CombinedRanking.php — Cross-tournament combined ranking page.
 *
 * Displays a form with two tournament selects (Tournament 1 pre-selected with the
 * current session tournament, Tournament 2 optional). On POST, generates and
 * streams a PDF ranking.
 *
 * GET  — shows the form
 * POST — validates, generates PDF (or shows error and re-renders form)
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once dirname(__FILE__) . '/Fun_CombinedRanking.php';
require_once dirname(__FILE__) . '/PrnCombinedRanking.php';

$currentTourId = (int)$_SESSION['TourId'];
$tournaments   = pl_combined_ranking_get_tournaments();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t1 = isset($_POST['tour1']) && $_POST['tour1'] !== '' ? (int)$_POST['tour1'] : 0;
    $t2 = isset($_POST['tour2']) && $_POST['tour2'] !== '' ? (int)$_POST['tour2'] : 0;

    if ($t1 === 0) {
        $error = 'Należy wybrać co najmniej jeden turniej (Dzień 1).';
    } else {
        // Load data from each tournament.
        $data1 = pl_combined_ranking_load($t1);
        $data2 = ($t2 > 0) ? pl_combined_ranking_load($t2) : [];

        $merged   = pl_combined_ranking_merge($data1, $data2);
        $labels   = pl_combined_ranking_get_div_labels($t1);
        $sections = pl_combined_ranking_compute($merged, $labels);

        // Resolve tournament names for the PDF header.
        $t1Name = '';
        $t2Name = '';
        foreach ($tournaments as $t) {
            if ($t['ToId'] === $t1) $t1Name = $t['ToName'];
            if ($t2 > 0 && $t['ToId'] === $t2) $t2Name = $t['ToName'];
        }

        // Stream PDF — nothing else is sent to the browser.
        pl_combined_ranking_print($sections, $t1Name, $t2Name);
        exit;
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

<form method="POST" action="">
    <table>
        <tr>
            <td><strong>Dzień 1 (Turniej 1):</strong></td>
            <td>
                <select name="tour1" style="min-width: 350px;">
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int)$t['ToId'] ?>"
                            <?= ($t['ToId'] === $currentTourId) ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$t['ToId'] ?>">
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
                            <?= ($t['ToId'] === $currentTourId) ? 'selected' : '' ?>>
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
                        <option value="<?= (int)$t['ToId'] ?>">
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
