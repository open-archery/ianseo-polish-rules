<?php
/**
 * Fun_PointsRanking.php — data layer for the PL points-ranking module.
 *
 * Auto-installs PLPointsTournamentConfig / PLVoivodeshipMap, reads the
 * per-tournament preset selection and the club→voivodeship map, and loads
 * ianseo's own tables (Individuals, Teams, TeamComponent, ...) into the
 * plain arrays PointsRankingCalc.php's pure functions consume.
 */

/** Preferred render order for divisions; unknown divisions sort after, alphabetically (D3). */
const PL_RANKING_DIVISION_ORDER = ['R' => 0, 'C' => 1, 'B' => 2];

/** Column header for a classification key in a COMBINED table (HTML and PDF). */
function pl_points_classification_label($classKey)
{
    $labels = ['ind' => 'Indywidualnie', 'tea' => 'Drużynowo', 'mix' => 'Mikst'];
    return $labels[$classKey] ?? $classKey;
}

/** Abbreviated form for the per-classification "M." / "Pkt." column pair headers. */
function pl_points_classification_short_label($classKey)
{
    $labels = ['ind' => 'ind.', 'tea' => 'zesp.', 'mix' => 'mikst'];
    return $labels[$classKey] ?? $classKey;
}

/**
 * Format a points value to at most 2 decimals, comma separator, no trailing
 * ",00" on whole values (spec "PDF report generation").
 */
function pl_points_format_number($value)
{
    $formatted = number_format((float) $value, 2, ',', '');
    $formatted = rtrim($formatted, '0');
    return rtrim($formatted, ',');
}

// --- Auto-install ------------------------------------------------------

function pl_points_ensure_tables()
{
    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLPointsTournamentConfig'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE PLPointsTournamentConfig (
            PltcTournament INT NOT NULL,
            PltcPresetKey VARCHAR(32) NOT NULL DEFAULT '',
            PRIMARY KEY (PltcTournament)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);

    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLVoivodeshipMap'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE PLVoivodeshipMap (
            PlvmCoCode VARCHAR(10) NOT NULL,
            PlvmVoivodeship VARCHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY (PlvmCoCode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);
}

// --- Tournament preset selection ----------------------------------------

function pl_points_get_tournament_preset($tourId)
{
    $Rs = safe_r_sql("SELECT PltcPresetKey FROM PLPointsTournamentConfig WHERE PltcTournament = " . intval($tourId));
    $key = null;
    if (safe_num_rows($Rs) > 0) {
        $row = safe_fetch($Rs);
        $key = $row->PltcPresetKey;
    }
    safe_free_result($Rs);
    return $key;
}

function pl_points_set_tournament_preset($tourId, $presetKey)
{
    $tourId = intval($tourId);

    $Rs = safe_r_sql("SELECT PltcTournament FROM PLPointsTournamentConfig WHERE PltcTournament = " . $tourId);
    $exists = safe_num_rows($Rs) > 0;
    safe_free_result($Rs);

    if ($exists) {
        safe_w_sql("UPDATE PLPointsTournamentConfig SET PltcPresetKey = " . StrSafe_DB($presetKey) . " WHERE PltcTournament = " . $tourId);
    } else {
        safe_w_sql("INSERT INTO PLPointsTournamentConfig (PltcTournament, PltcPresetKey) VALUES (" . $tourId . ", " . StrSafe_DB($presetKey) . ")");
    }
}

// --- Voivodeship map ------------------------------------------------------

function pl_points_get_voivodeship_map()
{
    $map = [];
    $Rs = safe_r_sql("SELECT PlvmCoCode, PlvmVoivodeship FROM PLVoivodeshipMap");
    while ($row = safe_fetch($Rs)) {
        $map[$row->PlvmCoCode] = $row->PlvmVoivodeship;
    }
    safe_free_result($Rs);
    return $map;
}

