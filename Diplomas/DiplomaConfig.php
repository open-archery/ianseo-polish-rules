<?php
/**
 * DiplomaConfig.php - Configuration page for PL Diploma settings.
 *
 * Allows admin to configure:
 * - Competition name, dates, location
 * - Place range (from/to)
 * - Body text
 * - Head of judges, Organizer
 * - Titles on/off toggle
 * - Per-event custom text overrides and title prefix/text
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('DiplomaSetup.php');
require_once('Fun_Diploma.php');

// Ensure tables exist
pl_diploma_ensure_tables();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveConfig'])) {
	// Save main config
	$data = array(
		'CompetitionName' => isset($_POST['CompetitionName']) ? trim($_POST['CompetitionName']) : '',
		'Dates' => isset($_POST['Dates']) ? trim($_POST['Dates']) : '',
		'Location' => isset($_POST['Location']) ? trim($_POST['Location']) : '',
		'PlaceFrom' => isset($_POST['PlaceFrom']) ? intval($_POST['PlaceFrom']) : 1,
		'PlaceTo' => isset($_POST['PlaceTo']) ? intval($_POST['PlaceTo']) : 3,
		'BodyText' => isset($_POST['BodyText']) ? trim($_POST['BodyText']) : '',
		'HeadJudge' => isset($_POST['HeadJudge']) ? trim($_POST['HeadJudge']) : '',
		'Organizer' => isset($_POST['Organizer']) ? trim($_POST['Organizer']) : '',
		'TitlesEnabled' => isset($_POST['TitlesEnabled']) ? 1 : 0,
	);

	// Validate
	if ($data['PlaceFrom'] < 1) $data['PlaceFrom'] = 1;
	if ($data['PlaceTo'] < $data['PlaceFrom']) $data['PlaceTo'] = $data['PlaceFrom'];

	pl_diploma_save_config($_SESSION['TourId'], $data);

	// Save event texts and title fields
	$allEvents = pl_diploma_get_events();
	foreach ($allEvents as $evCode => $ev) {
		$fieldName        = 'EventText_' . $evCode;
		$fieldPrefix      = 'TitlePrefix_' . $evCode;
		$fieldTitleText   = 'TitleText_' . $evCode;
		$text        = isset($_POST[$fieldName])      ? trim($_POST[$fieldName])      : '';
		$titlePrefix = isset($_POST[$fieldPrefix])    ? trim($_POST[$fieldPrefix])    : '';
		$titleText   = isset($_POST[$fieldTitleText]) ? trim($_POST[$fieldTitleText]) : '';
		pl_diploma_save_event_text($_SESSION['TourId'], $evCode, $text, $titlePrefix, $titleText);
	}

	$message = 'Konfiguracja została zapisana.';
}

// Load current config
$config = pl_diploma_get_config($_SESSION['TourId']);
$eventTexts = pl_diploma_get_event_texts($_SESSION['TourId']);
$allEvents = pl_diploma_get_events();

// Pre-fill defaults from session if config is empty
if (empty($config['CompetitionName'])) {
	$config['CompetitionName'] = isset($_SESSION['TourName']) ? $_SESSION['TourName'] : '';
}
if (empty($config['Dates'])) {
	$from = isset($_SESSION['TourWhenFrom']) ? $_SESSION['TourWhenFrom'] : '';
	$to = isset($_SESSION['TourWhenTo']) ? $_SESSION['TourWhenTo'] : '';
	$config['Dates'] = ($from === $to) ? $from : $from . ' - ' . $to;
}
if (empty($config['Location'])) {
	$config['Location'] = isset($_SESSION['TourWhere']) ? $_SESSION['TourWhere'] : '';
}

$PAGE_TITLE = 'Konfiguracja dyplomów';

$JS_SCRIPT = array(
	'<script type="text/javascript">
	</script>'
);

include('Common/Templates/head.php');

// Success message
if (!empty($message)) {
	echo '<div style="background:#d4edda;border:1px solid #28a745;padding:10px;margin:10px 0;border-radius:4px;">';
	echo htmlspecialchars($message);
	echo '</div>';
}

echo '<form method="post" action="">';
echo '<input type="hidden" name="saveConfig" value="1">';

// Main config table
echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Konfiguracja dyplomów</th></tr>';
echo '<tr><td colspan="2" style="text-align:right;padding:5px;">';
echo '<a href="Diplomas.php">&larr; Powrót do dyplomów</a>';
echo '</td></tr>';

// Competition name
echo '<tr>';
echo '<td style="width:250px;text-align:right;padding:8px;"><strong>Nazwa zawodów:</strong></td>';
echo '<td style="padding:8px;"><input type="text" name="CompetitionName" value="' . htmlspecialchars($config['CompetitionName']) . '" style="width:400px;padding:4px;"></td>';
echo '</tr>';

// Dates
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Data:</strong></td>';
echo '<td style="padding:8px;"><input type="text" name="Dates" value="' . htmlspecialchars($config['Dates']) . '" style="width:250px;padding:4px;"></td>';
echo '</tr>';

// Location
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Miejsce:</strong></td>';
echo '<td style="padding:8px;"><input type="text" name="Location" value="' . htmlspecialchars($config['Location']) . '" style="width:400px;padding:4px;"></td>';
echo '</tr>';

// Place range
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Dyplomy od miejsca:</strong></td>';
echo '<td style="padding:8px;"><input type="number" name="PlaceFrom" value="' . $config['PlaceFrom'] . '" min="1" style="width:80px;padding:4px;"></td>';
echo '</tr>';

echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Dyplomy do miejsca:</strong></td>';
echo '<td style="padding:8px;"><input type="number" name="PlaceTo" value="' . $config['PlaceTo'] . '" min="1" style="width:80px;padding:4px;"></td>';
echo '</tr>';

// Titles toggle
$titlesChecked = $config['TitlesEnabled'] ? ' checked' : '';
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Tytuły na dyplomach:</strong></td>';
echo '<td style="padding:8px;"><label><input type="checkbox" name="TitlesEnabled" value="1"' . $titlesChecked . '> Włącz tytuły dla miejsc 1–3</label></td>';
echo '</tr>';

// Body text
echo '<tr>';
echo '<td style="text-align:right;padding:8px;vertical-align:top;"><strong>Tekst dyplomu:</strong><br><small>(dodatkowy tekst)</small></td>';
echo '<td style="padding:8px;"><textarea name="BodyText" rows="3" style="width:400px;padding:4px;">' . htmlspecialchars($config['BodyText']) . '</textarea></td>';
echo '</tr>';

// Head of judges
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Sędzia główny:</strong></td>';
echo '<td style="padding:8px;"><input type="text" name="HeadJudge" value="' . htmlspecialchars($config['HeadJudge']) . '" style="width:300px;padding:4px;"></td>';
echo '</tr>';

// Organizer
echo '<tr>';
echo '<td style="text-align:right;padding:8px;"><strong>Organizator:</strong></td>';
echo '<td style="padding:8px;"><input type="text" name="Organizer" value="' . htmlspecialchars($config['Organizer']) . '" style="width:300px;padding:4px;"></td>';
echo '</tr>';

echo '</table>';

// Event text overrides + title config
if (count($allEvents)) {
	$titleYear = pl_diploma_extract_year($config['Dates']);

	echo '<br>';
	echo '<table class="Tabella">';
	echo '<tr><th class="SubTitle" colspan="5">Tekst kategorii i tytuły na dyplomach</th></tr>';
	echo '<tr>';
	echo '<th style="width:60px;">Kod</th>';
	echo '<th style="width:180px;">Nazwa domyślna</th>';
	echo '<th>Tekst na dyplomie<br><small>(puste = domyślny)</small></th>';
	echo '<th style="width:160px;">Prefiks tytułu<br><small>(np. Młodzieżowego)</small></th>';
	echo '<th>Tekst tytułu<br><small>(np. Polski Juniorów)</small></th>';
	echo '</tr>';

	$groupLabels = array('I' => 'Indywidualnie', 'T' => 'Drużynowo', 'M' => 'Mikst');
	$currentGroup = null;
	foreach ($allEvents as $evCode => $ev) {
		if ($ev['type'] !== $currentGroup) {
			$currentGroup = $ev['type'];
			$groupLabel = isset($groupLabels[$currentGroup]) ? $groupLabels[$currentGroup] : $currentGroup;
			echo '<tr><td colspan="5" style="padding:6px 4px;background:#e9ecef;font-weight:bold;">' . htmlspecialchars($groupLabel) . '</td></tr>';
		}

		// Current saved values (or empty defaults)
		$saved = isset($eventTexts[$evCode]) ? $eventTexts[$evCode] : array('customText' => '', 'titlePrefix' => '', 'titleText' => '');

		// Pre-fill title fields from hardcoded defaults when nothing saved yet
		$defaults = pl_diploma_get_title_defaults($ev['rawCode']);
		$displayPrefix = ($saved['titlePrefix'] !== '' || $saved['titleText'] !== '')
			? $saved['titlePrefix']
			: $defaults['prefix'];
		$displayTitleText = ($saved['titlePrefix'] !== '' || $saved['titleText'] !== '')
			? $saved['titleText']
			: $defaults['text'];

		// Build preview for rank 1 (isTeam/isMixed = false for preview)
		$preview = '';
		if (!empty($displayTitleText)) {
			$isMixedPreview = ($ev['type'] === 'M');
			$isTeamPreview  = ($ev['type'] === 'T' || $ev['type'] === 'M');
			$preview = pl_diploma_build_title(1, $displayPrefix, $displayTitleText, $titleYear, $isTeamPreview, $isMixedPreview);
		}

		echo '<tr>';
		echo '<td style="padding:4px;text-align:center;">' . htmlspecialchars($ev['rawCode']) . '</td>';
		echo '<td style="padding:4px;">' . htmlspecialchars($ev['name']) . '</td>';
		echo '<td style="padding:4px;"><input type="text" name="EventText_' . htmlspecialchars($evCode) . '" value="' . htmlspecialchars($saved['customText']) . '" style="width:95%;padding:3px;"></td>';
		echo '<td style="padding:4px;"><input type="text" name="TitlePrefix_' . htmlspecialchars($evCode) . '" value="' . htmlspecialchars($displayPrefix) . '" style="width:95%;padding:3px;"></td>';
		echo '<td style="padding:4px;">';
		echo '<input type="text" name="TitleText_' . htmlspecialchars($evCode) . '" value="' . htmlspecialchars($displayTitleText) . '" style="width:95%;padding:3px;">';
		if (!empty($preview)) {
			echo '<br><small style="color:#6c757d;">' . htmlspecialchars($preview) . '</small>';
		}
		echo '</td>';
		echo '</tr>';
	}

	echo '</table>';
}

// Save button
echo '<br>';
echo '<div style="text-align:center;padding:10px;">';
echo '<input type="submit" value="Zapisz konfigurację" style="padding:10px 30px;font-size:14px;font-weight:bold;">';
echo '</div>';

echo '</form>';

include('Common/Templates/tail.php');
?>
