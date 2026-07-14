<?php
/**
 * Test bootstrap: defines fake implementations of ianseo core globals.
 *
 * Module Fun_*.php files only CALL safe_r_sql, safe_w_sql, StrSafe_DB,
 * get_text, CheckTourSession — they never define or require them, so
 * defining them here before a module file is included gives tests full
 * control over the "database".
 */

declare(strict_types=1);

require __DIR__ . '/Support/FakeDb.php';
require __DIR__ . '/Support/PlTestCase.php';

$_SESSION = [];

if (!function_exists('safe_r_sql')) {
    function safe_r_sql($sql)
    {
        return FakeDb::query($sql);
    }

    function safe_w_sql($sql)
    {
        return FakeDb::query($sql);
    }

    // Mirrors mysqli_fetch_object: returns an object, or null when exhausted.
    function safe_fetch($rs): ?object
    {
        return $rs->fetch();
    }

    function safe_num_rows($rs): int
    {
        return $rs->numRows();
    }

    function safe_free_result($rs): void
    {
    }

    function safe_w_last_id(): int
    {
        return FakeDb::lastInsertId();
    }

    function safe_w_BeginTransaction(): void
    {
        FakeDb::$tx[] = 'begin';
    }

    function safe_w_Commit(): void
    {
        FakeDb::$tx[] = 'commit';
    }

    function safe_w_Rollback(): void
    {
        FakeDb::$tx[] = 'rollback';
    }

    // Mirrors Common/Fun_DB.inc.php StrSafe_DB semantics.
    function StrSafe_DB($s, $removeQuotes = false)
    {
        if (is_null($s)) {
            return $removeQuotes ? '' : "''";
        }
        if (is_array($s)) {
            return array_map(fn ($x) => StrSafe_DB($x, $removeQuotes), $s);
        }
        $escaped = addcslashes((string) $s, "\\'\"\0\n\r\x1a");
        return $removeQuotes ? $escaped : "'" . $escaped . "'";
    }

    function get_text($id, $file = '', $params = null): string
    {
        return $id;
    }

    function CheckTourSession($required = true): bool
    {
        return true;
    }
}
