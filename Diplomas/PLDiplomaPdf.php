<?php
/**
 * PLDiplomaPdf.php - Text-only diploma PDF class for the Polish Archery module.
 *
 * Extends TCPDF with empty Header/Footer and a printDiploma() helper method.
 * Portrait A4, no graphics.
 */

require_once('Common/tcpdf/tcpdf.php');

class PLDiplomaPdf extends TCPDF {

	/**
	 * Empty header - text-only diplomas.
	 */
	public function Header() {
		// intentionally empty
	}

	/**
	 * Empty footer - text-only diplomas.
	 */
	public function Footer() {
		// intentionally empty
	}

	/**
	 * Render a single diploma page.
	 *
	 * Template: "{NameSurname} {Club} za zajęcie {Place} w {TourName} w kategorii {ClassText}"
	 * Club is rendered below the name in smaller font.
	 * For teams: club name is shown prominently, with athlete names listed below.
	 *
	 * @param string $competitionName Name of the competition
	 * @param string $dates Competition dates
	 * @param string $location Competition location
	 * @param string $classText Event/class text (customizable per event)
	 * @param int $rank Place/rank number
	 * @param string $athleteName Full name of the athlete (individual) or empty for team
	 * @param string $clubName Club/country name
	 * @param array $teamMembers Array of team member names (for team diplomas)
	 * @param string $bodyText Optional additional text
	 * @param string $headJudge Name of head of judges
	 * @param string $organizer Name of the organizer
	 * @param string $titleText Pre-built title phrase e.g. "i zdobywa tytuł Mistrza Polski na rok 2026" (empty = no title line)
	 */
	public function printDiploma($competitionName, $dates, $location, $classText, $rank, $athleteName, $clubName, $teamMembers = array(), $bodyText = '', $headJudge = '', $organizer = '', $titleText = '') {
		$this->AddPage();

		$pageW = $this->getPageWidth();
		$margins = $this->getMargins();
		$contentW = $pageW - $margins['left'] - $margins['right'];

		// -- Top section: competition info --
		$this->SetY(100);


		$isTeam = !empty($teamMembers);

		if ($isTeam) {
			// Team diploma: club name prominent, then athlete names
			$this->SetFont('dejavusans', 'B', 26);
			$this->Cell($contentW, 10, $clubName, 0, 1, 'C');
			$this->Ln(2);

			// Team members
			$this->SetFont('dejavusans', '', 14);
			foreach ($teamMembers as $member) {
				$this->Cell($contentW, 7, $member, 0, 1, 'C');
			}
		} else {
			// Individual diploma: athlete name, club below
			$this->SetFont('dejavusans', 'B', 26);
			$this->Cell($contentW, 10, $athleteName, 0, 1, 'C');

			// Club below in smaller font
			$this->SetFont('dejavusans', '', 14);
			$this->Cell($contentW, 7, $clubName, 0, 1, 'C');
		}

		// "za zajęcie"
		$this->Ln(8);
		$this->SetFont('dejavusans', '', 14);
		$this->Cell($contentW, 8, mb_convert_encoding('za zajęcie', 'UTF-8', 'UTF-8'), 0, 1, 'C');

		// Rank (Polish ordinal + "miejsca")
		$this->SetFont('dejavusans', 'B', 24);
		$rankText = $this->_polishOrdinal($rank) . ' miejsca';
		$this->Cell($contentW, 12, $rankText, 0, 1, 'C');

		// "w {CompetitionName}"
		$this->Ln(3);
		$this->SetFont('dejavusans', '', 14);
		$this->Cell($contentW, 8, 'w ' . $competitionName, 0, 1, 'C');

		// "w kategorii {ClassText}"
		$this->Ln(2);
		$this->SetFont('dejavusans', '', 18);
		$this->Cell($contentW, 10, mb_convert_encoding('w kategorii ' . $classText, 'UTF-8', 'UTF-8'), 0, 1, 'C');

		// Title line for places 1–3 (e.g. "i zdobywa tytuł Mistrza Polski Seniorów na rok 2026")
		if (!empty($titleText)) {
			$this->Ln(6);
			$this->SetFont('dejavusans', 'I', 13);
			$this->Cell($contentW, 8, $titleText, 0, 1, 'C');
		}

		// Optional body text
		if (!empty($bodyText)) {
			$this->Ln(8);
			$this->SetFont('dejavusans', '', 12);
			$this->MultiCell($contentW, 6, $bodyText, 0, 'C');
		}


		// -- Bottom: signature lines --
		$signY = 220;
		$this->SetY($signY);
		$this->SetFont('dejavusans', '', 10);

		$halfW = $contentW / 2;
		$leftX = $margins['left'];
		$rightX = $margins['left'] + $halfW;

		// Signature lines
		$lineStyle = array('width' => 0.3, 'color' => array(0, 0, 0));
		$lineLen = 60;

		// Left: Sędzia główny
		$lineLX = $leftX + ($halfW - $lineLen) / 2;
		$this->Line($lineLX, $signY + 5, $lineLX + $lineLen, $signY + 5, $lineStyle);
		$this->SetXY($leftX, $signY + 5);
		$this->Cell($halfW, 5, mb_convert_encoding('Sędzia główny', 'UTF-8', 'UTF-8'), 0, 0, 'C');
		if (!empty($headJudge)) {
			$this->SetXY($leftX, $signY + 10);
			$this->Cell($halfW, 5, $headJudge, 0, 0, 'C');
		}

		// Right: Organizator
		$lineRX = $rightX + ($halfW - $lineLen) / 2;
		$this->Line($lineRX, $signY + 5, $lineRX + $lineLen, $signY + 5, $lineStyle);
		$this->SetXY($rightX, $signY + 5);
		$this->Cell($halfW, 5, 'Organizator', 0, 0, 'C');
		if (!empty($organizer)) {
			$this->SetXY($rightX, $signY + 10);
			$this->Cell($halfW, 5, $organizer, 0, 0, 'C');
		}

    $this->SetY(250);
    // Date and location at the bottom center
		$this->SetFont('dejavusans', '', 12);
    $this->Cell($contentW, 8, $location . ', ' . $dates, 0, 1, 'C');
	}

