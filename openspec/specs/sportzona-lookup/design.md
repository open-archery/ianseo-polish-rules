# Architecture: SportzonaLookup

**Feature:** PZŁucz Sportzona Athlete Lookup Integration
**Author:** Developer agent
**Status:** Approved for implementation

---

## 1. Integration Approach

### 1.1 The core problem

ianseo's lookup synchronisation system (`Partecipants/LookupTableLoad.php`) fetches
athlete data with a plain `file_get_contents()` GET request. The Sportzona endpoint
requires HTTP POST with a JSON body. These cannot be bridged without a local adapter.

### 1.2 Solution: `%`-path local adapter

ianseo's `LookUpPaths.LupPath` column supports a `%`-prefix convention: when the
path begins with `%`, ianseo constructs a local HTTP URL and GETs it from the same
server. By placing an adapter script at
`Modules/Sets/PL/Lookup/SportzonaProxy.php` and registering:

```
LupPath = '%Modules/Sets/PL/Lookup/SportzonaProxy.php'
```

ianseo will GET `http://localhost/…/Modules/Sets/PL/Lookup/SportzonaProxy.php`,
which in turn POSTs to Sportzona using cURL, transforms the response, and returns
a JSON array that ianseo processes via its extranet branch.

### 1.3 Why the extranet branch is triggered

`LookupTableLoad.php` activates the JSON/extranet processing branch when
`LupOrigin` is non-empty (line 324). The registration sets
`LupOrigin = 'Sportzona'`, which is sufficient to trigger this path.

### 1.4 Why cURL instead of file_get_contents with stream_context

`allow_url_fopen` may be disabled on production PHP servers. cURL is always
available in ianseo deployments (it is used elsewhere in the codebase) and
gives full control over method, headers, and body.

### 1.5 Why the adapter does NOT use ianseo bootstrap

The adapter is fetched by ianseo via HTTP GET from its own server — at the time
of the request there is no user session, no tournament context, and no ianseo
`config.php` has been included. Calling `CheckTourSession()` or requiring
`config.php` would cause a fatal error (session already started by ianseo's
process, or a double-bootstrap). The adapter is therefore a self-contained PHP
script that only uses cURL and built-in PHP functions.

---

## 2. File List

| File                                                    | Purpose                                                                                                                 |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `Modules/Sets/PL/Lookup/SportzonaProxy.php`             | Adapter script. Called by ianseo via GET; internally POSTs to Sportzona; outputs JSON array in ianseo extranet format.  |
| `Modules/Sets/PL/Lookup/Fun_ClubName.php`               | Club name transformation library: prefix table, short name algorithm, code algorithm, collision resolution.             |
| `Modules/Sets/PL/Lookup/Install.php`                    | One-time registration page (browser-accessible, ianseo session guarded). Inserts/updates the `LookUpPaths` row for POL. |
| `FeaturesDocumentation/SportzonaLookup/architecture.md` | This document.                                                                                                          |

---

## 3. Club Name Transformation Design

### 3.1 Design goals

- **Deterministic**: the same input club name must always produce the same code
  across multiple syncs, regardless of the order athletes are returned from Sportzona.
- **Collision-free**: if two clubs naturally derive the same code, one gets a
  disambiguating suffix (`CODE2`, `CODE3`, …).
- **Extensible**: the prefix table is a plain PHP array constant so it can be
  extended without changing algorithm code.

### 3.2 Prefix table ordering

The table is sorted by string length (longest first) at definition time so that
`Łuczniczy Uczniowski Klub Sportowy` is matched before the shorter `Uczniowski
Klub Sportowy`. The matching loop tries each prefix in order; the first match wins.

### 3.3 Short name algorithm

1. Normalize whitespace (trim + collapse multiple spaces to single space).
2. Special case: if the normalized name is `Niezrzeszony (Niezrzeszony)`, return
   `Niezrzeszony` immediately.
3. Extract city: capture the last `(…)` group with a regex.
4. Remove the trailing `(city)` from the working string.
5. Match the beginning of the working string against each prefix (longest first).
   If matched, record the abbreviation and strip the prefix.
6. Trim the remaining string (the proper name part).
7. If the proper name contains `"…"` (quoted), extract the quoted content.
   Then look for any unquoted words between the closing `"` and the end of the
   string; append them to the proper name (handles e.g. `"Silesia" Miechowice`).
8. Build: `{ABBR} {ProperName} {City}` (no leading space if no abbreviation found).

### 3.4 Code algorithm

1. Apply the same normalization and special-case check.
2. Derive proper name using the same logic as §3.3.
3. Derive city using the same regex.
4. Special case `Niezrzeszony`: code = `NIE`.
5. Split proper name on whitespace and hyphens into words.
6. Filter out pure-digit words (e.g. `"11"`, `"25"` do not contribute).
7. Take the first letter of each remaining word.
8. Append the first letter of the city.
9. If result < 2 chars, extend by adding additional letters from the city (2nd
   char, 3rd char, …) until length ≥ 2.
