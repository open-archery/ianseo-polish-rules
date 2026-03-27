<?php
/**
 * Fun_CombinedRanking.php — Data layer for the PL cross-tournament combined ranking.
 *
 * Functions:
 *   pl_combined_ranking_get_tournaments()       : list all tournaments
 *   pl_combined_ranking_load($tourId)           : athlete data for one tournament
 *   pl_combined_ranking_merge($data1, $data2)   : merge two datasets by licence
 *   pl_combined_ranking_points($place, $type)   : points formula
 *   pl_combined_ranking_compute($merged)        : build sorted sections per div+class
 */

/** Preferred render order for division+class combinations. */
$PL_DIVCLASS_ORDER = ['RM', 'RW', 'CM', 'CW', 'BM', 'BW'];

/**
 * Return all tournaments ordered by date descending.
 *
 * @return array[] Each element: ['ToId' => int, 'ToName' => string, 'ToWhenFrom' => string]
 */
function pl_combined_ranking_get_tournaments() {
    $sql = "SELECT ToId, ToName, ToWhenFrom FROM Tournament ORDER BY ToWhenFrom DESC, ToName ASC";
    $rs = safe_r_sql($sql);
    $result = [];
    while ($row = safe_fetch($rs)) {
        $result[] = [
            'ToId'      => (int)$row->ToId,
            'ToName'    => $row->ToName,
            'ToWhenFrom'=> $row->ToWhenFrom,
        ];
    }
    safe_free_result($rs);
    return $result;
}

/**
 * Load division+class display labels from tournament configuration.
 *
 * Returns a map of divClass key (e.g. 'RM') to a display label built from
 * DivDescription + ClDescription as configured in the tournament.
 *
 * @param int $tourId Tournament ID to read divisions/classes from
 * @return array e.g. ['RM' => 'Recurve Mężczyźni', 'RW' => 'Recurve Kobiety', ...]
 */
function pl_combined_ranking_get_div_labels($tourId) {
    $tourId = intval($tourId);

    $divSql = "SELECT DivId, DivDescription FROM Divisions
               WHERE DivTournament = {$tourId} AND DivAthlete = 1
               ORDER BY DivViewOrder";
    $rs = safe_r_sql($divSql);
    $divLabels = [];
    while ($row = safe_fetch($rs)) {
        $divLabels[$row->DivId] = $row->DivDescription;
    }
    safe_free_result($rs);

    $clsSql = "SELECT ClId, ClDescription FROM Classes
               WHERE ClTournament = {$tourId} AND ClAthlete = 1
               ORDER BY ClViewOrder";
    $rs = safe_r_sql($clsSql);
    $classLabels = [];
    while ($row = safe_fetch($rs)) {
        $classLabels[$row->ClId] = $row->ClDescription;
    }
    safe_free_result($rs);

    // Build labels for every combination present in both maps.
    $labels = [];
    foreach ($divLabels as $divId => $divDesc) {
        foreach ($classLabels as $clId => $clDesc) {
            $labels[$divId . $clId] = $divDesc . ' ' . $clDesc;
        }
    }
    return $labels;
}

/**
 * Load athlete data for one tournament.
 *
 * Bracket participation is detected by pre-fetching all FinAthlete IDs from
 * Finals (individual events only) into a set — avoiding a correlated subquery
 * per athlete row.
 *
 * @param int $tourId Tournament ID
 * @return array Keyed by EnCode (licence = bib): [
 *   'name'       => string,
 *   'club'       => string,
 *   'licence'    => string,
 *   'division'   => string,
 *   'class'      => string,
 *   'qual_rank'  => int|null,
 *   'qual_score' => int|null,
 *   'elim_rank'  => int|null  (null when athlete did not enter the bracket),
 *   'in_bracket' => bool,
 * ]
 */
