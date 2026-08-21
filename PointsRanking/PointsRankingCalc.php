<?php
/**
 * PointsRankingCalc.php — points-ranking computation.
 *
 * pl_points_bracket .. pl_points_build_reports are pure functions: no
 * database access, plain arrays in and out (see design.md "Calculation
 * Engine"). pl_points_calculate() is the orchestrator — it calls the
 * Fun_PointsRanking.php loaders and wires their output through the pure
 * functions above.
 *
 * Shape of one "classified" entry (the interface between the orchestrator
 * and pl_points_build_reports):
 *   [classificationKey => [
 *       'subject'      => 'IND'|'TEAM'|'MIXED',
 *       'athlete_rows' => [ ['enId','name','code','category','club_id','club_code','club_name','points','place'], ... ],
 *       'teams'        => [ ['category','club_id','club_code','club_name','points','place','members' => [enId => share], 'roster_empty'], ... ],  // TEAM/MIXED only
 *   ]]
 * 'category' on an athlete_row is the athlete's OWN division+class (D3) —
 * for a team/mixed share this differs from the team's own category (EvCode).
 */

/**
 * Points for a place under one classification's bracket table.
 * First matching [from, to, points] wins. DSQ/DNS/DNF (place >= 29999) and
 * "no result" (place 0) both score 0, as does any place outside every bracket.
 */
function pl_points_bracket(array $brackets, int $place)
{
    if ($place <= 0 || $place >= 29999) {
        return 0;
    }
    foreach ($brackets as $bracket) {
        [$from, $to, $points] = $bracket;
        if ($place >= $from && $place <= $to) {
            return $points;
        }
    }
    return 0;
}

/**
 * Zero every row sharing the worst place, per category, when that category's
 * qualification-starter count is below $maxRankTo (spec "Cutoff rule").
 *
 * @param array $rows Each row needs 'category' and 'place'
 * @param array $startersByCategory category => qualification starter count
 */
function pl_points_apply_cutoff(array $rows, int $maxRankTo, array $startersByCategory): array
{
    $byCategory = [];
    foreach ($rows as $i => $row) {
        $byCategory[$row['category']][] = $i;
    }

    foreach ($byCategory as $category => $indices) {
        $starters = $startersByCategory[$category] ?? 0;
        if ($starters >= $maxRankTo) {
            continue;
        }
        $worstPlace = max(array_map(fn ($i) => $rows[$i]['place'], $indices));
        foreach ($indices as $i) {
            if ($rows[$i]['place'] === $worstPlace) {
                $rows[$i]['points'] = 0;
            }
        }
    }

    return $rows;
}

/**
 * Split a team's points into even shares among its counting members (D4/D5).
 * When $threeOfFour and the roster has 4 members, the worst qualifier (by
 * $parentRanks, tie -> higher entry id) is dropped and credited 0.
 *
 * @param int $teamPoints
 * @param int[] $roster Entry ids
 * @param array<int,int> $parentRanks enId => qualification place in the parent individual event
 * @return array{members: array<int,float|int>, warning: bool}
 */
function pl_points_split_team($teamPoints, array $roster, bool $threeOfFour, array $parentRanks = []): array
{
    if (empty($roster)) {
        return ['members' => [], 'warning' => true];
    }

    $droppedEnId = null;
    $counting = $roster;

    if ($threeOfFour && count($roster) === 4) {
        // No result (rank 0/DSQ) or no Individuals row at all in the parent event
        // is treated as the worst possible qualifier, not as rank 0/best — so
        // that member is the one dropped, never a real, well-placed athlete.
        $normalizedRank = function ($enId) use ($parentRanks) {
            $rank = $parentRanks[$enId] ?? 0;
            return ($rank <= 0 || $rank >= 29999) ? PHP_INT_MAX : $rank;
        };
        $sorted = $roster;
        usort($sorted, function ($a, $b) use ($normalizedRank) {
            $rankA = $normalizedRank($a);
            $rankB = $normalizedRank($b);
            if ($rankA !== $rankB) {
                return $rankB - $rankA; // worst (highest) place first
            }
            return $b - $a; // tie -> higher entry id dropped
        });
        $droppedEnId = $sorted[0];
        $counting = array_values(array_filter($roster, fn ($enId) => $enId !== $droppedEnId));
    }

    $count = count($counting);
    $members = [];
    foreach ($counting as $enId) {
        $members[$enId] = $teamPoints / $count;
    }
    if ($droppedEnId !== null) {
        $members[$droppedEnId] = 0;
    }

    return ['members' => $members, 'warning' => false];
}

