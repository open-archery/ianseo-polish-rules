# Implementation Plan — PZŁucz Tournament Setup Scripts

## Scope

Three setup files for the Polish (PZŁucz) competition style:

| File             | Tournament type | Format                 |
| ---------------- | --------------- | ---------------------- |
| `Setup_1_PL.php` | 1 (1440 Round)  | Outdoor, 4 distances   |
| `Setup_3_PL.php` | 3 (Single-dist) | Outdoor, 1–2 distances |
| `Setup_6_PL.php` | 6 (Indoor 18m)  | Indoor                 |

Plus a shared `lib.php` with `CreateStandardDivisions`, `CreateStandardClasses`,
`CreateStandardEvents`, and `InsertStandardEvents` functions used by all three.

### Out of Scope

- **Post-elimination sub-ranking** (§2.6.5 — losers of the same round get unique places).
  This requires modifying `Rank/Obj_Rank_GridInd_calc.php` and `Rank/Obj_Rank_GridTeam_calc.php`
  in the core ranking engine. It is a runtime ranking feature, not a tournament configuration
  concern — setup scripts cannot configure this. It needs a separate implementation task.
- Para-archery, field archery, 3D archery, special shootings.

---

## Architecture

Following the FITA module pattern (the cleanest reference in the codebase):

```
Modules/Sets/PL/
├── sets.php               # Already exists — update to add sub-rules
├── lib.php                # NEW — divisions, classes, events, event-class bindings
├── Setup/
│   ├── Setup_1_PL.php     # NEW — 1440 Round
│   ├── Setup_3_PL.php     # NEW — Single-distance Round
│   └── Setup_6_PL.php     # NEW — Indoor 18m / 15m
```

Each `Setup_*_PL.php` sets tournament metadata variables and then
`require_once`s a shared setup target file (or inlines the logic, following the
FITA pattern where each Setup file sets `$TourType` + metadata then calls
`require_once('Setup_Target_PL.php')` — or we can inline it per-file since the
3 types are quite different).

**Decision:** Since PL's distances/targets differ significantly per type (unlike
IT which shares one giant `Setup_Target.php`), each `Setup_*_PL.php` file will
be self-contained, calling functions from `lib.php` directly. This keeps the
code clearer and avoids a monolithic switch.

---

## Step 0 — Update `sets.php`

Currently `sets.php` only registers types `[1, 3, 6]` with a single sub-rule
for type 3. We need sub-rules for all three types.

### Sub-rules design

Following the WA/FITA pattern of sub-rule naming (SetAllClass, SetOneClass, etc.),
PL will have a single sub-rule per type — "Full PZŁucz configuration". This can
be extended later.

```php
<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

$AllowedTypes = array(1, 3, 6);

$SetType['PL']['descr'] = get_text('Setup-PL', 'Install');
$SetType['PL']['types'] = array();
$SetType['PL']['rules'] = array();

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

// One sub-rule per type: "Full PZŁucz configuration"
foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = array(
        'Poland-Full',
    );
}
```

The sub-rule key `'Poland-Full'` must have a matching translation entry. The
sub-rules array is 0-indexed → `$SubRule` will be `'1'` for the first (and only)
entry.

---

## Step 1 — Create `lib.php` (Shared Functions)

