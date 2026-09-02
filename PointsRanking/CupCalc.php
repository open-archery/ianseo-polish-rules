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

/**
 * Mark printed next to rows that share a rank. A shared place carries no mark:
 * the regulation simply states no further criterion for that series, which the
 * equal rank already says.
 */
const PL_CUP_MARK_LABELS = ['BARRAGE' => 'baraż'];

/**
 * Series names, in the genitive the cup titles are written in, keyed by the age
 * part of the class code. Recurve series are named by age alone; the compound
 * and barebow cups are named by their bow, per PZŁucz's own annex titles.
 */
const PL_CUP_AGE_SERIES = [
    '' => ['M' => 'Seniorów', 'W' => 'Seniorek'],
    'U24' => ['M' => 'Młodzieżowców', 'W' => 'Młodzieżowczyń'],
    'U21' => ['M' => 'Juniorów', 'W' => 'Juniorek'],
    'U18' => ['M' => 'Juniorów młodszych', 'W' => 'Juniorek młodszych'],
    'U15' => ['M' => 'Młodzików', 'W' => 'Młodziczek'],
    'U12' => ['M' => 'Dzieci - chłopców', 'W' => 'Dzieci - dziewcząt'],
    '50' => ['M' => 'Masters - mężczyzn', 'W' => 'Masters - kobiet'],
];

/** Divisions whose cup is named by the bow rather than by the age series. */
const PL_CUP_DIVISION_SERIES = ['C' => 'łuków bloczkowych', 'B' => 'łuków barebow'];

/** Gender phrase for the bow-named series, which carry no gendered age noun. */
const PL_CUP_INDIVIDUAL_GENDER = ['M' => 'indywidualna mężczyzn', 'W' => 'indywidualna kobiet'];

/** Opening of every cup report title. */
const PL_CUP_TITLE_PREFIX = 'Klasyfikacja generalna Pucharu Polski';

/**
 * Series names as a diploma spells them: title case, and in the locative phrase
 * the diploma builds — "za zajęcie 1 miejsca **w Pucharze Polski Juniorów
 * Młodszych 2026**". Recurve cups are named by age and gender, the compound and
 * barebow cups by their bow, with gender left to the "w kategorii" line.
 */
const PL_CUP_DIPLOMA_AGE_SERIES = [
    '' => ['M' => 'Seniorów', 'W' => 'Seniorek'],
    'U24' => ['M' => 'Młodzieżowców', 'W' => 'Młodzieżowczyń'],
    'U21' => ['M' => 'Juniorów', 'W' => 'Juniorek'],
    'U18' => ['M' => 'Juniorów Młodszych', 'W' => 'Juniorek Młodszych'],
    'U15' => ['M' => 'Młodzików', 'W' => 'Młodziczek'],
    'U12' => ['M' => 'Dzieci', 'W' => 'Dzieci'],
    '50' => ['M' => 'Masters', 'W' => 'Masters'],
];

/** Bow-named cups, in the same title case. */
const PL_CUP_DIPLOMA_BOW = ['C' => 'Łuków Bloczkowych', 'B' => 'Łuków Barebow'];

/** Opening of every diploma's competition name, in the locative. */
const PL_CUP_DIPLOMA_PREFIX = 'Pucharze Polski';

/**
 * The competition name printed on one category's cup diplomas, e.g.
 *   "Pucharze Polski Juniorek Młodszych 2026"
 *   "Pucharze Polski Łuków Bloczkowych 2026"
 *   "Pucharze Polski Mikstów Łuków Barebow 2026"
 *
 * Derived, not configured: every part of it is fixed by the category and the
 * edition, so there is nothing for an operator to type or mistype.
 */
function pl_cup_diploma_competition_name($classification, $category, $edition)
{
    $parts = pl_cup_split_category($classification, $category);
    $series = $parts ? (PL_CUP_DIPLOMA_AGE_SERIES[$parts['age']] ?? null) : null;

    if ($parts === null || $series === null) {
        return trim(PL_CUP_DIPLOMA_PREFIX . ' ' . $edition);
    }

    $bow = PL_CUP_DIPLOMA_BOW[$parts['division']] ?? '';
    $name = PL_CUP_DIPLOMA_PREFIX;

    if ($classification === 'mix') {
        // The pair competes for the club, so the name carries no gender.
        $name .= ' Mikstów';
        $name .= $bow !== '' ? ' ' . $bow : '';
        $name .= $parts['age'] !== '' ? ' ' . $series['M'] : '';
        return $name . ' ' . $edition;
    }

    if ($bow === '') {
        // Recurve: the age series is the cup's name and carries the gender.
        return $name . ' ' . $series[$parts['gender']] . ' ' . $edition;
    }

    // Compound and barebow are named by the bow; only a younger class needs its
    // series added, so that two sections cannot share one name.
    $name .= ' ' . $bow;
    $name .= $parts['age'] !== '' ? ' ' . $series[$parts['gender']] : '';
    return $name . ' ' . $edition;
}