function pl_combined_ranking_load($tourId) {
    $tourId = intval($tourId);

    // Pre-fetch bracket participants (individual events only) for this tournament.
    $bracketSql = "
        SELECT DISTINCT FinAthlete
        FROM Finals
        INNER JOIN Events
            ON FinEvent = EvCode
            AND FinTournament = EvTournament
            AND EvTeamEvent = 0
        WHERE FinTournament = {$tourId}
    ";
    $rs = safe_r_sql($bracketSql);
    $bracketSet = [];
    while ($row = safe_fetch($rs)) {
        $bracketSet[(int)$row->FinAthlete] = true;
    }
    safe_free_result($rs);

    // Main query: Entries + Countries (club name) + Qualifications + Individuals.
    // Column names: EnName = family name, EnFirstName = given name.
    // Club is stored in Countries.CoName joined via EnCountry = CoId.
    // Qualifications.QuId = Entries.EnId (one qual record per entry).
    // Individuals is per-event; pick the single individual event for this tournament.
    $sql = "
        SELECT
            e.EnId,
            e.EnCode        AS licence,
            e.EnName        AS last_name,
            e.EnFirstName   AS first_name,
            co.CoName       AS club,
            e.EnDivision    AS division,
            e.EnClass       AS class,
            q.QuScore       AS qual_score,
            i.IndRank       AS qual_rank,
            i.IndRankFinal  AS elim_rank_raw
        FROM Entries e
        LEFT JOIN Countries co
            ON co.CoId = e.EnCountry AND co.CoTournament = e.EnTournament
        LEFT JOIN Qualifications q
            ON q.QuId = e.EnId
        LEFT JOIN Individuals i
            ON i.IndId          = e.EnId
            AND i.IndTournament = e.EnTournament
            AND i.IndEvent      = (
                SELECT EcCode FROM EventClass
                WHERE EcTournament = {$tourId}
                  AND EcTeamEvent  = 0
                  AND EcDivision   = e.EnDivision
                  AND EcClass      = e.EnClass
                LIMIT 1
            )
        WHERE e.EnTournament = {$tourId}
          AND e.EnCode       != ''
          AND e.EnDivision   IN ('R','C','B')
        ORDER BY e.EnDivision, e.EnClass, e.EnName, e.EnFirstName
    ";
    $rs = safe_r_sql($sql);
    $result = [];
    while ($row = safe_fetch($rs)) {
        $enId      = (int)$row->EnId;
        $inBracket = isset($bracketSet[$enId]);
        $result[$row->licence] = [
            'name'       => trim($row->last_name . ' ' . $row->first_name),
            'club'       => (string)($row->club ?? ''),
            'licence'    => (string)$row->licence,
            'division'   => (string)$row->division,
            'class'      => (string)$row->class,
            'qual_rank'  => ($row->qual_rank > 0)   ? (int)$row->qual_rank  : null,
            'qual_score' => ($row->qual_score !== null) ? (int)$row->qual_score : null,
            'elim_rank'  => ($inBracket && $row->elim_rank_raw > 0) ? (int)$row->elim_rank_raw : null,
            'in_bracket' => $inBracket,
        ];
    }
    safe_free_result($rs);
    return $result;
}

/**
 * Merge two tournament datasets into one keyed array.
 *
 * Full-outer-join logic in PHP: union of all licence keys from both arrays.
 * Tournament 1 data takes priority for athlete identity fields (name, club, div, class).
 *
 * @param array $data1 Result of pl_combined_ranking_load() for Tournament 1, or []
 * @param array $data2 Result of pl_combined_ranking_load() for Tournament 2, or []
 * @return array Keyed by licence: [
 *   'name', 'club', 'licence', 'division', 'class',
 *   'd1_qual_rank', 'd1_qual_score', 'd1_elim_rank',
 *   'd2_qual_rank', 'd2_qual_score', 'd2_elim_rank',
 * ]
 */
function pl_combined_ranking_merge(array $data1, array $data2) {
    $allKeys = array_unique(array_merge(array_keys($data1), array_keys($data2)));
    $merged  = [];
    foreach ($allKeys as $licence) {
        $a1   = $data1[$licence] ?? null;
        $a2   = $data2[$licence] ?? null;
        $base = $a1 ?? $a2; // prefer T1 for identity fields
        $merged[$licence] = [
            'name'          => $base['name'],
            'club'          => $base['club'],
            'licence'       => $licence,
            'division'      => $base['division'],
            'class'         => $base['class'],
            'd1_qual_rank'  => $a1 ? $a1['qual_rank']  : null,
            'd1_qual_score' => $a1 ? $a1['qual_score'] : null,
            'd1_elim_rank'  => $a1 ? $a1['elim_rank']  : null,
            'd2_qual_rank'  => $a2 ? $a2['qual_rank']  : null,
            'd2_qual_score' => $a2 ? $a2['qual_score'] : null,
            'd2_elim_rank'  => $a2 ? $a2['elim_rank']  : null,
        ];
    }
    return $merged;
}