File: `Modules/Sets/PL/lib.php` (new, **replacing** the current non-existent one
at the PL root — note: there's no PL/lib.php currently, so this is new).

### 1.1 — Global Settings

```php
<?php
$tourCollation = 'polish';
$tourDetIocCode = 'POL';
if (empty($SubRule)) $SubRule = '1';
```

### 1.2 — `CreateStandardDivisions($TourId, $TourType)`

Three divisions. The `DivId` / `DivRecDivision` / `DivWaDivision` codes match
the WA standard codes so ianseo's record system recognises them.

```php
function CreateStandardDivisions($TourId, $TourType) {
    $i = 1;
    // R = Łuk klasyczny (Recurve) — all tournament types
    CreateDivision($TourId, $i++, 'R', 'Łuk klasyczny', 1, 'R', 'R');

    // C = Łuk bloczkowy (Compound) — all tournament types
    CreateDivision($TourId, $i++, 'C', 'Łuk bloczkowy', 1, 'C', 'C');

    // B = Łuk barebow (Barebow) — all tournament types
    CreateDivision($TourId, $i++, 'B', 'Łuk barebow', 1, 'B', 'B');
}
```

Division codes: `R`, `C`, `B` — matching WA codes and making event codes like
`RM`, `CW`, `BU21M` readable.

### 1.3 — `CreateStandardClasses($TourId, $TourType)`

Age categories differ by tournament type:

- Type 1 (1440): M/W, U24M/W, U21M/W, U18M/W, 50M/W — **no U15, no U12**
- Type 3 (Single): adds U15M/W
- Type 6 (Indoor): adds U15M/W and U12M/W

The `ClDivisionsAllowed` column restricts which divisions a class may enter.
Per PZŁucz rules:

- U24: Recurve only → `'R'`
- U12: Recurve only → `'R'`
- U15: Recurve + Compound → `'R,C'` (no Barebow for U15)
- Barebow restricted to Senior, U21, U18 → done via ClDivisionsAllowed on those classes
  Actually, the constraint is on the _division_ side. But ianseo's model uses
  `ClDivisionsAllowed` on the _class_ — meaning an empty string means "all
  divisions allowed", and a comma-separated list restricts it.

**Key insight from requirements:**

- R: all ages
- C: U15 through Senior + 50+ (no U12, no U24)
- B: U18, U21, Senior only (no U12, U15, U24, 50+)

So `ClDivisionsAllowed`:

- M/W (Senior): `'R,C,B'` (all three)
- U24M/W: `'R'` (recurve only)
- U21M/W: `'R,C,B'` (all three)
- U18M/W: `'R,C,B'` (all three)
- 50M/50W: `'R,C'` (no barebow)
- U15M/W: `'R,C'` (no barebow)
- U12M/W: `'R'` (recurve only)

The `ClValidClass` column defines which classes an archer in this class can
compete "up" in. E.g., a U21 archer could also register as Senior.

```php
function CreateStandardClasses($TourId, $TourType) {
    $i = 1;
    $indoor = ($TourType == 6);
    $hasU15 = in_array($TourType, [3, 6]);
    $hasU12 = ($TourType == 6);

    // Senior M/W — age 21–49 (open)
    CreateClass($TourId, $i++, 21, 49, 0, 'M',  'M',    'Seniorzy',   1, 'R,C,B');
    CreateClass($TourId, $i++, 21, 49, 1, 'W',  'W',    'Seniorki',   1, 'R,C,B');

    // U24 — age 21-23, Recurve only
    CreateClass($TourId, $i++, 21, 23, 0, 'U24M', 'U24M,M', 'Młodzieżowiec', 1, 'R');
    CreateClass($TourId, $i++, 21, 23, 1, 'U24W', 'U24W,W', 'Młodzieżowniczka', 1, 'R');

    // U21 (Junior) — age 18-20
    CreateClass($TourId, $i++, 18, 20, 0, 'U21M', 'U21M,M', 'Junior',       1, 'R,C,B');
    CreateClass($TourId, $i++, 18, 20, 1, 'U21W', 'U21W,W', 'Juniorka',     1, 'R,C,B');

    // U18 (Junior młodszy) — age 15-17
    CreateClass($TourId, $i++, 15, 17, 0, 'U18M', 'U18M,U21M,M', 'Junior młodszy',  1, 'R,C,B');
    CreateClass($TourId, $i++, 15, 17, 1, 'U18W', 'U18W,U21W,W', 'Juniorka młodsza', 1, 'R,C,B');

    // Master 50+ — age 50+
    CreateClass($TourId, $i++, 50, 100, 0, '50M', '50M,M', 'Master M',    1, 'R,C');
    CreateClass($TourId, $i++, 50, 100, 1, '50W', '50W,W', 'Master K',    1, 'R,C');

    // U15 (Młodzik) — only in type 3 and 6, age 13-14
    if ($hasU15) {
        CreateClass($TourId, $i++, 13, 14, 0, 'U15M', 'U15M,U18M,U21M,M', 'Młodzik',     1, 'R,C');
        CreateClass($TourId, $i++, 13, 14, 1, 'U15W', 'U15W,U18W,U21W,W', 'Młodziczka',  1, 'R,C');
    }

    // U12 (Dziecko) — only in type 6 (indoor), Recurve only, age 9-12
    if ($hasU12) {
        CreateClass($TourId, $i++, 9, 12, 0, 'U12M', 'U12M,U15M,U18M,U21M,M', 'Dziecko M', 1, 'R');
        CreateClass($TourId, $i++, 9, 12, 1, 'U12W', 'U12W,U15W,U18W,U21W,W', 'Dziecko K', 1, 'R');
    }
}
```

> **Note on U24 overlap with Senior:** U24 range (21-23) overlaps with Senior
> (21-49). This is by design — ianseo uses the `ClValidClass` field to allow an
> archer to register in the most specific matching class, and the valid-class
> chain (`U24M,M`) lets them also appear in Senior rankings. The system picks
> the narrowest matching class by age.

### 1.4 — `CreateStandardEvents($TourId, $TourType)`

Events define the elimination bracket configuration for each division×class
combination. Each event gets an `EvCode` (e.g. `RM`, `CU21W`, `BM`).

**Match format per bow type:**

- **R (Recurve) and B (Barebow):** Set system → `EvMatchMode = 1`
  - Match: max 5 sets × 3 arrows → `EvFinEnds=5, EvFinArrows=3, EvFinSO=1`
  - Team: 4 sets × 6 arrows (2 per archer) → `EvFinEnds=4, EvFinArrows=6, EvFinSO=3`
- **C (Compound):** Cumulative → `EvMatchMode = 0`
  - Match: 5 ends × 3 arrows → `EvFinEnds=5, EvFinArrows=3, EvFinSO=1`
  - Team: 4 ends × 6 arrows → `EvFinEnds=4, EvFinArrows=6, EvFinSO=3`

**Bracket size:** The requirements mention top 104 for outdoor, top 32 for indoor.
ianseo's `EvFinalFirstPhase` uses phase constants:

- `FINAL_FROM_32` = 128 → 32-archer bracket (used for indoor, type 6)
- For 104 archers we need `FINAL_FROM_2` (the max standard bracket that fits).

Actually, looking at the constants more carefully:

- 104 bracket = non-standard. The closest is 128 (which seeds 128 archers).
  But the requirements say "top 104" which in typical archery means the bracket
  starts from 1/64 elimination round (yielding first 8 byes). The standard
  approach is to use `EvNumQualified=104` with `EvFinalFirstPhase` set to a
  phase that accommodates it. ianseo handles this via `numQualifiedByPhase()`.
  Looking at FITA/IT, they use `FirstPhase=48` for outdoor (48 = 1/32 individual).

  To keep it simple, we'll use `EvFinalFirstPhase=48` (from-1/32 = 56 spots,
  but actually the value 48 gives 56 qualified). Let me reconsider...

  From the constants:

  ```
  FINAL_FROM_32  = 128  → 32 qualified
  FINAL_FROM_16  = 192  → 16 qualified
  ```

  But FITA uses `$FirstPhase = 48` for outdoor individual — this is from the
  `Phases` table, not the FINAL\_\* constants. The `CreateEventNew` function uses
  `EvFinalFirstPhase` directly as a `PhId` reference.

  For PL: We use `EvFinalFirstPhase=48` for outdoor (allowing large brackets)
  and `EvFinalFirstPhase=16` for indoor type 6 (32 qualified).

  The actual number of qualified archers can be adjusted by the organizer via
  `EvNumQualified`. We'll use defaults matching the standard bracket.

**Special case — U15 in type 3:** No elimination phase → `EvFinalFirstPhase=0`
(which means `FINAL_NO_ELIM`).

