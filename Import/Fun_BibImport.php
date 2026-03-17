<?php
require_once __DIR__ . '/../Lookup/Fun_ClubName.php';

/**
 * Fun_BibImport.php — Processing functions for the BibImport feature.
 *
 * Provides:
 *  - pl_bibimport_lookup()          : Look up a licence number in LookUpEntries
 *  - pl_bibimport_is_duplicate()    : Check whether an entry already exists
 *  - pl_bibimport_resolve_class()   : Resolve age class from Classes table
 *  - pl_bibimport_upsert_country()  : Ensure a Countries row exists, return CoId
 *  - pl_bibimport_create_entry()    : INSERT a row into Entries
 *  - pl_bibimport_run()             : Orchestrate a full batch import
 */

/**
 * Look up a single licence number in LookUpEntries for IOC code 'POL'.
 *
 * @param string $code Licence number (already trimmed)
 * @return object|null The LookUpEntries row as an object, or null if not found
 */
function pl_bibimport_lookup($code) {
    $sql = "SELECT * FROM LookUpEntries"
         . " WHERE LueCode = " . StrSafe_DB($code)
         . "   AND LueIocCode = 'POL'"
         . " LIMIT 1";
    $rs  = safe_r_sql($sql);
    if (safe_num_rows($rs) > 0) {
        $row = safe_fetch($rs);
        safe_free_result($rs);
        return $row;
    }
    safe_free_result($rs);
    return null;
}

/**
 * Check whether an Entries row already exists for this licence in the current
 * tournament.
 *
 * @param string $code      Licence number
 * @param int    $tourId    Tournament ID
 * @return bool True if a duplicate entry exists
 */
function pl_bibimport_is_duplicate($code, $tourId) {
    $sql = "SELECT EnId FROM Entries"
         . " WHERE EnCode       = " . StrSafe_DB($code)
         . "   AND EnTournament = " . StrSafe_DB($tourId, true)
         . " LIMIT 1";
    $rs  = safe_r_sql($sql);
    $found = (safe_num_rows($rs) > 0);
    safe_free_result($rs);
    return $found;
}

/**
 * Resolve the most specific age class for an athlete.
 *
 * ClAgeFrom/ClAgeTo store the athlete's AGE in years (not birth year).
 * ianseo computes: age = year(tournament_end_date) - year(birth_date)
 * ClSex uses the same encoding as LueSex/EnSex: 0=male, 1=female, -1=unisex.
 * No conversion needed — pass LueSex directly.
 *
 * The narrowest age range wins (most specific class first).
 *
 * @param int    $tourId    Tournament ID
 * @param int    $lueSex    LueSex value (0=male, 1=female)
 * @param string $birthYear Four-digit birth year string (e.g. '2003')
 * @param string $division  Division code selected by the operator (e.g. 'R')
 * @return object|null Classes row object, or null if no class matched
 */
function pl_bibimport_resolve_class($tourId, $lueSex, $birthYear, $division) {
    // Compute athlete age at tournament end date (same formula as CheckCtrlCode.php)
    $tourYear  = intval(substr($_SESSION['TourWhenTo'], 0, 4));
    $birthYearInt = intval($birthYear);
    if ($birthYearInt <= 0 || $tourYear <= 0) {
        return null;
    }
    $age = $tourYear - $birthYearInt;

    $sql = "SELECT *"
         . " FROM Classes"
         . " WHERE ClTournament = " . StrSafe_DB($tourId, true)
         . "   AND ClSex IN (-1, " . intval($lueSex) . ")"
         . "   AND (ClDivisionsAllowed = '' OR FIND_IN_SET(" . StrSafe_DB($division) . ", ClDivisionsAllowed))"
         . "   AND ClAgeFrom <= " . intval($age)
         . "   AND ClAgeTo   >= " . intval($age)
         . " ORDER BY (ClAgeTo - ClAgeFrom) ASC"
         . " LIMIT 1";

    $rs = safe_r_sql($sql);
    if (safe_num_rows($rs) > 0) {
        $row = safe_fetch($rs);
        safe_free_result($rs);
        return $row;
    }
    safe_free_result($rs);
    return null;
}

/**
 * Ensure a Countries row exists for the given club code in this tournament.
 * If it does not exist, create it. Returns the CoId.
 *
 * CoCode is capped at 6 characters defensively (club codes are normally 3 chars).
 *
 * The $cache array (passed by reference) is used to avoid redundant SELECTs and
 * duplicate INSERTs when multiple athletes from the same club appear in one batch.
 * The read and write DB connections are separate in ianseo, so a SELECT on the read
 * connection would not see an uncommitted INSERT on the write connection — without
 * the cache this causes a duplicate key error on the second athlete from the same club.
 *
 * @param int    $tourId     Tournament ID
 * @param string $coCode     Club code (e.g. 'CSB')
 * @param string $coName     Full club name
 * @param array  &$cache     In-batch cache: coCode → CoId
 * @return int CoId of the existing or newly created row
 */
function pl_bibimport_upsert_country($tourId, $coCode, $coName, &$cache) {
    // Truncate defensively to stay within the 6-char column limit
    $safeCode = substr($coCode, 0, 6);

    // Return from cache if already resolved this batch
    if (isset($cache[$safeCode])) {
        return $cache[$safeCode];
    }

    $rs = safe_r_sql(
        "SELECT CoId FROM Countries"
        . " WHERE CoTournament = " . StrSafe_DB($tourId, true)
        . "   AND CoCode       = " . StrSafe_DB($safeCode)
        . " LIMIT 1"
    );

    if (safe_num_rows($rs) > 0) {
        $row = safe_fetch($rs);
        safe_free_result($rs);
        $cache[$safeCode] = (int) $row->CoId;
        return $cache[$safeCode];
    }
    safe_free_result($rs);

    // Row not found — insert it
    safe_w_sql(
        "INSERT INTO Countries SET"
        . "  CoTournament = " . StrSafe_DB($tourId, true)
        . ", CoCode       = " . StrSafe_DB($safeCode)
        . ", CoName       = " . StrSafe_DB($coName)
    );

    $cache[$safeCode] = (int) safe_w_last_id();
    return $cache[$safeCode];
}

