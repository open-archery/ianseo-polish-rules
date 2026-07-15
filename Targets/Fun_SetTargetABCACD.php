<?php

/**
 * Builds an ordered slot list using the ABC/ACD alternating pattern.
 * Odd boss numbers → A, B, C (D empty); even → A, C, D (B empty).
 */
function pl_abc_acd_build_slots(int $from, int $to): array
{
    $slots = [];
    for ($boss = $from; $boss <= $to; $boss++) {
        if ($boss % 2 !== 0) {
            $slots[] = $boss . 'A';
            $slots[] = $boss . 'B';
            $slots[] = $boss . 'C';
        } else {
            $slots[] = $boss . 'A';
            $slots[] = $boss . 'C';
            $slots[] = $boss . 'D';
        }
    }
    return $slots;
}

/**
 * Assigns athletes to slots using a column-priority strategy:
 *
 *   Column A  (every boss, wave 1) → largest club fills from the start
 *   Column C  (every boss, wave 2) → second largest club fills from the start
 *   Clubs 2+  → each club is placed in the first column that can hold all its
 *               athletes: remaining A, then remaining C, then B, then D.
 *               If no single column fits, falls back to slot-by-slot A→C→B→D.
 *
 * Placing whole clubs in single columns keeps teammates on consecutive bosses
 * and on a consistent wave. Smaller clubs that don't fit in A or C get B or D.
 *
 * $waveTally biases the A-vs-C choice per club (PZŁucz §2.5.1.5, cross-class
 * balance within a session): a club that is wave1-heavy from classes already
 * saved in the same session prefers column C now, and vice versa. An empty
 * tally (the default) reproduces the plain rank-order behavior above.
 *
 * @param  array $clubs     club_code => [[id, name, club], ...]  (sorted DESC by size)
 * @param  array $slots     ordered slot strings from pl_abc_acd_build_slots()
 * @param  array $waveTally club_code => ['wave1' => int, 'wave2' => int] from
 *                          pl_abc_acd_session_wave_tally()
 * @return array [assignments: slot => athlete_array, unassigned: [athlete_array, ...]]
 */
