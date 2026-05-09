<?php
/**
 * CupRanking.php — Puchar Polski cup ranking page.
 *
 * GET  — renders a page with a "Generuj PDF" button.
 * POST — computes the ranking from the current session tournament and streams the PDF.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once dirname(__FILE__) . '/Fun_CupRanking.php';
require_once dirname(__FILE__) . '/PrnCupRanking.php';

// ─── Handle PDF generation (POST) ────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tourId   = (int)$_SESSION['TourId'];
    $tourName = (string)($_SESSION['TourName'] ?? '');

    $rows     = pl_cup_ranking_load($tourId);
    $labels   = pl_cup_ranking_get_div_labels($tourId);
    $sections = pl_cup_ranking_compute($rows, $labels);

    pl_cup_ranking_print($sections, $tourName);
    exit;
}

// ─── Render the form (GET) ───────────────────────────────────────────────────

$PAGE_TITLE = 'Ranking Pucharu Polski';
require_once 'Common/Templates/head.php';
?>

<h2>Ranking Pucharu Polski</h2>
<p>Generuje ranking pomocniczy IORWA dla bieżącego turnieju na podstawie miejsc zdobytych w rundzie eliminacyjnej.</p>

<form method="POST" action="" target="_blank">
    <input type="submit" value="Generuj PDF">
</form>

<?php require_once 'Common/Templates/tail.php'; ?>
