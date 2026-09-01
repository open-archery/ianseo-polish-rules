<?php
/**
 * CupCalc.php — pure computation for the Puchar Polski cup classification.
 *
 * No database access: stored round rows (see Fun_Cup.php for their shape) in,
 * ranked classifications out. Aggregation, the per-series tie-break chain and
 * the snapshot diff all live here so they are unit-testable without ianseo.
 */

/**
 * Tie-break chain per cup series, exactly as the 2026 annexes state it (D6).
 *
 * THE single edit point for tie-breaking: PZŁucz has been asked to interpret the
 * series where the annex is silent, and a change to the rules is an edit to this
 * table alone. First matching entry wins; an empty 'classes' matches every class
 * of that division.
 *
 *   steps: 'place' = best (lowest) place in any round, 'qual' = highest
 *          qualification score in any round; applied in the listed order.
 *   terminator: what happens to rows still equal afterwards — 'BARRAGE' when the
 *          annex prescribes a shoot-off, 'SHARED' when it states nothing (those
 *          rows share a rank; the system must not invent a step).
 */
const PL_CUP_TIEBREAK_RULES = [
    // Puchar Polski Juniorów — points, best place, 2x70 m score; no baraż stated.
    ['classification' => 'ind', 'division' => 'R', 'classes' => ['U21M', 'U21W'], 'steps' => ['place', 'qual'], 'terminator' => 'SHARED'],
    // Puchar Polski Juniorów Młodszych — same chain over 2x60 m, then a baraż.
    ['classification' => 'ind', 'division' => 'R', 'classes' => ['U18M', 'U18W'], 'steps' => ['place', 'qual'], 'terminator' => 'BARRAGE'],
    // Puchar Polski Seniorów — nothing stated.
    ['classification' => 'ind', 'division' => 'R', 'classes' => ['M', 'W'], 'steps' => [], 'terminator' => 'SHARED'],
    // Puchar Polski łuków barebow — 2x50 m score alone (no place step), then a baraż.
    ['classification' => 'ind', 'division' => 'B', 'classes' => [], 'steps' => ['qual'], 'terminator' => 'BARRAGE'],
    // Puchar Polski łuków bloczkowych — nothing stated, not even in the Final Round regulation.
    ['classification' => 'ind', 'division' => 'C', 'classes' => [], 'steps' => [], 'terminator' => 'SHARED'],
];

/** Every series the table above does not name — including mixed — states no step. */
const PL_CUP_TIEBREAK_DEFAULT = ['steps' => [], 'terminator' => 'SHARED'];

/** Mark printed next to rows that share a rank. */
const PL_CUP_MARK_LABELS = ['BARRAGE' => 'baraż', 'SHARED' => 'miejsce dzielone'];

/**
 * @param string $classification 'ind' | 'mix'
 * @param string $category individual division+class (e.g. "RU21M") or a mixed EvCode
 * @return array{steps: string[], terminator: string}
 */
function pl_cup_tiebreak_rule($classification, $category)
{
    foreach (PL_CUP_TIEBREAK_RULES as $rule) {
        if ($rule['classification'] !== $classification) {
            continue;
        }
        if ($rule['division'] !== substr($category, 0, 1)) {
            continue;
        }
        if (!empty($rule['classes']) && !in_array(substr($category, 1), $rule['classes'], true)) {
            continue;
        }
        return ['steps' => $rule['steps'], 'terminator' => $rule['terminator']];
    }
    return PL_CUP_TIEBREAK_DEFAULT;
}

/**
 * Group round rows into one row per cup competitor.
 *
 * Name and club come from the competitor's most recent round, so an athlete
 * absent from the final round still shows the data of the last round they shot.
 *
 * @param array $roundRows rows as stored, each with 'round'
 * @return array list of ['classification','category','identity','name','club_name',
 *   'rounds'=>[round=>points], 'places'=>[round=>place], 'quals'=>[round=>qual],
 *   'total','best_place','best_qual']
 */
