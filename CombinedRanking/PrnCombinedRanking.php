<?php
/**
 * PrnCombinedRanking.php — TCPDF PDF renderer for the combined ranking.
 *
 * Extends IanseoPdf (landscape A4). One section per division+class with a
 * two-row header (Dzień 1 / Dzień 2 spanning groups) and data rows.
 *
 * Public API:
 *   pl_combined_ranking_print($sections, $t1Name, $t2Name)
 */

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/Common/pdf/IanseoPdf.php';

class PLCombinedRankingPdf extends IanseoPdf
{
    // Column widths in mm (landscape A4, 277 mm content width; total = 277 mm).
    // 10 + 65 + 47 + 20 + 8×12 + 20 + 19 = 277
    const W_RANK    = 10;
    const W_NAME    = 65;
    const W_CLUB    = 47;
    const W_LIC     = 20;
    const W_CELL    = 12; // each of the 8 qual/elim sub-columns
    const W_BEST    = 20;
    const W_TOTAL   = 19;

    // Row heights.
    const H_TITLE   = 7;
    const H_HEADER  = 5;
    const H_DATA    = 4.5;

    /**
     * Render one division+class section.
     *
     * @param array  $section  Section data from pl_combined_ranking_compute()
     * @param string $t1Name   Tournament 1 name (Day 1 label)
     * @param string $t2Name   Tournament 2 name (Day 2 label), empty when not used
     */
    public function renderSection(array $section)
    {

        // ── Title row ────────────────────────────────────────────────────────
        $this->SetFont($this->FontStd, 'B', 11);
        $this->SetFillColor(0xCC, 0xCC, 0xCC);
        $totalWidth = self::W_RANK + self::W_NAME + self::W_CLUB + self::W_LIC
                    + 8 * self::W_CELL + self::W_BEST + self::W_TOTAL;
        $this->Cell($totalWidth, self::H_TITLE, $section['label'], 1, 1, 'C', true);

        // ── Header row 1: spanning group labels ──────────────────────────────
        $this->SetFont($this->FontStd, 'B', 6);
        $this->SetFillColor(0xE0, 0xE0, 0xE0);

        // Two-row header with simulated rowspan via SetXY().
        // Fixed columns (Miejsce, Imię Nazwisko, Klub, Nr licencji) and trailing
        // columns (Najl. 2x70m, Łącznie pkt) span both rows (height = 2×H_HEADER).
        // Dzień 1 / Dzień 2 group labels occupy only row 1 (height = H_HEADER).
        // Sub-column labels occupy only row 2, rendered via SetXY jump.

        $startY  = $this->GetY();
        $startX  = $this->GetX();
        $doubleH = self::H_HEADER * 2;
        $dayWidth = 4 * self::W_CELL;

        // ── Row 1: fixed spanning columns ────────────────────────────────────
        $this->Cell(self::W_RANK, $doubleH, 'Miejsce',       1, 0, 'C', true);
        $this->Cell(self::W_NAME, $doubleH, 'Imię Nazwisko', 1, 0, 'C', true);
        $this->Cell(self::W_CLUB, $doubleH, 'Klub',          1, 0, 'C', true);
        $this->Cell(self::W_LIC,  $doubleH, 'Nr licencji',   1, 0, 'C', true);

        // Save X where Dzień 1 starts (used to jump back for row 2 sub-headers).
        $midX = $this->GetX();

        // ── Row 1: group labels (single height) ──────────────────────────────
        $this->Cell($dayWidth, self::H_HEADER, 'Dzień 1', 1, 0, 'C', true);
        $this->Cell($dayWidth, self::H_HEADER, 'Dzień 2', 1, 0, 'C', true);

        // ── Row 1: trailing spanning columns ─────────────────────────────────
        $this->Cell(self::W_BEST,  $doubleH, 'Najl. 2x70m', 1, 0, 'C', true);
        $this->Cell(self::W_TOTAL, $doubleH, 'Łącznie pkt',  1, 0, 'C', true);

        // ── Row 2: jump back to sub-column area and render sub-headers ────────
        $this->SetXY($midX, $startY + self::H_HEADER);

        $this->Cell(self::W_CELL, self::H_HEADER, 'Kwal Msc', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Kwal Pkt', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Elim Msc', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Elim Pkt', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Kwal Msc', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Kwal Pkt', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Elim Msc', 1, 0, 'C', true);
        $this->Cell(self::W_CELL, self::H_HEADER, 'Elim Pkt', 1, 0, 'C', true);

        // Advance cursor past the full double-height header area.
        $this->SetXY($startX, $startY + $doubleH);

        // ── Data rows ─────────────────────────────────────────────────────────
        $this->SetFillColor(0xFF, 0xFF, 0xFF);
        $altFill = false;

        foreach ($section['rows'] as $row) {
            // Shade alternate rows lightly.
            if ($altFill) {
                $this->SetFillColor(0xF5, 0xF5, 0xF5);
            } else {
                $this->SetFillColor(0xFF, 0xFF, 0xFF);
            }
            $altFill = !$altFill;

            $fill = true;

            // Rank (bold)
            $this->SetFont($this->FontStd, 'B', 8);
            $this->Cell(self::W_RANK, self::H_DATA, $row['rank'], 1, 0, 'C', $fill);

            // Name, Club, Licence
            $this->SetFont($this->FontStd, '', 8);
            $this->Cell(self::W_NAME, self::H_DATA, $row['name'],    1, 0, 'L', $fill);
            $this->Cell(self::W_CLUB, self::H_DATA, $row['club'],    1, 0, 'L', $fill);
            $this->SetFont($this->FontFix, '', 7);
            $this->Cell(self::W_LIC,  self::H_DATA, $row['licence'], 1, 0, 'C', $fill);

            // Day 1 qual place / pts
            $this->SetFont($this->FontStd, '', 8);
            $this->Cell(self::W_CELL, self::H_DATA,
                $row['d1_qual_place'] !== null ? $row['d1_qual_place'] : '',
                1, 0, 'C', $fill);
            $this->Cell(self::W_CELL, self::H_DATA, $row['d1_qual_pts'], 1, 0, 'C', $fill);

            // Day 1 elim place / pts (blank place when not in bracket)
            $this->Cell(self::W_CELL, self::H_DATA,
                $row['d1_elim_place'] !== null ? $row['d1_elim_place'] : '',
                1, 0, 'C', $fill);
            $this->Cell(self::W_CELL, self::H_DATA, $row['d1_elim_pts'], 1, 0, 'C', $fill);

            // Day 2 qual place / pts
            $this->Cell(self::W_CELL, self::H_DATA,
                $row['d2_qual_place'] !== null ? $row['d2_qual_place'] : '',
                1, 0, 'C', $fill);
            $this->Cell(self::W_CELL, self::H_DATA, $row['d2_qual_pts'], 1, 0, 'C', $fill);

            // Day 2 elim place / pts
            $this->Cell(self::W_CELL, self::H_DATA,
                $row['d2_elim_place'] !== null ? $row['d2_elim_place'] : '',
                1, 0, 'C', $fill);
            $this->Cell(self::W_CELL, self::H_DATA, $row['d2_elim_pts'], 1, 0, 'C', $fill);

            // Best 2x70m (tiebreaker) and total
            $this->SetFont($this->FontFix, '', 8);
            $this->Cell(self::W_BEST, self::H_DATA,
                $row['best_2x70m'] !== null ? $row['best_2x70m'] : '',
                1, 0, 'R', $fill);
            $this->SetFont($this->FontStd, 'B', 8);
            $this->Cell(self::W_TOTAL, self::H_DATA, $row['total_pts'], 1, 1, 'R', $fill);
        }
    }
}

/**
 * Generate and stream the combined ranking PDF to the browser.
 *
 * @param array  $sections Array from pl_combined_ranking_compute()
 * @param string $t1Name   Tournament 1 name
 * @param string $t2Name   Tournament 2 name (empty when not used)
 */
function pl_combined_ranking_print(array $sections, $t1Name, $t2Name) {
    $title = 'Ranking łączony';
    if ($t1Name !== '') $title .= ' — ' . $t1Name;
    if ($t2Name !== '') $title .= ' / ' . $t2Name;

    $pdf = new PLCombinedRankingPdf($title, false /* landscape */);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->startPageGroup();

    $firstSection = true;
    foreach ($sections as $section) {
        if (!empty($section['rows'])) {
            $pdf->AddPage();
            $firstSection = false;
            $pdf->renderSection($section);
        }
    }

    // If nothing to render, add a blank page with a message.
    if ($firstSection) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Brak danych do wyświetlenia.', 0, 1, 'C');
    }

    $pdf->Output('ranking_laczony.pdf', 'D');
}