/**
 * Calculate ranking points from a place.
 *
 * Qualification: points = max(0, 16 - place)          → 1st=15 pts, 15th=1 pt
 * Elimination:   points = max(0, (16 - place) * 2)    → 1st=30 pts, 15th=2 pts
 * Null place (athlete absent or not in bracket) → 0 pts.
 *
 * @param int|null $place 1-based finishing place, or null
 * @param string   $type  'qual' or 'elim'
 * @return int
 */
function pl_combined_ranking_points($place, $type) {
    if ($place === null || $place < 1) return 0;
    if ($type === 'qual') return max(0, 16 - $place);
    if ($type === 'elim') return max(0, (16 - $place) * 2);
    return 0;
}

/**
 * Build the combined ranking, grouped and sorted by division+class.
 *
 * @param array $merged Result of pl_combined_ranking_merge()
 * @param array $labels Optional map of divClass → label from pl_combined_ranking_get_div_labels()
 * @return array Array of sections: [
 *   'divClass' => string,
 *   'label'    => string,
 *   'rows'     => array of row arrays,
 * ]
 * Each row: [
 *   'rank', 'name', 'club', 'licence',
 *   'd1_qual_place', 'd1_qual_pts', 'd1_elim_place', 'd1_elim_pts',
 *   'd2_qual_place', 'd2_qual_pts', 'd2_elim_place', 'd2_elim_pts',
 *   'best_2x70m', 'total_pts',
 * ]
 */
function pl_combined_ranking_compute(array $merged, array $labels = []) {
    global $PL_DIVCLASS_ORDER;

    // Group athletes by division+class key (e.g. 'RM', 'RW').
    $groups = [];
    foreach ($merged as $athlete) {
        $key = $athlete['division'] . $athlete['class'];
        $groups[$key][] = $athlete;
    }

    // Sort groups by defined order; unknowns fall alphabetically at the end.
    uksort($groups, function ($a, $b) use ($PL_DIVCLASS_ORDER) {
        $posA = array_search($a, $PL_DIVCLASS_ORDER);
        $posB = array_search($b, $PL_DIVCLASS_ORDER);
        if ($posA === false) $posA = 999;
        if ($posB === false) $posB = 999;
        if ($posA !== $posB) return $posA - $posB;
        return strcmp($a, $b);
    });

    $sections = [];
    foreach ($groups as $divClass => $athletes) {
        $rows = [];
        foreach ($athletes as $a) {
            $d1QualPts = pl_combined_ranking_points($a['d1_qual_rank'], 'qual');
            $d1ElimPts = pl_combined_ranking_points($a['d1_elim_rank'], 'elim');
            $d2QualPts = pl_combined_ranking_points($a['d2_qual_rank'], 'qual');
            $d2ElimPts = pl_combined_ranking_points($a['d2_elim_rank'], 'elim');
            $totalPts  = $d1QualPts + $d1ElimPts + $d2QualPts + $d2ElimPts;

            // Best qual score across both tournaments (0 treated as absent).
            $s1        = ($a['d1_qual_score'] !== null && $a['d1_qual_score'] > 0) ? $a['d1_qual_score'] : 0;
            $s2        = ($a['d2_qual_score'] !== null && $a['d2_qual_score'] > 0) ? $a['d2_qual_score'] : 0;
            $best2x70m = max($s1, $s2);

            $rows[] = [
                'name'          => $a['name'],
                'club'          => $a['club'],
                'licence'       => $a['licence'],
                'd1_qual_place' => $a['d1_qual_rank'],
                'd1_qual_pts'   => $d1QualPts,
                'd1_elim_place' => $a['d1_elim_rank'],
                'd1_elim_pts'   => $d1ElimPts,
                'd2_qual_place' => $a['d2_qual_rank'],
                'd2_qual_pts'   => $d2QualPts,
                'd2_elim_place' => $a['d2_elim_rank'],
                'd2_elim_pts'   => $d2ElimPts,
                'best_2x70m'    => $best2x70m > 0 ? $best2x70m : null,
                'total_pts'     => $totalPts,
            ];
        }

        // Sort: total_pts DESC, then best_2x70m DESC.
        usort($rows, function ($a, $b) {
            if ($b['total_pts'] !== $a['total_pts']) return $b['total_pts'] - $a['total_pts'];
            return ($b['best_2x70m'] ?? 0) - ($a['best_2x70m'] ?? 0);
        });

        // Assign sequential rank.
        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank'] = $rank++;
        }
        unset($row);

        $sections[] = [
            'divClass' => $divClass,
            'label'    => $labels[$divClass] ?? $divClass,
            'rows'     => $rows,
        ];
    }

    return $sections;
}
