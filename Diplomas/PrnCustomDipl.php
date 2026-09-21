<?php
/**
 * PrnCustomDipl.php - Generate a single custom diploma PDF.
 *
 * GET parameters:
 *   athleteId  - Entry ID of the athlete (optional, for DB lookup)
 *   athleteName - Manual athlete name (used if athleteId is empty)
 *   clubName   - Manual club name (used if athleteId is empty)
 *   eventCode  - Event code (for class text lookup)
 *   rank       - Place/rank number
 *   customText - Custom body text (replaces default body text)
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('DiplomaSetup.php');
require_once('Fun_Diploma.php');
require_once('PLDiplomaPdf.php');

// Ensure tables exist
pl_diploma_ensure_tables();

// Fetch config
$config = pl_diploma_get_config($_SESSION['TourId']);
$eventTexts = pl_diploma_get_event_texts($_SESSION['TourId']);

// Parse request
$athleteId = isset($_GET['athleteId']) ? intval($_GET['athleteId']) : 0;
$athleteName = isset($_GET['athleteName']) ? substr($_GET['athleteName'], 0, 255) : '';
$clubName = isset($_GET['clubName']) ? substr($_GET['clubName'], 0, 255) : '';
$eventCode = isset($_GET['eventCode']) ? substr($_GET['eventCode'], 0, 15) : '';
$rank = isset($_GET['rank']) ? intval($_GET['rank']) : 1;
$customText = isset($_GET['customText']) ? substr($_GET['customText'], 0, 1000) : '';

// If athleteId is provided, look up athlete details from DB
if ($athleteId > 0) {
	$athlete = pl_diploma_get_athlete($athleteId);
	if ($athlete) {
		$athleteName = $athlete['EnFullName'];
		$clubName = $athlete['CoName'];
	}
}

// Validate required fields
if (empty($athleteName)) {
	die('Nie podano nazwiska zawodnika.');
}
if ($rank < 1) {
	die('Nieprawidłowe miejsce.');
}

// Determine event type ('I'/'T'/'M') from the composite event code's prefix,
// the same convention used everywhere else in this module — no event
// selected (manual athlete entry) defaults to individual.
$evType = 'I';
if (strlen($eventCode) > 2 && $eventCode[1] === ':') {
	$evType = $eventCode[0];
}
$rawEventCode = pl_diploma_raw_event_code($eventCode);
$eventTextRow = isset($eventTexts[$eventCode]) ? $eventTexts[$eventCode] : array();

$categoryLine = pl_diploma_resolve_category_line($rawEventCode, $evType, $eventTextRow);

// Build title phrase if titles are enabled, same as the batch diploma printouts
$titleText = '';
if ($config['TitlesEnabled'] && $rawEventCode !== '') {
	$titleYear = pl_diploma_extract_year($config['Dates']);
	$titleText = pl_diploma_build_title(
		$rank,
		isset($eventTextRow['titlePrefix']) ? $eventTextRow['titlePrefix'] : '',
		isset($eventTextRow['titleText']) ? $eventTextRow['titleText'] : '',
		$titleYear,
		$evType !== 'I',   // team or mixed
		$evType === 'M'    // mixed adds "w mikście", suppresses "Zespołowego"
	);
}

// Use custom text as body text override
$bodyText = !empty($customText) ? $customText : $config['BodyText'];

// Generate PDF
$pdf = PLDiplomaPdf::createInstance('Dyplom indywidualny');

$pdf->printDiploma(
	$config['CompetitionName'],
	$config['Dates'],
	$config['Location'],
	$categoryLine,
	$rank,
	$athleteName,
	$clubName,
	array(),
	$bodyText,
	$config['HeadJudge'],
	$config['Organizer'],
	$titleText
);

$pdf->Output('dyplom.pdf', 'I');
