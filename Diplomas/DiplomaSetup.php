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
			PRIMARY KEY (PlDcTournament)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}
	safe_free_result($Rs);

	// Check if PLDiplomaEventText table exists
	$Rs = safe_r_sql("SHOW TABLES LIKE 'PLDiplomaEventText'");
	if (safe_num_rows($Rs) == 0) {
		safe_w_sql("CREATE TABLE PLDiplomaEventText (
			PlDeTournament INT NOT NULL,
			PlDeEventCode VARCHAR(15) NOT NULL DEFAULT '',
			PlDeCustomText VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (PlDeTournament, PlDeEventCode)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	} else {
		// Widen column for composite keys (I:CODE, T:CODE, M:CODE)
		safe_w_sql("ALTER TABLE PLDiplomaEventText MODIFY PlDeEventCode VARCHAR(15) NOT NULL DEFAULT ''");
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
 * @param array $data Associative array with keys: CompetitionName, Dates, Location, PlaceFrom, PlaceTo, BodyText, HeadJudge, Organizer
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
			. "PlDcOrganizer = " . StrSafe_DB($data['Organizer']) . " "
			. "WHERE PlDcTournament = " . $tourId
		);
	} else {
		safe_w_sql("INSERT INTO PLDiplomaConfig (PlDcTournament, PlDcCompetitionName, PlDcDates, PlDcLocation, PlDcPlaceFrom, PlDcPlaceTo, PlDcBodyText, PlDcHeadJudge, PlDcOrganizer) VALUES ("
			. $tourId . ", "
			. StrSafe_DB($data['CompetitionName']) . ", "
			. StrSafe_DB($data['Dates']) . ", "
			. StrSafe_DB($data['Location']) . ", "
			. intval($data['PlaceFrom']) . ", "
			. intval($data['PlaceTo']) . ", "
			. StrSafe_DB($data['BodyText']) . ", "
			. StrSafe_DB($data['HeadJudge']) . ", "
			. StrSafe_DB($data['Organizer']) . ")"
		);
	}
}

/**
 * Get all custom event texts for a tournament.
 *
 * @param int $tourId Tournament ID
 * @return array Associative array [EventCode => CustomText]
 */
function pl_diploma_get_event_texts($tourId) {
	$texts = array();
	$Rs = safe_r_sql("SELECT PlDeEventCode, PlDeCustomText FROM PLDiplomaEventText WHERE PlDeTournament = " . intval($tourId));
	if (safe_num_rows($Rs) > 0) {
		while ($row = safe_fetch($Rs)) {
			$texts[$row->PlDeEventCode] = $row->PlDeCustomText;
		}
		safe_free_result($Rs);
	}
	return $texts;
}

/**
 * Save a custom event text for a tournament (INSERT or UPDATE).
 *
 * @param int $tourId Tournament ID
 * @param string $eventCode Event code
 * @param string $text Custom text
 */
function pl_diploma_save_event_text($tourId, $eventCode, $text) {
	$tourId = intval($tourId);

	// Check if record exists
	$Rs = safe_r_sql("SELECT PlDeEventCode FROM PLDiplomaEventText WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode));
	$exists = (safe_num_rows($Rs) > 0);
	safe_free_result($Rs);

	if (empty($text)) {
		// If text is empty, delete the override
		if ($exists) {
			safe_w_sql("DELETE FROM PLDiplomaEventText WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode));
		}
	} elseif ($exists) {
		safe_w_sql("UPDATE PLDiplomaEventText SET PlDeCustomText = " . StrSafe_DB($text) . " WHERE PlDeTournament = " . $tourId . " AND PlDeEventCode = " . StrSafe_DB($eventCode));
	} else {
		safe_w_sql("INSERT INTO PLDiplomaEventText (PlDeTournament, PlDeEventCode, PlDeCustomText) VALUES (" . $tourId . ", " . StrSafe_DB($eventCode) . ", " . StrSafe_DB($text) . ")");
	}
}
