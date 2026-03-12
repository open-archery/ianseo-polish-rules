<?php

declare(strict_types=1);
/**
 * Fun_Diploma.php - Data functions for PL Diploma generation.
 *
 * Provides functions to fetch events, individual results, team results,
 * and athlete lists for diploma generation.
 *
 * Event keys use composite format: {type}:{EvCode} e.g. 'I:RM', 'T:RM', 'M:RX'
 * to distinguish individual/team/mixed events that share the same EvCode.
 */

/**
 * Extract the raw EvCode from a composite event key.
 * E.g. 'I:RM' => 'RM', 'T:CU21M' => 'CU21M'
 * If no prefix is found, returns the input as-is.
 *
 * @param string $compositeKey Composite key like 'I:RM'
 * @return string Raw event code
 */
function pl_diploma_raw_event_code($compositeKey)
{
    if (strlen($compositeKey) > 2 && $compositeKey[1] === ':') {
        return substr($compositeKey, 2);
    }
    return $compositeKey;
}

/**
 * Get events for the current tournament, optionally filtered by type.
 *
 * @param string|null $type 'individual', 'team', 'mixed', or null for all
 * @return array [EvCode => ['name' => EvEventName, 'type' => 'I'|'T'|'M']]
 */
function pl_diploma_get_events($type = null)
{
    $ReturnEvents = [];

    $MySql = "SELECT EvCode, EvEventName, EvTeamEvent, EvMixedTeam ";
    $MySql .= "FROM Events ";
    $MySql .= "WHERE EvTournament = " . StrSafe_DB($_SESSION['TourId']) . " ";
    $MySql .= "AND EvCodeParent = '' ";

    switch ($type) {
        case 'individual':
            $MySql .= "AND EvTeamEvent = 0 ";
            break;
        case 'team':
            $MySql .= "AND EvTeamEvent = 1 AND EvMixedTeam = 0 ";
            break;
        case 'mixed':
            $MySql .= "AND EvTeamEvent = 1 AND EvMixedTeam = 1 ";
            break;
    }

    $MySql .= "ORDER BY EvTeamEvent ASC, EvMixedTeam ASC, EvProgr ASC, EvCode ASC";
    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $evType = 'I';
            if ($row->EvTeamEvent === 1) {
                $evType = ($row->EvMixedTeam === 1) ? 'M' : 'T';
            }
            $compositeKey = $evType . ':' . $row->EvCode;
            $ReturnEvents[$compositeKey] = [
                'name' => get_text($row->EvEventName, '', '', true),
                'type' => $evType,
                'rawCode' => $row->EvCode,
            ];
        }
        safe_free_result($Rs);
    }

    return $ReturnEvents;
}

/**
 * Fetch individual qualification results within a place range.
 *
 * @param array $events Array of event codes to filter (empty = all)
 * @param int $placeFrom Minimum rank (inclusive)
 * @param int $placeTo Maximum rank (inclusive)
 * @return array Array of result rows
 */
