<?php
/**
 * Fun_Cup.php — data layer for the Puchar Polski multi-round cup classification.
 *
 * Auto-installs PLCupConfig / PLCupRound / PLCupBarrage, reads the per-tournament
 * cup settings, turns the `pp` points calculation into stored round rows, and
 * reads/writes the CSV transport used between the four rounds' hosts (design
 * D2/D3/D4/D7).
 *
 * Shape of one round row (the single storage/aggregation/CSV shape):
 *   ['classification' => 'ind'|'mix', 'category' => string, 'identity' => string,
 *    'name' => string, 'club_name' => string,
 *    'place' => int, 'points' => int, 'qual' => int]
 * Rows read back from PLCupRound additionally carry 'round' => int.
 */

require_once __DIR__ . '/Presets.php';
require_once __DIR__ . '/Fun_PointsRanking.php';
require_once __DIR__ . '/PointsRankingCalc.php';
require_once __DIR__ . '/CupCalc.php';

/** The cup is shot as four rounds (proposal Non-goals: not configurable). */
const PL_CUP_ROUNDS = 4;

/** The only preset the cup layer works with. */
const PL_CUP_PRESET_KEY = 'pp';

/** CSV header, in column order (design D7). */
const PL_CUP_CSV_COLUMNS = ['Klasyfikacja', 'Kategoria', 'Identyfikator', 'Nazwa', 'Klub', 'Miejsce', 'Punkty', 'Kwalifikacje'];

// --- Auto-install ---------------------------------------------------------

function pl_cup_ensure_tables()
{
    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLCupConfig'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE IF NOT EXISTS PLCupConfig (
            PlCcTournament INT NOT NULL,
            PlCcEdition INT NOT NULL DEFAULT 0,
            PlCcRound INT NOT NULL DEFAULT 0,
            PlCcDiplomaName VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (PlCcTournament)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);

    // Not keyed by tournament: rounds 1-3 were shot elsewhere and have no
    // tournament here. The edition (a year) is the season boundary (D2).
    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLCupRound'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE IF NOT EXISTS PLCupRound (
            PlCrEdition INT NOT NULL,
            PlCrRound INT NOT NULL,
            PlCrClassification VARCHAR(8) NOT NULL,
            PlCrCategory VARCHAR(15) NOT NULL,
            PlCrIdentity VARCHAR(32) NOT NULL,
            PlCrName VARCHAR(255) NOT NULL DEFAULT '',
            PlCrClubName VARCHAR(255) NOT NULL DEFAULT '',
            PlCrPlace INT NOT NULL DEFAULT 0,
            PlCrPoints INT NOT NULL DEFAULT 0,
            PlCrQualScore INT NOT NULL DEFAULT 0,
            PRIMARY KEY (PlCrEdition, PlCrRound, PlCrClassification, PlCrCategory, PlCrIdentity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);

    // Keyed by identity, not by "tied group": a group has no stable id, and the
    // outcome must survive a re-import of any round (D6a).
    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLCupBarrage'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE IF NOT EXISTS PLCupBarrage (
            PlCbEdition INT NOT NULL,
            PlCbClassification VARCHAR(8) NOT NULL,
            PlCbCategory VARCHAR(15) NOT NULL,
            PlCbIdentity VARCHAR(32) NOT NULL,
            PlCbOrder INT NOT NULL DEFAULT 0,
            PRIMARY KEY (PlCbEdition, PlCbClassification, PlCbCategory, PlCbIdentity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);
}

// --- Per-tournament cup configuration -------------------------------------

/** Year of the tournament's last day, falling back to the current year. */
function pl_cup_default_edition()
{
    $to = isset($_SESSION['TourRealWhenTo']) ? (string) $_SESSION['TourRealWhenTo'] : '';
    if (preg_match('/^(\d{4})/', $to, $m)) {
        return intval($m[1]);
    }
    return intval(date('Y'));
}

/**
 * @return array{Edition:int, Round:int, DiplomaName:string}
 */