function pl_abc_acd_assign(array $clubs, array $slots, array $waveTally = []): array
{
    // Partition slots into named columns
    $colA  = [];
    $colC  = [];
    $colBD = [];  // B on odd bosses, D on even bosses, interleaved

    foreach ($slots as $slot) {
        $l = substr($slot, -1);
        if ($l === 'A')     $colA[]  = $slot;
        elseif ($l === 'C') $colC[]  = $slot;
        else                $colBD[] = $slot;
    }

    $assignments = [];
    $unassigned  = [];
    $clubCodes   = array_keys($clubs);
    $clubList    = array_values($clubs);
    $numClubs    = count($clubList);

    if ($numClubs === 0) return [$assignments, $unassigned];

    // needA > 0: the club is wave2-heavy in this session so far, so it should
    // shoot wave1 (column A) now; needA < 0: wave1-heavy, prefers column C.
    $needA = fn($club): int =>
        ($waveTally[$club]['wave2'] ?? 0) - ($waveTally[$club]['wave1'] ?? 0);

    if ($numClubs === 1) {
        // Single club → one full column: A by default, C when the club is
        // wave1-heavy this session. Overflow beyond the column's capacity is
        // left unassigned rather than spilling into other columns: placing a
        // second club-0 athlete there would put two athletes from the same
        // club on one boss, violating the one-club-per-boss invariant.
        $col = $needA($clubCodes[0]) < 0 ? $colC : $colA;
        $idx = 0;
        foreach ($clubList[0] as $athlete) {
            if ($idx < count($col)) {
                $assignments[$col[$idx++]] = $athlete;
            } else {
                $unassigned[] = $athlete;
            }
        }
        return [$assignments, $unassigned];
    }

    // Clubs 0 and 1 get columns A and C: rank order (largest → A) unless the
    // session tally says club 1 needs wave1 strictly more than club 0.
    $swap = $needA($clubCodes[1]) > $needA($clubCodes[0]);
    $col0 = $swap ? $colC : $colA;
    $col1 = $swap ? $colA : $colC;

    // Overflow beyond each column's capacity stays unassigned (see above).
    $idx0 = 0;
    foreach ($clubList[0] as $athlete) {
        if ($idx0 < count($col0)) {
            $assignments[$col0[$idx0++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    $idx1 = 0;
    foreach ($clubList[1] as $athlete) {
        if ($idx1 < count($col1)) {
            $assignments[$col1[$idx1++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    if ($numClubs <= 2) return [$assignments, $unassigned];

    // Clubs 2+: fit the whole club into the first column that has enough room.
    // Priority: remaining A → remaining C → B → D → slot-by-slot fallback.
    // A wave1-heavy club tries remaining C before remaining A; the B → D tail
    // never changes (B/D are fixed leftover slots, not an assignable choice).
    $pool = [
        'A' => array_slice($colA, $swap ? $idx1 : $idx0),
        'C' => array_slice($colC, $swap ? $idx0 : $idx1),
        'B' => array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'B')),
        'D' => array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'D')),
    ];
    $poolIdx = ['A' => 0, 'C' => 0, 'B' => 0, 'D' => 0];

    foreach (array_slice($clubCodes, 2) as $i => $code) {
        $athletes = $clubList[$i + 2];
        $n        = count($athletes);
        $order    = $needA($code) < 0 ? ['C', 'A', 'B', 'D'] : ['A', 'C', 'B', 'D'];

        $placed = false;
        foreach ($order as $cn) {
            if ($poolIdx[$cn] + $n <= count($pool[$cn])) {
                foreach ($athletes as $a) $assignments[$pool[$cn][$poolIdx[$cn]++]] = $a;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            // Club is larger than any remaining single column — fill slot by slot
            foreach ($athletes as $a) {
                $done = false;
                foreach (['A', 'C', 'B', 'D'] as $cn) {
                    if ($poolIdx[$cn] < count($pool[$cn])) {
                        $assignments[$pool[$cn][$poolIdx[$cn]++]] = $a;
                        $done = true;
                        break;
                    }
                }
                if (!$done) $unassigned[] = $a;
            }
        }
    }

    return [$assignments, $unassigned];
}

/**
 * Loads athletes for the given class+session, grouped by club (EnCountry),
 * sorted with the largest club first.
 *
 * @return array club_code => [['id','name','club','clubName'], ...]
 */
function pl_abc_acd_load_athletes(int $tourId, int $sesOrder, string $event): array
{
    $q = safe_r_sql(
        "SELECT EnId, EnFirstName, EnName, EnDivision, EnClass, CoCode EnCountry, CoName EnClubName"
        . " FROM Entries"
        . " INNER JOIN Countries ON EnCountry=CoId"
        . " INNER JOIN Qualifications ON EnId=QuId"
        . " INNER JOIN Divisions ON EnDivision=DivId AND EnTournament=DivTournament AND DivAthlete=1"
        . " INNER JOIN Classes   ON EnClass=ClId    AND EnTournament=ClTournament  AND ClAthlete=1"
        . " WHERE EnTournament=" . StrSafe_DB($tourId)
        . "   AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($event)
        . "   AND QuSession=" . StrSafe_DB($sesOrder)
        . " ORDER BY EnCountry, RAND()"
    );

    $clubCounts   = [];
    $clubAthletes = [];
    while ($r = safe_fetch($q)) {
        $code = $r->EnCountry;
        $clubAthletes[$code][] = [
            'id'       => (int)$r->EnId,
            'name'     => trim($r->EnName . ' ' . $r->EnFirstName),
            'club'     => $code,
            'clubName' => $r->EnClubName,
        ];
        $clubCounts[$code] = ($clubCounts[$code] ?? 0) + 1;
    }
    safe_free_result($q);

    arsort($clubCounts); // largest club first

    $orderedClubs = [];
    foreach (array_keys($clubCounts) as $code) {
        $orderedClubs[$code] = $clubAthletes[$code];
    }

    return $orderedClubs;
}

/**
 * Tallies saved wave assignments per club for a session, excluding the class
 * currently being assigned: letters A/B count as wave1, C/D as wave2. Only
 * committed rows (QuTarget!=0) count — unsaved previews are invisible here.
 *
 * @return array club_code => ['wave1' => int, 'wave2' => int]
 */
function pl_abc_acd_session_wave_tally(int $tourId, int $sesOrder, string $excludeEvent): array
{
    $q = safe_r_sql(
        "SELECT CoCode EnCountry, QuLetter"
        . " FROM Entries"
        . " INNER JOIN Countries ON EnCountry=CoId"
        . " INNER JOIN Qualifications ON EnId=QuId"
        . " INNER JOIN Divisions ON EnDivision=DivId AND EnTournament=DivTournament AND DivAthlete=1"
        . " INNER JOIN Classes   ON EnClass=ClId    AND EnTournament=ClTournament  AND ClAthlete=1"
        . " WHERE EnTournament=" . StrSafe_DB($tourId)
        . "   AND QuSession=" . StrSafe_DB($sesOrder)
        . "   AND QuTarget!=0"
        . "   AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) NOT LIKE " . StrSafe_DB($excludeEvent)
    );

    $tally = [];
    while ($r = safe_fetch($q)) {
        $club = $r->EnCountry;
        if (!isset($tally[$club])) {
            $tally[$club] = ['wave1' => 0, 'wave2' => 0];
        }
        $letter = strtoupper(trim((string)$r->QuLetter));
        if ($letter === 'A' || $letter === 'B') {
            $tally[$club]['wave1']++;
        } elseif ($letter === 'C' || $letter === 'D') {
            $tally[$club]['wave2']++;
        }
    }
    safe_free_result($q);

    return $tally;
}

/**
 * Erases existing target assignments (QuTarget, QuLetter, QuBacknoPrinted)
 * for the given class+session, touching Entries timestamps for affected rows.
 */
function pl_abc_acd_erase(int $tourId, int $sesOrder, string $event): void
{
    $where = "EnTournament=" . StrSafe_DB($tourId)
           . " AND QuSession=" . StrSafe_DB($sesOrder)
           . " AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($event);

    safe_w_sql(
        "UPDATE Entries INNER JOIN Qualifications ON EnId=QuId"
        . " SET EnTimestamp='" . date('Y-m-d H:i:s') . "',"
        . "     EnMainInfoUpdate='" . date('Y-m-d H:i:s') . "'"
        . " WHERE QuTarget!=0 AND " . $where
    );
    safe_w_sql(
        "UPDATE Qualifications INNER JOIN Entries ON QuId=EnId"
        . " SET QuTarget=0, QuLetter='', QuBacknoPrinted=0"
        . " WHERE " . $where
    );
}

/**
 * Erases existing assignments for the class+session, then writes the new
 * slot => athlete assignments to Qualifications/Entries, inside a single
 * transaction so a mid-loop failure can't leave assignments partially wiped.
 *
 * @return int number of athletes saved
 */
function pl_abc_acd_save(int $tourId, int $sesOrder, string $event, array $assignments): int
{
    safe_w_BeginTransaction();
    try {
        pl_abc_acd_erase($tourId, $sesOrder, $event);

        $now = date('Y-m-d H:i:s');
        foreach ($assignments as $slot => $athlete) {
            $tgtNum = (int)$slot;
            $letter = strtoupper(substr($slot, -1));
            safe_w_sql(
                "UPDATE Qualifications"
                . " SET QuTimestamp=QuTimestamp,"
                . "     QuTarget=" . $tgtNum . ","
                . "     QuLetter='" . $letter . "',"
                . "     QuBacknoPrinted=0"
                . " WHERE QuId=" . (int)$athlete['id']
            );
            safe_w_sql(
                "UPDATE Entries"
                . " SET EnTimestamp='" . $now . "', EnMainInfoUpdate='" . $now . "'"
                . " WHERE EnId=" . (int)$athlete['id']
            );
        }
        safe_w_Commit();
    } catch (Exception $e) {
        safe_w_Rollback();
        throw $e;
    }

    return count($assignments);
}
