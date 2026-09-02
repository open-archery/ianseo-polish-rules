# Gotchas

Non-obvious footguns discovered while working in this repo, kept here so future sessions
don't rediscover them the hard way. See `CLAUDE.md` for general project instructions.

**Keep this file current.** When you hit something that cost you real debugging time —
a reserved word, a wrong assumption about ianseo core, a tooling trap, a subtle logic bug
whose failure mode wasn't obvious from reading the code — add an entry here in the same
commit as the fix. Terse is fine; the goal is "don't step on this rake again," not prose.

## TCPDF

- **Detecting a page break by polling `PageNo()` after the fact is one iteration too
  late.** `SetAutoPageBreak(true, ...)` triggers `AddPage()` *inside* whatever `Cell()`
  call doesn't fit — not between loop iterations. Checking "did the page number change"
  at the top of the *next* iteration means the row that actually caused the break already
  got drawn (unlabeled) on the new page one iteration earlier than the check catches it;
  if that row is the *last* one, the check never runs again and the mislabeling is never
  caught at all. To repeat a header (or otherwise react to a break) correctly, preflight
  the row's height with the base class's own break check *before* drawing it — e.g. TCPDF's
  `checkPageBreak($height)` (protected, but callable from a subclass method) returns `true`
  and performs the break itself if the height doesn't fit, so you can act on that return
  value before any of the row's cells are drawn, not after.

## PHP / MySQL

- **A POST field can be an array when you expect a string.** `$_POST['Field']` is `[...]`,
  not a string, whenever the request sends `Field[]=x` (trivial to craft, not just a typo).
  Passing that into a string-only function — `trim()`, `str_*`, etc. — throws a `TypeError`
  in PHP 8.2+, so a crafted request can 500 a page that otherwise validates its input
  carefully. Guard with `is_string($_POST['Field'])` before calling string functions on
  anything read from `$_POST`/`$_GET`, not just an `isset()` check. Note `intval()` is *not*
  in this category — it silently accepts an array (`0` for empty, `1` for non-empty) rather
  than throwing, so don't assume every scalar-looking builtin behaves the same way.

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

- **`$SubRule` inside a `Setup_{Type}_{Lang}.php` script is a raw 1-based dropdown position
  ('1', '2', ...), not the sub-rule's descriptive string** — `Tournament/index.php`'s real
  form handler calls `GetSetupFile($TourId, $ToType, $Lang, $_REQUEST['d_SubRule'], $ToTypeSubRule)`,
  and `$_REQUEST['d_SubRule']` is literally the `<option value="...">` position from the
  sub-rule `<select>`, built as `$k+1` over `$SetType[...]['rules'][$ToType]`'s array
  indices. The actual rule name (e.g. `'Poland-4x70m'`) only exists as `$subRuleName` (5th
  param) — comparing `$SubRule === 'Poland-4x70m'` inside the setup script silently never
  matches through the real UI, even though the sub-rule dropdown itself shows/stores the
  right label and even though `GetSetupFile()` called *manually* with a literal string in
  both the 4th and 5th argument positions (e.g. from a CLI smoke test) masks the bug by
  coincidence. `require_once($file)` inside `GetSetupFile()` shares that function's local
  scope, so the required setup script can read `$subRuleName` directly — compare against
  that (with an `isset()` guard, since non-UI callers may not set it), not `$SubRule`.

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
- **Calling `GetSetupFile()` (`Common/Fun_ScriptsOnNewTour.inc.php`) from a bare CLI script
  exits 255 with empty stdout/stderr, even on the unmodified core path** (reproduces
  identically for an unrelated `Poland-Full` call, not just a new sub-rule) — something
  downstream of the setup script assumes an HTTP/session context that plain `php script.php`
  doesn't have. The DB writes made *by the setup script itself* (Divisions, Classes,
  `TournamentDistances`, Events, ...) commit fine before the crash; only steps at/after the
  final `UpdateTourDetails()` call are unverified this way (`Tournament.ToNumDist`/
  `ToTypeSubRule` were still unchanged after the crash in testing). To sanity-check a
  `Setup_*_PL.php` change from the CLI: call `GetSetupFile()` in one `php` invocation, then
  query the affected tables in a *separate* invocation — don't trust that script's own exit
  code or trailing output, and don't treat `Tournament`-table columns as confirmed without a
  real browser-driven tournament create/reset.

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

## Docker Desktop bind mounts (Windows)

- **New files added to an already-mounted subdirectory can stay invisible inside the
  container until it is restarted.** The `Modules/Sets/PL` bind mount showed a *newly created
  directory* (`openspec/changes/cup-ranking/`) immediately, but files newly created in the
  long-existing `PointsRanking/` directory never appeared: `ls` kept returning the old
  listing and the directory's mtime stayed frozen at its old value, so a page 404'd / an
  `include` silently failed while the file was plainly there on the host. Touching the
  directory from the host does not flush it; `docker restart <app-container>` does. Before
  debugging "my new module file isn't loading", check `docker exec <c> ls <dir>` — if the
  file is missing there, it's the mount, not your code.
- **`docker cp` writes into the container as root, but `docker exec` runs as the image's
  user**, so a tree copied in that way can't be `rm -rf`'d by a later `docker exec` (every
  entry fails with "Permission denied"). Use `docker exec -u root` for the cleanup, and
  remember `docker cp SRC container:/path` *nests* (`/path/SRC`) when `/path` already exists.
- **`docker cp`/`docker exec` with an absolute container path mangles under Git Bash**
  (MSYS path conversion turns `/tmp/x.php` into `C:/...`). Prefix the command with
  `MSYS_NO_PATHCONV=1`, or write the path as `//tmp/x.php`.

## ianseo scores

- **`Individuals.IndScore` is not the qualification total.** In this ruleset's competitions
  it sits at `-1` for every athlete of every tournament, so anything reading it silently
  exports `-1` as a score (and, if the consumer validates, rejects its own export on
  re-import). The qualification round total ianseo maintains per entry is
  `Qualifications.QuScore` — one row per entry, keyed by `QuId = Entries.EnId`, with no
  `QuTournament` column, so join through `Entries` to scope it to a tournament. Team totals
  *are* in `Teams.TeScore`. Clamp negatives to 0 rather than trusting any of these columns
  to be populated.
