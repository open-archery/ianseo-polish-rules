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

/** Comment line naming the competition a CSV was exported from. */
const PL_CUP_CSV_SOURCE_TAG = 'Zawody';

/**
 * Markers a result list uses for "no result" in the place column. Such a row is
 * skipped, not rejected: a file exported from someone else's software lists
 * everyone who entered, and an archer who did not finish simply has nothing to
 * contribute to the cup.
 */
const PL_CUP_NO_RESULT_MARKERS = ['DNF', 'DNS', 'DSQ', 'DQ', 'ABS', 'RET', 'WD', 'NS', 'IRM', 'BRAK', 'WYC', '-', '--'];

// --- Auto-install ---------------------------------------------------------

function pl_cup_ensure_tables()
{
    $Rs = safe_r_sql("SHOW TABLES LIKE 'PLCupConfig'");
    if (safe_num_rows($Rs) == 0) {
        safe_w_sql("CREATE TABLE IF NOT EXISTS PLCupConfig (
            PlCcTournament INT NOT NULL,
            PlCcEdition INT NOT NULL DEFAULT 0,
            PlCcRound INT NOT NULL DEFAULT 0,
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
            PlCrImportedAt DATETIME NULL DEFAULT NULL,
            PlCrSource VARCHAR(255) NOT NULL DEFAULT '',
            PlCrImportTournament INT NOT NULL DEFAULT 0,
            PRIMARY KEY (PlCrEdition, PlCrRound, PlCrClassification, PlCrCategory, PlCrIdentity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    safe_free_result($Rs);

    // Provenance of every row: which import put it there, from where, and in
    // which competition it was performed. Added separately so an installation
    // that already stores rounds picks them up on the next visit.
    foreach ([
        'PlCrImportedAt' => 'DATETIME NULL DEFAULT NULL',
        'PlCrSource' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'PlCrImportTournament' => 'INT NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        $Rs = safe_r_sql("SHOW COLUMNS FROM PLCupRound LIKE " . StrSafe_DB($column));
        if (safe_num_rows($Rs) == 0) {
            safe_w_sql("ALTER TABLE PLCupRound ADD COLUMN $column $definition");
        }
        safe_free_result($Rs);
    }

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
 * @return array{Edition:int, Round:int}
 */
function pl_cup_get_config($tourId)
{
    $config = ['Edition' => pl_cup_default_edition(), 'Round' => 0];

    $Rs = safe_r_sql("SELECT PlCcEdition, PlCcRound FROM PLCupConfig WHERE PlCcTournament = " . intval($tourId));
    if (safe_num_rows($Rs) > 0) {
        $row = safe_fetch($Rs);
        if (intval($row->PlCcEdition) > 0) {
            $config['Edition'] = intval($row->PlCcEdition);
        }
        $config['Round'] = intval($row->PlCcRound);
    }
    safe_free_result($Rs);

    return $config;
}

function pl_cup_set_config($tourId, $edition, $round)
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
        safe_w_sql("UPDATE PLCupConfig SET PlCcEdition = $edition, PlCcRound = $round WHERE PlCcTournament = " . $tourId);
        return;
    }

    safe_w_sql("INSERT INTO PLCupConfig (PlCcTournament, PlCcEdition, PlCcRound) VALUES ($tourId, $edition, $round)");
}

// --- Qualification scores (not read by the points engine, D4) --------------

/**
 * The qualification score the tie-break reads: an athlete's total over the whole
 * qualification round (2x70 m, 2x60 m, 2x50 m …).
 *
 * It comes from `Qualifications.QuScore`, the sum ianseo maintains per entry —
 * **not** from `Individuals.IndScore`, which this ruleset's competitions leave
 * at its -1 sentinel. Team totals do come from `Teams.TeScore`, which ianseo
 * does fill in.
 *
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
        SELECT Qualifications.QuId AS EnId, Qualifications.QuScore AS QualScore
        FROM Qualifications
        INNER JOIN Entries ON Entries.EnId = Qualifications.QuId AND Entries.EnTournament = $tourId
    ");
    while ($row = safe_fetch($Rs)) {
        // A missing or sentinel score counts as none, never as a negative one.
        $scores['ind'][intval($row->EnId)] = max(0, intval($row->QualScore));
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
        $scores['mix'][$row->ClubId . '_' . $row->Event] = max(0, intval($row->QualScore));
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
 * Store one import: the rows replace **the categories they contain** in that
 * round, and nothing else.
 *
 * A round is routinely assembled from several sources — the juniors from an
 * ianseo competition, the barebow and compound rounds from CSV files typed by
 * their own hosts — so an import owns its categories, not the whole round.
 * Atomic: a failed write leaves everything as it was.
 *
 * @param string $source where the rows came from (a competition, or a file)
 * @param int $importTournament the competition open while importing, for context
 * @return string '' on success, an error message otherwise
 */
function pl_cup_store_import($edition, $round, array $rows, $source, $importTournament)
{
    $edition = intval($edition);
    $round = intval($round);
    $importTournament = intval($importTournament);
    // One timestamp for the whole batch: it is what groups these rows into a
    // single entry in the import history.
    $importedAt = date('Y-m-d H:i:s');

    $categories = [];
    foreach ($rows as $row) {
        $categories[$row['classification'] . '|' . $row['category']] = [$row['classification'], $row['category']];
    }
    if (empty($categories)) {
        return '';
    }

    $scope = [];
    foreach ($categories as [$classification, $category]) {
        $scope[] = '(PlCrClassification = ' . StrSafe_DB($classification)
            . ' AND PlCrCategory = ' . StrSafe_DB($category) . ')';
    }

    safe_w_BeginTransaction();
    try {
        safe_w_sql("DELETE FROM PLCupRound WHERE PlCrEdition = $edition AND PlCrRound = $round
            AND (" . implode(' OR ', $scope) . ')');
        foreach ($rows as $row) {
            safe_w_sql("INSERT INTO PLCupRound (PlCrEdition, PlCrRound, PlCrClassification, PlCrCategory,
                PlCrIdentity, PlCrName, PlCrClubName, PlCrPlace, PlCrPoints, PlCrQualScore,
                PlCrImportedAt, PlCrSource, PlCrImportTournament) VALUES ("
                . $edition . ', '
                . $round . ', '
                . StrSafe_DB($row['classification']) . ', '
                . StrSafe_DB($row['category']) . ', '
                . StrSafe_DB($row['identity']) . ', '
                . StrSafe_DB($row['name']) . ', '
                . StrSafe_DB($row['club_name']) . ', '
                . intval($row['place']) . ', '
                . intval($row['points']) . ', '
                . intval($row['qual']) . ', '
                . StrSafe_DB($importedAt) . ', '
                . StrSafe_DB($source) . ', '
                . $importTournament . ')');
        }
        safe_w_Commit();
    } catch (\Exception $e) {
        safe_w_Rollback();
        return 'Zapis rundy nie powiódł się: ' . $e->getMessage();
    }

    return '';
}

/**
 * The imports currently feeding the classification, newest first per round.
 *
 * Grouped out of the stored rows themselves rather than kept as a log, so an
 * import that has been wholly replaced simply stops being listed, and one that
 * was partly replaced shrinks to the categories it still owns.
 *
 * @return array list of ['round','source','import_tournament','imported_at',
 *   'categories' => [['classification','category'], ...], 'rows' => int]
 */
function pl_cup_load_imports($edition)
{
    $Rs = safe_r_sql("
        SELECT PlCrRound, PlCrSource, PlCrImportTournament, PlCrImportedAt,
               COUNT(*) AS RowCnt,
               GROUP_CONCAT(DISTINCT CONCAT(PlCrClassification, ':', PlCrCategory) ORDER BY PlCrCategory SEPARATOR ',') AS Categories
        FROM PLCupRound
        WHERE PlCrEdition = " . intval($edition) . "
        GROUP BY PlCrRound, PlCrSource, PlCrImportTournament, PlCrImportedAt
        ORDER BY PlCrRound, PlCrImportedAt DESC
    ");
    $imports = [];
    while ($row = safe_fetch($Rs)) {
        $categories = [];
        foreach (explode(',', (string) $row->Categories) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$classification, $category] = array_pad(explode(':', $pair, 2), 2, '');
            $categories[] = ['classification' => $classification, 'category' => $category];
        }
        $imports[] = [
            'round' => intval($row->PlCrRound),
            'source' => (string) $row->PlCrSource,
            'import_tournament' => intval($row->PlCrImportTournament),
            'imported_at' => (string) $row->PlCrImportedAt,
            'categories' => $categories,
            'rows' => intval($row->RowCnt),
        ];
    }
    safe_free_result($Rs);
    return $imports;
}

/** Remove exactly one import: its round, its source and its own timestamp. */
function pl_cup_delete_import($edition, $round, $source, $importedAt)
{
    $where = 'PlCrEdition = ' . intval($edition) . ' AND PlCrRound = ' . intval($round)
        . ' AND PlCrSource = ' . StrSafe_DB($source);
    // A round stored before the provenance columns existed has no timestamp -
    // and the column is NULL there, never an empty string: comparing a DATETIME
    // to '' is rejected outright by a strict MySQL (error 1525).
    $where .= $importedAt === ''
        ? ' AND PlCrImportedAt IS NULL'
        : ' AND PlCrImportedAt = ' . StrSafe_DB($importedAt);

    safe_w_sql("DELETE FROM PLCupRound WHERE $where");
}

/** Remove a whole round of an edition, whatever it was assembled from. */
function pl_cup_delete_round($edition, $round)
{
    safe_w_sql('DELETE FROM PLCupRound WHERE PlCrEdition = ' . intval($edition)
        . ' AND PlCrRound = ' . intval($round));
}

/**
 * @param int|null $round null = every stored round of the edition
 * @return array round rows, each with an extra 'round' key
 */
function pl_cup_load_rounds($edition, $round = null)
{
    $sql = "SELECT PlCrRound, PlCrClassification, PlCrCategory, PlCrIdentity, PlCrName,
                   PlCrClubName, PlCrPlace, PlCrPoints, PlCrQualScore, PlCrSource
            FROM PLCupRound WHERE PlCrEdition = " . intval($edition);
    if ($round !== null) {
        $sql .= ' AND PlCrRound = ' . intval($round);
    }
    // Category by category, best place first: the order a result list is read
    // in, and the order an exported file is checked against its source PDF.
    $sql .= ' ORDER BY PlCrRound, PlCrClassification, PlCrCategory, PlCrPlace, PlCrIdentity';

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
            'source' => isset($row->PlCrSource) ? (string) $row->PlCrSource : '',
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

/**
 * Semicolon-separated, UTF-8 with BOM — what Polish Excel opens without a wizard.
 *
 * A "#Zawody:" line names the competition the results come from, so the host who
 * imports the file sees where it came from without being told. It is a comment
 * line: a file typed by hand simply has none.
 */
function pl_cup_csv_write(array $rows, $source = '')
{
    $out = "\xEF\xBB\xBF";
    if (trim((string) $source) !== '') {
        $out .= '#' . PL_CUP_CSV_SOURCE_TAG . ': ' . str_replace(["\r", "\n"], ' ', trim($source)) . "\r\n";
    }
    $out .= implode(';', PL_CUP_CSV_COLUMNS) . "\r\n";
    foreach ($rows as $row) {
        $out .= implode(';', array_map('pl_cup_csv_escape', [
            $row['classification'],
            $row['category'],
            $row['identity'],
            $row['name'],
            $row['club_name'],
            intval($row['place']),
            intval($row['points']),
            // A row stored before the qualification-score fix carries -1; it
            // must not leave the file as a value the import then rejects.
            max(0, intval($row['qual'])),
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
    $warnings = [];
    $skipped = 0;
    $source = '';
    $lineNo = 0;
    $headerSeen = false;

    while (($cells = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
        $lineNo++;
        if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
            continue;
        }

        $first = trim((string) $cells[0]);
        if (substr($first, 0, 1) === '#') {
            // Comment line; "#Zawody: ..." names the exporting competition.
            if (preg_match('/^#\s*' . PL_CUP_CSV_SOURCE_TAG . '\s*:\s*(.+)$/ui', $first, $m)) {
                $source = trim($m[1]);
            }
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
        $warnings = array_merge($warnings, $parsed['warnings']);
        if (!empty($parsed['skipped'])) {
            $skipped++;
            continue;
        }
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
    if ($skipped > 0) {
        $warnings[] = 'Pominięto ' . $skipped . ' wierszy bez wyniku (DNF, DNS, DSQ i podobne).';
    }

    return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings, 'skipped' => $skipped, 'source' => $source];
}

/**
 * Validate and normalise one CSV record.
 *
 * Values a stored round cannot hold are rejected here rather than persisted: a
 * snapshot never produces a row without a place, with a DSQ/DNS sentinel, or
 * with a negative point or qualification value, and such a row would distort the
 * totals and the tie-break of everyone in its category.
 *
 * Points are always computed from the place with this cup's own bracket table,
 * never taken from the file: under the `pp` preset the points are a pure
 * function of the place, so a file scored under another annex — or not scored at
 * all — still imports correctly. A points column that disagrees is reported as a
 * warning, since that usually means the place is wrong.
 *
 * @return array{row: array|null, errors: string[], warnings: string[]}
 */
function pl_cup_csv_parse_row(array $cells, $lineNo, array $validCategories)
{
    $prefix = 'Wiersz ' . $lineNo . ': ';

    if (count($cells) !== count(PL_CUP_CSV_COLUMNS)) {
        return ['row' => null, 'warnings' => [], 'skipped' => false, 'errors' => [$prefix . 'oczekiwano ' . count(PL_CUP_CSV_COLUMNS) . ' kolumn, znaleziono ' . count($cells) . '.']];
    }

    [$classification, $category, $identity, $name, $clubName, $place, $points, $qual] = array_map(
        fn ($cell) => trim((string) $cell),
        $cells
    );

    if (!in_array($classification, ['ind', 'mix'], true)) {
        return ['row' => null, 'warnings' => [], 'skipped' => false, 'errors' => [$prefix . 'nieznana klasyfikacja "' . $classification . '" (dozwolone: ind, mix).']];
    }
    if (!in_array($category, $validCategories[$classification] ?? [], true)) {
        return ['row' => null, 'warnings' => [], 'skipped' => false, 'errors' => [$prefix . 'nieznana kategoria "' . $category . '".']];
    }

    if (pl_cup_is_no_result($place)) {
        // Checked before the other columns: a DNF row's name or qualification
        // score being blank says nothing, since the row is not stored anyway.
        return ['row' => null, 'errors' => [], 'warnings' => [], 'skipped' => true];
    }

    $errors = [];
    // The name identifies the athlete against the rounds already stored, so it
    // is required; the qualification score has to be stated even when it is 0,
    // because an empty cell is far more often a forgotten column than a real
    // zero, and the tie-break reads it.
    if ($classification === 'ind' && $name === '') {
        $errors[] = $prefix . 'brak nazwiska zawodnika (kolumna Nazwa).';
    }
    if ($place === '') {
        $errors[] = $prefix . 'brak miejsca w zawodach (kolumna Miejsce).';
    }
    if ($qual === '') {
        $errors[] = $prefix . 'brak wyniku kwalifikacji (kolumna Kwalifikacje) - wpisz 0, jeśli wyniku nie ma.';
    }
    foreach (['Miejsce' => $place, 'Punkty' => $points, 'Kwalifikacje' => $qual] as $label => $value) {
        if ($value !== '' && !pl_cup_is_numeric($value)) {
            $errors[] = $prefix . 'kolumna ' . $label . ' nie jest liczbą ("' . $value . '").';
        }
    }
    if (!empty($errors)) {
        return ['row' => null, 'warnings' => [], 'skipped' => false, 'errors' => $errors];
    }

    $fromFile = $points;
    $place = pl_cup_to_int($place);
    $qual = pl_cup_to_int($qual);
    $points = pl_cup_points_for_place($classification, $place);

    if ($place <= 0 || $place >= 29999) {
        $errors[] = $prefix . 'miejsce poza zakresem (' . $place . ') - runda zapisuje tylko sklasyfikowanych zawodników.';
    }
    if ($qual < 0) {
        $errors[] = $prefix . 'ujemny wynik kwalifikacji (' . $qual . ').';
    }
    if (!empty($errors)) {
        return ['row' => null, 'warnings' => [], 'skipped' => false, 'errors' => $errors];
    }

    $warnings = [];
    if ($fromFile !== '' && pl_cup_to_int($fromFile) !== $points) {
        $warnings[] = $prefix . 'punkty z pliku (' . pl_cup_to_int($fromFile) . ') różnią się od wyliczonych dla miejsca '
            . $place . ' (' . $points . ') - zapisano wyliczone; sprawdź miejsce.';
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
    ], 'errors' => [], 'warnings' => $warnings, 'skipped' => false];
}

/**
 * Whether a place column says "this archer has no result": one of the markers a
 * result list uses, or a numeric value outside the classified range (0 or one of
 * ianseo's DSQ/DNS/DNF sentinels).
 *
 * Anything else that is not a number stays an error — a marker is recognised,
 * a typo is reported.
 */
function pl_cup_is_no_result($place)
{
    $place = trim((string) $place);
    if ($place === '') {
        return false;
    }
    if (in_array(mb_strtoupper($place, 'UTF-8'), PL_CUP_NO_RESULT_MARKERS, true)) {
        return true;
    }
    if (!pl_cup_is_numeric($place)) {
        return false;
    }
    $value = pl_cup_to_int($place);
    return $value <= 0 || $value >= 29999;
}

/**
 * The cup's own points for a place — the `pp` bracket table of that
 * classification (the mixed table has no 17-32 row). `pp` has no cutoff, so the
 * value depends on nothing but the place.
 */
function pl_cup_points_for_place($classification, $place)
{
    $key = $classification === 'mix' ? 'mix' : 'ind';
    $brackets = PL_POINTS_PRESETS[PL_CUP_PRESET_KEY]['classifications'][$key]['brackets'];
    return pl_points_bracket($brackets, intval($place));
}

// --- Athlete identity across rounds ----------------------------------------

/**
 * A name reduced to what a comparison should care about: no case, no diacritics,
 * no punctuation, no word order ("Kowalski Jan" == "Jan Kowalski").
 */
function pl_cup_normalize_name($name)
{
    $name = mb_strtolower(trim((string) $name), 'UTF-8');
    $name = strtr($name, [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ]);
    $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
    $tokens = array_values(array_filter(explode(' ', trim($name))));
    sort($tokens);
    return implode(' ', $tokens);
}

/**
 * Whether two spellings under **the same licence** are the same person's:
 * identical once normalised, or within a small edit distance of each other, so
 * that "Szkolnicki" and "Szkolnicky" do not raise a false alarm.
 *
 * The tolerance is only safe in this direction. The licence already asserts that
 * the two rows are one person, so the comparison is looking for a spelling slip,
 * not deciding identity.
 */
function pl_cup_names_match($a, $b)
{
    $a = pl_cup_normalize_name($a);
    $b = pl_cup_normalize_name($b);
    if ($a === '' || $b === '' || $a === $b) {
        return true;
    }
    $tolerance = min(3, max(1, intdiv(max(strlen($a), strlen($b)), 8)));
    return levenshtein($a, $b) <= $tolerance;
}

/**
 * Whether two names under **different licences** are one and the same person.
 *
 * Exact, once case, diacritics, spacing and word order are normalised away — no
 * typo tolerance here, because two different athletes routinely differ by a
 * single letter ("Zapała Daria" and "Zapała Maria" are sisters, not a typo),
 * and this is the direction that would otherwise reject a correct file.
 */
function pl_cup_same_person($a, $b)
{
    $a = pl_cup_normalize_name($a);
    $b = pl_cup_normalize_name($b);
    return $a !== '' && $a === $b;
}

/**
 * Contradictions between an incoming set of rows and what the edition already
 * holds: one licence carrying two different athletes, or one athlete appearing
 * under two licences. Both mean a wrong licence number somewhere, and an
 * imported round cannot be corrected afterwards without knowing which row is
 * wrong — so the caller rejects the whole file and the operator fixes the CSV.
 *
 * Only individual rows are compared: a mixed row is a club, and its pair may be
 * two different athletes in every round.
 *
 * @param array $rows the incoming rows
 * @param array $storedRows rows already stored for the edition (any round)
 * @return string[] one message per contradiction
 */
function pl_cup_identity_conflicts(array $rows, array $storedRows)
{
    $known = [];
    foreach ($storedRows as $row) {
        if ($row['classification'] !== 'ind' || trim((string) $row['name']) === '') {
            continue;
        }
        $known[] = [
            'identity' => $row['identity'],
            'name' => $row['name'],
            'where' => 'runda ' . intval($row['round'] ?? 0),
        ];
    }

    $conflicts = [];
    foreach ($rows as $row) {
        if ($row['classification'] !== 'ind' || trim((string) $row['name']) === '') {
            continue;
        }
        foreach ($known as $seen) {
            $sameIdentity = $seen['identity'] === $row['identity'];

            if ($sameIdentity && !pl_cup_names_match($seen['name'], $row['name'])) {
                $conflicts[] = 'Licencja ' . $row['identity'] . ': w pliku "' . $row['name']
                    . '", a w zapisanych danych "' . $seen['name'] . '" (' . $seen['where'] . ').';
            } elseif (!$sameIdentity && pl_cup_same_person($seen['name'], $row['name'])) {
                $conflicts[] = 'Zawodnik "' . $row['name'] . '" ma w pliku licencję ' . $row['identity']
                    . ', a w zapisanych danych ' . $seen['identity'] . ' (' . $seen['where'] . ').';
            }
        }
        // Compared against the rest of the file too, so a contradiction inside
        // one import is caught on the same screen.
        $known[] = ['identity' => $row['identity'], 'name' => $row['name'], 'where' => 'ten sam plik'];
    }

    return array_values(array_unique($conflicts));
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
 * Names and clubs as **this competition** holds them, keyed by the cup's own
 * identity: the licence for athletes, the club code for mixed rows.
 *
 * The classification prefers these over what an import carried, so an athlete
 * entered here is shown with this competition's spelling of their club. An
 * athlete who is not entered here — someone who scored in an earlier round and
 * did not come to this one — keeps the spelling of their most recent round,
 * which is then the only source there is.
 *
 * @return array{ind: array<string,array>, mix: array<string,array>}
 */
function pl_cup_current_directory($tourId)
{
    $tourId = intval($tourId);
    $directory = ['ind' => [], 'mix' => []];

    foreach (pl_points_load_entries_directory($tourId) as $entry) {
        $code = trim((string) $entry['code']);
        if ($code === '') {
            continue;
        }
        $directory['ind'][$code] = ['name' => $entry['name'], 'club_name' => $entry['club_name']];
    }

    $Rs = safe_r_sql("SELECT CoCode, CoName FROM Countries WHERE CoTournament = $tourId");
    while ($row = safe_fetch($Rs)) {
        $code = trim((string) $row->CoCode);
        if ($code === '') {
            continue;
        }
        $directory['mix'][$code] = ['name' => '', 'club_name' => $row->CoName];
    }
    safe_free_result($Rs);

    return $directory;
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
