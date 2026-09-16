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
 * $waveTally biases the A-vs-C (and, for clubs 2+, B-vs-D) choice per club
 * (PZŁucz §2.5.1.5, cross-class balance within a session): a club that is
 * wave1-heavy from classes already saved in the same session prefers column
 * C (and D over B) now, and vice versa. An empty tally (the default)
 * reproduces the plain rank-order behavior above.
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
    // needA also decides the B-vs-D order (B=wave1, D=wave2), same as A-vs-C:
    // a wave1-heavy club tries remaining C then D before A then B.
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
        $order    = $needA($code) < 0 ? ['C', 'A', 'D', 'B'] : ['A', 'C', 'B', 'D'];

        $placed = false;
        foreach ($order as $cn) {
            if ($poolIdx[$cn] + $n <= count($pool[$cn])) {
                foreach ($athletes as $a) $assignments[$pool[$cn][$poolIdx[$cn]++]] = $a;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            // Club is larger than any remaining single column — fill slot by
            // slot in the same needA-biased order, skipping any slot whose
            // boss this club already occupies so two of its athletes never
            // land on the same boss.
            $usedBosses = [];
            foreach ($athletes as $a) {
                $done = false;
                foreach ($order as $cn) {
                    while ($poolIdx[$cn] < count($pool[$cn])
                        && in_array((int)$pool[$cn][$poolIdx[$cn]], $usedBosses, true)) {
                        $poolIdx[$cn]++;
                    }
                    if ($poolIdx[$cn] < count($pool[$cn])) {
                        $slot = $pool[$cn][$poolIdx[$cn]++];
                        $assignments[$slot] = $a;
                        $usedBosses[] = (int)$slot;
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
 * Loads athletes for the given class+session, optionally split into groups by
 * division and/or class (mirroring native ianseo's GroupByDiv/GroupByClass),
 * each grouped by club (EnCountry) and sorted with the largest club first
 * within its group.
 *
 * When neither $groupByDiv nor $groupByClass is set, the result is a single
 * group covering everything $event matches (today's pooled behavior). Groups
 * are ordered by whichever of Divisions.DivViewOrder/Classes.ClViewOrder are
 * active for the grouping, matching native's own group ordering convention.
 *
 * @return array list of ['label' => string, 'clubs' => club_code => [['id','name','club','clubName'], ...]]
 */
function pl_abc_acd_load_athletes(int $tourId, int $sesOrder, string $event, bool $groupByDiv = false, bool $groupByClass = false): array
{
    $q = safe_r_sql(
        "SELECT EnId, EnFirstName, EnName, EnDivision, EnClass, CoCode EnCountry, CoName EnClubName,"
        . " DivViewOrder, ClViewOrder"
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

    // groupKey => ['label', 'order' => [divOrder, clOrder], 'clubCounts', 'clubAthletes']
    $buckets = [];
    while ($r = safe_fetch($q)) {
        $divPart = $groupByDiv   ? trim($r->EnDivision) : '';
        $clPart  = $groupByClass ? trim($r->EnClass)    : '';
        $key     = $divPart . '|' . $clPart;

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'label'        => $divPart . $clPart,
                'order'        => [$groupByDiv ? (int)$r->DivViewOrder : 0, $groupByClass ? (int)$r->ClViewOrder : 0],
                'clubCounts'   => [],
                'clubAthletes' => [],
            ];
        }

        $code = $r->EnCountry;
        $buckets[$key]['clubAthletes'][$code][] = [
            'id'       => (int)$r->EnId,
            'name'     => trim($r->EnName . ' ' . $r->EnFirstName),
            'club'     => $code,
            'clubName' => $r->EnClubName,
        ];
        $buckets[$key]['clubCounts'][$code] = ($buckets[$key]['clubCounts'][$code] ?? 0) + 1;
    }
    safe_free_result($q);

    uasort($buckets, fn($a, $b) => $a['order'] <=> $b['order']);

    $groups = [];
    foreach ($buckets as $bucket) {
        $clubCounts = $bucket['clubCounts'];
        arsort($clubCounts); // largest club first

        $orderedClubs = [];
        foreach (array_keys($clubCounts) as $code) {
            $orderedClubs[$code] = $bucket['clubAthletes'][$code];
        }

        $groups[] = ['label' => $bucket['label'], 'clubs' => $orderedClubs];
    }

    return $groups;
}

/**
 * Splits [$tgtFrom, $tgtTo] into one boss-aligned sub-range per group, sized
 * to hold each group's athlete count (3 usable ABC/ACD slots per boss - see
 * pl_abc_acd_build_slots). Groups are carved in order; a group that doesn't
 * fully fit in what's left gets whatever whole bosses remain (possibly none),
 * so overflow is reported via the normal unassigned-athlete path rather than
 * thrown.
 *
 * @param  int[] $groupSizes athlete count per group, in carving order
 * @return array list of [$from, $to] boss ranges, one per entry in $groupSizes,
 *               in the same order (an exhausted/zero-size group yields an
 *               empty range where $to < $from)
 */
function pl_abc_acd_carve_group_ranges(int $tgtFrom, int $tgtTo, array $groupSizes): array
{
    $ranges = [];
    $cursor = $tgtFrom;

    foreach ($groupSizes as $size) {
        if ($size <= 0 || $cursor > $tgtTo) {
            $ranges[] = [$cursor, $cursor - 1];
            continue;
        }

        $bossesNeeded = (int)ceil($size / 3);
        $end          = min($tgtTo, $cursor + $bossesNeeded - 1);

        $ranges[] = [$cursor, $end];
        $cursor   = $end + 1;
    }

    return $ranges;
}

/**
 * Folds a computed assignment map (slot => athlete) into a wave1/wave2 tally,
 * additive with whatever the base tally already has. Used to thread the
 * cross-class wave-balance bias (PZŁucz §2.5.1.5) across groups processed
 * within the same request, on top of the saved-history tally from
 * pl_abc_acd_session_wave_tally().
 *
 * @param  array $tally       club_code => ['wave1' => int, 'wave2' => int]
 * @param  array $assignments slot => athlete_array (from pl_abc_acd_assign)
 * @return array merged tally, same shape as $tally
 */
function pl_abc_acd_merge_tally(array $tally, array $assignments): array
{
    foreach ($assignments as $slot => $athlete) {
        $club = $athlete['club'];
        if (!isset($tally[$club])) {
            $tally[$club] = ['wave1' => 0, 'wave2' => 0];
        }
        $letter = strtoupper(substr($slot, -1));
        if ($letter === 'A' || $letter === 'B') {
            $tally[$club]['wave1']++;
        } elseif ($letter === 'C' || $letter === 'D') {
            $tally[$club]['wave2']++;
        }
    }

    return $tally;
}

/**
 * Tallies saved wave assignments per club for a session, excluding every
 * class matching $excludeEvent (a LIKE pattern, same shape as the page's
 * Event filter — a group-separation run may reassign several classes
 * matched by one wildcard pattern in a single request, and all of them must
 * be excluded from their own tally, not just an exact literal match):
 * letters A/B count as wave1, C/D as wave2. Only committed rows
 * (QuTarget!=0) count — unsaved previews from a different request are
 * invisible here.
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
 * Loads the full target-occupancy slot list for a session, using core's
 * createAvailableTargetSQL() as the source of "which slots exist" — the same
 * function ianseo core itself uses for free-target lookups elsewhere — so
 * this view can never drift out of sync with core's own notion of session
 * capacity. Every valid (Target, Letter) slot appears once; an occupied slot
 * carries its CONCAT(EnDivision,EnClass) label, an empty slot is null.
 *
 * @return array list of ['target' => int, 'letter' => string, 'label' => ?string]
 */
function pl_abc_acd_target_view_slots(int $tourId, int $sesOrder): array
{
    $atSql = createAvailableTargetSQL($sesOrder, $tourId);

    // Qualifications carries no tournament column of its own and QuId
    // (=Entries.EnId) is a globally unique PK, so joining it to the slot
    // list by QuSession/QuTarget/QuLetter alone (with the tournament filter
    // applied only afterwards, on Entries) would fan out across every past
    // tournament in the install that happens to reuse the same small
    // session/target/letter values. Scoping Entries+Qualifications to this
    // tournament first, in their own derived table — the same
    // "FROM Entries ... WHERE EnTournament=..." shape pl_abc_acd_load_athletes()
    // uses above — keeps the outer join to the full slot list a plain 1:1
    // match per occupied slot. The Divisions/Classes athlete-type filter
    // lives here too, as real INNER JOINs, so DivAthlete=1/ClAthlete=1
    // actually excludes non-athlete rows instead of being a no-op on a
    // LEFT JOIN.
    $q = safe_r_sql(
        "SELECT at.FullTgtTarget AS Target, at.FullTgtLetter AS Letter, occ.Label AS Label"
        . " FROM (" . $atSql . ") at"
        . " LEFT JOIN ("
        . "     SELECT QuSession, QuTarget, QuLetter,"
        . "            CONCAT(TRIM(EnDivision), TRIM(EnClass)) AS Label"
        . "     FROM Entries"
        . "     INNER JOIN Qualifications ON EnId=QuId"
        . "     INNER JOIN Divisions ON EnDivision=DivId AND EnTournament=DivTournament AND DivAthlete=1"
        . "     INNER JOIN Classes   ON EnClass=ClId    AND EnTournament=ClTournament  AND ClAthlete=1"
        . "     WHERE EnTournament=" . StrSafe_DB($tourId)
        . " ) occ ON occ.QuSession=at.FullTgtSession"
        . "   AND occ.QuTarget=at.FullTgtTarget AND occ.QuLetter=at.FullTgtLetter"
        . " ORDER BY at.FullTgtTarget, at.FullTgtLetter"
    );

    $slots = [];
    while ($r = safe_fetch($q)) {
        $slots[] = [
            'target' => (int)$r->Target,
            'letter' => (string)$r->Letter,
            'label'  => $r->Label !== null ? (string)$r->Label : null,
        ];
    }
    safe_free_result($q);

    return $slots;
}

/**
 * Aggregates per-slot labels (pl_abc_acd_target_view_slots()) into one label
 * per boss: zero distinct labels among its occupied letters -> free
 * ("Wolne"); one distinct label -> that division+class (a partially-filled
 * single-class boss is normal, not mixed — unfilled letters carry a null
 * label and are ignored here); more than one distinct label -> mixed,
 * combined with "+".
 *
 * @param  array $slots from pl_abc_acd_target_view_slots()
 * @return array target => ['label' => string, 'free' => bool, 'mixed' => bool], ordered by target
 */
function pl_abc_acd_target_view_boss_labels(array $slots): array
{
    $byBoss = [];
    foreach ($slots as $slot) {
        $byBoss[$slot['target']][] = $slot['label'];
    }

    $bosses = [];
    foreach ($byBoss as $target => $labels) {
        $distinct = array_values(array_unique(array_filter(
            $labels,
            fn($label) => $label !== null
        )));

        if (count($distinct) === 0) {
            $bosses[$target] = ['label' => 'Wolne', 'free' => true, 'mixed' => false];
        } elseif (count($distinct) === 1) {
            $bosses[$target] = ['label' => $distinct[0], 'free' => false, 'mixed' => false];
        } else {
            $bosses[$target] = ['label' => implode('+', $distinct), 'free' => false, 'mixed' => true];
        }
    }

    ksort($bosses);
    return $bosses;
}

/**
 * Run-length encodes per-boss labels (pl_abc_acd_target_view_boss_labels())
 * into colspan groups for rendering: consecutive bosses sharing the
 * identical label merge into one group — except a mixed boss is always its
 * own singleton group, even when a neighbor happens to compute an identical
 * combined label string, so every anomaly stays individually visible rather
 * than occasionally hidden by a coincidental string match.
 *
 * @param  array $bossLabels target => ['label', 'free', 'mixed'], ordered by target
 * @return array list of ['label' => string, 'free' => bool, 'mixed' => bool, 'from' => int, 'to' => int, 'colspan' => int]
 */
function pl_abc_acd_target_view_ranges(array $bossLabels): array
{
    $ranges = [];
    foreach ($bossLabels as $target => $info) {
        $lastIdx = count($ranges) - 1;
        if ($lastIdx >= 0
            && !$info['mixed']
            && !$ranges[$lastIdx]['mixed']
            && $ranges[$lastIdx]['label'] === $info['label']
            && $ranges[$lastIdx]['to'] === $target - 1
        ) {
            $ranges[$lastIdx]['to'] = $target;
            $ranges[$lastIdx]['colspan']++;
        } else {
            $ranges[] = [
                'label'   => $info['label'],
                'free'    => $info['free'],
                'mixed'   => $info['mixed'],
                'from'    => $target,
                'to'      => $target,
                'colspan' => 1,
            ];
        }
    }

    return $ranges;
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