function pl_cup_aggregate(array $roundRows)
{
    $byKey = [];

    foreach ($roundRows as $row) {
        $key = $row['classification'] . '|' . $row['category'] . '|' . $row['identity'];
        if (!isset($byKey[$key])) {
            $byKey[$key] = [
                'classification' => $row['classification'],
                'category' => $row['category'],
                'identity' => $row['identity'],
                'name' => $row['name'],
                'club_name' => $row['club_name'],
                'rounds' => [],
                'places' => [],
                'quals' => [],
                'total' => 0,
                'best_place' => 0,
                'best_qual' => 0,
                'last_round' => 0,
            ];
        }
        $agg = &$byKey[$key];
        $round = intval($row['round']);

        $agg['rounds'][$round] = intval($row['points']);
        $agg['places'][$round] = intval($row['place']);
        $agg['quals'][$round] = intval($row['qual']);
        $agg['total'] += intval($row['points']);

        $place = intval($row['place']);
        if ($place > 0 && ($agg['best_place'] === 0 || $place < $agg['best_place'])) {
            $agg['best_place'] = $place;
        }
        if (intval($row['qual']) > $agg['best_qual']) {
            $agg['best_qual'] = intval($row['qual']);
        }
        if ($round >= $agg['last_round']) {
            $agg['last_round'] = $round;
            $agg['name'] = $row['name'];
            $agg['club_name'] = $row['club_name'];
        }
        unset($agg);
    }

    return array_values($byKey);
}

/** Comparator over the steps the regulation actually states for this series. */
function pl_cup_compare_regulation(array $a, array $b, array $steps)
{
    $cmp = $b['total'] <=> $a['total'];
    if ($cmp !== 0) {
        return $cmp;
    }

    foreach ($steps as $step) {
        if ($step === 'place') {
            // No place at all sorts last, never first.
            $pa = $a['best_place'] > 0 ? $a['best_place'] : PHP_INT_MAX;
            $pb = $b['best_place'] > 0 ? $b['best_place'] : PHP_INT_MAX;
            $cmp = $pa <=> $pb;
        } elseif ($step === 'qual') {
            $cmp = $b['best_qual'] <=> $a['best_qual'];
        } else {
            $cmp = 0;
        }
        if ($cmp !== 0) {
            return $cmp;
        }
    }

    return 0;
}

/**
 * Rank one category's aggregated rows.
 *
 * Rows still equal after the stated steps share a rank and carry the series'
 * mark. Where the annex prescribes a baraż and the final round is stored, a
 * recorded shoot-off order (D6a) separates them as the last step; rows without a
 * recorded position stay tied and keep the "baraż" mark.
 *
 * @param array $barrages "classification|category|identity" => order (1 = winner)
 * @param bool $barrageAllowed the edition's final round is stored
 * @return array rows with 'rank', 'tie_mark' ('' | 'SHARED' | 'BARRAGE'),
 *   'barrage_resolved' and 'tie_group' (-1 when the row is not tied)
 */
/**
 * The shoot-off position recorded for one row, or null when none is.
 *
 * @param array $barrages "classification|category|identity" => order (1 = winner)
 */
function pl_cup_barrage_order(array $row, array $barrages)
{
    $order = $barrages[$row['classification'] . '|' . $row['category'] . '|' . $row['identity']] ?? 0;
    return $order > 0 ? $order : null;
}

