<?php
/**
 * Records calls to shimmed ianseo core builder functions (CreateDivision,
 * CreateClass, InsertClassEvent, ...) so orchestration logic in lib.php can
 * be tested by asserting the call sequence rather than DB side effects.
 */
final class CallLog
{
    /** @var array<string, array<int, array>> function name => list of arg arrays */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function record(string $fn, array $args): void
    {
        self::$calls[$fn][] = $args;
    }

    /** All recorded arg arrays for $fn, in call order. */
    public static function calls(string $fn): array
    {
        return self::$calls[$fn] ?? [];
    }

    /** Calls to $fn where $callback(args) returns true. */
    public static function callsMatching(string $fn, callable $predicate): array
    {
        return array_values(array_filter(self::calls($fn), $predicate));
    }
}
