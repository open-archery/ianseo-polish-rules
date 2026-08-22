# Gotchas

Non-obvious footguns discovered while working in this repo, kept here so future sessions
don't rediscover them the hard way. See `CLAUDE.md` for general project instructions.

**Keep this file current.** When you hit something that cost you real debugging time —
a reserved word, a wrong assumption about ianseo core, a tooling trap, a subtle logic bug
whose failure mode wasn't obvious from reading the code — add an entry here in the same
commit as the fix. Terse is fine; the goal is "don't step on this rake again," not prose.

## PHP / MySQL

- **`DIV` is a reserved MySQL keyword** (integer-division operator). A bare column alias
  `AS Div` breaks the SQL parser with a 1064 syntax error near the *next* token, which reads
  as if the problem is somewhere else. Any two-letter-plus alias that might collide with a
  SQL keyword (`DIV`, `RANK`, `GROUP`, `ORDER`, ...) should get a longer, unambiguous alias
  (`DivCode`, not `Div`). This one shipped past a full PHPUnit run because the test harness
  never touches a real MySQL parser — only live testing against the actual DB caught it.
- **`pl_points_rank`-style bugs: compare numeric totals with `<=>`, not `!==`.** The same
  logical point value can arrive as `int` (a bracket lookup, `9`) or `float` (a team share,
  `9.0`) depending on which code path produced it. `9 !== 9.0` is `true` in PHP, so identity
  comparison silently breaks tie-detection between rows of different origin. This class of
  bug is invisible in a test suite that only ever constructs `int` fixtures.
- **`max()` over "last place" can pick a DSQ/DNS/DNF sentinel instead of the real last
  place.** This module encodes "no valid result" as a place `>= 29999`. Any cutoff-style
  "zero the worst place" logic must filter those sentinels out *before* taking `max()`,
  or a single DSQ entrant in a category silently defeats the cutoff for everyone else.

## ianseo core paths

- **`Modules/config.php` is a proxy shim**, not the real `config.php`. It exists so that a
  file at `Modules/Sets/PL/<SubDir>/File.php` can reach ianseo's root `config.php` with a
  *4-level* `dirname(__FILE__)` chain even though the real file sits *5* levels up. It only
  covers `config.php` — it does **not** generalize to other core paths. Copying the 4-level
  pattern for e.g. `Common/pdf/IanseoPdf.php` silently resolves to a non-existent file
  (`Modules/Common/pdf/IanseoPdf.php`) and 500s with an *empty* response body (no PHP error
  text reaches the client). Verify the real depth against the actual container filesystem
  (`docker exec <app-container> php -r 'var_dump(file_exists($path));'`) before trusting a
  `dirname()` chain copied from a different file, especially one at a different nesting
  depth or reaching a different subtree of core.
- **`CheckTourSession($PrintCrack = false)`**: passing `false` (or omitting the arg) makes
  it return `false` silently on an invalid session — it does **not** stop execution. If you
  call it with `false`, you must check the return value yourself, or the rest of the script
  runs against a missing `$_SESSION['TourId']`. Every other PL entry point uses `true`
  (crash-page-and-exit on invalid session); use `false` only when you have a specific reason
  and actually check the result.

## Docker / this repo's dev environment

- **Apache's `error.log`/`access.log` inside the app container are symlinks to
  `/dev/stderr`/`/dev/stdout`.** Running `docker exec <container> cat /var/log/apache2/error.log`
  (or `tail`, `wc -l`, ...) opens *that new exec process's own* stderr as a file to read from,
  which has no writer and blocks forever — it looks like a hang, not an error. Use
  `docker logs <container>` (reads the container's actual stdout/stderr stream) instead.
- **Git-Bash on Windows mangles absolute Unix paths passed to `docker exec`.** An argument
  like `/var/log/apache2/error.log` gets MSYS-translated into a Windows path
  (`C:/Program Files/Git/var/log/apache2/error.log`) before `docker exec` ever sees it. Use
  the PowerShell tool for `docker exec` calls that take absolute container paths as
  arguments, or the command silently targets the wrong (nonexistent) path.
- **PHP 8.5 runs in the app container** (this repo's CLAUDE.md/tools scripts assume 8.2+;
  the actual container is newer). Test locally against the pinned PHPUnit + a portable PHP
  if you can't reach the container, but treat "works in the unit suite" and "works against
  the real container" as two separate checks — this repo's DB layer (raw SQL strings, no
  query builder) only gets validated by the second one.

## Testing (`tests/Support/FakeDb.php`)

- **Handler registration order = match precedence, and it's easy to get backwards.**
  `FakeDb::on()` prepends (`array_unshift`), so the *most recently registered* pattern is
  checked first. Broad patterns like `/FROM Teams\b/` and `/FROM Individuals/` will also
  match *other* queries against the same table (e.g. a starter-count query has its own
  `FROM Teams` clause). If two different queries in the code under test hit the same table,
  register a narrow, query-specific pattern (matching a literal column alias unique to that
  query, e.g. `/Teams\.TeEvent AS Category/`) *after* the broad one, so it wins for that one
  query without needing to make the broad stub itself more specific.

## Cross-branch / spec assumptions

- **A design doc can reference code that doesn't exist on your branch yet.** The
  points-ranking design assumed `CupRanking/Fun_CupRanking.php` existed (to move shared code
  out of it) — that module only exists on an unmerged sibling branch (`feat/cup-ranking`),
  based on a commit *before* this repo's PHPUnit harness was added. Check `git log`/`git
  branch -a` for a referenced file before assuming it's missing by mistake; the design may
  simply predate a merge that hasn't happened. Implement the shared piece standalone and
  leave a note for whoever merges the other branch later, rather than blocking on it.