```php
function CreateStandardEvents($TourId, $TourType) {
    $indoor = ($TourType == 6);
    $hasU15 = in_array($TourType, [3, 6]);
    $hasU12 = ($TourType == 6);

    // Target types
    $tgtR  = $indoor ? TGT_IND_6_big10 : TGT_OUT_FULL;     // Recurve: full face
    $tgtC  = $indoor ? TGT_IND_6_small10 : TGT_OUT_5_big10; // Compound: 6-ring
    $tgtB  = $indoor ? TGT_IND_1_big10 : TGT_OUT_FULL;      // Barebow: full face

    // Target sizes depend on type and are set per-event below
    // Bracket phases
    $indFirstPhase = $indoor ? 16 : 48;
    $teamFirstPhase = $indoor ? 8 : 12;

    // Base options for Recurve (set system)
    $optR = [
        'EvFinalFirstPhase' => $indFirstPhase,
        'EvFinalTargetType' => $tgtR,
        'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
        'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
        'EvMatchMode' => 1,
        'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
        'EvGolds' => $indoor ? '10' : '10+X',
        'EvXNine' => $indoor ? '9' : 'X',
        'EvGoldsChars' => $indoor ? 'L' : 'KL',
        'EvXNineChars' => $indoor ? 'J' : 'K',
    ];

    // Compound (cumulative system)
    $optC = $optR;
    $optC['EvMatchMode'] = 0;
    $optC['EvFinalTargetType'] = $tgtC;

    // Barebow (set system, same as Recurve)
    $optB = $optR;
    $optB['EvFinalTargetType'] = $tgtB;

    $i = 1;

    // --- RECURVE INDIVIDUAL EVENTS ---
    // Distances and target sizes set per category
    // ... (see per-type event creation in Steps 2-4 below)
}
```

The full event creation will be inlined in each `Setup_*_PL.php` file because
the events, distances, and target sizes vary significantly per type. The lib.php
will provide only the shared functions above; the event creation logic goes in
the setup files themselves.

### 1.5 — `InsertStandardEvents($TourId, $TourType)`

Binds classes to events via `InsertClassEvent()`. Each call associates a
division+class pair with an event code.

The event code naming convention:

- Individual: `{Div}{ClassCode}` → e.g. `RM`, `RU24W`, `CU21M`, `B50M`
- Team: `{Div}{ClassCode}T` → e.g. `RMT`, `CU21WT`

```php
function InsertStandardEvents($TourId, $TourType) {
    // Map: event-code-prefix → division-id
    //       classes → list of class-ids to bind

    // Build class list based on type
    $rClasses = ['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'];
    $cClasses = ['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'];
    $bClasses = ['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W'];

    if (in_array($TourType, [3, 6])) {
        $rClasses[] = 'U15M'; $rClasses[] = 'U15W';
        $cClasses[] = 'U15M'; $cClasses[] = 'U15W';
    }
    if ($TourType == 6) {
        $rClasses[] = 'U12M'; $rClasses[] = 'U12W';
    }

    // Individual events
    foreach ($rClasses as $cl) {
        InsertClassEvent($TourId, 0, 1, "R{$cl}", 'R', $cl);
    }
    foreach ($cClasses as $cl) {
        InsertClassEvent($TourId, 0, 1, "C{$cl}", 'C', $cl);
    }
    foreach ($bClasses as $cl) {
        InsertClassEvent($TourId, 0, 1, "B{$cl}", 'B', $cl);
    }

    // Team events
    foreach ($rClasses as $cl) {
        InsertClassEvent($TourId, 1, 3, "R{$cl}", 'R', $cl);
    }
    foreach ($cClasses as $cl) {
        InsertClassEvent($TourId, 1, 3, "C{$cl}", 'C', $cl);
    }
    foreach ($bClasses as $cl) {
        InsertClassEvent($TourId, 1, 3, "B{$cl}", 'B', $cl);
    }
}
```

---

## Step 2 — `Setup_1_PL.php` (1440 Round)

### 2.1 — Tournament Metadata

```php
<?php
$TourType = 1;

$tourDetTypeName       = 'Type_FITA';
$tourDetNumDist        = '4';
$tourDetNumEnds        = '12';      // 6 ends per distance × 2 scored sessions
$tourDetMaxDistScore   = '360';     // 36 arrows × 10
$tourDetMaxFinIndScore = '150';     // not used (no elimination in 1440)
$tourDetMaxFinTeamScore= '240';
$tourDetCategory       = '1';       // Outdoor
$tourDetElabTeam       = '0';       // Standard
$tourDetElimination    = '0';       // Qualification only
$tourDetGolds          = '10+X';
$tourDetXNine          = 'X';
$tourDetGoldsChars     = 'KL';
$tourDetXNineChars     = 'K';
$tourDetDouble         = '0';

// 4 distances, each: 6 ends × 6 arrows
$DistanceInfoArray = array(array(6, 6), array(6, 6), array(6, 6), array(6, 6));

require_once(dirname(__FILE__) . '/../lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');
```

### 2.2 — Divisions and Classes

```php
CreateStandardDivisions($TourId, $TourType);
CreateStandardClasses($TourId, $TourType);
```

Type 1 excludes U15 and U12 (handled by `CreateStandardClasses`).

### 2.3 — Distances

Distances per category, following the FITA `CreateDistanceNew` pattern.
The category filter uses SQL LIKE matching against the event code.

```php
// RECURVE distances (vary by category)
// R Men senior (M, U24M, U21M): 90-70-50-30
CreateDistanceNew($TourId, $TourType, 'R_M',    array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU24M',  array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU21M',  array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));

// R Women senior (W, U24W, U21W) & R Junior młodszy M (U18M) & R Master M (50M): 70-60-50-30
CreateDistanceNew($TourId, $TourType, 'R_W',    array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU24W',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU21W',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU18M',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'R50M',   array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));

// R Juniorka młodsza K (U18W) & R Master K (50W): 60-50-40-30
CreateDistanceNew($TourId, $TourType, 'RU18W',  array(array('60 m',60), array('50 m',50), array('40 m',40), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'R50W',   array(array('60 m',60), array('50 m',50), array('40 m',40), array('30 m',30)));

// COMPOUND — all categories: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'C%',     array(array('50m-1',50), array('50m-2',50), array('50m-3',50), array('50m-4',50)));

// BAREBOW — all categories: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'B%',     array(array('50m-1',50), array('50m-2',50), array('50m-3',50), array('50m-4',50)));
```

