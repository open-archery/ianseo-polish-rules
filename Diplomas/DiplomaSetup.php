<?php
/**
 * DiplomaSetup.php - Auto-install and CRUD functions for PL Diploma configuration.
 *
 * Creates PLDiplomaConfig and PLDiplomaEventText tables on first use.
 * Provides functions to read/write diploma configuration per tournament.
 */

/**
 * Ensures the diploma config tables exist in the database.
 * Called on every page load of the Diplomas module.
 */
function pl_diploma_ensure_tables() {
	// Check if PLDiplomaConfig table exists
	$Rs = safe_r_sql("SHOW TABLES LIKE 'PLDiplomaConfig'");
	if (safe_num_rows($Rs) == 0) {
		safe_w_sql("CREATE TABLE PLDiplomaConfig (
			PlDcTournament INT NOT NULL,
			PlDcCompetitionName VARCHAR(255) NOT NULL DEFAULT '',
			PlDcDates VARCHAR(100) NOT NULL DEFAULT '',
			PlDcLocation VARCHAR(255) NOT NULL DEFAULT '',
			PlDcPlaceFrom INT NOT NULL DEFAULT 1,
			PlDcPlaceTo INT NOT NULL DEFAULT 3,
			PlDcBodyText TEXT,
			PlDcHeadJudge VARCHAR(255) NOT NULL DEFAULT '',
			PlDcOrganizer VARCHAR(255) NOT NULL DEFAULT '',
			PlDcTitlesEnabled TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (PlDcTournament)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	} else {
		// Add title enable column if upgrading from earlier version
		safe_w_sql("ALTER TABLE PLDiplomaConfig ADD COLUMN IF NOT EXISTS PlDcTitlesEnabled TINYINT(1) NOT NULL DEFAULT 0");
	}
	safe_free_result($Rs);

	// Check if PLDiplomaEventText table exists
	$Rs = safe_r_sql("SHOW TABLES LIKE 'PLDiplomaEventText'");
	if (safe_num_rows($Rs) == 0) {
		safe_w_sql("CREATE TABLE PLDiplomaEventText (
			PlDeTournament INT NOT NULL,
			PlDeEventCode VARCHAR(15) NOT NULL DEFAULT '',
			PlDeCustomText VARCHAR(255) NOT NULL DEFAULT '',
			PlDeTitlePrefix VARCHAR(100) NOT NULL DEFAULT '',
			PlDeTitleText VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (PlDeTournament, PlDeEventCode)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	} else {
		// Widen column for composite keys (I:CODE, T:CODE, M:CODE)
		safe_w_sql("ALTER TABLE PLDiplomaEventText MODIFY PlDeEventCode VARCHAR(15) NOT NULL DEFAULT ''");
		// Add title columns if upgrading from earlier version
		safe_w_sql("ALTER TABLE PLDiplomaEventText ADD COLUMN IF NOT EXISTS PlDeTitlePrefix VARCHAR(100) NOT NULL DEFAULT ''");
		safe_w_sql("ALTER TABLE PLDiplomaEventText ADD COLUMN IF NOT EXISTS PlDeTitleText VARCHAR(255) NOT NULL DEFAULT ''");
	}
	safe_free_result($Rs);
}

/**
 * Get diploma configuration for a tournament.
 * Returns associative array with config values, or defaults if not yet configured.
 *
 * @param int $tourId Tournament ID
 * @return array Configuration values
 */
function pl_diploma_get_config($tourId) {
	$defaults = array(
		'CompetitionName' => '',
		'Dates' => '',
		'Location' => '',
		'PlaceFrom' => 1,
		'PlaceTo' => 3,
		'BodyText' => '',
		'HeadJudge' => '',
		'Organizer' => '',
		'TitlesEnabled' => 0,
	);

	$Rs = safe_r_sql("SELECT * FROM PLDiplomaConfig WHERE PlDcTournament = " . intval($tourId));
	if (safe_num_rows($Rs) > 0) {
		$row = safe_fetch($Rs);
		$defaults['CompetitionName'] = $row->PlDcCompetitionName;
		$defaults['Dates'] = $row->PlDcDates;
		$defaults['Location'] = $row->PlDcLocation;
		$defaults['PlaceFrom'] = intval($row->PlDcPlaceFrom);
		$defaults['PlaceTo'] = intval($row->PlDcPlaceTo);
		$defaults['BodyText'] = $row->PlDcBodyText;
		$defaults['HeadJudge'] = $row->PlDcHeadJudge;
		$defaults['Organizer'] = $row->PlDcOrganizer;
		$defaults['TitlesEnabled'] = intval($row->PlDcTitlesEnabled);
		safe_free_result($Rs);
		return $defaults;
	}
	safe_free_result($Rs);
	return $defaults;
}

/**
 * Save diploma configuration for a tournament (INSERT or UPDATE).
 *
 * @param int $tourId Tournament ID
 * @param array $data Associative array with keys: CompetitionName, Dates, Location,
 *                    PlaceFrom, PlaceTo, BodyText, HeadJudge, Organizer, TitlesEnabled
 */
function pl_diploma_save_config($tourId, $data) {
	$tourId = intval($tourId);

	// Check if record exists
	$Rs = safe_r_sql("SELECT PlDcTournament FROM PLDiplomaConfig WHERE PlDcTournament = " . $tourId);
	$exists = (safe_num_rows($Rs) > 0);
	safe_free_result($Rs);

	if ($exists) {
		safe_w_sql("UPDATE PLDiplomaConfig SET "
			. "PlDcCompetitionName = " . StrSafe_DB($data['CompetitionName']) . ", "
			. "PlDcDates = " . StrSafe_DB($data['Dates']) . ", "
			. "PlDcLocation = " . StrSafe_DB($data['Location']) . ", "
			. "PlDcPlaceFrom = " . intval($data['PlaceFrom']) . ", "
			. "PlDcPlaceTo = " . intval($data['PlaceTo']) . ", "
			. "PlDcBodyText = " . StrSafe_DB($data['BodyText']) . ", "
			. "PlDcHeadJudge = " . StrSafe_DB($data['HeadJudge']) . ", "
			. "PlDcOrganizer = " . StrSafe_DB($data['Organizer']) . ", "
			. "PlDcTitlesEnabled = " . intval($data['TitlesEnabled']) . " "
			. "WHERE PlDcTournament = " . $tourId
		);
	} else {
		safe_w_sql("INSERT INTO PLDiplomaConfig (PlDcTournament, PlDcCompetitionName, PlDcDates, PlDcLocation, PlDcPlaceFrom, PlDcPlaceTo, PlDcBodyText, PlDcHeadJudge, PlDcOrganizer, PlDcTitlesEnabled) VALUES ("
			. $tourId . ", "
			. StrSafe_DB($data['CompetitionName']) . ", "
			. StrSafe_DB($data['Dates']) . ", "
			. StrSafe_DB($data['Location']) . ", "
			. intval($data['PlaceFrom']) . ", "
			. intval($data['PlaceTo']) . ", "
			. StrSafe_DB($data['BodyText']) . ", "
			. StrSafe_DB($data['HeadJudge']) . ", "
			. StrSafe_DB($data['Organizer']) . ", "
			. intval($data['TitlesEnabled']) . ")"
		);
	}
}

/**
 * Get all custom event texts for a tournament.
 *
 * @param int $tourId Tournament ID
 * @return array Associative array [EventCode => ['customText' => ..., 'titlePrefix' => ..., 'titleText' => ...]]
 */
function pl_diploma_get_event_texts($tourId) {
	$texts = array();
	$Rs = safe_r_sql("SELECT PlDeEventCode, PlDeCustomText, PlDeTitlePrefix, PlDeTitleText FROM PLDiplomaEventText WHERE PlDeTournament = " . intval($tourId));
	if (safe_num_rows($Rs) > 0) {
		while ($row = safe_fetch($Rs)) {
			$texts[$row->PlDeEventCode] = array(
				'customText' => $row->PlDeCustomText,
				'titlePrefix' => $row->PlDeTitlePrefix,
				'titleText' => $row->PlDeTitleText,
			);
		}
		safe_free_result($Rs);
	}
	return $texts;
}

/**
 * Save a custom event text and title fields for a tournament (INSERT or UPDATE).
 * Deletes the row if all three fields are empty.
 *
 * @param int $tourId Tournament ID
 * @param string $eventCode Event code
 * @param string $text Custom display text
 * @param string $titlePrefix Title prefix (e.g. "Młodzieżowego")
 * @param string $titleText Title base text (e.g. "Polski Juniorów")
 */
function pl_diploma_save_event_text($tourId, $eventCode, $text, $titlePrefix = '', $titleText = '') {
	$tourId = intval($tourId);

	// Check if record exists
	$Rs = safe_r_sql("SELECT PlDeEventCode FROM PLDiplomaEventText WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode));
	$exists = (safe_num_rows($Rs) > 0);
	safe_free_result($Rs);

	$allEmpty = (empty($text) && empty($titlePrefix) && empty($titleText));

	if ($allEmpty) {
		// Remove override row entirely when everything is blank
		if ($exists) {
			safe_w_sql("DELETE FROM PLDiplomaEventText WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode));
		}
	} elseif ($exists) {
		safe_w_sql("UPDATE PLDiplomaEventText SET "
			. "PlDeCustomText = " . StrSafe_DB($text) . ", "
			. "PlDeTitlePrefix = " . StrSafe_DB($titlePrefix) . ", "
			. "PlDeTitleText = " . StrSafe_DB($titleText) . " "
			. "WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode)
		);
	} else {
		safe_w_sql("INSERT INTO PLDiplomaEventText (PlDeTournament, PlDeEventCode, PlDeCustomText, PlDeTitlePrefix, PlDeTitleText) VALUES ("
			. $tourId . ", "
			. StrSafe_DB($eventCode) . ", "
			. StrSafe_DB($text) . ", "
			. StrSafe_DB($titlePrefix) . ", "
			. StrSafe_DB($titleText) . ")"
		);
	}
}

/**
 * Return hardcoded default title prefix and text for a given raw event code.
 * Defaults represent standard PZŁucz championship titles.
 * The division prefix (R/C/B) affects U18 defaults.
 *
 * @param string $rawEventCode Raw event code e.g. "RM", "RU21M", "RU18X"
 * @return array ['prefix' => string, 'text' => string]
 */
function pl_diploma_get_title_defaults($rawEventCode) {
	$division = substr($rawEventCode, 0, 1); // R, C, or B
	$rest     = substr($rawEventCode, 1);

	// Mixed events end in X (e.g. RX, RU21X, RU18X)
	if (substr($rest, -1) === 'X') {
		$ageCode = substr($rest, 0, -1);
		switch ($ageCode) {
			case '':
				return array('prefix' => '', 'text' => 'Polski Seniorów');
			case 'U24':
				return array('prefix' => 'Młodzieżowego', 'text' => 'Polski');
			case 'U21':
				return array('prefix' => '', 'text' => 'Polski Juniorów');
			case 'U18':
				if ($division === 'R') return array('prefix' => '', 'text' => 'Ogólnopolskiej Olimpiady Młodzieży');
				return array('prefix' => '', 'text' => 'Polski Juniorów Młodszych');
			case '50':
				return array('prefix' => '', 'text' => '');
			case 'U15':
				return array('prefix' => 'Międzywojewódzkiego', 'text' => 'Młodzików');
			default:
				return array('prefix' => '', 'text' => '');
		}
	}

	// Individual and team events
	switch ($rest) {
		case 'M':
		case 'W':
			return array('prefix' => '', 'text' => 'Polski Seniorów');
		case 'U24M':
		case 'U24W':
			return array('prefix' => 'Młodzieżowego', 'text' => 'Polski');
		case 'U21M':
		case 'U21W':
			return array('prefix' => '', 'text' => 'Polski Juniorów');
		case 'U18M':
		case 'U18W':
			if ($division === 'R') return array('prefix' => '', 'text' => 'Ogólnopolskiej Olimpiady Młodzieży');
			return array('prefix' => '', 'text' => 'Polski Juniorów Młodszych');
		case '50M':
		case '50W':
			return array('prefix' => '', 'text' => '');
		case 'U15M':
		case 'U15W':
			return array('prefix' => 'Międzywojewódzkiego', 'text' => 'Młodzików');
		case 'U12M':
		case 'U12W':
			return array('prefix' => '', 'text' => '');
		default:
			return array('prefix' => '', 'text' => '');
	}
}

/**
 * Build the full title string for a diploma.
 *
 * Template: [Zespołowego] [prefix] [Mistrza|Wicemistrza|II Wicemistrza] [text] [w mikście] na rok [year]
 *
 * Returns empty string if text is empty or rank is outside 1–3.
 *
 * @param int $rank Place (1, 2, or 3)
 * @param string $prefix Title prefix (e.g. "Młodzieżowego")
 * @param string $text Title base text (e.g. "Polski Juniorów")
 * @param int $year Competition year
 * @param bool $isTeam True for team events (regular or mixed)
 * @param bool $isMixed True for mixed team events
 * @return string Full title phrase or empty string
 */
function pl_diploma_build_title($rank, $prefix, $text, $year, $isTeam, $isMixed) {
	if (empty($text) || $rank < 1 || $rank > 3) return '';

	$infixes = array(1 => 'Mistrza', 2 => 'Wicemistrza', 3 => 'II Wicemistrza');

	$parts = array();
	if ($isTeam && !$isMixed) $parts[] = 'Zespołowego';
	if (!empty($prefix)) $parts[] = $prefix;
	$parts[] = $infixes[$rank];
	$parts[] = $text;
	if ($isMixed) $parts[] = 'w mikście';
	$parts[] = 'na rok ' . intval($year);

	return 'i zdobywa tytuł ' . implode(' ', $parts);
}

/**
 * Extract the four-digit year from a dates string.
 * Falls back to the current calendar year if no year is found.
 *
 * @param string $datesString e.g. "15-17.03.2026" or "2026-03-15"
 * @return int Year
 */
function pl_diploma_extract_year($datesString) {
	if (preg_match('/\b(20\d{2})\b/', $datesString, $m)) {
		return intval($m[1]);
	}
	return intval(date('Y'));
}