/**
 * Keep the top $cap positive values (all, when $cap is 0), summed.
 *
 * @param array<string,float|int> $valuesByClassification classification key => value
 * @return array{kept: array<string,float|int>, dropped: array<string,float|int>, total: float|int}
 */
function pl_points_combine(array $valuesByClassification, int $cap): array
{
    $positive = array_filter($valuesByClassification, fn ($v) => $v > 0);
    arsort($positive);

    if ($cap > 0 && count($positive) > $cap) {
        $kept = array_slice($positive, 0, $cap, true);
        $dropped = array_slice($positive, $cap, null, true);
    } else {
        $kept = $positive;
        $dropped = [];
    }

    return ['kept' => $kept, 'dropped' => $dropped, 'total' => array_sum($kept)];
}

/**
 * Sort by points desc then place asc; equal points share a rank.
 *
 * @param array $rows Each row needs 'points'; 'place' defaults to 0 when absent (club/voivodeship rows)
 */
function pl_points_rank(array $rows): array
{
    usort($rows, function ($a, $b) {
        if ($a['points'] !== $b['points']) {
            return $b['points'] <=> $a['points'];
        }
        return ($a['place'] ?? 0) <=> ($b['place'] ?? 0);
    });

    $rank = 0;
    $prevPoints = null;
    $position = 0;
    foreach ($rows as &$row) {
        $position++;
        if ($prevPoints === null || $row['points'] !== $prevPoints) {
            $rank = $position;
            $prevPoints = $row['points'];
        }
        $row['rank'] = $rank;
    }
    unset($row);

    return $rows;
}

/**
 * Group rows by 'category', rank each group, order the groups by the
 * category metadata's 'order' tuple.
 *
 * @param array $categoryMeta category => ['label' => string, 'order' => array]
 */
function pl_points_sectioned_rows(array $rows, array $categoryMeta): array
{
    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[$row['category']][] = $row;
    }

    $sections = [];
    foreach ($byCategory as $category => $categoryRows) {
        $meta = $categoryMeta[$category] ?? ['label' => $category, 'order' => [999, 999]];
        $sections[] = [
            'category' => $category,
            'label' => $meta['label'],
            'order' => $meta['order'],
            'rows' => pl_points_rank($categoryRows),
        ];
    }

    usort($sections, fn ($a, $b) => $a['order'] <=> $b['order']);

    return $sections;
}

/**
 * Per-athlete combine across the classifications feeding a COMBINED report
 * (or every classification, uncapped, when no COMBINED report is declared —
 * used for the CLUB rollup in that case). Shared by the COMBINED report and
 * the CLUB rollup so both agree on what was kept vs dropped by the cap.
 *
 * @return array<int, array{enId:int,name:string,code:string,category:string,club_id:int,club_code:string,club_name:string,values:array,places:array,kept:array,dropped:array,total:int|float,place:int}>
 */
function pl_points_combine_athletes(array $classified, array $classificationKeys, int $cap): array
{
    $byAthlete = [];
    foreach ($classificationKeys as $ck) {
        if (!isset($classified[$ck])) {
            continue;
        }
        foreach ($classified[$ck]['athlete_rows'] as $row) {
            $enId = $row['enId'];
            if (!isset($byAthlete[$enId])) {
                $byAthlete[$enId] = [
                    'enId' => $enId,
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'category' => $row['category'],
                    'club_id' => $row['club_id'],
                    'club_code' => $row['club_code'],
                    'club_name' => $row['club_name'],
                    'values' => [],
                    'raw_values' => [],
                    'places' => [],
                ];
            }
            $byAthlete[$enId]['values'][$ck] = $row['points'];
            // Pre-cutoff bracket value, for showing a cutoff-zeroed or cap-dropped
            // column crossed out instead of just disappearing (see build_combined_report).
            $byAthlete[$enId]['raw_values'][$ck] = $row['raw_points'] ?? $row['points'];
            $byAthlete[$enId]['places'][$ck] = $row['place'];
        }
    }

    $result = [];
    foreach ($byAthlete as $enId => $athlete) {
        $combine = pl_points_combine($athlete['values'], $cap);
        $bestPlace = null;
        foreach (array_keys($combine['kept']) as $ck) {
            $place = $athlete['places'][$ck];
            if ($bestPlace === null || $place < $bestPlace) {
                $bestPlace = $place;
            }
        }
        $result[$enId] = $athlete + [
            'kept' => $combine['kept'],
            'dropped' => $combine['dropped'],
            'total' => $combine['total'],
            'place' => $bestPlace ?? 0,
        ];
    }

    return $result;
}