> **Note:** The `R_M` filter with underscore matches `RM` via LIKE (the
> underscore is a single-char wildcard). But this could also match `RUM` etc.
> We should use exact codes where possible. Looking at the FITA module, they use
> `_M` which matches `RM` (2-char code where `_` = any char). For PL with codes
> like `RM`, `RW`, the filter `R_M` would match `R` + any char + `M` which
> is wrong for a 2-char code like `RM`.
>
> **Fix:** Use the exact code or `%` wildcard properly:
>
> - `RM` (exact) for Senior Men Recurve
> - `RU24M` (exact) for U24 Men Recurve
> - Or use regex mode: `REG-^R.*M$`

Actually, re-examining how `CreateDistanceNew` works:

```php
function CreateDistanceNew($TourId, $Type, $CategoryFilter, $Distances) {
    safe_w_sql("INSERT INTO TournamentDistances set
        TdTournament=$TourId, TdType=$Type,
        TdClasses=".StrSafe_DB($CategoryFilter) ...);
}
```

And the matching is done via SQL LIKE against event codes. So `R_M` would match
any 3-char code starting with R and ending with M. But `RM` is only 2 chars.
Let's look at how FITA does it: they use `_M` which matches any 2-char code
ending in M (i.e., `RM`, `CM`, `BM`). And `_U21_` matches `RU21M`, `RU21W`,
`CU21M`, etc.

For PL we **want division-specific distances**, so we need:

- `RM` → matches event code `RM` exactly (2-char)
- But LIKE requires the value to match. `TdClasses='RM'` would need the event
  code to be LIKE 'RM' — which matches only exact `RM`.

Wait — reading the code more carefully, `TdClasses` is matched against the
concatenation of DivId+ClassId. Looking at how events are looked up...

Actually after more examination, `TdClasses` is matched against event codes
like `RM`, `CU21M`, etc. using SQL LIKE. So:

- `RM` → matches exactly `RM`
- `R%` → matches all Recurve events
- `C%` → matches all Compound events

This is the right approach. Let me revise the distances section:

```php
// RECURVE — Men & U24M & U21M: 90-70-50-30
CreateDistanceNew($TourId, $TourType, 'RM',     array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU24M',  array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU21M',  array(array('90 m',90), array('70 m',70), array('50 m',50), array('30 m',30)));

// RECURVE — Women & U24W & U21W & U18M & 50M: 70-60-50-30
CreateDistanceNew($TourId, $TourType, 'RW',     array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU24W',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU21W',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'RU18M',  array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'R50M',   array(array('70 m',70), array('60 m',60), array('50 m',50), array('30 m',30)));

// RECURVE — U18W & 50W: 60-50-40-30
CreateDistanceNew($TourId, $TourType, 'RU18W',  array(array('60 m',60), array('50 m',50), array('40 m',40), array('30 m',30)));
CreateDistanceNew($TourId, $TourType, 'R50W',   array(array('60 m',60), array('50 m',50), array('40 m',40), array('30 m',30)));

// COMPOUND — all: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'C%',     array(array('50m-1',50), array('50m-2',50), array('50m-3',50), array('50m-4',50)));

// BAREBOW — all: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'B%',     array(array('50m-1',50), array('50m-2',50), array('50m-3',50), array('50m-4',50)));
```

### 2.4 — Events (Qualification Only)

The 1440 Round has **no elimination phase**, so we set `EvFinalFirstPhase = 0`.
We still need events for ranking purposes.

```php
// No elimination events needed for 1440 Round (qualification only)
// Events are still created for ranking but with no bracket
$i = 1;
$optBase = [
    'EvFinalFirstPhase' => 0,  // No elimination
    'EvGolds' => '10+X', 'EvXNine' => 'X',
    'EvGoldsChars' => 'KL', 'EvXNineChars' => 'K',
];

// Recurve events
foreach (['M','W','U24M','U24W','U21M','U21W','U18M','U18W','50M','50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny {$cl}", $i++, $optBase + [
        'EvFinalTargetType' => TGT_OUT_FULL,
    ]);
}

// Compound events
$optC = $optBase;
$optC['EvFinalTargetType'] = TGT_OUT_5_big10;
foreach (['M','W','U21M','U21W','U18M','U18W','50M','50W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy {$cl}", $i++, $optC);
}

// Barebow events
$optB = $optBase;
$optB['EvFinalTargetType'] = TGT_OUT_FULL;
foreach (['M','W','U21M','U21W','U18M','U18W'] as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow {$cl}", $i++, $optB);
}
```

### 2.5 — Target Faces

```php
$i = 1;
// Default: 122cm for long distances, 80cm for short distances
// Recurve: dist1 (122cm), dist2 (122cm), dist3 (80cm), dist4 (80cm)
CreateTargetFace($TourId, $i++, 'Recurve domyślna', 'R%', '1',
    TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);

// Compound: 80cm 6-ring for all 4 distances
CreateTargetFace($TourId, $i++, 'Compound domyślna', 'C%', '1',
    TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80);

// Barebow: 80cm full face for all 4 distances
CreateTargetFace($TourId, $i++, 'Barebow domyślna', 'B%', '1',
    TGT_OUT_FULL, 80, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);
```

### 2.6 — Event-Class Bindings & Finals

```php
InsertStandardEvents($TourId, $TourType);
CreateFinals($TourId);
```

### 2.7 — Distance Information & Tour Update