function pl_points_save_voivodeship($coCode, $voivodeship)
{
    $coCode = trim($coCode);
    if ($coCode === '') {
        return;
    }
    $voivodeship = trim($voivodeship);

    $Rs = safe_r_sql("SELECT PlvmCoCode FROM PLVoivodeshipMap WHERE PlvmCoCode = " . StrSafe_DB($coCode));
    $exists = safe_num_rows($Rs) > 0;
    safe_free_result($Rs);

    if ($voivodeship === '') {
        if ($exists) {
            safe_w_sql("DELETE FROM PLVoivodeshipMap WHERE PlvmCoCode = " . StrSafe_DB($coCode));
        }
        return;
    }

    // Reject anything not one of the 16 real voivodeships — in particular the
    // "Nieprzypisane" sentinel pl_points_build_club_report() uses for "unmapped",
    // which would otherwise silently vanish from every VOIVODESHIP total if stored.
    if (!in_array($voivodeship, PL_VOIVODESHIPS, true)) {
        return;
    }

    if ($exists) {
        safe_w_sql("UPDATE PLVoivodeshipMap SET PlvmVoivodeship = " . StrSafe_DB($voivodeship) . " WHERE PlvmCoCode = " . StrSafe_DB($coCode));
    } else {
        safe_w_sql("INSERT INTO PLVoivodeshipMap (PlvmCoCode, PlvmVoivodeship) VALUES (" . StrSafe_DB($coCode) . ", " . StrSafe_DB($voivodeship) . ")");
    }
}

// --- Shared division/class labels & order (D3) ---------------------------
// Formerly duplicated as pl_cup_ranking_get_div_labels() in
// CupRanking/Fun_CupRanking.php (feat/cup-ranking, not yet merged here);
// this is the shared home, keyed by DivId.ClId with an added sort order.

/**
 * @return array<string, array{division:string,class:string,label:string,order:array}>
 *   keyed by DivId . ClId (e.g. "RU21M")
 */
function pl_ranking_div_labels($tourId)
{
    $tourId = intval($tourId);

    $divs = [];
    $Rs = safe_r_sql("SELECT DivId, DivDescription FROM Divisions WHERE DivTournament = $tourId AND DivAthlete = 1");
    while ($row = safe_fetch($Rs)) {
        $divs[$row->DivId] = $row->DivDescription;
    }
    safe_free_result($Rs);

    $classes = [];
    $Rs = safe_r_sql("SELECT ClId, ClDescription, ClViewOrder FROM Classes WHERE ClTournament = $tourId AND ClAthlete = 1");
    while ($row = safe_fetch($Rs)) {
        $classes[$row->ClId] = ['desc' => $row->ClDescription, 'order' => intval($row->ClViewOrder)];
    }
    safe_free_result($Rs);

    $divKeys = array_keys($divs);
    usort($divKeys, function ($a, $b) {
        $oa = PL_RANKING_DIVISION_ORDER[$a] ?? 999;
        $ob = PL_RANKING_DIVISION_ORDER[$b] ?? 999;
        if ($oa !== $ob) {
            return $oa - $ob;
        }
        return strcmp($a, $b);
    });
    $divOrder = array_flip($divKeys);

    $labels = [];
    foreach ($divs as $divId => $divDesc) {
        foreach ($classes as $clId => $cl) {
            $labels[$divId . $clId] = [
                'division' => $divId,
                'class' => $clId,
                'label' => trim($divDesc . ' ' . $cl['desc']),
                'order' => [$divOrder[$divId], $cl['order']],
            ];
        }
    }
    return $labels;
}

// --- Categories (scope-filtered sections) ---------------------------------

/** Whether a division/class pair falls inside a preset's scope declaration. */
function pl_points_in_scope($div, $class, array $scope)
{
    $divs = $scope['divisions'] ?? [];
    $classes = $scope['classes'] ?? [];
    if (!empty($divs) && !in_array($div, $divs, true)) {
        return false;
    }
    if (!empty($classes) && !in_array($class, $classes, true)) {
        return false;
    }
    return true;
}