/**
 * Club totals: post-cap athlete values for IND, exact team-value-minus-
 * dropped-shares for TEAM/MIXED (D5) — never a naive sum of individual
 * fractional shares.
 *
 * @return array<int, array{club_id:int,club_code:string,club_name:string,total:int|float}>
 */
function pl_points_compute_club_totals(array $classified, array $classificationKeys, array $athleteCombine): array
{
    $totals = [];
    $addClub = function ($clubId, $clubCode, $clubName, $amount) use (&$totals) {
        if (!isset($totals[$clubId])) {
            $totals[$clubId] = ['club_id' => $clubId, 'club_code' => $clubCode, 'club_name' => $clubName, 'total' => 0];
        }
        $totals[$clubId]['total'] += $amount;
    };

    foreach ($classificationKeys as $ck) {
        if (!isset($classified[$ck])) {
            continue;
        }
        $classification = $classified[$ck];

        if ($classification['subject'] === 'IND') {
            foreach ($classification['athlete_rows'] as $row) {
                $keptVal = $athleteCombine[$row['enId']]['kept'][$ck] ?? 0;
                if ($keptVal > 0) {
                    $addClub($row['club_id'], $row['club_code'], $row['club_name'], $keptVal);
                }
            }
            continue;
        }

        foreach ($classification['teams'] as $team) {
            if ($team['roster_empty']) {
                $addClub($team['club_id'], $team['club_code'], $team['club_name'], $team['points']);
                continue;
            }
            $positiveMembers = array_filter($team['members'], fn ($share) => $share > 0);
            $countingSize = count($positiveMembers);
            if ($countingSize === 0) {
                continue; // team's points were already zeroed (e.g. cutoff) — nothing to credit
            }
            $droppedCount = 0;
            foreach (array_keys($positiveMembers) as $enId) {
                if (!isset($athleteCombine[$enId])) {
                    // No attribution data for this member at all (e.g. a stale
                    // roster row with no matching Entries record) — not a cap
                    // decision, so don't treat their share as dropped.
                    continue;
                }
                if (!isset($athleteCombine[$enId]['kept'][$ck])) {
                    $droppedCount++;
                }
            }
            $droppedSum = $droppedCount * ($team['points'] / $countingSize);
            $addClub($team['club_id'], $team['club_code'], $team['club_name'], $team['points'] - $droppedSum);
        }
    }

    return array_values($totals);
}

function pl_points_build_separate_report(array $report, array $classified, array $categories): array
{
    $ck = $report['classification'];
    if (!isset($classified[$ck])) {
        return ['report' => null, 'rows_present' => false];
    }
    $classification = $classified[$ck];

    $isTeam = $classification['subject'] !== 'IND';
    $sourceRows = $isTeam ? $classification['teams'] : $classification['athlete_rows'];
    $categoryMeta = $isTeam ? $categories['team'] : $categories['individual'];

    $sections = pl_points_sectioned_rows($sourceRows, $categoryMeta);

    return [
        'report' => ['kind' => 'SEPARATE', 'label' => $report['label'], 'subject' => $classification['subject'], 'sections' => $sections],
        'rows_present' => !empty($sections),
    ];
}

