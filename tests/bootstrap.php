<?php
/**
 * Test bootstrap: defines fake implementations of ianseo core globals.
 *
 * Module Fun_*.php files only CALL safe_r_sql, safe_w_sql, StrSafe_DB,
 * get_text, CheckTourSession — they never define or require them, so
 * defining them here before a module file is included gives tests full
 * control over the "database".
 *
 * Each shim is guarded individually (not by one umbrella check) so that a
 * partially-bootstrapped environment (e.g. a real ianseo core already
 * defining safe_r_sql) still gets the remaining shims instead of silently
 * skipping all of them.
 */

declare(strict_types=1);

require __DIR__ . '/Support/FakeDb.php';
require __DIR__ . '/Support/CallLog.php';
require __DIR__ . '/Support/PlTestCase.php';

$_SESSION = [];

if (!function_exists('safe_r_sql')) {
    function safe_r_sql($sql)
    {
        return FakeDb::query($sql);
    }
}

if (!function_exists('safe_w_sql')) {
    function safe_w_sql($sql)
    {
        return FakeDb::query($sql);
    }
}

if (!function_exists('safe_fetch')) {
    // Mirrors mysqli_fetch_object: returns an object, or null when exhausted.
    function safe_fetch($rs): ?object
    {
        return $rs->fetch();
    }
}

if (!function_exists('safe_num_rows')) {
    function safe_num_rows($rs): int
    {
        return $rs->numRows();
    }
}

if (!function_exists('safe_free_result')) {
    function safe_free_result($rs): void
    {
    }
}

if (!function_exists('safe_w_last_id')) {
    function safe_w_last_id(): int
    {
        return FakeDb::lastInsertId();
    }
}

if (!function_exists('safe_w_BeginTransaction')) {
    function safe_w_BeginTransaction(): void
    {
        FakeDb::$tx[] = 'begin';
    }
}

if (!function_exists('safe_w_Commit')) {
    function safe_w_Commit(): void
    {
        FakeDb::$tx[] = 'commit';
    }
}

if (!function_exists('safe_w_Rollback')) {
    function safe_w_Rollback(): void
    {
        FakeDb::$tx[] = 'rollback';
    }
}

if (!function_exists('StrSafe_DB')) {
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
}

if (!function_exists('get_text')) {
    // No return type: real ianseo get_text passes non-string input through
    // unchanged (e.g. numeric column values), it doesn't force a string cast.
    function get_text($id, $file = '', $params = null)
    {
        return $id;
    }
}

if (!function_exists('CheckTourSession')) {
    function CheckTourSession($required = true): bool
    {
        return true;
    }
}

// Recording shims for Modules/Sets/lib.php builder functions used by this
// module's Setup_*_PL.php / lib.php orchestration logic.
if (!function_exists('CreateDivision')) {
    function CreateDivision(...$args): void
    {
        CallLog::record('CreateDivision', $args);
    }
}

if (!function_exists('CreateClass')) {
    function CreateClass(...$args): void
    {
        CallLog::record('CreateClass', $args);
    }
}

if (!function_exists('InsertClassEvent')) {
    function InsertClassEvent(...$args): void
    {
        CallLog::record('InsertClassEvent', $args);
    }
}
