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
 * @param  array $clubs  club_code => [[id, name, club], ...]  (sorted DESC by size)
 * @param  array $slots  ordered slot strings from pl_abc_acd_build_slots()
 * @return array [assignments: slot => athlete_array, unassigned: [athlete_array, ...]]
 */
function pl_abc_acd_assign(array $clubs, array $slots): array
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
    $clubList    = array_values($clubs);
    $numClubs    = count($clubList);

    if ($numClubs === 0) return [$assignments, $unassigned];

    // Club 0 (largest) → A column: always wave 1 on every boss
    $idxA = 0;
    foreach ($clubList[0] as $athlete) {
        if ($idxA < count($colA)) {
            $assignments[$colA[$idxA++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    if ($numClubs === 1) return [$assignments, $unassigned];

    // Club 1 (second largest) → C column: always wave 2 on every boss
    $idxC = 0;
    foreach ($clubList[1] as $athlete) {
        if ($idxC < count($colC)) {
            $assignments[$colC[$idxC++]] = $athlete;
        } else {
            $unassigned[] = $athlete;
        }
    }

    if ($numClubs <= 2) return [$assignments, $unassigned];

    // Clubs 2+: fit the whole club into the first column that has enough room.
    // Priority: remaining A → remaining C → B → D → slot-by-slot fallback.
    $remainingA = array_slice($colA, $idxA);
    $remainingC = array_slice($colC, $idxC);
    $colB       = array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'B'));
    $colD       = array_values(array_filter($colBD, fn($s) => substr($s, -1) === 'D'));
    $idxRA = 0; $idxRC = 0; $idxB = 0; $idxD = 0;

    foreach (array_slice($clubList, 2) as $athletes) {
        $n = count($athletes);
        if ($idxRA + $n <= count($remainingA)) {
            foreach ($athletes as $a) $assignments[$remainingA[$idxRA++]] = $a;
        } elseif ($idxRC + $n <= count($remainingC)) {
            foreach ($athletes as $a) $assignments[$remainingC[$idxRC++]] = $a;
        } elseif ($idxB + $n <= count($colB)) {
            foreach ($athletes as $a) $assignments[$colB[$idxB++]] = $a;
        } elseif ($idxD + $n <= count($colD)) {
            foreach ($athletes as $a) $assignments[$colD[$idxD++]] = $a;
        } else {
            // Club is larger than any remaining single column — fill slot by slot
            foreach ($athletes as $a) {
                if ($idxRA < count($remainingA))     { $assignments[$remainingA[$idxRA++]] = $a; }
                elseif ($idxRC < count($remainingC)) { $assignments[$remainingC[$idxRC++]] = $a; }
                elseif ($idxB < count($colB))        { $assignments[$colB[$idxB++]] = $a; }
                elseif ($idxD < count($colD))        { $assignments[$colD[$idxD++]] = $a; }
                else                                  { $unassigned[] = $a; }
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
 * slot => athlete assignments to Qualifications/Entries.
 *
 * @return int number of athletes saved
 */
function pl_abc_acd_save(int $tourId, int $sesOrder, string $event, array $assignments): int
{
    pl_abc_acd_erase($tourId, $sesOrder, $event);

    $now = date('Y-m-d H:i:s');
    foreach ($assignments as $slot => $athlete) {
        $tgtNum = (int)$slot;
        $letter = strtoupper(substr($slot, -1));
        safe_w_sql(
            "UPDATE Qualifications"
            . " SET QuTimestamp=QuTimestamp, QuTarget=" . $tgtNum . ", QuLetter='" . $letter . "'"
            . " WHERE QuId=" . $athlete['id']
        );
        safe_w_sql(
            "UPDATE Entries"
            . " SET EnTimestamp='" . $now . "', EnMainInfoUpdate='" . $now . "'"
            . " WHERE EnId=" . $athlete['id']
        );
        safe_w_sql(
            "UPDATE Qualifications"
            . " SET QuBacknoPrinted=0, QuTimestamp=QuTimestamp"
            . " WHERE QuId=" . $athlete['id']
        );
    }

    return count($assignments);
}