function pl_points_build_combined_report(array $report, array $categories, array $athleteCombine): array
{
    $rows = [];
    foreach ($athleteCombine as $athlete) {
        // Show the athlete if they scored *something* in any listed classification,
        // even if every value was later zeroed by cutoff or excluded by the cap
        // (Suma = 0) — that is different from never having scored at all (a place
        // outside every bracket), which stays omitted.
        $everScored = array_filter($athlete['raw_values'], fn ($v) => $v > 0);
        if (empty($everScored)) {
            continue;
        }
        // Per classification: the raw (pre-cutoff, pre-cap) value, whether it
        // actually counted toward Suma (a positive raw value that was zeroed by
        // cutoff or excluded by the cap is 'dropped' — renderer crosses it out),
        // and the athlete's place in that classification.
        $columns = [];
        foreach ($report['classifications'] as $ck) {
            $raw = $athlete['raw_values'][$ck] ?? 0;
            $columns[$ck] = [
                'value' => $raw,
                'dropped' => $raw > 0 && !isset($athlete['kept'][$ck]),
                'place' => $athlete['places'][$ck] ?? null,
            ];
        }
        $rows[] = [
            'enId' => $athlete['enId'],
            'name' => $athlete['name'],
            'code' => $athlete['code'],
            'category' => $athlete['category'],
            'club_id' => $athlete['club_id'],
            'club_code' => $athlete['club_code'],
            'club_name' => $athlete['club_name'],
            'columns' => $columns,
            'points' => $athlete['total'],
            'place' => $athlete['place'],
        ];
    }

    $sections = pl_points_sectioned_rows($rows, $categories['individual']);

    return [
        'report' => ['kind' => 'COMBINED', 'label' => $report['label'], 'classifications' => $report['classifications'], 'sections' => $sections],
        'rows_present' => !empty($sections),
    ];
}

function pl_points_build_club_report(array $report, array $classified, array $capClassifications, array $athleteCombine, array $voivodeshipMap, bool $hasVoivodeshipReport): array
{
    $clubTotals = pl_points_compute_club_totals($classified, $capClassifications, $athleteCombine);

    $rows = [];
    foreach ($clubTotals as $club) {
        if ($club['total'] <= 0) {
            continue;
        }
        $voivodeship = $voivodeshipMap[$club['club_code']] ?? '';
        $rows[] = [
            'club_id' => $club['club_id'],
            'club_code' => $club['club_code'],
            'club_name' => $club['club_name'],
            'voivodeship' => $voivodeship !== '' ? $voivodeship : 'Nieprzypisane',
            'points' => $club['total'],
        ];
    }

    $ranked = pl_points_rank($rows);

    return [
        'report' => ['kind' => 'CLUB', 'label' => $report['label'], 'show_voivodeship' => $hasVoivodeshipReport, 'rows' => $ranked],
        'rows_present' => !empty($ranked),
        'club_rows' => $ranked,
    ];
}

function pl_points_build_voivodeship_report(array $report, array $clubRows): array
{
    $totals = [];
    foreach ($clubRows as $club) {
        if ($club['voivodeship'] === 'Nieprzypisane') {
            continue;
        }
        $totals[$club['voivodeship']] = ($totals[$club['voivodeship']] ?? 0) + $club['points'];
    }

    $rows = [];
    foreach ($totals as $voivodeship => $total) {
        $rows[] = ['voivodeship' => $voivodeship, 'points' => $total];
    }

    $ranked = pl_points_rank($rows);

    return [
        'report' => ['kind' => 'VOIVODESHIP', 'label' => $report['label'], 'rows' => $ranked],
        'rows_present' => !empty($ranked),
    ];
}

/**
 * Build the preset's declared reports from already-classified data.
 * A report that produces no rows is omitted entirely.
 *
 * @param array $categories ['individual' => [divclass => ['label','order']], 'team' => [EvCode => ['label','order']]]
 * @param array $voivodeshipMap CoCode => voivodeship name
 * @return array{reports: array, warnings: string[]}
 */
