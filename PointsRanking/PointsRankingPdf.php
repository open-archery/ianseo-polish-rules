<?php
/**
 * PointsRankingPdf.php - Tabular report PDF class for points-ranking output.
 *
 * Extends the core IanseoPdf (standard tournament header with logos, page
 * numbers and version footer — same base every other ianseo report PDF
 * uses) rather than TCPDF directly, so the output matches the rest of
 * ianseo. Table styling (grey title bar, grey column headers, alternating
 * row fill, bordered cells) follows CupRanking/PrnCupRanking.php.
 */

// NOTE: 5 dirname() calls, not 4 — unlike config.php (which this module's
// other files reach via Modules/config.php, a proxy shim that forwards up
// one more level), there is no such shim for Common/pdf/. Verified against
// the actual container filesystem after a require_once here 404'd silently.
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/Common/pdf/IanseoPdf.php');

class PointsRankingPdf extends IanseoPdf
{
    const H_TITLE = 7;
    const H_HEADER = 5;
    const H_DATA = 4.5;

    /**
     * Render one table: a full-width title bar, a shaded column-header row,
     * then bordered, alternately-filled data rows.
     *
     * @param string $title
     * @param array $columns [['label' => string, 'width' => float, 'align' => 'L'|'C'|'R', 'bold' => bool]]
     * @param array $rows list of cell-value arrays, same order as $columns. A cell
     *   may be a plain string, or ['dropped' => string] — a value that was cut off
     *   or excluded from the sum, rendered struck through with a plain 0 beside it.
     */
    public function renderTable($title, array $columns, array $rows)
    {
        $totalWidth = array_sum(array_column($columns, 'width'));
        $last = count($columns) - 1;

        $this->SetFont($this->FontStd, 'B', 11);
        $this->SetFillColor(0xCC, 0xCC, 0xCC);
        $this->Cell($totalWidth, self::H_TITLE, $title, 1, 1, 'C', true);

        $this->SetFont($this->FontStd, 'B', 8);
        $this->SetFillColor(0xE0, 0xE0, 0xE0);
        foreach ($columns as $i => $col) {
            $this->Cell($col['width'], self::H_HEADER, $col['label'], 1, $i === $last ? 1 : 0, 'C', true);
        }

        $altFill = false;
        foreach ($rows as $row) {
            $shade = $altFill ? 0xF5 : 0xFF;
            $this->SetFillColor($shade, $shade, $shade);
            $altFill = !$altFill;

            foreach ($columns as $i => $col) {
                $cell = $row[$i] ?? '';
                $ln = $i === $last ? 1 : 0;

                if (is_array($cell) && isset($cell['dropped'])) {
                    $rawW = $col['width'] * 0.62;
                    $zeroW = $col['width'] - $rawW;
                    $this->SetFont($this->FontStd, 'D', 8); // D = strikeout
                    $this->Cell($rawW, self::H_DATA, $cell['dropped'], 'LTB', 0, 'R', true);
                    $this->SetFont($this->FontStd, '', 8);
                    $this->Cell($zeroW, self::H_DATA, '0', 'RTB', $ln, 'L', true);
                    continue;
                }

                $this->SetFont($this->FontStd, !empty($col['bold']) ? 'B' : '', 8);
                $this->Cell($col['width'], self::H_DATA, $cell, 1, $ln, $col['align'] ?? 'L', true);
            }
        }
        $this->Ln(3);
    }
}
