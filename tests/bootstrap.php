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

if (!function_exists('CreateDistanceNew')) {
    function CreateDistanceNew(...$args): void
    {
        CallLog::record('CreateDistanceNew', $args);
    }
}

if (!function_exists('CreateEventNew')) {
    function CreateEventNew(...$args): void
    {
        CallLog::record('CreateEventNew', $args);
    }
}

if (!function_exists('CreateTargetFace')) {
    function CreateTargetFace(...$args): void
    {
        CallLog::record('CreateTargetFace', $args);
    }
}

if (!function_exists('CreateFinals')) {
    function CreateFinals(...$args): void
    {
        CallLog::record('CreateFinals', $args);
    }
}

if (!function_exists('CreateDistanceInformation')) {
    function CreateDistanceInformation(...$args): void
    {
        CallLog::record('CreateDistanceInformation', $args);
    }
}

if (!function_exists('UpdateTourDetails')) {
    function UpdateTourDetails(...$args): void
    {
        CallLog::record('UpdateTourDetails', $args);
    }
}

// Target-type constants (Modules/Sets/lib.php in real ianseo) — only the ones
// this module's setup scripts actually reference (see ianseo-internals.md
// §6.1 for the full table).
if (!defined('TGT_IND_1_big10'))   define('TGT_IND_1_big10', 1);
if (!defined('TGT_IND_6_big10'))   define('TGT_IND_6_big10', 2);
if (!defined('TGT_IND_1_small10')) define('TGT_IND_1_small10', 3);
if (!defined('TGT_IND_6_small10')) define('TGT_IND_6_small10', 4);
if (!defined('TGT_OUT_FULL'))      define('TGT_OUT_FULL', 5);
if (!defined('TGT_OUT_5_big10'))   define('TGT_OUT_5_big10', 9);

// Recording shim for ianseo core (Qualification/Fun_Qualification.local.inc.php).
// Seeds one Individuals row per entered athlete; module code calls it after
// adding entries. Real signature: MakeIndividuals(&$affected, $tournament = 0): int
// (0 = ok, 1 = error).
if (!function_exists('MakeIndividuals')) {
    function MakeIndividuals(&$affected, $tournament = 0): int
    {
        CallLog::record('MakeIndividuals', [$affected, $tournament]);
        return 0;
    }
}