10. If result > 4 chars, truncate to 4.
11. Uppercase.

### 3.5 Collision resolution

Collision resolution is performed at the batch level (not per-club):

1. Build a map of `rawClubName → {code, shortName}` for all distinct club names
   in the Sportzona response.
2. Group by derived code. Clubs whose code is unique keep it unchanged.
3. For clubs sharing a code, sort them alphabetically by raw club name (case-
   insensitive, then case-sensitive for stability). The first in the sorted order
   keeps the base code; subsequent clubs get `{code}2`, `{code}3`, etc.
4. This guarantees that the same club always receives the same code — provided its
   raw club name does not change in the Sportzona registry between syncs.

---

## 4. Status Value Mapping

ianseo uses the following `LueStatus` integer values (observed from
`DoLookupEntriesCheck` in `LookupTableLoad.php`):

| Sportzona condition                                                                  | `LueStatus` value | Meaning in ianseo |
| ------------------------------------------------------------------------------------ | ----------------- | ----------------- |
| `isArchived = false` AND (`licenceDate` is in the future OR `licenceDate` is absent) | `1`               | Active            |
| `isArchived = false` AND `licenceDate` is in the past                                | `5`               | Licence expired   |
| `isArchived = true`                                                                  | `8`               | Archived/inactive |

Justification:

- `1` (active) is the standard status for a valid participant.
- `5` (expired) is the value ianseo itself assigns when `ToWhenTo > LueStatusValidUntil`.
  Using it for expired licences matches the semantics already understood by ianseo.
- `8` (archived) is assigned to inactive athletes; ianseo skips status updates for
  entries with `EnStatus = 6` or `= 7` but archived athletes with status `8` can
  still be looked up and matched.

Date comparison uses the server's current date (PHP `date('Y-m-d')`) to check
whether `licenceDate` is in the past.

---

## 5. One-Time Registration

### 5.1 SQL

The `Install.php` page executes:

```sql
INSERT INTO LookUpPaths (LupIocCode, LupPath, LupOrigin)
VALUES ('POL', '%Modules/Sets/PL/Lookup/SportzonaProxy.php', 'Sportzona')
ON DUPLICATE KEY UPDATE
    LupPath   = '%Modules/Sets/PL/Lookup/SportzonaProxy.php',
    LupOrigin = 'Sportzona';
```

### 5.2 Guard

`Install.php` requires an ianseo session with a valid tournament open
(`CheckTourSession(true)`), so only authenticated ianseo administrators can run it.
It outputs a confirmation message after executing the SQL. It is idempotent — re-
running it is safe.

### 5.3 Verification

After running `Install.php`, the operator should open
`Partecipants/LookupTableLoad.php` and verify that a `POL` row appears with a
checkbox in the "Sync" column.

---

## 6. Data Flow Diagram

```
Tournament operator browser
  │
  │  POST form (tick "POL" download checkbox)
  ▼
Partecipants/LookupTableLoad.php
  │  reads LookUpPaths WHERE LupIocCode='POL'
  │  constructs URL: http://localhost/.../SportzonaProxy.php
  │
  │  file_get_contents(URL)  ← GET request
  ▼
Modules/Sets/PL/Lookup/SportzonaProxy.php
  │  cURL POST → https://sportzona.pl/wsx/players/list/discipline
  │  receives {"players": [...], "numberOfPlayers": N}
  │  requires Fun_ClubName.php
  │  transforms each player record
  │  resolves code collisions across all clubs
  │  outputs JSON array
  ▼
LookupTableLoad.php (back in DoLookupEntries)
  │  json_decode($DataSource)
  │  DELETE FROM LookUpEntries WHERE LueIocCode='POL'
  │  INSERT/UPDATE LookUpEntries for each archer
  │  calls DoLookupEntriesCheck() to update Entries table
  ▼
ianseo LookUpEntries table (populated)
```

---

## 7. Error Handling

| Failure scenario                      | Adapter behaviour                      |
| ------------------------------------- | -------------------------------------- |
| cURL fails to connect to Sportzona    | Output `[]` with `HTTP/1.1 503` header |
| Sportzona returns non-200 HTTP status | Output `[]` with `HTTP/1.1 502` header |
| Sportzona response is malformed JSON  | Output `[]` with `HTTP/1.1 502` header |
| `players` array is missing or empty   | Output `[]` (empty array, HTTP 200)    |
| Individual player has no `licence`    | Skip the record silently               |

All error conditions return valid JSON (empty array), so ianseo's
`json_decode($DataSource)` will succeed and simply import zero records rather
than crashing.

---

## 8. No Menu Entry Required

This feature does not add a menu item. The entry point for operators is the
existing ianseo Synchronize page (`Partecipants/LookupTableLoad.php`). The
`Install.php` registration page is a one-time admin tool, not a recurring menu
item — it should be run once after deployment and does not need a persistent
menu link.