function pl_points_build_reports(array $preset, array $classified, array $categories, array $voivodeshipMap = []): array
{
    $warnings = [];
    foreach ($classified as $classification) {
        foreach ($classification['teams'] ?? [] as $team) {
            if ($team['roster_empty']) {
                $warnings[] = 'Brak składu drużyny: ' . $team['club_name'] . ' (' . $team['category'] . ')';
            }
        }
    }

    // Only the first COMBINED report is used to determine cap classifications
    // (none of the shipped presets declares more than one).
    $combinedReport = null;
    foreach ($preset['reports'] as $report) {
        if ($report['kind'] === 'COMBINED') {
            $combinedReport = $report;
            break;
        }
    }
    $clubReport = null;
    $voivodeshipReport = null;
    foreach ($preset['reports'] as $report) {
        if ($report['kind'] === 'CLUB') {
            $clubReport = $report;
        } elseif ($report['kind'] === 'VOIVODESHIP') {
            $voivodeshipReport = $report;
        }
    }
    $capClassifications = $combinedReport ? $combinedReport['classifications'] : array_keys($classified);
    $cap = $combinedReport ? $combinedReport['cap'] : 0;
    $athleteCombine = pl_points_combine_athletes($classified, $capClassifications, $cap);

    // Computed once, independent of the presets's declared display order (D6:
    // VOIVODESHIP may be listed before CLUB), since VOIVODESHIP is derived from
    // CLUB's rows regardless of which prints first.
    $clubBuilt = $clubReport
        ? pl_points_build_club_report($clubReport, $classified, $capClassifications, $athleteCombine, $voivodeshipMap, (bool) $voivodeshipReport)
        : ['report' => null, 'rows_present' => false, 'club_rows' => []];
    $voivodeshipBuilt = $voivodeshipReport
        ? pl_points_build_voivodeship_report($voivodeshipReport, $clubBuilt['club_rows'])
        : ['report' => null, 'rows_present' => false];

    $reports = [];

    foreach ($preset['reports'] as $report) {
        switch ($report['kind']) {
            case 'SEPARATE':
                $built = pl_points_build_separate_report($report, $classified, $categories);
                break;
            case 'COMBINED':
                $built = pl_points_build_combined_report($report, $categories, $athleteCombine);
                break;
            case 'CLUB':
                $built = $clubBuilt;
                break;
            case 'VOIVODESHIP':
                $built = $voivodeshipBuilt;
                break;
            default:
                $built = ['report' => null, 'rows_present' => false];
        }
        if ($built['rows_present']) {
            $reports[] = $built['report'];
        }
    }

    if (!empty($preset['min_participation'])) {
        $clubRows = $clubBuilt['club_rows'];
        $clubCount = count(array_unique(array_map(fn ($r) => $r['club_id'], $clubRows)));
        $voivCount = count(array_unique(array_filter(array_map(
            fn ($r) => $r['voivodeship'] === 'Nieprzypisane' ? null : $r['voivodeship'],
            $clubRows
        ))));
        $min = $preset['min_participation'];
        if ($clubCount < $min['clubs'] || $voivCount < $min['voivodeships']) {
            $warnings[] = sprintf(
                'Minimalna liczba uczestników nie została osiągnięta (%d klub(y/ów) z %d województw; wymagane: %d z %d) - zgodnie z regulaminem klasyfikacja jest nieważna.',
                $clubCount,
                $voivCount,
                $min['clubs'],
                $min['voivodeships']
            );
        }
    }

    return ['reports' => $reports, 'warnings' => $warnings];
}

/**
 * Orchestrate the loaders (Fun_PointsRanking.php) and the pure functions
 * above into the preset's final reports. See design.md "Calculation Engine".
 */