function pl_cup_get_config($tourId)
{
    $config = ['Edition' => pl_cup_default_edition(), 'Round' => 0, 'DiplomaName' => ''];

    $Rs = safe_r_sql("SELECT PlCcEdition, PlCcRound, PlCcDiplomaName FROM PLCupConfig WHERE PlCcTournament = " . intval($tourId));
    if (safe_num_rows($Rs) > 0) {
        $row = safe_fetch($Rs);
        if (intval($row->PlCcEdition) > 0) {
            $config['Edition'] = intval($row->PlCcEdition);
        }
        $config['Round'] = intval($row->PlCcRound);
        $config['DiplomaName'] = $row->PlCcDiplomaName;
    }
    safe_free_result($Rs);

    return $config;
}

function pl_cup_set_config($tourId, $edition, $round, $diplomaName)
{
    $tourId = intval($tourId);
    $edition = intval($edition);
    $round = intval($round);
    if ($round < 0 || $round > PL_CUP_ROUNDS) {
        $round = 0;
    }

    $Rs = safe_r_sql("SELECT PlCcTournament FROM PLCupConfig WHERE PlCcTournament = " . $tourId);
    $exists = safe_num_rows($Rs) > 0;
    safe_free_result($Rs);

    if ($exists) {
        safe_w_sql("UPDATE PLCupConfig SET PlCcEdition = $edition, PlCcRound = $round, "
            . "PlCcDiplomaName = " . StrSafe_DB($diplomaName) . " WHERE PlCcTournament = " . $tourId);
        return;
    }

    safe_w_sql("INSERT INTO PLCupConfig (PlCcTournament, PlCcEdition, PlCcRound, PlCcDiplomaName) "
        . "VALUES ($tourId, $edition, $round, " . StrSafe_DB($diplomaName) . ")");
}

// --- Qualification scores (not read by the points engine, D4) --------------

/**
 * @return array{ind: array<int,int>, mix: array<string,int>} individual scores by
 *   EnId; mixed scores by "clubId_EvCode" — the pair's own sub-team is dropped
 *   inside pl_points_calculate(), which is safe because one club may enter only
 *   one mixed pair per Puchar Polski round (D3).
 */
