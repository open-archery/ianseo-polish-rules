<?php
/**
 * Fun_CupRanking.php — Data layer for the PL Puchar Polski cup ranking.
 *
 * Functions:
 *   pl_cup_ranking_load($tourId)            : athlete rows for one tournament
 *   pl_cup_ranking_points($place)           : points from the Puchar Polski table
 *   pl_cup_ranking_compute($rows, $labels)  : grouped + sorted sections
 *   pl_cup_ranking_get_div_labels($tourId)  : divClass → display label map
 */

/** Preferred render order for division+class combinations. */
$PL_CUP_DIVCLASS_ORDER = ['RM', 'RW', 'CM', 'CW', 'BM', 'BW'];

/**
 * Return Puchar Polski auxiliary points for a given elimination place.
 *
 * Places 1–8 each have a unique value; 9–16 all receive 5; 17–32 all receive 1;
 * anything outside 1–32 returns 0.
 *
 * @param int $place 1-based final elimination place
 * @return int
 */
function pl_cup_ranking_points($place) {
    $table = [
        1 => 25,
        2 => 21,
        3 => 18,
        4 => 15,
        5 => 13,
        6 => 12,
        7 => 11,
        8 => 10,
    ];
    if (isset($table[$place])) return $table[$place];
    if ($place >= 9  && $place <= 16) return 5;
    if ($place >= 17 && $place <= 32) return 1;
    return 0;
}

/**
 * Load division+class display labels from tournament configuration.
 *
 * @param int $tourId Tournament ID
 * @return array e.g. ['RM' => 'Recurve Mężczyźni', 'RW' => 'Recurve Kobiety', ...]
 */
function pl_cup_ranking_get_div_labels($tourId) {
    $tourId = intval($tourId);

    $rs = safe_r_sql("
        SELECT DivId, DivDescription FROM Divisions
        WHERE DivTournament = {$tourId} AND DivAthlete = 1
        ORDER BY DivViewOrder
    ");
    $divLabels = [];
    while ($row = safe_fetch($rs)) {
        $divLabels[$row->DivId] = $row->DivDescription;
    }
    safe_free_result($rs);

    $rs = safe_r_sql("
        SELECT ClId, ClDescription FROM Classes
        WHERE ClTournament = {$tourId} AND ClAthlete = 1
        ORDER BY ClViewOrder
    ");
    $classLabels = [];
    while ($row = safe_fetch($rs)) {
        $classLabels[$row->ClId] = $row->ClDescription;
    }
    safe_free_result($rs);

    $labels = [];
    foreach ($divLabels as $divId => $divDesc) {
        foreach ($classLabels as $clId => $clDesc) {
            $labels[$divId . $clId] = $divDesc . ' ' . $clDesc;
        }
    }
    return $labels;
}

/**
 * Load athletes with a final elimination place in 1–32 for the given tournament.
 *
 * Athletes with IndRankFinal = 0 (no bracket entry or ranking not yet run) are
 * excluded by the WHERE clause.
 *
 * @param int $tourId Tournament ID
 * @return array[] Each element: [
 *   'name'      => string,
 *   'club'      => string,
 *   'licence'   => string,
 *   'division'  => string,
 *   'class'     => string,
 *   'elim_rank' => int (1–32),
 * ]
 */
function pl_cup_ranking_load($tourId) {
    $tourId = intval($tourId);

    $sql = "
        SELECT
            e.EnCode            AS licence,
            e.EnName            AS last_name,
            e.EnFirstName       AS first_name,
            co.CoName           AS club,
            e.EnDivision        AS division,
            e.EnClass           AS class,
            YEAR(e.EnDob)       AS birth_year,
            i.IndRankFinal      AS elim_rank
        FROM Individuals i
        INNER JOIN Entries e
            ON e.EnId = i.IndId AND e.EnTournament = i.IndTournament
        LEFT JOIN Countries co
            ON co.CoId = e.EnCountry AND co.CoTournament = e.EnTournament
        INNER JOIN Events ev
            ON ev.EvCode = i.IndEvent AND ev.EvTournament = i.IndTournament
            AND ev.EvTeamEvent = 0
        WHERE i.IndTournament = {$tourId}
          AND i.IndRankFinal BETWEEN 1 AND 32
        ORDER BY e.EnDivision, e.EnClass, i.IndRankFinal ASC
    ";
    $rs = safe_r_sql($sql);
    $result = [];
    while ($row = safe_fetch($rs)) {
        $result[] = [
            'name'       => trim($row->last_name . ' ' . $row->first_name),
            'club'       => (string)($row->club ?? ''),
            'licence'    => (string)$row->licence,
            'division'   => (string)$row->division,
            'class'      => (string)$row->class,
            'birth_year' => $row->birth_year > 0 ? (int)$row->birth_year : '',
            'elim_rank'  => (int)$row->elim_rank,
        ];
    }
    safe_free_result($rs);
    return $result;
}

/**
 * Build the cup ranking, grouped and sorted by division+class.
 *
 * Within each section, athletes are sorted by points DESC, then elim_rank ASC.
 * Athletes with 0 points (place 33+) are omitted — they are already excluded
 * by pl_cup_ranking_load(), but the filter is applied here as well for safety.
 *
 * @param array $rows   Result of pl_cup_ranking_load()
 * @param array $labels Optional map of divClass → label from pl_cup_ranking_get_div_labels()
 * @return array Array of sections: [
 *   'divClass' => string,
 *   'label'    => string,
 *   'rows'     => array of row arrays,
 * ]
 * Each row: ['rank', 'name', 'club', 'licence', 'elim_rank', 'points']
 */
function pl_cup_ranking_compute(array $rows, array $labels = []) {
    global $PL_CUP_DIVCLASS_ORDER;

    $groups = [];
    foreach ($rows as $athlete) {
        $pts = pl_cup_ranking_points($athlete['elim_rank']);
        if ($pts <= 0) continue;

        $key          = $athlete['division'] . $athlete['class'];
        $groups[$key][] = array_merge($athlete, ['points' => $pts]);
    }

    uksort($groups, function ($a, $b) use ($PL_CUP_DIVCLASS_ORDER) {
        $posA = array_search($a, $PL_CUP_DIVCLASS_ORDER);
        $posB = array_search($b, $PL_CUP_DIVCLASS_ORDER);
        if ($posA === false) $posA = 999;
        if ($posB === false) $posB = 999;
        if ($posA !== $posB) return $posA - $posB;
        return strcmp($a, $b);
    });

    $sections = [];
    foreach ($groups as $divClass => $athletes) {
        usort($athletes, function ($a, $b) {
            if ($b['points'] !== $a['points']) return $b['points'] - $a['points'];
            return $a['elim_rank'] - $b['elim_rank'];
        });

        $rank = 1;
        foreach ($athletes as &$a) {
            $a['rank'] = $rank++;
        }
        unset($a);

        $sections[] = [
            'divClass' => $divClass,
            'label'    => $labels[$divClass] ?? $divClass,
            'rows'     => $athletes,
        ];
    }

    return $sections;
}
