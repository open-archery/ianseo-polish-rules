<?php
/**
 * PrnIndividualDipl.php - Generate individual diploma PDFs.
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
	$results = pl_diploma_get_ind_final_results($ThisEvents, $placeFrom, $placeTo);
} else {
	$results = pl_diploma_get_ind_qual_results($ThisEvents, $placeFrom, $placeTo);
}

// Check if there are results
if (empty($results)) {
	die('Brak wyników do wygenerowania dyplomów.');
}

// Generate PDF
$pdf = PLDiplomaPdf::createInstance('Dyplomy indywidualne');

foreach ($results as $individual) {
	// Determine class text: custom override or default event name
	$classText = $individual['EvEventName'];
	$compositeKey = 'I:' . $individual['IndEvent'];
	if (isset($eventTexts[$compositeKey]) && !empty($eventTexts[$compositeKey])) {
		$classText = $eventTexts[$compositeKey];
	}

	$pdf->printDiploma(
		$config['CompetitionName'],
		$config['Dates'],
		$config['Location'],
		$classText,
		$individual['Rank'],
		$individual['EnFullName'],
		$individual['CoName'],
		array(),           // no team members for individual
		$config['BodyText'],
		$config['HeadJudge'],
		$config['Organizer']
	);
}

$pdf->Output('dyplomy_indywidualne.pdf', 'I');