/**
 * @return array{individual: array, team: array} category => ['division'?,'class'?,'label','order']
 */
function pl_points_load_categories($tourId, array $scope)
{
    $tourId = intval($tourId);
    $divLabels = pl_ranking_div_labels($tourId);

    $individual = [];
    $Rs = safe_r_sql("
        SELECT DISTINCT EnDivision, EnClass FROM Entries
        WHERE EnTournament = $tourId AND EnDivision != '' AND EnClass != ''
    ");
    while ($row = safe_fetch($Rs)) {
        if (!pl_points_in_scope($row->EnDivision, $row->EnClass, $scope)) {
            continue;
        }
        $key = $row->EnDivision . $row->EnClass;
        $individual[$key] = $divLabels[$key] ?? ['division' => $row->EnDivision, 'class' => $row->EnClass, 'label' => $key, 'order' => [999, 999]];
    }
    safe_free_result($Rs);

    $team = [];
    $Rs = safe_r_sql("
        SELECT EventClass.EcCode AS EvCode, EventClass.EcDivision AS DivCode, EventClass.EcClass AS Class
        FROM EventClass
        INNER JOIN Events ON Events.EvCode = EventClass.EcCode
            AND Events.EvTournament = EventClass.EcTournament AND Events.EvTeamEvent = 1
        WHERE EventClass.EcTournament = $tourId
    ");
    $teamPairs = [];
    while ($row = safe_fetch($Rs)) {
        $teamPairs[$row->EvCode][] = [$row->DivCode, $row->Class];
    }
    safe_free_result($Rs);

    foreach ($teamPairs as $evCode => $pairs) {
        $inScopePairs = array_values(array_filter($pairs, fn ($p) => pl_points_in_scope($p[0], $p[1], $scope)));
        if (empty($inScopePairs)) {
            continue;
        }
        // Representative pair for the section label/order when an event maps to several.
        usort($inScopePairs, function ($a, $b) use ($divLabels) {
            $ia = $divLabels[$a[0] . $a[1]]['order'] ?? [999, 999];
            $ib = $divLabels[$b[0] . $b[1]]['order'] ?? [999, 999];
            return $ia <=> $ib;
        });
        [$div, $class] = $inScopePairs[0];
        $team[$evCode] = $divLabels[$div . $class] ?? ['division' => $div, 'class' => $class, 'label' => $evCode, 'order' => [999, 999]];
    }

    return ['individual' => $individual, 'team' => $team];
}

// --- Club directory (shared by individual & team loaders) -----------------

function pl_points_load_club_directory($tourId)
{
    $tourId = intval($tourId);
    $dir = [];
    $Rs = safe_r_sql("SELECT CoId, CoCode, CoName FROM Countries WHERE CoTournament = $tourId");
    while ($row = safe_fetch($Rs)) {
        $dir[intval($row->CoId)] = ['CoCode' => $row->CoCode, 'CoName' => $row->CoName];
    }
    safe_free_result($Rs);
    return $dir;
}

function pl_points_attach_clubs($tourId, array $rows)
{
    $dir = pl_points_load_club_directory($tourId);
    foreach ($rows as &$row) {
        $club = $dir[$row['ClubId']] ?? ['CoCode' => '', 'CoName' => ''];
        $row['CoId'] = $row['ClubId'];
        $row['CoCode'] = $club['CoCode'];
        $row['CoName'] = $club['CoName'];
    }
    unset($row);
    return $rows;
}

// --- Individuals ------------------------------------------------------------

/**
 * @param string $source 'QUAL' | 'ELIM'
 * @param array $categories pl_points_load_categories() result (or just its 'individual' map)
 */
function pl_points_load_individuals($tourId, $source, array $categories)
{
    $tourId = intval($tourId);
    $catKeys = array_keys($categories['individual'] ?? $categories);
    if (empty($catKeys)) {
        return [];
    }

    $placeExpr = $source === 'ELIM'
        ? 'IF(Events.EvFinalFirstPhase=0, Individuals.IndRank, ABS(Individuals.IndRankFinal))'
        : 'Individuals.IndRank';

    $inList = implode(',', array_map(fn ($k) => StrSafe_DB($k), $catKeys));

    $sql = "
        SELECT Entries.EnId, Entries.EnCode, Entries.EnName, Entries.EnFirstName,
               Entries.EnDivision, Entries.EnClass,
               IF(Entries.EnCountry2=0, Entries.EnCountry, Entries.EnCountry2) AS ClubId,
               $placeExpr AS Place
        FROM Individuals
        INNER JOIN Entries ON Entries.EnId = Individuals.IndId AND Entries.EnTournament = Individuals.IndTournament
        INNER JOIN Events ON Events.EvCode = Individuals.IndEvent AND Events.EvTournament = Individuals.IndTournament AND Events.EvTeamEvent = 0
        WHERE Individuals.IndTournament = $tourId
        AND CONCAT(Entries.EnDivision, Entries.EnClass) IN ($inList)
    ";
    $Rs = safe_r_sql($sql);
    $rows = [];
    while ($row = safe_fetch($Rs)) {
        $rows[] = [
            'EnId' => intval($row->EnId),
            'EnCode' => $row->EnCode,
            'Name' => trim($row->EnFirstName . ' ' . $row->EnName),
            'Category' => $row->EnDivision . $row->EnClass,
            'ClubId' => intval($row->ClubId),
            'Place' => intval($row->Place),
        ];
    }
    safe_free_result($Rs);

    return pl_points_attach_clubs($tourId, $rows);
}

// --- Teams --------------------------------------------------------------

/**
 * @param string $source 'QUAL' | 'ELIM'
 * @param bool $mixed true for a mixed-team classification
 * @param array $categories pl_points_load_categories() result (or just its 'team' map)
 */
function pl_points_load_teams($tourId, $source, $mixed, array $categories)
{
    $tourId = intval($tourId);
    $evCodes = array_keys($categories['team'] ?? $categories);
    if (empty($evCodes)) {
        return [];
    }

    $placeExpr = $source === 'ELIM'
        ? 'IF(Events.EvFinalFirstPhase=0, Teams.TeRank, ABS(Teams.TeRankFinal))'
        : 'Teams.TeRank';

    $inList = implode(',', array_map(fn ($c) => StrSafe_DB($c), $evCodes));
    $mixedFilter = $mixed ? 'AND Events.EvMixedTeam = 1' : 'AND Events.EvMixedTeam = 0';

    $sql = "
        SELECT Teams.TeCoId, Teams.TeSubTeam, Teams.TeEvent, $placeExpr AS Place
        FROM Teams
        INNER JOIN Events ON Events.EvCode = Teams.TeEvent AND Events.EvTournament = Teams.TeTournament AND Events.EvTeamEvent = 1
        WHERE Teams.TeTournament = $tourId
        AND Teams.TeFinEvent = 1
        AND Teams.TeEvent IN ($inList)
        $mixedFilter
    ";
    $Rs = safe_r_sql($sql);
    $rows = [];
    while ($row = safe_fetch($Rs)) {
        $rows[] = [
            'ClubId' => intval($row->TeCoId),
            'SubTeam' => intval($row->TeSubTeam),
            'Event' => $row->TeEvent,
            'Place' => intval($row->Place),
        ];
    }
    safe_free_result($Rs);

    return pl_points_attach_clubs($tourId, $rows);
}

// --- Rosters --------------------------------------------------------------

/**
 * @param string $source 'QUAL' | 'ELIM'
 * @param array $teams list of ['ClubId','SubTeam','Event'] — the teams to build rosters for
 * @return array<string,int[]> "ClubId_SubTeam_Event" => [enId, ...]
 */
function pl_points_load_rosters($tourId, $source, array $teams)
{
    $tourId = intval($tourId);
    if (empty($teams)) {
        return [];
    }

    // TeamComponent additionally carries finals-vs-qualification snapshots via
    // TcFinEvent; pl_points_load_teams() already restricts to the definitive
    // (TeFinEvent = 1) team row, so the roster read mirrors that here.
    if ($source === 'ELIM') {
        $sql = "SELECT TfcCoId AS ClubId, TfcSubTeam AS SubTeam, TfcEvent AS Event, TfcId AS EnId
                FROM TeamFinComponent WHERE TfcTournament = $tourId";
    } else {
        $sql = "SELECT TcCoId AS ClubId, TcSubTeam AS SubTeam, TcEvent AS Event, TcId AS EnId
                FROM TeamComponent WHERE TcTournament = $tourId AND TcFinEvent = 1";
    }

    $Rs = safe_r_sql($sql);
    $rosters = [];
    while ($row = safe_fetch($Rs)) {
        $key = $row->ClubId . '_' . $row->SubTeam . '_' . $row->Event;
        $rosters[$key][] = intval($row->EnId);
    }
    safe_free_result($Rs);

    $result = [];
    foreach ($teams as $team) {
        $key = $team['ClubId'] . '_' . $team['SubTeam'] . '_' . $team['Event'];
        $result[$key] = $rosters[$key] ?? [];
    }
    return $result;
}

/**
 * Qualification place of each roster member in the team's own individual
 * event, for the 3-of-4 rule.
 *
 * Team and individual events of the same category share one EvCode in this
 * module's setup scripts (EvTeamEvent distinguishes them — see the I:/T:
 * composite-key convention in Diplomas/Fun_Diploma.php); Events.EvCodeParent
 * is unrelated (it chains sub-events within the same kind, e.g. finals
 * brackets) and is not populated for this link, so the individual event is
 * simply the one with the same EvCode.
 *
 * @param int[] $enIds
 * @param string $teamEvent the team's EvCode
 * @return array<int,int> enId => qualification place (0 if not found)
 */
function pl_points_load_parent_ranks($tourId, array $enIds, $teamEvent)
{
    $tourId = intval($tourId);
    if (empty($enIds)) {
        return [];
    }

    $idList = implode(',', array_map('intval', $enIds));
    $sql = "
        SELECT IndId, IndRank FROM Individuals
        WHERE IndTournament = $tourId AND IndEvent = " . StrSafe_DB($teamEvent) . "
        AND IndId IN ($idList)
    ";
    $Rs = safe_r_sql($sql);
    $ranks = [];
    while ($row = safe_fetch($Rs)) {
        $ranks[intval($row->IndId)] = intval($row->IndRank);
    }
    safe_free_result($Rs);
    return $ranks;
}

// --- Starters (for the cutoff rule) ----------------------------------------

/**
 * Qualification-starter counts per category, regardless of the classification's
 * own rank source (D10): IndRank/TeRank valid (> 0, < 29999).
 *
 * @return array{individual: array<string,int>, team: array<string,int>}
 */
function pl_points_load_starters($tourId, array $categories)
{
    $tourId = intval($tourId);
    $starters = ['individual' => [], 'team' => []];

    $indKeys = array_keys($categories['individual'] ?? []);
    if (!empty($indKeys)) {
        $inList = implode(',', array_map(fn ($k) => StrSafe_DB($k), $indKeys));
        $sql = "
            SELECT CONCAT(Entries.EnDivision, Entries.EnClass) AS Category, COUNT(*) AS Cnt
            FROM Individuals
            INNER JOIN Entries ON Entries.EnId = Individuals.IndId AND Entries.EnTournament = Individuals.IndTournament
            WHERE Individuals.IndTournament = $tourId
            AND Individuals.IndRank > 0 AND Individuals.IndRank < 29999
            AND CONCAT(Entries.EnDivision, Entries.EnClass) IN ($inList)
            GROUP BY Category
        ";
        $Rs = safe_r_sql($sql);
        while ($row = safe_fetch($Rs)) {
            $starters['individual'][$row->Category] = intval($row->Cnt);
        }
        safe_free_result($Rs);
    }

    $teamKeys = array_keys($categories['team'] ?? []);
    if (!empty($teamKeys)) {
        $inList = implode(',', array_map(fn ($k) => StrSafe_DB($k), $teamKeys));
        $sql = "
            SELECT Teams.TeEvent AS Category, COUNT(*) AS Cnt
            FROM Teams
            WHERE Teams.TeTournament = $tourId
            AND Teams.TeFinEvent = 1
            AND Teams.TeRank > 0 AND Teams.TeRank < 29999
            AND Teams.TeEvent IN ($inList)
            GROUP BY Category
        ";
        $Rs = safe_r_sql($sql);
        while ($row = safe_fetch($Rs)) {
            $starters['team'][$row->Category] = intval($row->Cnt);
        }
        safe_free_result($Rs);
    }

    return $starters;
}

/**
 * The 16 Polish voivodeships, in their canonical capitalized form — stored
 * and displayed verbatim (dropdown, club/voivodeship tables, diplomas), so
 * capitalization only needs fixing in one place.
 */
const PL_VOIVODESHIPS = [
    'Dolnośląskie', 'Kujawsko-Pomorskie', 'Lubelskie', 'Lubuskie', 'Łódzkie',
    'Małopolskie', 'Mazowieckie', 'Opolskie', 'Podkarpackie', 'Podlaskie',
    'Pomorskie', 'Śląskie', 'Świętokrzyskie', 'Warmińsko-Mazurskie',
    'Wielkopolskie', 'Zachodniopomorskie',
];

/**
 * Clubs (by CoCode) with at least one entry in the current tournament, for
 * the voivodeship mapping page.
 *
 * @return array<string, string> CoCode => CoName
 */
function pl_points_load_clubs_in_tournament($tourId)
{
    $tourId = intval($tourId);
    $sql = "
        SELECT DISTINCT Countries.CoCode, Countries.CoName
        FROM Entries
        INNER JOIN Countries ON Countries.CoId = IF(Entries.EnCountry2=0, Entries.EnCountry, Entries.EnCountry2)
            AND Countries.CoTournament = Entries.EnTournament
        WHERE Entries.EnTournament = $tourId
        ORDER BY Countries.CoName
    ";
    $Rs = safe_r_sql($sql);
    $clubs = [];
    while ($row = safe_fetch($Rs)) {
        $clubs[$row->CoCode] = $row->CoName;
    }
    safe_free_result($Rs);
    return $clubs;
}

// --- Entries directory (team-member attribution) ---------------------------

/**
 * Every entry in the tournament, keyed by EnId — used to attribute a team or
 * mixed share to the member's own category/club (D3), independent of
 * whether that member also has an individual result.
 *
 * @return array<int, array{code:string,name:string,category:string,club_id:int,club_code:string,club_name:string}>
 */
function pl_points_load_entries_directory($tourId)
{
    $tourId = intval($tourId);
    $clubDir = pl_points_load_club_directory($tourId);

    $sql = "
        SELECT EnId, EnCode, EnName, EnFirstName, EnDivision, EnClass,
               IF(EnCountry2=0, EnCountry, EnCountry2) AS ClubId
        FROM Entries
        WHERE EnTournament = $tourId
    ";
    $Rs = safe_r_sql($sql);
    $directory = [];
    while ($row = safe_fetch($Rs)) {
        $clubId = intval($row->ClubId);
        $club = $clubDir[$clubId] ?? ['CoCode' => '', 'CoName' => ''];
        $directory[intval($row->EnId)] = [
            'code' => $row->EnCode,
            'name' => trim($row->EnFirstName . ' ' . $row->EnName),
            'category' => $row->EnDivision . $row->EnClass,
            'club_id' => $clubId,
            'club_code' => $club['CoCode'],
            'club_name' => $club['CoName'],
        ];
    }
    safe_free_result($Rs);
    return $directory;
}