function pl_diploma_get_ind_qual_results($events = [], $placeFrom = 1, $placeTo = 3)
{
    $results = [];
    $tourId = StrSafe_DB($_SESSION['TourId']);
    $evFilter = '';
    if (!empty($events)) {
        $parts = [];
        foreach ($events as $ev) {
            $parts[] = StrSafe_DB(substr(pl_diploma_raw_event_code($ev), 0, 10));
        }
        $evFilter = " AND IndEvent IN (" . implode(',', $parts) . ") ";
    }

    $MySql  = "SELECT CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Individuals.IndEvent, Events.EvEventName, ";
    $MySql .= "Qualifications.QuScore, Qualifications.QuClRank ";
    $MySql .= "FROM Individuals ";
    $MySql .= "INNER JOIN Entries ON Individuals.IndId = Entries.EnId ";
    $MySql .= "INNER JOIN Events ON Individuals.IndEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 0 ";
    $MySql .= "INNER JOIN Countries ON Entries.EnCountry = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Qualifications ON Individuals.IndId = Qualifications.QuId ";
    $MySql .= "WHERE Individuals.IndTournament = " . $tourId . " ";
    $MySql .= $evFilter;
    $MySql .= "AND Qualifications.QuClRank >= " . (int) $placeFrom . " ";
    $MySql .= "AND Qualifications.QuClRank <= " . (int) $placeTo . " ";
    $MySql .= "AND Qualifications.QuScore > 0 ";
    $MySql .= "AND Entries.EnStatus <= 1 ";
    $MySql .= "ORDER BY Events.EvProgr, Events.EvCode, Qualifications.QuClRank ASC";

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $results[] = [
                'EnFullName' => get_text($row->EnFullName, '', '', true),
                'CoName' => get_text($row->CoName, '', '', true),
                'IndEvent' => $row->IndEvent,
                'EvEventName' => get_text($row->EvEventName, '', '', true),
                'QuScore' => $row->QuScore,
                'Rank' => (int) ($row->QuClRank),
            ];
        }
        safe_free_result($Rs);
    }

    return $results;
}

/**
 * Fetch individual finals results within a place range.
 * Only returns results for events where finals exist (EvFinalFirstPhase > 0).
 *
 * @param array $events Array of event codes to filter (empty = all)
 * @param int $placeFrom Minimum rank (inclusive)
 * @param int $placeTo Maximum rank (inclusive)
 * @return array Array of result rows
 */
function pl_diploma_get_ind_final_results($events = [], $placeFrom = 1, $placeTo = 3)
{
    $results = [];
    $tourId = StrSafe_DB($_SESSION['TourId']);
    $evFilter = '';
    if (!empty($events)) {
        $parts = [];
        foreach ($events as $ev) {
            $parts[] = StrSafe_DB(substr(pl_diploma_raw_event_code($ev), 0, 10));
        }
        $evFilter = " AND IndEvent IN (" . implode(',', $parts) . ") ";
    }

    $MySql  = "SELECT CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Individuals.IndEvent, Events.EvEventName, ";
    $MySql .= "Qualifications.QuScore, ";
    $MySql .= "IF(EvShootOff+EvE1ShootOff+EvE2ShootOff=0, IndRank, ABS(IndRankFinal)) AS FinalRank ";
    $MySql .= "FROM Individuals ";
    $MySql .= "INNER JOIN Entries ON Individuals.IndId = Entries.EnId ";
    $MySql .= "INNER JOIN Events ON Individuals.IndEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 0 ";
    $MySql .= "INNER JOIN Countries ON Entries.EnCountry = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Qualifications ON Individuals.IndId = Qualifications.QuId ";
    $MySql .= "INNER JOIN IrmTypes ON IrmId = IndIrmTypeFinal ";
    $MySql .= "WHERE Individuals.IndTournament = " . $tourId . " ";
    $MySql .= "AND Events.EvFinalFirstPhase > 0 ";
    $MySql .= $evFilter;
    $MySql .= "AND IF(EvShootOff+EvE1ShootOff+EvE2ShootOff=0, IndRank, ABS(IndRankFinal)) >= " . (int) $placeFrom . " ";
    $MySql .= "AND IF(EvShootOff+EvE1ShootOff+EvE2ShootOff=0, IndRank, ABS(IndRankFinal)) <= " . (int) $placeTo . " ";
    $MySql .= "AND (QuScore > 0 OR IndRankFinal != 0) ";
    $MySql .= "AND Entries.EnStatus <= 1 ";
    $MySql .= "AND Entries.EnAthlete = 1 AND Entries.EnIndFEvent = 1 ";
    $MySql .= "ORDER BY Events.EvProgr, Events.EvCode, FinalRank ASC";

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $results[] = [
                'EnFullName' => get_text($row->EnFullName, '', '', true),
                'CoName' => get_text($row->CoName, '', '', true),
                'IndEvent' => $row->IndEvent,
                'EvEventName' => get_text($row->EvEventName, '', '', true),
                'QuScore' => $row->QuScore,
                'Rank' => (int) ($row->FinalRank),
            ];
        }
        safe_free_result($Rs);
    }

    return $results;
}

