<?php
/**
 * PrnCupRanking.php — TCPDF PDF renderer for the Puchar Polski cup ranking.
 *
 * Portrait A4, one section per division+class. Content width: 180 mm.
 *
 * Public API:
 *   pl_cup_ranking_print($sections, $tourName)
 */

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/Common/pdf/IanseoPdf.php';

class PLCupRankingPdf extends IanseoPdf
{
    // Column widths in mm (portrait A4, 180 mm content width).
    // 12 + 61 + 50 + 22 + 14 + 21 = 180
    const W_RANK    = 12;
    const W_NAME    = 61;
    const W_CLUB    = 50;
    const W_LIC     = 22;
    const W_YEAR    = 14;
    const W_POINTS  = 21;

    // Row heights.
    const H_TITLE   = 7;
    const H_HEADER  = 5;
    const H_DATA    = 4.5;

    /**
     * Render one division+class section.
     *
     * @param array $section Section data from pl_cup_ranking_compute()
     */
    public function renderSection(array $section) {
        $totalWidth = self::W_RANK + self::W_NAME + self::W_CLUB
                    + self::W_LIC + self::W_YEAR + self::W_POINTS;

        // ── Title row ────────────────────────────────────────────────────────
        $this->SetFont($this->FontStd, 'B', 11);
        $this->SetFillColor(0xCC, 0xCC, 0xCC);
        $this->Cell($totalWidth, self::H_TITLE, $section['label'], 1, 1, 'C', true);

        // ── Header row ───────────────────────────────────────────────────────
        $this->SetFont($this->FontStd, 'B', 8);
        $this->SetFillColor(0xE0, 0xE0, 0xE0);
        $this->Cell(self::W_RANK,   self::H_HEADER, 'Lp.',          1, 0, 'C', true);
        $this->Cell(self::W_NAME,   self::H_HEADER, 'Imię Nazwisko',1, 0, 'C', true);
        $this->Cell(self::W_CLUB,   self::H_HEADER, 'Klub',         1, 0, 'C', true);
        $this->Cell(self::W_LIC,    self::H_HEADER, 'Nr licencji',  1, 0, 'C', true);
        $this->Cell(self::W_YEAR,   self::H_HEADER, 'Rok ur.',      1, 0, 'C', true);
        $this->Cell(self::W_POINTS, self::H_HEADER, 'Punkty',       1, 1, 'C', true);

        // ── Data rows ────────────────────────────────────────────────────────
        $altFill = false;
        foreach ($section['rows'] as $row) {
            $this->SetFillColor($altFill ? 0xF5 : 0xFF, $altFill ? 0xF5 : 0xFF, $altFill ? 0xF5 : 0xFF);
            $altFill = !$altFill;

            $this->SetFont($this->FontStd, 'B', 8);
            $this->Cell(self::W_RANK, self::H_DATA, $row['rank'], 1, 0, 'C', true);

            $this->SetFont($this->FontStd, '', 8);
            $this->Cell(self::W_NAME, self::H_DATA, $row['name'], 1, 0, 'L', true);
            $this->Cell(self::W_CLUB, self::H_DATA, $row['club'], 1, 0, 'L', true);

            $this->SetFont($this->FontFix, '', 7);
            $this->Cell(self::W_LIC, self::H_DATA, $row['licence'], 1, 0, 'C', true);

            $this->SetFont($this->FontStd, '', 8);
            $this->Cell(self::W_YEAR, self::H_DATA, $row['birth_year'], 1, 0, 'C', true);

            $this->SetFont($this->FontStd, 'B', 8);
            $this->Cell(self::W_POINTS, self::H_DATA, $row['points'], 1, 1, 'C', true);
        }
    }
}

/**
 * Generate and stream the Puchar Polski cup ranking PDF to the browser.
 *
 * @param array  $sections  Array from pl_cup_ranking_compute()
 * @param string $tourName  Tournament name shown in the PDF title
 */
function pl_cup_ranking_print(array $sections, $tourName) {
    $title = 'Ranking Pucharu Polski';
    if ($tourName !== '') $title .= ' — ' . $tourName;

    $pdf = new PLCupRankingPdf($title, true /* portrait */);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->startPageGroup();

    $hasContent = false;
    foreach ($sections as $section) {
        if (!empty($section['rows'])) {
            $pdf->AddPage();
            $hasContent = true;
            $pdf->renderSection($section);
        }
    }

    if (!$hasContent) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Brak danych do wyświetlenia.', 0, 1, 'C');
    }

    $pdf->Output('ranking_pucharu_polski.pdf', 'I');
}
