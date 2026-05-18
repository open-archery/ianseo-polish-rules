<?php
/**
 * Fun_ScorecardsOcr.php — Shared helpers for the ScorecardsOCR module.
 *
 * Provides:
 *  - pl_ocr_install()         : Auto-create PLOcrConfig table if absent
 *  - pl_ocr_get_config()      : Read a config value
 *  - pl_ocr_save_config()     : Write a config value
 *  - pl_ocr_lookup_scores()   : Fetch QuD{N} scores from Qualifications by bib + session
 */

/**
 * Auto-install PLOcrConfig table if it does not yet exist.
 */
function pl_ocr_install(): void
{
    $rs = safe_r_sql("SHOW TABLES LIKE 'PLOcrConfig'");
    if (safe_num_rows($rs) === 0) {
        safe_w_sql(
            "CREATE TABLE PLOcrConfig (
                PlocKey   VARCHAR(100) NOT NULL,
                PlocValue TEXT,
                PRIMARY KEY (PlocKey)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }
    safe_free_result($rs);
}

/**
 * Read a single config value from PLOcrConfig.
 *
 * @param string $key     Config key (e.g. 'api_key', 'model')
 * @param string $default Returned when the key does not exist
 */
function pl_ocr_get_config(string $key, string $default = ''): string
{
    $rs = safe_r_sql("SELECT PlocValue FROM PLOcrConfig WHERE PlocKey = " . StrSafe_DB($key));
    if (safe_num_rows($rs) > 0) {
        $row = safe_fetch($rs);
        safe_free_result($rs);
        return (string)($row->PlocValue ?? $default);
    }
    safe_free_result($rs);
    return $default;
}

/**
 * Write (upsert) a config value into PLOcrConfig.
 *
 * @param string $key   Config key
 * @param string $value Config value
 */
function pl_ocr_save_config(string $key, string $value): void
{
    safe_w_sql(
        "INSERT INTO PLOcrConfig (PlocKey, PlocValue)"
        . " VALUES (" . StrSafe_DB($key) . ", " . StrSafe_DB($value) . ")"
        . " ON DUPLICATE KEY UPDATE PlocValue = " . StrSafe_DB($value)
    );
}

/**
 * Look up qualification scores for an archer by bib number.
 *
 * Queries Entries to resolve EnId by bib + tournament, then reads scores from
 * Qualifications. When $session is 1–8 the per-distance columns (QuD{N}Score
 * etc.) are returned; when $session is 0 the grand total (QuScore/QuGold/
 * QuXnine) is returned instead.
 *
 * @param string $bib     Bib number as read from the scorecard barcode
 * @param int    $session Distance index (1–8) or 0 for grand total
 * @param int    $tourId  Current tournament ID
 * @return array{found: bool, score: int|null, gold: int|null, xnine: int|null}
 */
function pl_ocr_lookup_scores(string $bib, int $session, int $tourId): array
{
    $notFound = ['found' => false, 'score' => null, 'gold' => null, 'xnine' => null];

    if ($bib === '') {
        return $notFound;
    }

    $tourSafe = intval($tourId);

    $rs = safe_r_sql(
        "SELECT EnId FROM Entries"
        . " WHERE EnCode = " . StrSafe_DB($bib)
        . "   AND EnTournament = {$tourSafe}"
        . " LIMIT 1"
    );

    if (safe_num_rows($rs) === 0) {
        safe_free_result($rs);
        return $notFound;
    }

    $entry = safe_fetch($rs);
    safe_free_result($rs);
    $enId = intval($entry->EnId);

    if ($session >= 1 && $session <= 8) {
        $n   = intval($session);
        $rs2 = safe_r_sql(
            "SELECT QuD{$n}Score AS score, QuD{$n}Gold AS gold, QuD{$n}Xnine AS xnine"
            . " FROM Qualifications WHERE QuId = {$enId}"
        );
    } else {
        $rs2 = safe_r_sql(
            "SELECT QuScore AS score, QuGold AS gold, QuXnine AS xnine"
            . " FROM Qualifications WHERE QuId = {$enId}"
        );
    }

    if (safe_num_rows($rs2) === 0) {
        safe_free_result($rs2);
        return $notFound;
    }

    $row = safe_fetch($rs2);
    safe_free_result($rs2);

    return [
        'found' => true,
        'score' => $row->score !== null ? intval($row->score) : null,
        'gold'  => $row->gold  !== null ? intval($row->gold)  : null,
        'xnine' => $row->xnine !== null ? intval($row->xnine) : null,
    ];
}