/**
 * Fetch team qualification results within a place range.
 * Includes both regular team and mixed team events.
 *
 * @param array $events Array of event codes to filter (empty = all)
 * @param int $placeFrom Minimum rank (inclusive)
 * @param int $placeTo Maximum rank (inclusive)
 * @return array Array of team result groups
 */
function pl_diploma_get_team_qual_results($events = [], $placeFrom = 1, $placeTo = 3)
{
    $ReturnTeams = [];
    $tourId = StrSafe_DB($_SESSION['TourId']);
    $evFilter = '';
    if (!empty($events)) {
        $parts = [];
        foreach ($events as $ev) {
            $parts[] = StrSafe_DB(substr(pl_diploma_raw_event_code($ev), 0, 10));
        }
        $evFilter = " AND Teams.TeEvent IN (" . implode(',', $parts) . ") ";
    }

    $MySql  = "SELECT Teams.TeCoId, Teams.TeSubTeam, Teams.TeEvent, Teams.TeRank, Teams.TeScore, ";
    $MySql .= "Events.EvEventName, Events.EvMixedTeam, ";
    $MySql .= "CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Qualifications.QuScore, TeamComponent.TcOrder ";
    $MySql .= "FROM Teams ";
    $MySql .= "INNER JOIN TeamComponent ON Teams.TeCoId = TeamComponent.TcCoId AND Teams.TeSubTeam = TeamComponent.TcSubTeam AND Teams.TeEvent = TeamComponent.TcEvent AND Teams.TeTournament = TeamComponent.TcTournament AND Teams.TeFinEvent = TeamComponent.TcFinEvent ";
    $MySql .= "INNER JOIN Events ON Teams.TeEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 1 ";
    $MySql .= "INNER JOIN Entries ON TeamComponent.TcId = Entries.EnId ";
    $MySql .= "INNER JOIN Countries ON Teams.TeCoId = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Qualifications ON TeamComponent.TcId = Qualifications.QuId ";
    $MySql .= "WHERE Teams.TeTournament = " . $tourId . " ";
    $MySql .= "AND Teams.TeFinEvent = 1 ";
    $MySql .= $evFilter;
    $MySql .= "AND Teams.TeRank >= " . (int) $placeFrom . " ";
    $MySql .= "AND Teams.TeRank <= " . (int) $placeTo . " ";
    $MySql .= "ORDER BY Events.EvProgr, Teams.TeEvent ASC, Teams.TeRank ASC, TeamComponent.TcOrder ASC";

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $TempIndex = $row->TeCoId . '_' . $row->TeSubTeam . '_' . $row->TeEvent;

            if (!isset($ReturnTeams[$TempIndex])) {
                $ReturnTeams[$TempIndex] = [
                    'EventId' => $row->TeEvent,
                    'EventName' => get_text($row->EvEventName, '', '', true),
                    'Rank' => (int) ($row->TeRank),
                    'Club' => get_text($row->CoName, '', '', true),
                    'Score' => (int) ($row->TeScore),
                    'IsMixed' => (int) ($row->EvMixedTeam),
                    'Athletes' => [],
                ];
            }

            $ReturnTeams[$TempIndex]['Athletes'][] = [
                'EnFullName' => get_text($row->EnFullName, '', '', true),
                'QuScore' => $row->QuScore,
            ];
        }
        safe_free_result($Rs);
    }

    return $ReturnTeams;
}

/**
 * Fetch team finals results within a place range.
 * Uses UNION ALL pattern: teams that went to finals + teams that didn't.
 *
 * @param array $events Array of event codes to filter (empty = all)
 * @param int $placeFrom Minimum rank (inclusive)
 * @param int $placeTo Maximum rank (inclusive)
 * @return array Array of team result groups
 */