```php
CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);

$tourDetails = array(
    'ToCollation' => $tourCollation,
    'ToTypeName'  => $tourDetTypeName,
    'ToNumDist'   => $tourDetNumDist,
    'ToNumEnds'   => $tourDetNumEnds,
    'ToMaxDistScore'    => $tourDetMaxDistScore,
    'ToMaxFinIndScore'  => $tourDetMaxFinIndScore,
    'ToMaxFinTeamScore' => $tourDetMaxFinTeamScore,
    'ToCategory'     => $tourDetCategory,
    'ToElabTeam'     => $tourDetElabTeam,
    'ToElimination'  => $tourDetElimination,
    'ToGolds'        => $tourDetGolds,
    'ToXNine'        => $tourDetXNine,
    'ToGoldsChars'   => $tourDetGoldsChars,
    'ToXNineChars'   => $tourDetXNineChars,
    'ToDouble'       => $tourDetDouble,
    'ToIocCode'      => $tourDetIocCode,
);
UpdateTourDetails($TourId, $tourDetails);
```

---

## Step 3 — `Setup_3_PL.php` (Single-Distance Round)

### 3.1 — Tournament Metadata

```php
$TourType = 3;

$tourDetTypeName       = 'Type_70m Round';
$tourDetNumDist        = '2';        // 2 sessions for standard; U15 = 2 different distances
$tourDetNumEnds        = '12';       // 6 ends per session × 2 (standard) or 12×3arr (U15)
$tourDetMaxDistScore   = '360';      // 36 arrows × 10
$tourDetMaxFinIndScore = '150';
$tourDetMaxFinTeamScore= '240';
$tourDetCategory       = '1';        // Outdoor
$tourDetElabTeam       = '0';
$tourDetElimination    = '0';
$tourDetGolds          = '10+X';
$tourDetXNine          = 'X';
$tourDetGoldsChars     = 'KL';
$tourDetXNineChars     = 'K';
$tourDetDouble         = '0';

$DistanceInfoArray = array(array(6, 6), array(6, 6));
```

### 3.2 — Distances

```php
// RECURVE — Senior M/W, U24M/W, U21M/W at 70m
CreateDistanceNew($TourId, $TourType, 'RM',     array(array('70m-1',70), array('70m-2',70)));
CreateDistanceNew($TourId, $TourType, 'RW',     array(array('70m-1',70), array('70m-2',70)));
CreateDistanceNew($TourId, $TourType, 'RU24M',  array(array('70m-1',70), array('70m-2',70)));
CreateDistanceNew($TourId, $TourType, 'RU24W',  array(array('70m-1',70), array('70m-2',70)));
CreateDistanceNew($TourId, $TourType, 'RU21M',  array(array('70m-1',70), array('70m-2',70)));
CreateDistanceNew($TourId, $TourType, 'RU21W',  array(array('70m-1',70), array('70m-2',70)));

// RECURVE — U18, Master at 60m
CreateDistanceNew($TourId, $TourType, 'RU18M',  array(array('60m-1',60), array('60m-2',60)));
CreateDistanceNew($TourId, $TourType, 'RU18W',  array(array('60m-1',60), array('60m-2',60)));
CreateDistanceNew($TourId, $TourType, 'R50M',   array(array('60m-1',60), array('60m-2',60)));
CreateDistanceNew($TourId, $TourType, 'R50W',   array(array('60m-1',60), array('60m-2',60)));

// RECURVE — U15 at 40m + 20m (two different distances)
CreateDistanceNew($TourId, $TourType, 'RU15M',  array(array('40 m',40), array('20 m',20)));
CreateDistanceNew($TourId, $TourType, 'RU15W',  array(array('40 m',40), array('20 m',20)));

// COMPOUND — all at 50m
CreateDistanceNew($TourId, $TourType, 'C%',     array(array('50m-1',50), array('50m-2',50)));

// BAREBOW — all at 50m
CreateDistanceNew($TourId, $TourType, 'B%',     array(array('50m-1',50), array('50m-2',50)));
```

### 3.3 — Events with Elimination

```php
$indFirstPhase = 48;  // Allows up to 104 qualified
$teamFirstPhase = 12;

// --- RECURVE (Set system) ---
$optR = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 1,    // Set system
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 122, 'EvDistance' => 70,
    'EvGolds' => '10+X', 'EvXNine' => 'X',
    'EvGoldsChars' => 'KL', 'EvXNineChars' => 'K',
];
$i = 1;

CreateEventNew($TourId, 'RM',    'Łuk klasyczny Seniorzy',               $i++, $optR);
CreateEventNew($TourId, 'RW',    'Łuk klasyczny Seniorki',               $i++, $optR);
CreateEventNew($TourId, 'RU24M', 'Łuk klasyczny Młodzieżowiec',          $i++, $optR);
CreateEventNew($TourId, 'RU24W', 'Łuk klasyczny Młodzieżowniczka',       $i++, $optR);
CreateEventNew($TourId, 'RU21M', 'Łuk klasyczny Junior',                 $i++, $optR);
CreateEventNew($TourId, 'RU21W', 'Łuk klasyczny Juniorka',               $i++, $optR);

$optR['EvDistance'] = 60;
CreateEventNew($TourId, 'RU18M', 'Łuk klasyczny Junior młodszy',         $i++, $optR);
CreateEventNew($TourId, 'RU18W', 'Łuk klasyczny Juniorka młodsza',       $i++, $optR);
CreateEventNew($TourId, 'R50M',  'Łuk klasyczny Master M',               $i++, $optR);
CreateEventNew($TourId, 'R50W',  'Łuk klasyczny Master K',               $i++, $optR);

// U15 Recurve — NO ELIMINATION
$optRU15 = $optR;
$optRU15['EvFinalFirstPhase'] = 0;
$optRU15['EvDistance'] = 40;
$optRU15['EvTargetSize'] = 122;
CreateEventNew($TourId, 'RU15M', 'Łuk klasyczny Młodzik',                $i++, $optRU15);
CreateEventNew($TourId, 'RU15W', 'Łuk klasyczny Młodziczka',             $i++, $optRU15);

// --- COMPOUND (Cumulative) ---
$optC = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_5_big10,
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 0,    // Cumulative
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 80, 'EvDistance' => 50,
    'EvGolds' => '10+X', 'EvXNine' => 'X',
    'EvGoldsChars' => 'KL', 'EvXNineChars' => 'K',
];

CreateEventNew($TourId, 'CM',    'Łuk bloczkowy Seniorzy',               $i++, $optC);
CreateEventNew($TourId, 'CW',    'Łuk bloczkowy Seniorki',               $i++, $optC);
CreateEventNew($TourId, 'CU21M', 'Łuk bloczkowy Junior',                 $i++, $optC);
CreateEventNew($TourId, 'CU21W', 'Łuk bloczkowy Juniorka',               $i++, $optC);
CreateEventNew($TourId, 'CU18M', 'Łuk bloczkowy Junior młodszy',         $i++, $optC);
CreateEventNew($TourId, 'CU18W', 'Łuk bloczkowy Juniorka młodsza',       $i++, $optC);
CreateEventNew($TourId, 'C50M',  'Łuk bloczkowy Master M',               $i++, $optC);
CreateEventNew($TourId, 'C50W',  'Łuk bloczkowy Master K',               $i++, $optC);

// U15 Compound — NO ELIMINATION
$optCU15 = $optC;
$optCU15['EvFinalFirstPhase'] = 0;
CreateEventNew($TourId, 'CU15M', 'Łuk bloczkowy Młodzik',                $i++, $optCU15);
CreateEventNew($TourId, 'CU15W', 'Łuk bloczkowy Młodziczka',             $i++, $optCU15);

// --- BAREBOW (Set system) ---
$optB = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 1,
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 122, 'EvDistance' => 50,
    'EvGolds' => '10+X', 'EvXNine' => 'X',
    'EvGoldsChars' => 'KL', 'EvXNineChars' => 'K',
];

CreateEventNew($TourId, 'BM',    'Łuk barebow Seniorzy',                 $i++, $optB);
CreateEventNew($TourId, 'BW',    'Łuk barebow Seniorki',                 $i++, $optB);
CreateEventNew($TourId, 'BU21M', 'Łuk barebow Junior',                   $i++, $optB);
CreateEventNew($TourId, 'BU21W', 'Łuk barebow Juniorka',                 $i++, $optB);
CreateEventNew($TourId, 'BU18M', 'Łuk barebow Junior młodszy',           $i++, $optB);
CreateEventNew($TourId, 'BU18W', 'Łuk barebow Juniorka młodsza',         $i++, $optB);
```