/**
 * Split a category code into its division, age and gender parts.
 *
 * Individual codes are division + class ("RU18W"); mixed event codes are
 * division + age + "X" ("BX", "RU21X") and have no gender of their own.
 *
 * @return array{division:string, age:string, gender:string}|null null when the
 *   code does not follow the module's own scheme (lib.php)
 */
function pl_cup_split_category($classification, $category)
{
    $division = substr($category, 0, 1);
    $rest = substr($category, 1);

    if ($classification === 'mix') {
        if (substr($rest, -1) !== 'X') {
            return null;
        }
        return ['division' => $division, 'age' => substr($rest, 0, -1), 'gender' => 'M'];
    }

    $gender = substr($rest, -1);
    if ($gender !== 'M' && $gender !== 'W') {
        return null;
    }
    return ['division' => $division, 'age' => substr($rest, 0, -1), 'gender' => $gender];
}

/**
 * Title of one category's cup report, e.g.
 *   "Klasyfikacja generalna Pucharu Polski Juniorek młodszych"
 *   "Klasyfikacja generalna Pucharu Polski łuków barebow - indywidualna mężczyzn"
 *   "Klasyfikacja generalna Pucharu Polski Seniorów - miksty"
 *
 * @param string $fallbackLabel the tournament's own category label, used when the
 *   code does not follow the module's scheme
 */
function pl_cup_series_title($classification, $category, $fallbackLabel = '')
{
    $parts = pl_cup_split_category($classification, $category);
    $series = $parts ? (PL_CUP_AGE_SERIES[$parts['age']] ?? null) : null;

    if ($parts === null || $series === null) {
        return PL_CUP_TITLE_PREFIX . ' - ' . ($fallbackLabel !== '' ? $fallbackLabel : $category);
    }

    $isMixed = $classification === 'mix';
    $bow = PL_CUP_DIVISION_SERIES[$parts['division']] ?? null;

    if ($bow === null) {
        // Recurve: the age series is the cup's name and already carries the gender.
        return PL_CUP_TITLE_PREFIX . ' ' . $series[$parts['gender']] . ($isMixed ? ' - miksty' : '');
    }

    $title = PL_CUP_TITLE_PREFIX . ' ' . $bow;
    if ($parts['age'] !== '') {
        // Only the senior cup is named by the bow alone; younger classes still
        // need their age series to tell the sections apart.
        $title .= ' ' . $series[$parts['gender']];
        return $title . ($isMixed ? ' - miksty' : '');
    }
    if ($isMixed) {
        return $title . ' - miksty';
    }
    return $title . ' - ' . PL_CUP_INDIVIDUAL_GENDER[$parts['gender']];
}

/**
 * The series alone, without the report-title opening: "Juniorek młodszych",
 * "łuków barebow - indywidualna mężczyzn", "Seniorów - miksty". Derived from the
 * category code, so it also names categories this competition does not run.
 */
function pl_cup_series_label($classification, $category, $fallbackLabel = '')
{
    $title = pl_cup_series_title($classification, $category, $fallbackLabel);
    $prefix = PL_CUP_TITLE_PREFIX . ' ';
    if (strpos($title, $prefix) === 0) {
        return substr($title, strlen($prefix));
    }
    return substr($title, strlen(PL_CUP_TITLE_PREFIX . ' - '));
}

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

/**
 * Replace an aggregated row's display name and club with the current
 * competition's own, where it knows the competitor. A row it does not know —
 * an athlete who scored earlier and did not enter this competition — keeps what
 * their most recent round carried, the only source available for them.
 */
function pl_cup_apply_directory(array $rows, array $directory)
{
    foreach ($rows as &$row) {
        $current = $directory[$row['classification']][$row['identity']] ?? null;
        if ($current === null) {
            continue;
        }
        if (trim((string) ($current['name'] ?? '')) !== '') {
            $row['name'] = $current['name'];
        }
        if (trim((string) ($current['club_name'] ?? '')) !== '') {
            $row['club_name'] = $current['club_name'];
        }
    }
    unset($row);

    return $rows;
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
 * @param array $directory names and clubs as the current competition holds them,
 *   keyed by identity — they win over whatever an import carried, since the same
 *   club is written differently by different hosts
 * @return array ['ind' => ['label' => string, 'sections' => [...]], 'mix' => [...]]
 */
function pl_cup_build_classifications(array $roundRows, array $barrages, array $categoryMeta, $barrageAllowed, array $directory = [])
{
    $aggregated = pl_cup_apply_directory(pl_cup_aggregate($roundRows), $directory);

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
            // Only categories this competition actually runs: the stored rounds
            // cover the whole cup, but a junior competition prints junior
            // classifications, not the barebow and compound ones it imported.
            if (!isset($categoryMeta[$classification][$category])) {
                continue;
            }
            $meta = $categoryMeta[$classification][$category];
            $rule = pl_cup_tiebreak_rule($classification, $category);
            $sections[] = [
                'category' => $category,
                'label' => $meta['label'],
                'title' => pl_cup_series_title($classification, $category, $meta['label']),
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