function pl_diploma_get_team_final_results($events = [], $placeFrom = 1, $placeTo = 3)
{
    $ReturnTeams = [];
    $tourId = StrSafe_DB($_SESSION['TourId']);
    $evFilterTe = '';
    if (!empty($events)) {
        $parts = [];
        foreach ($events as $ev) {
            $parts[] = StrSafe_DB(substr(pl_diploma_raw_event_code($ev), 0, 10));
        }
        $evFilterTe = " AND Teams.TeEvent IN (" . implode(',', $parts) . ") ";
    }

    // Teams that went to finals (use TeamFinComponent for members)
    $MySql  = "SELECT Teams.TeCoId, Teams.TeSubTeam, Teams.TeEvent, ";
    $MySql .= "IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) AS FinalRank, ";
    $MySql .= "Teams.TeScore, Events.EvEventName, Events.EvMixedTeam, ";
    $MySql .= "CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Qualifications.QuScore, TfcOrder AS MemberOrder ";
    $MySql .= "FROM Teams ";
    $MySql .= "INNER JOIN TeamFinComponent ON Teams.TeCoId = TeamFinComponent.TfcCoId AND Teams.TeSubTeam = TeamFinComponent.TfcSubTeam AND Teams.TeEvent = TeamFinComponent.TfcEvent AND Teams.TeTournament = TeamFinComponent.TfcTournament ";
    $MySql .= "INNER JOIN Events ON Teams.TeEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 1 ";
    $MySql .= "INNER JOIN Entries ON TeamFinComponent.TfcId = Entries.EnId ";
    $MySql .= "INNER JOIN Countries ON Teams.TeCoId = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Qualifications ON TeamFinComponent.TfcId = Qualifications.QuId ";
    $MySql .= "WHERE Teams.TeTournament = " . $tourId . " ";
    $MySql .= "AND Teams.TeFinEvent = 1 ";
    $MySql .= "AND Events.EvFinalFirstPhase > 0 ";
    $MySql .= $evFilterTe;
    $MySql .= "AND IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) >= " . (int) $placeFrom . " ";
    $MySql .= "AND IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) <= " . (int) $placeTo . " ";

    $MySql .= "UNION ALL ";

    // Teams that didn't make finals (use TeamComponent for members)
    $MySql .= "SELECT Teams.TeCoId, Teams.TeSubTeam, Teams.TeEvent, ";
    $MySql .= "IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) AS FinalRank, ";
    $MySql .= "Teams.TeScore, Events.EvEventName, Events.EvMixedTeam, ";
    $MySql .= "CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Qualifications.QuScore, TcOrder AS MemberOrder ";
    $MySql .= "FROM Teams ";
    $MySql .= "INNER JOIN TeamComponent ON Teams.TeCoId = TeamComponent.TcCoId AND Teams.TeSubTeam = TeamComponent.TcSubTeam AND Teams.TeEvent = TeamComponent.TcEvent AND Teams.TeTournament = TeamComponent.TcTournament AND Teams.TeFinEvent = TeamComponent.TcFinEvent ";
    $MySql .= "INNER JOIN Events ON Teams.TeEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 1 ";
    $MySql .= "INNER JOIN Entries ON TeamComponent.TcId = Entries.EnId ";
    $MySql .= "INNER JOIN Countries ON Teams.TeCoId = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Qualifications ON TeamComponent.TcId = Qualifications.QuId ";
    $MySql .= "WHERE Teams.TeTournament = " . $tourId . " ";
    $MySql .= "AND Teams.TeFinEvent = 1 ";
    $MySql .= "AND Events.EvFinalFirstPhase > 0 ";
    $MySql .= $evFilterTe;
    $MySql .= "AND IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) > (Events.EvFinalFirstPhase * 2) ";
    $MySql .= "AND IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) >= " . (int) $placeFrom . " ";
    $MySql .= "AND IF(EvFinalFirstPhase=0, Teams.TeRank, Teams.TeRankFinal) <= " . (int) $placeTo . " ";

    $MySql .= "ORDER BY TeEvent ASC, FinalRank ASC, MemberOrder ASC";

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $TempIndex = $row->TeCoId . '_' . $row->TeSubTeam . '_' . $row->TeEvent;

            if (!isset($ReturnTeams[$TempIndex])) {
                $ReturnTeams[$TempIndex] = [
                    'EventId' => $row->TeEvent,
                    'EventName' => get_text($row->EvEventName, '', '', true),
                    'Rank' => (int) ($row->FinalRank),
                    'Club' => get_text($row->CoName, '', '', true),
                    'Score' => (int) ($row->TeScore),
                    'IsMixed' => (int) ($row->EvMixedTeam),
                    'Athletes' => [],
                ];
            }

            $ReturnTeams[$TempIndex]['Athletes'][] = [
                'EnFullName' => get_text($row->EnFullName, '', '', true),
                'QuScore' => $row->QuScore,
            ];
        }
        safe_free_result($Rs);
    }

    return $ReturnTeams;
}