/**
 * Insert a single row into Entries.
 *
 * ianseo column name quirk:
 *   EnFirstName → family name (LueFamilyName)
 *   EnName      → given name  (LueName)
 *
 * @param int    $tourId    Tournament ID
 * @param object $lue       LookUpEntries row
 * @param string $division  Division code selected by the operator
 * @param string $classId   Age class ID, or '' if unresolved
 * @param int    $coId      Countries.CoId
 * @return void
 */
function pl_bibimport_create_entry($tourId, $lue, $division, $classId, $coId) {
    safe_w_sql(
        "INSERT INTO Entries SET"
        . "  EnTournament = " . StrSafe_DB($tourId, true)
        . ", EnCode       = " . StrSafe_DB($lue->LueCode)
        . ", EnFirstName  = " . StrSafe_DB($lue->LueFamilyName)
        . ", EnName       = " . StrSafe_DB($lue->LueName)
        . ", EnSex        = " . StrSafe_DB($lue->LueSex, true)
        . ", EnDob        = " . StrSafe_DB($lue->LueCtrlCode)
        . ", EnDivision   = " . StrSafe_DB($division)
        . ", EnClass      = " . StrSafe_DB($classId)
        . ", EnCountry    = " . StrSafe_DB($coId, true)
        . ", EnIocCode    = 'POL'"
        . ", EnStatus     = " . StrSafe_DB($lue->LueStatus, true)
    );
}

/**
 * Run the full batch import for a list of licence numbers.
 *
 * Processes each licence in order:
 *   1. Normalise (trim, skip blank)
 *   2. Look up in LookUpEntries
 *   3. Check for duplicates in Entries
 *   4. Resolve age class from Classes
 *   5. Upsert Countries record
 *   6. Insert Entries row
 *
 * All Entries inserts are wrapped in a single transaction. If any DB write
 * fails, the transaction is rolled back and an error is returned.
 *
 * @param int    $tourId    Tournament ID
 * @param string $rawInput  Raw textarea content (lines of licence numbers)
 * @param string $division  Division code selected by the operator
 * @return array [
 *   'imported'        => int,
 *   'duplicates'      => [ ['code'=>..., 'name'=>...], ... ],
 *   'unmatched'       => [ 'code', ... ],
 *   'classUnresolved' => [ ['code'=>..., 'name'=>..., 'birthYear'=>...], ... ],
 *   'error'           => string|null,
 * ]
 */
function pl_bibimport_run($tourId, $rawInput, $division) {
    $result = [
        'imported'        => 0,
        'duplicates'      => [],
        'unmatched'       => [],
        'classUnresolved' => [],
        'error'           => null,
    ];

    // Normalise input: split on newlines, trim each line, drop blank lines
    $lines = preg_split('/\r?\n/', $rawInput);
    $codes = [];
    foreach ($lines as $line) {
        $code = trim($line);
        if ($code !== '') {
            $codes[] = $code;
        }
    }

    if (empty($codes)) {
        return $result;
    }

    // Collect all athletes that need to be inserted before opening the transaction,
    // so that we do not hold a transaction open during potentially slow lookups.
    $toInsert = []; // each element: ['lue' => object, 'classId' => string, 'classUnresolved' => bool]

    foreach ($codes as $code) {
        // Step 1: Lookup
        $lue = pl_bibimport_lookup($code);
        if ($lue === null) {
            $result['unmatched'][] = $code;
            continue;
        }

        // Step 2: Duplicate check
        if (pl_bibimport_is_duplicate($code, $tourId)) {
            $result['duplicates'][] = [
                'code' => $code,
                'name' => $lue->LueFamilyName . ' ' . $lue->LueName,
            ];
            continue;
        }

        // Step 3: Age class resolution
        $birthYear = (strlen($lue->LueCtrlCode) >= 4)
            ? substr($lue->LueCtrlCode, 0, 4)
            : '0';

        $classRow        = pl_bibimport_resolve_class($tourId, $lue->LueSex, $birthYear, $division);
        $classId         = ($classRow !== null) ? $classRow->ClId : '';
        $classUnresolved = ($classRow === null);

        if ($classUnresolved) {
            $result['classUnresolved'][] = [
                'code'      => $code,
                'name'      => $lue->LueFamilyName . ' ' . $lue->LueName,
                'birthYear' => $birthYear,
            ];
        }

        $toInsert[] = [
            'lue'             => $lue,
            'classId'         => $classId,
        ];
    }

    if (empty($toInsert)) {
        return $result;
    }

    // Open transaction for all writes
    $countryCache = []; // coCode → CoId, prevents duplicate inserts within this batch
    safe_w_BeginTransaction();
    try {
        foreach ($toInsert as $item) {
            $lue     = $item['lue'];
            $classId = $item['classId'];

            // Step 4: Country upsert (inside transaction so it rolls back with entries)
            $coId = pl_bibimport_upsert_country(
                $tourId,
                $lue->LueCountry,
                pl_club_short_name($lue->LueCoDescr),
                $countryCache
            );

            // Step 5: Create entry
            pl_bibimport_create_entry($tourId, $lue, $division, $classId, $coId);

            $result['imported']++;
        }
        safe_w_Commit();
    } catch (Exception $e) {
        safe_w_Rollback();
        $result['imported'] = 0;
        $result['error']    = $e->getMessage();
    }

    return $result;
}