	/**
	 * Convert a number to a Polish ordinal string for diploma use.
	 * e.g., 1 => "I", 2 => "II", 3 => "III", 4 => "IV", etc.
	 *
	 * @param int $n The rank number
	 * @return string Polish ordinal representation
	 */
	private function _polishOrdinal($n) {
		// Use Roman numerals for places (common in Polish diplomas)
		$n = intval($n);
		$map = array(
			1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
			100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
			10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
		);
		$result = '';
		foreach ($map as $value => $numeral) {
			while ($n >= $value) {
				$result .= $numeral;
				$n -= $value;
			}
		}
		return $result;
	}

	/**
	 * Create and configure a new PLDiplomaPdf instance ready for use.
	 *
	 * @param string $title Document title
	 * @return PLDiplomaPdf Configured instance
	 */
	public static function createInstance($title = 'Dyplomy') {
		$pdf = new PLDiplomaPdf('P', 'mm', 'A4', true, 'UTF-8', false);

		$pdf->SetCreator('Ianseo');
		$pdf->SetAuthor('Ianseo - PL Module');
		$pdf->SetTitle($title);
		$pdf->SetSubject($title);
		$pdf->SetKeywords('Dyplomy, Ianseo, PL');

		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		$pdf->SetMargins(15, 15, 15);
		$pdf->SetHeaderMargin(0);
		$pdf->SetFooterMargin(0);

		$pdf->SetAutoPageBreak(false, 0);

		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$pdf->setFontSubsetting(true);

		$pdf->SetTextColor(0, 0, 0);

		return $pdf;
	}
}