function pl_points_calculate($tourId, array $preset): array
{
    require_once __DIR__ . '/Fun_PointsRanking.php';

    $categories = pl_points_load_categories($tourId, $preset['scope']);
    $starters = pl_points_load_starters($tourId, $categories);
    $directory = pl_points_load_entries_directory($tourId);
    $voivodeshipMap = pl_points_get_voivodeship_map();

    $classified = [];

    foreach ($preset['classifications'] as $classKey => $classification) {
        $subject = $classification['subject'];
        $maxRankTo = max(array_column($classification['brackets'], 1));

        if ($subject === 'IND') {
            $rows = pl_points_load_individuals($tourId, $classification['source'], $categories);
            $rows = array_map(fn ($row) => [
                'enId' => $row['EnId'], 'name' => $row['Name'], 'code' => $row['EnCode'],
                'category' => $row['Category'], 'club_id' => $row['CoId'],
                'club_code' => $row['CoCode'], 'club_name' => $row['CoName'], 'place' => $row['Place'],
            ], $rows);

            foreach ($rows as &$row) {
                $row['points'] = pl_points_bracket($classification['brackets'], $row['place']);
                // Captured before cutoff can zero 'points', so a cutoff-zeroed
                // row can still show what it would have scored (crossed out).
                $row['raw_points'] = $row['points'];
            }
            unset($row);

            if ($classification['cutoff']) {
                $rows = pl_points_apply_cutoff($rows, $maxRankTo, $starters['individual']);
            }

            $classified[$classKey] = ['subject' => 'IND', 'athlete_rows' => $rows];
            continue;
        }

        $mixed = $subject === 'MIXED';
        $teamRows = pl_points_load_teams($tourId, $classification['source'], $mixed, $categories);
        $teamRows = array_map(fn ($row) => [
            'category' => $row['Event'], 'place' => $row['Place'], 'club_id' => $row['CoId'],
            'club_code' => $row['CoCode'], 'club_name' => $row['CoName'], 'sub_team' => $row['SubTeam'],
        ], $teamRows);

        if (!empty($preset['one_team_per_club'])) {
            $teamRows = array_values(array_filter($teamRows, fn ($r) => $r['sub_team'] === 0));
        }

        foreach ($teamRows as &$row) {
            $row['points'] = pl_points_bracket($classification['brackets'], $row['place']);
            $row['raw_points'] = $row['points']; // pre-cutoff, see the IND branch above
        }
        unset($row);

        if ($classification['cutoff']) {
            $teamRows = pl_points_apply_cutoff($teamRows, $maxRankTo, $starters['team']);
        }

        $rosters = pl_points_load_rosters($tourId, $classification['source'], array_map(fn ($r) => [
            'ClubId' => $r['club_id'], 'SubTeam' => $r['sub_team'], 'Event' => $r['category'],
        ], $teamRows));

        $teams = [];
        $athleteRows = [];
        $threeOfFour = !empty($preset['three_of_four']);

        foreach ($teamRows as $row) {
            $rosterKey = $row['club_id'] . '_' . $row['sub_team'] . '_' . $row['category'];
            $roster = $rosters[$rosterKey] ?? [];

            $parentRanks = ($threeOfFour && count($roster) === 4)
                ? pl_points_load_parent_ranks($tourId, $roster, $row['category'])
                : [];

            $split = pl_points_split_team($row['points'], $roster, $threeOfFour, $parentRanks);
            // Same roster/parentRanks -> identical counting-vs-dropped split; only
            // the numerator differs, giving each member's pre-cutoff raw share.
            $rawSplit = pl_points_split_team($row['raw_points'], $roster, $threeOfFour, $parentRanks);

            // A mixed pair is identified by its two athletes' names, not the club
            // (a SEPARATE(mix) table would otherwise just repeat the club name).
            $memberNames = [];
            foreach ($roster as $enId) {
                if (isset($directory[$enId])) {
                    $memberNames[] = $directory[$enId]['name'];
                }
            }

            $teams[] = [
                'category' => $row['category'], 'club_id' => $row['club_id'], 'club_code' => $row['club_code'],
                'club_name' => $row['club_name'], 'points' => $row['points'], 'place' => $row['place'],
                'members' => $split['members'], 'roster_empty' => $split['warning'],
                'name' => $mixed && !empty($memberNames) ? implode(' / ', $memberNames) : null,
            ];

            foreach ($split['members'] as $enId => $share) {
                if (!isset($directory[$enId])) {
                    continue;
                }
                $entry = $directory[$enId];
                $athleteRows[] = [
                    'enId' => $enId, 'name' => $entry['name'], 'code' => $entry['code'],
                    'category' => $entry['category'], 'club_id' => $entry['club_id'],
                    'club_code' => $entry['club_code'], 'club_name' => $entry['club_name'],
                    'points' => $share, 'raw_points' => $rawSplit['members'][$enId] ?? $share, 'place' => $row['place'],
                ];
            }
        }

        $classified[$classKey] = ['subject' => $subject, 'athlete_rows' => $athleteRows, 'teams' => $teams];
    }

    return pl_points_build_reports($preset, $classified, $categories, $voivodeshipMap);
}