**Team events** follow the same pattern but with `EvTeamEvent=1`,
`EvMaxTeamPerson=3`, adjusted arrow counts (4 ends × 6 arrows for teams),
and team-specific SO values.

### 3.4 — Target Faces

```php
$i = 1;
// Recurve & Barebow: 122cm full face
CreateTargetFace($TourId, $i++, 'Recurve/Barebow domyślna', 'REG-^R|^B', '1',
    TGT_OUT_FULL, 122, TGT_OUT_FULL, 122);

// Compound: 80cm 6-ring
CreateTargetFace($TourId, $i++, 'Compound domyślna', 'C%', '1',
    TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80);

// U15 Recurve: 122cm for 40m, 80cm for 20m
CreateTargetFace($TourId, $i++, 'Młodzik Recurve', 'RU15%', '1',
    TGT_OUT_FULL, 122, TGT_OUT_FULL, 80);
```

### 3.5 — U15 Distance Information Special Case

The U15 40m+20m variant uses 12 ends × 3 arrows per distance. This differs from
the standard 6 ends × 6 arrows. We need a separate distance info setup:

```php
// Standard: 6 ends × 6 arrows per session
CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);

// For U15 the distance info should be 12 ends × 3 arrows
// This requires a note: the organizer may need to adjust session settings manually
// for U15 categories, as ianseo's distance information is session-global, not per-category.
```

> **Implementation note:** ianseo's `DistanceInformation` table is per-session,
> not per-category. All archers in the same session share the same end/arrow
> structure. If U15 archers are in the same session as seniors, they'd inherit
> the 6×6 structure. To handle U15's 12×3 format, either:
>
> 1. U15 archers should be assigned to a separate session (organizer handles this)
> 2. Or the 6×6 format is used (total arrows = 36 either way; 6×6 = 12×3 = 36)
>
> Since 6×6 and 12×3 both yield 36 arrows, the scoring sheet format differs but
> the total is the same. The organizer can adjust this per-session.

---

## Step 4 — `Setup_6_PL.php` (Indoor 18m / 15m)

### 4.1 — Tournament Metadata

```php
$TourType = 6;

$tourDetTypeName       = 'Type_Indoor 18';
$tourDetNumDist        = '2';        // 2 sessions of 18m (or 15m for U12)
$tourDetNumEnds        = '10';       // 10 ends per session × 3 arrows
$tourDetMaxDistScore   = '300';      // 30 arrows × 10
$tourDetMaxFinIndScore = '150';
$tourDetMaxFinTeamScore= '240';
$tourDetCategory       = '2';        // Indoor
$tourDetElabTeam       = '0';
$tourDetElimination    = '0';
$tourDetGolds          = '10';
$tourDetXNine          = '9';
$tourDetGoldsChars     = 'L';
$tourDetXNineChars     = 'J';
$tourDetDouble         = '0';

// 20 ends × 3 arrows, split into 2 sessions of 10 ends
$DistanceInfoArray = array(array(10, 3), array(10, 3));
```

### 4.2 — Distances

```php
// All categories at 18m (two sessions)
CreateDistanceNew($TourId, $TourType, '%', array(array('18m-1',18), array('18m-2',18)));

// Override U12: 15m
CreateDistanceNew($TourId, $TourType, 'RU12_', array(array('15m-1',15), array('15m-2',15)));
```

> **Note:** Distance matching works by specificity — more specific patterns
> override general ones. The `RU12_` pattern will match U12 event codes and
> override the `%` wildcard. We need to verify this assumption by checking
> how ianseo resolves distance conflicts. If it uses "last match wins" or
> "most specific", we may need to adjust. The safest approach is to not use
> `%` if some categories differ, and instead list all explicitly:

```php
// Alternative (safer): explicit distances for each
CreateDistanceNew($TourId, $TourType, 'R_',     array(array('18m-1',18), array('18m-2',18))); // matches RM, RW
CreateDistanceNew($TourId, $TourType, 'RU24_',  array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'RU21_',  array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'RU18_',  array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'R50_',   array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'RU15_',  array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'RU12_',  array(array('15m-1',15), array('15m-2',15)));
CreateDistanceNew($TourId, $TourType, 'C%',     array(array('18m-1',18), array('18m-2',18)));
CreateDistanceNew($TourId, $TourType, 'B%',     array(array('18m-1',18), array('18m-2',18)));
```

### 4.3 — Events with Elimination

```php
$indFirstPhase = 16;  // Top 32 qualified
$teamFirstPhase = 8;

$optR = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_big10,  // Triple 40cm
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 1,
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 40, 'EvDistance' => 18,
    'EvGolds' => '10', 'EvXNine' => '9',
    'EvGoldsChars' => 'L', 'EvXNineChars' => 'J',
];

$i = 1;

// Senior, U24, U21 — Triple 40cm (TGT_IND_6_big10)
CreateEventNew($TourId, 'RM',    'Łuk klasyczny Seniorzy',               $i++, $optR);
CreateEventNew($TourId, 'RW',    'Łuk klasyczny Seniorki',               $i++, $optR);
CreateEventNew($TourId, 'RU24M', 'Łuk klasyczny Młodzieżowiec',          $i++, $optR);
CreateEventNew($TourId, 'RU24W', 'Łuk klasyczny Młodzieżowniczka',       $i++, $optR);
CreateEventNew($TourId, 'RU21M', 'Łuk klasyczny Junior',                 $i++, $optR);
CreateEventNew($TourId, 'RU21W', 'Łuk klasyczny Juniorka',               $i++, $optR);

// U18 Recurve — Single 40cm (TGT_IND_1_big10)
$optRU18 = $optR;
$optRU18['EvFinalTargetType'] = TGT_IND_1_big10;
CreateEventNew($TourId, 'RU18M', 'Łuk klasyczny Junior młodszy',         $i++, $optRU18);
CreateEventNew($TourId, 'RU18W', 'Łuk klasyczny Juniorka młodsza',       $i++, $optRU18);

// Master — same target as Senior (triple 40cm)
CreateEventNew($TourId, 'R50M',  'Łuk klasyczny Master M',               $i++, $optR);
CreateEventNew($TourId, 'R50W',  'Łuk klasyczny Master K',               $i++, $optR);

// U15 Recurve — 60cm face
$optRU15 = $optR;
$optRU15['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU15['EvTargetSize'] = 60;
CreateEventNew($TourId, 'RU15M', 'Łuk klasyczny Młodzik',                $i++, $optRU15);
CreateEventNew($TourId, 'RU15W', 'Łuk klasyczny Młodziczka',             $i++, $optRU15);

// U12 Recurve — 80cm face at 15m
$optRU12 = $optR;
$optRU12['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU12['EvTargetSize'] = 80;
$optRU12['EvDistance'] = 15;
CreateEventNew($TourId, 'RU12M', 'Łuk klasyczny Dziecko M',              $i++, $optRU12);
CreateEventNew($TourId, 'RU12W', 'Łuk klasyczny Dziecko K',              $i++, $optRU12);

// --- COMPOUND ---
// Senior, U21 — Triple 40cm (TGT_IND_6_small10)
$optC = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_small10,  // Triple 40cm (compound variant)
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 0,
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 40, 'EvDistance' => 18,
    'EvGolds' => '10', 'EvXNine' => '9',
    'EvGoldsChars' => 'L', 'EvXNineChars' => 'J',
];

CreateEventNew($TourId, 'CM',    'Łuk bloczkowy Seniorzy',               $i++, $optC);
CreateEventNew($TourId, 'CW',    'Łuk bloczkowy Seniorki',               $i++, $optC);
CreateEventNew($TourId, 'CU21M', 'Łuk bloczkowy Junior',                 $i++, $optC);
CreateEventNew($TourId, 'CU21W', 'Łuk bloczkowy Juniorka',               $i++, $optC);

// U18 Compound — Single 40cm
$optCU18 = $optC;
$optCU18['EvFinalTargetType'] = TGT_IND_1_small10;
CreateEventNew($TourId, 'CU18M', 'Łuk bloczkowy Junior młodszy',         $i++, $optCU18);
CreateEventNew($TourId, 'CU18W', 'Łuk bloczkowy Juniorka młodsza',       $i++, $optCU18);

// Master Compound
CreateEventNew($TourId, 'C50M',  'Łuk bloczkowy Master M',               $i++, $optC);
CreateEventNew($TourId, 'C50W',  'Łuk bloczkowy Master K',               $i++, $optC);

// U15 Compound — 60cm
$optCU15 = $optC;
$optCU15['EvFinalTargetType'] = TGT_IND_1_small10;
$optCU15['EvTargetSize'] = 60;
CreateEventNew($TourId, 'CU15M', 'Łuk bloczkowy Młodzik',                $i++, $optCU15);
CreateEventNew($TourId, 'CU15W', 'Łuk bloczkowy Młodziczka',             $i++, $optCU15);

// --- BAREBOW ---
// All Barebow — Single 40cm (TGT_IND_1_big10)
$optB = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_1_big10,
    'EvElimEnds' => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'  => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode' => 1,
    'EvMatchArrowsNo' => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize' => 40, 'EvDistance' => 18,
    'EvGolds' => '10', 'EvXNine' => '9',
    'EvGoldsChars' => 'L', 'EvXNineChars' => 'J',
];

CreateEventNew($TourId, 'BM',    'Łuk barebow Seniorzy',                 $i++, $optB);
CreateEventNew($TourId, 'BW',    'Łuk barebow Seniorki',                 $i++, $optB);
CreateEventNew($TourId, 'BU21M', 'Łuk barebow Junior',                   $i++, $optB);
CreateEventNew($TourId, 'BU21W', 'Łuk barebow Juniorka',                 $i++, $optB);
CreateEventNew($TourId, 'BU18M', 'Łuk barebow Junior młodszy',           $i++, $optB);
CreateEventNew($TourId, 'BU18W', 'Łuk barebow Juniorka młodsza',         $i++, $optB);
```

