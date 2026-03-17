<?php
/**
 * PrnTeamDipl.php - Generate team and mixed team diploma PDFs.
 *
 * GET parameters:
 *   Event[]  - selected event codes
 *   Source   - 'qualification' or 'finals'
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
$ThisEvents = isset($_GET['Event']) ? $_GET['Event'] : array();
$source = isset($_GET['Source']) ? $_GET['Source'] : 'qualification';

// Sanitize event codes (composite format: I:CODE, T:CODE, M:CODE)
foreach ($ThisEvents as &$TempEvent) {
	$TempEvent = substr($TempEvent, 0, 15);
}
unset($TempEvent);

// Catch 'all' events
if (count($ThisEvents) == 1 && $ThisEvents[0] == '.') {
	$ThisEvents = array();
}

// Get place range from config
$placeFrom = $config['PlaceFrom'];
$placeTo = $config['PlaceTo'];

// Fetch results based on source
if ($source === 'finals') {
	$results = pl_diploma_get_team_final_results($ThisEvents, $placeFrom, $placeTo);
} else {
	$results = pl_diploma_get_team_qual_results($ThisEvents, $placeFrom, $placeTo);
}

// Check if there are results
if (empty($results)) {
	die('Brak wyników do wygenerowania dyplomów.');
}

// Pre-compute title year once
$titleYear = pl_diploma_extract_year($config['Dates']);

// Generate PDF
$pdf = PLDiplomaPdf::createInstance('Dyplomy drużynowe');

foreach ($results as $team) {
	$isMixed = ($team['IsMixed'] == 1);
	$evType  = $isMixed ? 'M' : 'T';
	$compositeKey = $evType . ':' . $team['EventId'];

	// Determine class text: custom override or default event name
	$classText = $team['EventName'];
	if (isset($eventTexts[$compositeKey]) && !empty($eventTexts[$compositeKey]['customText'])) {
		$classText = $eventTexts[$compositeKey]['customText'];
	}

	// Build title phrase if titles are enabled
	$titleText = '';
	if ($config['TitlesEnabled']) {
		$evData = isset($eventTexts[$compositeKey])
			? $eventTexts[$compositeKey]
			: array('titlePrefix' => '', 'titleText' => '');
		$titleText = pl_diploma_build_title(
			$team['Rank'],
			$evData['titlePrefix'],
			$evData['titleText'],
			$titleYear,
			true,     // team event
			$isMixed  // mixed adds "w mikście", suppresses "Zespołowego"
		);
	}

	// Build team member names array
	$memberNames = array();
	foreach ($team['Athletes'] as $athlete) {
		$memberNames[] = $athlete['EnFullName'];
	}

	$pdf->printDiploma(
		$config['CompetitionName'],
		$config['Dates'],
		$config['Location'],
		$classText,
		$team['Rank'],
		'',                // no individual athlete name for team
		$team['Club'],
		$memberNames,
		$config['BodyText'],
		$config['HeadJudge'],
		$config['Organizer'],
		$titleText
	);
}

$pdf->Output('dyplomy_druzynowe.pdf', 'I');