function pl_cup_load_qual_scores($tourId)
{
    $tourId = intval($tourId);
    $scores = ['ind' => [], 'mix' => []];

    $Rs = safe_r_sql("
        SELECT Individuals.IndId AS EnId, MAX(Individuals.IndScore) AS QualScore
        FROM Individuals
        INNER JOIN Events ON Events.EvCode = Individuals.IndEvent
            AND Events.EvTournament = Individuals.IndTournament AND Events.EvTeamEvent = 0
        WHERE Individuals.IndTournament = $tourId
        GROUP BY Individuals.IndId
    ");
    while ($row = safe_fetch($Rs)) {
        $scores['ind'][intval($row->EnId)] = intval($row->QualScore);
    }
    safe_free_result($Rs);

    $Rs = safe_r_sql("
        SELECT Teams.TeCoId AS ClubId, Teams.TeEvent AS Event, MAX(Teams.TeScore) AS QualScore
        FROM Teams
        INNER JOIN Events ON Events.EvCode = Teams.TeEvent
            AND Events.EvTournament = Teams.TeTournament AND Events.EvTeamEvent = 1 AND Events.EvMixedTeam = 1
        WHERE Teams.TeTournament = $tourId AND Teams.TeFinEvent = 1
        GROUP BY Teams.TeCoId, Teams.TeEvent
    ");
    while ($row = safe_fetch($Rs)) {
        $scores['mix'][$row->ClubId . '_' . $row->Event] = intval($row->QualScore);
    }
    safe_free_result($Rs);

    return $scores;
}

// --- Snapshot of the current tournament ------------------------------------

/**
 * Turn a `pp` calculation plus qualification scores into round rows.
 *
 * Rows that scored no points are kept as long as the athlete/pair has a valid
 * place: the regulation's tie-break reads the best place and the highest
 * qualification score "w dowolnej rundzie", so a round outside the point
 * brackets still carries data that can decide the cup. Only rows with no
 * result at all (place 0, or a DSQ/DNS/DNF sentinel >= 29999) are dropped.
 * Rows that never score anywhere are left out of the classification itself,
 * not of the stored round (see pl_cup_aggregate()).
 *
 * @param array $result pl_points_calculate() output
 * @param array $qualScores pl_cup_load_qual_scores() output
 */
function pl_cup_rows_from_result(array $result, array $qualScores)
{
    $rows = [];

    foreach ($result['reports'] as $report) {
        if ($report['kind'] !== 'SEPARATE') {
            continue;
        }
        $isMixed = ($report['subject'] ?? 'IND') === 'MIXED';
        if (!$isMixed && ($report['subject'] ?? 'IND') !== 'IND') {
            continue; // TEAM classifications are not part of Puchar Polski
        }

        foreach ($report['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                $place = intval($row['place']);
                if ($place <= 0 || $place >= 29999) {
                    continue;
                }
                if ($isMixed) {
                    $qual = $qualScores['mix'][$row['club_id'] . '_' . $row['category']] ?? 0;
                    $rows[] = [
                        'classification' => 'mix',
                        'category' => $row['category'],
                        'identity' => (string) $row['club_code'],
                        // A mixed cup row belongs to the club, and its pair may be
                        // two different athletes in every round - the names are
                        // neither imported nor displayed, so they are not stored.
                        'name' => '',
                        'club_name' => (string) $row['club_name'],
                        'place' => intval($row['place']),
                        'points' => intval($row['points']),
                        'qual' => intval($qual),
                    ];
                    continue;
                }
                $rows[] = [
                    'classification' => 'ind',
                    'category' => $row['category'],
                    'identity' => (string) $row['code'],
                    'name' => (string) $row['name'],
                    'club_name' => (string) $row['club_name'],
                    'place' => intval($row['place']),
                    'points' => intval($row['points']),
                    'qual' => intval($qualScores['ind'][$row['enId']] ?? 0),
                ];
            }
        }
    }

    return $rows;
}

/**
 * Build this tournament's round rows from the live `pp` calculation.
 *
 * @return array{rows: array, errors: string[]} errors non-empty => nothing may be stored
 */
function pl_cup_build_snapshot($tourId)
{
    $result = pl_points_calculate($tourId, PL_POINTS_PRESETS[PL_CUP_PRESET_KEY]);
    $rows = pl_cup_rows_from_result($result, pl_cup_load_qual_scores($tourId));

    return ['rows' => $rows, 'errors' => pl_cup_validate_rows($rows)];
}

/**
 * Identity rules (spec "Row identity"): every individual row needs a licence
 * number, every mixed row a club code, and one club may hold only one mixed row
 * per category. A violation rejects the whole set rather than storing a row that
 * can never match its counterpart in another round.
 *
 * @return string[] Polish error messages, empty when the set is valid
 */
function pl_cup_validate_rows(array $rows)
{
    $errors = [];
    $seen = [];

    foreach ($rows as $row) {
        $identity = trim((string) $row['identity']);
        $label = trim(($row['name'] ?? '') . ' (' . ($row['club_name'] ?? '') . ')');

        if ($identity === '') {
            $errors[] = $row['classification'] === 'mix'
                ? 'Brak kodu klubu dla miksta: ' . $label . ' [' . $row['category'] . ']'
                : 'Brak numeru licencji: ' . $label . ' [' . $row['category'] . ']';
            continue;
        }

        // A repeated identity is also the stored round's primary key, and a
        // duplicate-key INSERT dies inside ianseo's safe_w_sql() (an error page
        // and exit(), no exception to roll back) - so it has to be caught here.
        $key = $row['classification'] . '|' . $row['category'] . '|' . $identity;
        if (isset($seen[$key])) {
            $errors[] = $row['classification'] === 'mix'
                ? 'Dwa miksty tego samego klubu w kategorii ' . $row['category'] . ': ' . $identity
                : 'Ten sam numer licencji dwa razy w kategorii ' . $row['category'] . ': ' . $identity;
            continue;
        }
        $seen[$key] = true;
    }

    return $errors;
}

// --- Stored rounds ----------------------------------------------------------

/**
 * Replace one round's rows in full. Atomic: a failed write leaves the previously
 * stored round untouched rather than a half-replaced one.
 *
 * @return string '' on success, an error message otherwise
 */
function pl_cup_store_round($edition, $round, array $rows)
{
    $edition = intval($edition);
    $round = intval($round);

    safe_w_BeginTransaction();
    try {
        safe_w_sql("DELETE FROM PLCupRound WHERE PlCrEdition = $edition AND PlCrRound = $round");
        foreach ($rows as $row) {
            safe_w_sql("INSERT INTO PLCupRound (PlCrEdition, PlCrRound, PlCrClassification, PlCrCategory,
                PlCrIdentity, PlCrName, PlCrClubName, PlCrPlace, PlCrPoints, PlCrQualScore) VALUES ("
                . $edition . ', '
                . $round . ', '
                . StrSafe_DB($row['classification']) . ', '
                . StrSafe_DB($row['category']) . ', '
                . StrSafe_DB($row['identity']) . ', '
                . StrSafe_DB($row['name']) . ', '
                . StrSafe_DB($row['club_name']) . ', '
                . intval($row['place']) . ', '
                . intval($row['points']) . ', '
                . intval($row['qual']) . ')');
        }
        safe_w_Commit();
    } catch (\Exception $e) {
        safe_w_Rollback();
        return 'Zapis rundy nie powiódł się: ' . $e->getMessage();
    }

    return '';
}

/**
 * @param int|null $round null = every stored round of the edition
 * @return array round rows, each with an extra 'round' key
 */
function pl_cup_load_rounds($edition, $round = null)
{
    $sql = "SELECT PlCrRound, PlCrClassification, PlCrCategory, PlCrIdentity, PlCrName,
                   PlCrClubName, PlCrPlace, PlCrPoints, PlCrQualScore
            FROM PLCupRound WHERE PlCrEdition = " . intval($edition);
    if ($round !== null) {
        $sql .= ' AND PlCrRound = ' . intval($round);
    }

    $Rs = safe_r_sql($sql);
    $rows = [];
    while ($row = safe_fetch($Rs)) {
        $rows[] = [
            'round' => intval($row->PlCrRound),
            'classification' => $row->PlCrClassification,
            'category' => $row->PlCrCategory,
            'identity' => $row->PlCrIdentity,
            'name' => $row->PlCrName,
            'club_name' => $row->PlCrClubName,
            'place' => intval($row->PlCrPlace),
            'points' => intval($row->PlCrPoints),
            'qual' => intval($row->PlCrQualScore),
        ];
    }
    safe_free_result($Rs);

    return $rows;
}

/** @return int[] round numbers with at least one stored row, ascending */
function pl_cup_stored_rounds($edition)
{
    $Rs = safe_r_sql("SELECT DISTINCT PlCrRound FROM PLCupRound WHERE PlCrEdition = " . intval($edition) . " ORDER BY PlCrRound");
    $rounds = [];
    while ($row = safe_fetch($Rs)) {
        $rounds[] = intval($row->PlCrRound);
    }
    safe_free_result($Rs);
    return $rounds;
}

// --- Baraż outcomes ---------------------------------------------------------

/** @return array<string,int> "classification|category|identity" => order (1 = winner) */
function pl_cup_load_barrages($edition)
{
    $Rs = safe_r_sql("SELECT PlCbClassification, PlCbCategory, PlCbIdentity, PlCbOrder
                      FROM PLCupBarrage WHERE PlCbEdition = " . intval($edition));
    $orders = [];
    while ($row = safe_fetch($Rs)) {
        $orders[$row->PlCbClassification . '|' . $row->PlCbCategory . '|' . $row->PlCbIdentity] = intval($row->PlCbOrder);
    }
    safe_free_result($Rs);
    return $orders;
}

/** Record (or, with $order <= 0, clear) one row's shoot-off position. */
function pl_cup_set_barrage($edition, $classification, $category, $identity, $order)
{
    $edition = intval($edition);
    $order = intval($order);
    $where = "PlCbEdition = $edition AND PlCbClassification = " . StrSafe_DB($classification)
        . " AND PlCbCategory = " . StrSafe_DB($category)
        . " AND PlCbIdentity = " . StrSafe_DB($identity);

    // Upsert rather than delete-then-insert: a failed insert would otherwise
    // lose the recorded outcome and drop the rows back to a shared rank.
    if ($order > 0) {
        safe_w_sql("INSERT INTO PLCupBarrage (PlCbEdition, PlCbClassification, PlCbCategory, PlCbIdentity, PlCbOrder)
            VALUES ($edition, " . StrSafe_DB($classification) . ', ' . StrSafe_DB($category) . ', '
            . StrSafe_DB($identity) . ", $order)
            ON DUPLICATE KEY UPDATE PlCbOrder = VALUES(PlCbOrder)");
        return;
    }

    safe_w_sql("DELETE FROM PLCupBarrage WHERE $where");
}

// --- CSV transport (design D7) ---------------------------------------------

/** Semicolon-separated, UTF-8 with BOM — what Polish Excel opens without a wizard. */
function pl_cup_csv_write(array $rows)
{
    $out = "\xEF\xBB\xBF" . implode(';', PL_CUP_CSV_COLUMNS) . "\r\n";
    foreach ($rows as $row) {
        $out .= implode(';', array_map('pl_cup_csv_escape', [
            $row['classification'],
            $row['category'],
            $row['identity'],
            $row['name'],
            $row['club_name'],
            intval($row['place']),
            intval($row['points']),
            intval($row['qual']),
        ])) . "\r\n";
    }
    return $out;
}

function pl_cup_csv_escape($value)
{
    $value = (string) $value;
    if (strpbrk($value, ";\"\r\n") === false) {
        return $value;
    }
    return '"' . str_replace('"', '""', $value) . '"';
}

/**
 * Parse a round CSV. Nothing is written when any line fails (spec "Round import
 * from CSV") — the caller stores only when 'errors' is empty.
 *
 * Records are read with fgetcsv(), not by splitting on newlines: a quoted field
 * may itself contain a line break (pl_cup_csv_write() quotes those), and such a
 * record has to survive the export/import round trip in one piece.
 *
 * @param array $validCategories ['ind' => [code, ...], 'mix' => [code, ...]] — the
 *   tournament's *configured* categories, not the ones with entries (D8)
 * @return array{rows: array, errors: string[]}
 */
function pl_cup_csv_parse($content, array $validCategories)
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rows = [];
    $errors = [];
    $lineNo = 0;
    $headerSeen = false;

    while (($cells = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
        $lineNo++;
        if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
            continue;
        }

        if (!$headerSeen) {
            $headerSeen = true;
            // Accept a file with or without the header row.
            if (strcasecmp(trim((string) $cells[0]), PL_CUP_CSV_COLUMNS[0]) === 0) {
                continue;
            }
        }

        $parsed = pl_cup_csv_parse_row($cells, $lineNo, $validCategories);
        if (!empty($parsed['errors'])) {
            $errors = array_merge($errors, $parsed['errors']);
            continue;
        }
        $rows[] = $parsed['row'];
    }
    fclose($handle);

    if (empty($errors)) {
        $errors = pl_cup_validate_rows($rows);
    }

    return ['rows' => $rows, 'errors' => $errors];
}

/**
 * Validate and normalise one CSV record.
 *
 * Values a stored round cannot hold are rejected here rather than persisted: a
 * snapshot never produces a row without a place, with a DSQ/DNS sentinel, or
 * with a negative point or qualification value, and such a row would distort the
 * totals and the tie-break of everyone in its category.
 *
 * @return array{row: array|null, errors: string[]}
 */
function pl_cup_csv_parse_row(array $cells, $lineNo, array $validCategories)
{
    $prefix = 'Wiersz ' . $lineNo . ': ';

    if (count($cells) !== count(PL_CUP_CSV_COLUMNS)) {
        return ['row' => null, 'errors' => [$prefix . 'oczekiwano ' . count(PL_CUP_CSV_COLUMNS) . ' kolumn, znaleziono ' . count($cells) . '.']];
    }

    [$classification, $category, $identity, $name, $clubName, $place, $points, $qual] = array_map(
        fn ($cell) => trim((string) $cell),
        $cells
    );

    if (!in_array($classification, ['ind', 'mix'], true)) {
        return ['row' => null, 'errors' => [$prefix . 'nieznana klasyfikacja "' . $classification . '" (dozwolone: ind, mix).']];
    }
    if (!in_array($category, $validCategories[$classification] ?? [], true)) {
        return ['row' => null, 'errors' => [$prefix . 'nieznana kategoria "' . $category . '".']];
    }

    $errors = [];
    foreach (['Miejsce' => $place, 'Punkty' => $points, 'Kwalifikacje' => $qual] as $label => $value) {
        if (!pl_cup_is_numeric($value)) {
            $errors[] = $prefix . 'kolumna ' . $label . ' nie jest liczbą ("' . $value . '").';
        }
    }
    if (!empty($errors)) {
        return ['row' => null, 'errors' => $errors];
    }

    $place = pl_cup_to_int($place);
    $points = pl_cup_to_int($points);
    $qual = pl_cup_to_int($qual);

    if ($place <= 0 || $place >= 29999) {
        $errors[] = $prefix . 'miejsce poza zakresem (' . $place . ') - runda zapisuje tylko sklasyfikowanych zawodników.';
    }
    if ($points < 0) {
        $errors[] = $prefix . 'ujemna liczba punktów (' . $points . ').';
    }
    if ($qual < 0) {
        $errors[] = $prefix . 'ujemny wynik kwalifikacji (' . $qual . ').';
    }
    if (!empty($errors)) {
        return ['row' => null, 'errors' => $errors];
    }

    return ['row' => [
        'classification' => $classification,
        'category' => $category,
        'identity' => $identity,
        'name' => $name,
        'club_name' => $clubName,
        'place' => $place,
        'points' => $points,
        'qual' => $qual,
    ], 'errors' => []];
}

/** Both decimal separators are accepted on reading (D7); an empty cell counts as 0. */
function pl_cup_is_numeric($value)
{
    return $value === '' || preg_match('/^-?\d+([.,]\d+)?$/', $value) === 1;
}

function pl_cup_to_int($value)
{
    return intval(round((float) str_replace(',', '.', (string) $value)));
}

/**
 * Category codes the import accepts: every configured individual division/class
 * pair, and every mixed team event (D8) — a category shot in round 1 but empty in
 * round 4 must still import.
 *
 * @return array{ind: string[], mix: string[]}
 */
function pl_cup_valid_categories($tourId)
{
    $tourId = intval($tourId);

    $ind = array_keys(pl_ranking_div_labels($tourId));

    $mix = [];
    $Rs = safe_r_sql("
        SELECT DISTINCT Events.EvCode AS Event
        FROM Events
        WHERE Events.EvTournament = $tourId AND Events.EvTeamEvent = 1 AND Events.EvMixedTeam = 1
    ");
    while ($row = safe_fetch($Rs)) {
        $mix[] = $row->Event;
    }
    safe_free_result($Rs);

    return ['ind' => $ind, 'mix' => $mix];
}

/**
 * Section labels and order for the categories **this competition runs**.
 *
 * The stored rounds cover the whole cup, so a junior competition would otherwise
 * render (and print diplomas for) the barebow and compound sections it only
 * imported. Individual categories therefore come from this tournament's entries
 * and mixed ones from the pairs that actually started here.
 *
 * @return array{ind: array, mix: array} category code => ['label' => string, 'order' => array]
 */
function pl_cup_category_meta($tourId)
{
    $tourId = intval($tourId);
    $categories = pl_points_load_categories($tourId, PL_POINTS_PRESETS[PL_CUP_PRESET_KEY]['scope']);

    $mix = [];
    $Rs = safe_r_sql("
        SELECT DISTINCT Teams.TeEvent AS Event
        FROM Teams
        INNER JOIN Events ON Events.EvCode = Teams.TeEvent
            AND Events.EvTournament = Teams.TeTournament AND Events.EvTeamEvent = 1 AND Events.EvMixedTeam = 1
        WHERE Teams.TeTournament = $tourId AND Teams.TeFinEvent = 1
    ");
    while ($row = safe_fetch($Rs)) {
        if (isset($categories['team'][$row->Event])) {
            $mix[$row->Event] = $categories['team'][$row->Event];
        }
    }
    safe_free_result($Rs);

    return ['ind' => $categories['individual'], 'mix' => $mix];
}