/**
 * Get all athletes in the current tournament for the custom diploma picker.
 *
 * @param string $search Optional search string to filter by name
 * @return array Array of athlete records
 */
function pl_diploma_get_all_athletes($search = '')
{
    $results = [];
    $tourId = StrSafe_DB($_SESSION['TourId']);

    $MySql  = "SELECT Entries.EnId, CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName, Individuals.IndEvent, Events.EvEventName ";
    $MySql .= "FROM Entries ";
    $MySql .= "INNER JOIN Individuals ON Entries.EnId = Individuals.IndId AND Individuals.IndTournament = " . $tourId . " ";
    $MySql .= "INNER JOIN Events ON Individuals.IndEvent = Events.EvCode AND Events.EvTournament = " . $tourId . " AND Events.EvTeamEvent = 0 ";
    $MySql .= "INNER JOIN Countries ON Entries.EnCountry = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "WHERE Entries.EnTournament = " . $tourId . " ";
    $MySql .= "AND Entries.EnStatus <= 1 ";

    if (!empty($search)) {
        $searchSafe = StrSafe_DB('%' . $search . '%');
        $MySql .= "AND (Entries.EnName LIKE " . $searchSafe . " OR Entries.EnFirstName LIKE " . $searchSafe . " OR CONCAT(Entries.EnFirstName, ' ', Entries.EnName) LIKE " . $searchSafe . ") ";
    }

    $MySql .= "ORDER BY Entries.EnName, Entries.EnFirstName ASC";

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        while ($row = safe_fetch($Rs)) {
            $results[] = [
                'EnId' => $row->EnId,
                'EnFullName' => get_text($row->EnFullName, '', '', true),
                'CoName' => get_text($row->CoName, '', '', true),
                'IndEvent' => $row->IndEvent,
                'EvEventName' => get_text($row->EvEventName, '', '', true),
            ];
        }
        safe_free_result($Rs);
    }

    return $results;
}

/**
 * Get a single athlete's details by ID.
 *
 * @param int $enId Athlete/Entry ID
 * @return array|null Athlete details or null if not found
 */
function pl_diploma_get_athlete($enId)
{
    $tourId = StrSafe_DB($_SESSION['TourId']);

    $MySql  = "SELECT Entries.EnId, CONCAT(Entries.EnFirstName, ' ', Entries.EnName) AS EnFullName, ";
    $MySql .= "Countries.CoName ";
    $MySql .= "FROM Entries ";
    $MySql .= "INNER JOIN Countries ON Entries.EnCountry = Countries.CoId AND Countries.CoTournament = " . $tourId . " ";
    $MySql .= "WHERE Entries.EnId = " . (int) $enId . " ";
    $MySql .= "AND Entries.EnTournament = " . $tourId;

    $Rs = safe_r_sql($MySql);
    if (safe_num_rows($Rs) > 0) {
        $row = safe_fetch($Rs);
        safe_free_result($Rs);
        return [
            'EnId' => $row->EnId,
            'EnFullName' => get_text($row->EnFullName, '', '', true),
            'CoName' => get_text($row->CoName, '', '', true),
        ];
    }
    return null;
}