### 4.4 — Target Faces (Indoor)

```php
$i = 1;

// Recurve & Compound Senior/U24/U21: Triple 40cm
CreateTargetFace($TourId, $i++, 'Triple 40 cm (R/C Senior/U24/U21)',
    'REG-^[RC](M|W|U24|U21)', '1',
    TGT_IND_6_big10, 40, TGT_IND_6_big10, 40);

// Compound variant: Triple 40cm with small 10 ring
CreateTargetFace($TourId, $i++, 'Triple 40 cm Compound',
    'REG-^C(M|W|U21)', '1',
    TGT_IND_6_small10, 40, TGT_IND_6_small10, 40);

// U18 R/C: Single 40cm
CreateTargetFace($TourId, $i++, 'Single 40 cm (U18)',
    'REG-^[RC]U18', '1',
    TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);

// Barebow all: Single 40cm
CreateTargetFace($TourId, $i++, 'Single 40 cm (Barebow)',
    'B%', '1',
    TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);

// Master R/C: Triple 40cm (same as Senior, mapped to same target face)
// Already covered by the first two entries via regex

// U15 all: 60cm
CreateTargetFace($TourId, $i++, '60 cm (U15)',
    'REG-U15', '1',
    TGT_IND_1_big10, 60, TGT_IND_1_big10, 60);

// U12: 80cm
CreateTargetFace($TourId, $i++, '80 cm (U12)',
    'RU12%', '1',
    TGT_IND_1_big10, 80, TGT_IND_1_big10, 80);
```

### 4.5 — Event Bindings, Finals, Distance Info, Update

```php
InsertStandardEvents($TourId, $TourType);
CreateFinals($TourId);
CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
UpdateTourDetails($TourId, $tourDetails);
```

---

## Step 5 — Update `sets.php`

Replace existing `sets.php` content with proper sub-rules:

```php
<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

$AllowedTypes = array(1, 3, 6);

$SetType['PL']['descr'] = get_text('Setup-PL', 'Install');
$SetType['PL']['types'] = array();
$SetType['PL']['rules'] = array();

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = array(
        'Poland-Full',
    );
}
```

---

## Key Design Decisions

### Event Code Convention

| Pattern         | Example | Meaning                   |
| --------------- | ------- | ------------------------- |
| `{Div}{Class}`  | `RM`    | Recurve Senior Men (ind.) |
| `{Div}{Class}`  | `CU21W` | Compound U21 Women (ind.) |
| `{Div}{Class}`  | `RU24M` | Recurve U24 Men (ind.)    |
| `{Div}{Class}`  | `BU18M` | Barebow U18 Men (ind.)    |
| `{Div}{Class}T` | `RMT`   | Recurve Senior Men (team) |

This follows the FITA module convention (`RM`, `CW`, `BU21M`, etc.) rather
than the IT convention (`OLSM`, `COJF`, etc.).

### Target Face Type Mapping

| ianseo constant     | Value | Meaning                              | PL usage                |
| ------------------- | ----- | ------------------------------------ | ----------------------- |
| `TGT_OUT_FULL`      | 5     | Outdoor full face (1-10 + X)         | R outdoor, B outdoor    |
| `TGT_OUT_5_big10`   | 9     | Outdoor 6-ring (5-10 + X)            | C outdoor               |
| `TGT_IND_1_big10`   | 1     | Indoor single face (10+X big ring)   | U18 R, all B, U15, U12  |
| `TGT_IND_6_big10`   | 2     | Indoor triple face (big 10 ring)     | Senior/U24/U21 R indoor |
| `TGT_IND_1_small10` | 3     | Indoor single face (10+X small ring) | U18 Compound indoor     |
| `TGT_IND_6_small10` | 4     | Indoor triple face (small 10 ring)   | Senior/U21 C indoor     |

### What `EvMatchMode` Values Mean

| Value | Mode       | Usage            |
| ----- | ---------- | ---------------- |
| 0     | Cumulative | Compound         |
| 1     | Set system | Recurve, Barebow |

---

## Out of Scope — Post-Elimination Sub-Ranking

The PZŁucz requirement for unique placement of losers within the same elimination
round (§2.6.5) is a **runtime ranking calculation**, not a tournament setup
concern. It requires modifying the ranking engine:

- **Files to modify:** `Rank/Obj_Rank_GridInd_calc.php`, `Rank/Obj_Rank_GridTeam_calc.php`
  (or PL overrides thereof in `Modules/Sets/PL/Rank/`)
- **Logic:** After standard bracket ranking, losers in each round must be
  sub-sorted by: (1) match score in their losing match, (2) qualification rank
- **Complexity:** Medium-high — requires understanding ianseo's full ranking
  pipeline and hook mechanism for module overrides

This should be implemented as a separate task with its own spec. The existing
`Modules/Sets/PL/Rank/` directory already contains ranking customizations and
is the right place for this.

---

## Implementation Order

1. **`lib.php`** — shared functions (divisions, classes, event binding)
2. **`Setup_1_PL.php`** — simplest (no elimination, straightforward distances)
3. **`Setup_3_PL.php`** — adds U15, elimination, mixed-distance U15 variant
4. **`Setup_6_PL.php`** — indoor, U12, different target faces
5. **`sets.php`** — update sub-rules (may be done first since it's a small change)
6. **Manual testing** — create a test tournament of each type and verify
   categories, distances, target faces, and elimination brackets

---

## Verification After Implementation

For each setup type, create a test tournament and verify:

- [ ] Correct divisions appear (R, C, B)
- [ ] Age categories present/absent per type (no U15/U12 in type 1; U15 in type 3; all in type 6)
- [ ] U24 only has Recurve in its `ClDivisionsAllowed`
- [ ] U12 only has Recurve and only appears in type 6
- [ ] Distances match the requirements table for each division × class combination
- [ ] Target faces default correctly per division and class
- [ ] Elimination brackets are created for appropriate events (not for type 1; not for U15 in type 3)
- [ ] Set system vs cumulative assigned correctly per bow type
- [ ] Indoor scoring (10/9) vs outdoor scoring (10+X/X) applied correctly
