<?php
/**
 * Minimal fake DB backing the safe_* shims in tests/bootstrap.php.
 * Regex pattern -> canned rows, plus a recorded-query log for write assertions.
 */
final class FakeDb
{
    /** @var string[] every SQL statement executed, in order */
    public static array $queries = [];

    /** @var string[] 'begin' | 'commit' | 'rollback' */
    public static array $tx = [];

    /** @var array<int, array{0: string, 1: array}> */
    private static array $handlers = [];

    /** @var array<int, array{0: string, 1: string}> */
    private static array $throwHandlers = [];

    /** @var int[] */
    private static array $lastIds = [];

    public static function reset(): void
    {
        self::$queries = [];
        self::$tx = [];
        self::$handlers = [];
        self::$throwHandlers = [];
        self::$lastIds = [];
    }

    /** Any query matching $pattern (regex) returns $rows (list of assoc arrays). Later registrations win. */
    public static function on(string $pattern, array $rows): void
    {
        array_unshift(self::$handlers, [$pattern, $rows]);
    }

    /** Any query matching $pattern throws an Exception($message) instead of returning a result. */
    public static function throwOn(string $pattern, string $message = 'fake db error'): void
    {
        array_unshift(self::$throwHandlers, [$pattern, $message]);
    }

    public static function willInsertId(int $id): void
    {
        self::$lastIds[] = $id;
    }

    public static function query(string $sql): FakeResult
    {
        self::$queries[] = $sql;
        foreach (self::$throwHandlers as [$pattern, $message]) {
            if (preg_match($pattern, $sql)) {
                throw new \Exception($message);
            }
        }
        foreach (self::$handlers as [$pattern, $rows]) {
            if (preg_match($pattern, $sql)) {
                return new FakeResult($rows);
            }
        }
        return new FakeResult([]);
    }

    public static function lastInsertId(): int
    {
        return self::$lastIds ? array_shift(self::$lastIds) : 0;
    }

    /** Recorded queries matching $pattern, in execution order. */
    public static function executed(string $pattern): array
    {
        return array_values(array_filter(self::$queries, fn ($q) => preg_match($pattern, $q) === 1));
    }
}

final class FakeResult
{
    private int $pos = 0;

    public function __construct(private readonly array $rows)
    {
    }

    public function fetch(): ?object
    {
        return isset($this->rows[$this->pos]) ? (object) $this->rows[$this->pos++] : null;
    }

    public function numRows(): int
    {
        return count($this->rows);
    }
}