function pl_cup_rank_category(array $rows, array $rule, array $barrages, $barrageAllowed)
{
    usort($rows, fn ($a, $b) => pl_cup_compare_regulation($a, $b, $rule['steps']));

    $useBarrage = $barrageAllowed && $rule['terminator'] === 'BARRAGE';

    // Groups of rows the regulation itself cannot separate.
    $groups = [];
    foreach ($rows as $row) {
        $last = count($groups) - 1;
        if ($last >= 0 && pl_cup_compare_regulation($groups[$last][0], $row, $rule['steps']) === 0) {
            $groups[$last][] = $row;
            continue;
        }
        $groups[] = [$row];
    }

    $ranked = [];
    $position = 0;
    $groupIndex = 0;

    foreach ($groups as $group) {
        $tied = count($group) > 1;
        $groupId = $tied ? $groupIndex++ : -1;

        // Sub-groups that still share a rank after the shoot-off step.
        $blocks = [$group];
        if ($tied && $useBarrage) {
            // Unrecorded rows sort last and stay tied among themselves.
            $orderOf = fn (array $row) => pl_cup_barrage_order($row, $barrages) ?? PHP_INT_MAX;
            usort($group, fn ($a, $b) => $orderOf($a) <=> $orderOf($b));

            $blocks = [];
            foreach ($group as $row) {
                $last = count($blocks) - 1;
                if ($last >= 0 && $orderOf($blocks[$last][0]) === $orderOf($row)) {
                    $blocks[$last][] = $row;
                    continue;
                }
                $blocks[] = [$row];
            }
        }

        foreach ($blocks as $block) {
            $rank = $position + 1;
            $stillTied = count($block) > 1;
            // Only a recorded position resolves a row. The last row of a
            // partially recorded group also ends up alone in its block, but
            // nobody shot it off — it keeps the "baraż" mark.
            $recorded = $tied && $useBarrage && pl_cup_barrage_order($block[0], $barrages) !== null;
            $resolved = $recorded && !$stillTied;

            foreach ($block as $row) {
                $row['rank'] = $rank;
                $row['tie_mark'] = ($stillTied || ($tied && $useBarrage && !$recorded)) ? $rule['terminator'] : '';
                $row['barrage_resolved'] = $resolved;
                $row['tie_group'] = $groupId;
                $ranked[] = $row;
                $position++;
            }
        }
    }

    return $ranked;
}

/**
 * Build both cup classifications, sectioned per category in the round reports'
 * own order.
 *
 * @param array $categoryMeta ['ind' => [code => ['label','order']], 'mix' => [...]]
 * @return array ['ind' => ['label' => string, 'sections' => [...]], 'mix' => [...]]
 */
function pl_cup_build_classifications(array $roundRows, array $barrages, array $categoryMeta, $barrageAllowed)
{
    $aggregated = pl_cup_aggregate($roundRows);

    $labels = ['ind' => 'Klasyfikacja indywidualna Pucharu Polski', 'mix' => 'Klasyfikacja mikstów Pucharu Polski'];
    $out = [];

    foreach (['ind', 'mix'] as $classification) {
        $byCategory = [];
        foreach ($aggregated as $row) {
            // Stored rounds also hold placed rows that scored nothing (they carry
            // the place and qualification score the tie-break may need); someone
            // who never scored in any round is not classified.
            if ($row['classification'] === $classification && $row['total'] > 0) {
                $byCategory[$row['category']][] = $row;
            }
        }

        $sections = [];
        foreach ($byCategory as $category => $rows) {
            $meta = $categoryMeta[$classification][$category] ?? ['label' => $category, 'order' => [999, 999]];
            $rule = pl_cup_tiebreak_rule($classification, $category);
            $sections[] = [
                'category' => $category,
                'label' => $meta['label'],
                'order' => $meta['order'],
                'terminator' => $rule['terminator'],
                'rows' => pl_cup_rank_category($rows, $rule, $barrages, $barrageAllowed),
            ];
        }

        usort($sections, fn ($a, $b) => $a['order'] <=> $b['order']);

        $out[$classification] = ['label' => $labels[$classification], 'sections' => $sections];
    }

    return $out;
}

/**
 * How many rows differ between a freshly built snapshot and the stored one
 * (added, removed or changed). Rebuilt every view rather than checksummed, so a
 * change in the builder cannot make an old checksum lie (D5).
 */
function pl_cup_diff_snapshot(array $fresh, array $stored)
{
    $key = fn ($row) => $row['classification'] . '|' . $row['category'] . '|' . $row['identity'];
    $comparable = fn ($row) => [
        (string) $row['name'],
        (string) $row['club_name'],
        intval($row['place']),
        intval($row['points']),
        intval($row['qual']),
    ];

    $freshByKey = [];
    foreach ($fresh as $row) {
        $freshByKey[$key($row)] = $comparable($row);
    }
    $storedByKey = [];
    foreach ($stored as $row) {
        $storedByKey[$key($row)] = $comparable($row);
    }

    $differing = 0;
    foreach ($freshByKey as $k => $values) {
        if (!isset($storedByKey[$k]) || $storedByKey[$k] !== $values) {
            $differing++;
        }
    }
    foreach ($storedByKey as $k => $values) {
        if (!isset($freshByKey[$k])) {
            $differing++;
        }
    }

    return $differing;
}
