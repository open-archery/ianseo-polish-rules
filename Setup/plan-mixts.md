# Implementation Plan — Mixed Team Events (zespoły mieszane)

## Overview

Add mixed team events to `Setup_3_PL.php` (outdoor) and `Setup_6_PL.php` (indoor),
plus update `lib.php` to handle mixed team class-event bindings.
`Setup_1_PL.php` is **out of scope** — the 1440 Round requirements do not specify
mixed team events.

---

## 1. What Changes and Why

### 1.1 Requirements Source

REQUIREMENTS.md §2 "Mixed Team Events (Outdoor)" and §3 "Mixed Team Events (Indoor)":

- Mixed teams = 1 man + 1 woman, same club, same division, same age category
- Bracket: top **24 mixed teams** → `EvFinalFirstPhase = 12` (1/12 finału)
- 4 arrows/end (2 per archer), 4 ends, shoot-off = 2 arrows (1 each)
- Set system for R/B (`EvMatchMode = 1`), cumulative for C (`EvMatchMode = 0`)
- U15 mixed teams: **no elimination** (`EvFinalFirstPhase = 0`)
- U12: no mixed teams (no standalone U12 mixed events in requirements)

### 1.2 Event Codes (suffix `X`)

| Division | Event codes                          |
| -------- | ------------------------------------ |
| R        | RX, RU24X, RU21X, RU18X, R50X, RU15X |
| C        | CX, CU21X, CU18X, C50X, CU15X        |
| B        | BX, BU21X, BU18X                     |

### 1.3 ianseo API Key Points

From FITA `lib.php` reference — mixed team options differ from standard team:

| Parameter         | Standard team | Mixed team |
| ----------------- | ------------- | ---------- |
| `EvTeamEvent`     | 1             | 1          |
| `EvMixedTeam`     | 0             | **1**      |
| `EvMaxTeamPerson` | 3             | **2**      |
| `EvElimArrows`    | 6             | **4**      |
| `EvElimSO`        | 3             | **2**      |
| `EvFinArrows`     | 4→6           | **4**      |
| `EvFinSO`         | 3             | **2**      |
| `EvElimEnds`      | 4             | 4          |
| `EvFinEnds`       | 4             | 4          |

`InsertClassEvent` for mixed teams (from FITA `lib.php`):

```php
InsertClassEvent($TourId, 1, 1, "{$div}{$cl}X", $div, "{$cl}W");  // Team=1, bind W
InsertClassEvent($TourId, 2, 1, "{$div}{$cl}X", $div, "{$cl}M");  // Team=2, bind M
```

---

## 2. Files to Modify

### 2.1 `lib.php` — two changes

#### A. Add `$PL_MIXED_CLASS_NAMES` mapping

Mixed team event names use gender-neutral age category labels. Add a new array
after `$PL_CLASS_NAMES`:

```php
$PL_MIXED_CLASS_NAMES = array(
    ''    => 'Seniorzy',
    'U24' => 'Młodzieżowcy',
    'U21' => 'Juniorzy',
    'U18' => 'Juniorzy młodsi',
    '50'  => 'Master',
    'U15' => 'Młodziki',
    'U12' => 'Dzieci',
);
```

Event names will follow the pattern:
`"Łuk klasyczny - Seniorzy zespoły mieszane"` etc.

#### B. Update `InsertStandardEvents()` — add mixed team bindings

After existing team bindings, add a new block for mixed teams. Mixed team
`InsertClassEvent` uses `Team=1` for W class and `Team=2` for M class,
both with `Number=1`:

```php
// Mixed Team
$rMixedAges = array('', 'U24', 'U21', 'U18', '50');
$cMixedAges = array('', 'U21', 'U18', '50');
$bMixedAges = array('', 'U21', 'U18');

if (in_array($TourType, array(3, 6))) {
    $rMixedAges[] = 'U15';
    $cMixedAges[] = 'U15';
}

foreach ($rMixedAges as $age) {
    InsertClassEvent($TourId, 1, 1, "R{$age}X", 'R', "{$age}W");
    InsertClassEvent($TourId, 2, 1, "R{$age}X", 'R', "{$age}M");
}
foreach ($cMixedAges as $age) {
    InsertClassEvent($TourId, 1, 1, "C{$age}X", 'C', "{$age}W");
    InsertClassEvent($TourId, 2, 1, "C{$age}X", 'C', "{$age}M");
}
foreach ($bMixedAges as $age) {
    InsertClassEvent($TourId, 1, 1, "B{$age}X", 'B', "{$age}W");
    InsertClassEvent($TourId, 2, 1, "B{$age}X", 'B', "{$age}M");
}
```

---

### 2.2 `Setup_3_PL.php` — add mixed team events section

Insert a new `// ---- Mixed Team Events` section after the existing team events
block and before target faces. Counter `$i` resets to `1` for mixed team events
(they are a separate event category from regular team events, same as FITA pattern).

#### Recurve mixed teams (set system)

```php
$mixFirstPhase = 8;  // top 16
$i = 1;

$optRMX = array(
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 122, 'EvDistance' => 70,
    'EvGolds' => $tourDetGolds, 'EvXNine' => $tourDetXNine,
    'EvGoldsChars' => $tourDetGoldsChars, 'EvXNineChars' => $tourDetXNineChars,
);
```

Create events per age category, adjusting `EvDistance` per group:

- Senior/U24/U21: 70 m
- U18/50: 60 m
- U15: 40 m, `EvFinalFirstPhase = 0`

Loop pattern (using `$PL_MIXED_CLASS_NAMES`):

```php
foreach (array('', 'U24', 'U21') as $age) {
    CreateEventNew($TourId, "R{$age}X",
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
}
$optRMX['EvDistance'] = 60;
foreach (array('U18', '50') as $age) {
    CreateEventNew($TourId, "R{$age}X",
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
}
$optRMXU15 = $optRMX;
$optRMXU15['EvFinalFirstPhase'] = 0;
$optRMXU15['EvDistance'] = 40;
$optRMXU15['EvTargetSize'] = 122;
CreateEventNew($TourId, "RU15X",
    "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optRMXU15);
```

#### Compound mixed teams (cumulative)

```php
$optCMX = array(
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_5_big10,
    'EvMatchMode'       => 0,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 80, 'EvDistance' => 50,
    'EvGolds' => ..., 'EvXNine' => ...,
);
foreach (array('', 'U21', 'U18', '50') as $age) {
    CreateEventNew($TourId, "C{$age}X",
        "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optCMX);
}
// U15 — no elimination
$optCMXU15 = $optCMX;
$optCMXU15['EvFinalFirstPhase'] = 0;
CreateEventNew($TourId, "CU15X",
    "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optCMXU15);
```

#### Barebow mixed teams (set system)

```php
$optBMX = array(
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 122, 'EvDistance' => 50,
    'EvGolds' => ..., 'EvXNine' => ...,
);
foreach (array('', 'U21', 'U18') as $age) {
    CreateEventNew($TourId, "B{$age}X",
        "Łuk barebow - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optBMX);
}
```

---

### 2.3 `Setup_6_PL.php` — add mixed team events section

Same structure as outdoor but with indoor-specific parameters:

- All distances: 18 m (no U12 mixed teams)
- `EvFinalFirstPhase = 8` (top 16)
- Target faces match individual elimination faces per category

#### Recurve mixed teams (indoor, set system)

| Age category      | EvFinalTargetType | EvTargetSize | EvDistance |
| ----------------- | ----------------- | ------------ | ---------- |
| Senior/U24/U21/50 | TGT_IND_6_big10   | 40           | 18         |
| U18               | TGT_IND_1_big10   | 40           | 18         |
| U15 (no elim)     | TGT_IND_1_big10   | 60           | 18         |

```php
$mixFirstPhase = 8;
$i = 1;

$optRMX = array(
    'EvTeamEvent' => 1, 'EvMixedTeam' => 1, 'EvMaxTeamPerson' => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_big10,
    'EvMatchMode' => 1,
    'EvElimEnds' => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'  => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize' => 40, 'EvDistance' => 18,
    ...golds/xnine...
);
// Senior / U24 / U21 / 50+: triple 40 cm
foreach (array('', 'U24', 'U21', '50') as $age) { ... }
// U18: single 40 cm
$optRMX['EvFinalTargetType'] = TGT_IND_1_big10;
CreateEventNew($TourId, "RU18X", ..., $i++, $optRMX);
// U15: single 60 cm, no elimination
$optRMXU15 = $optRMX;
$optRMXU15['EvFinalFirstPhase'] = 0;
$optRMXU15['EvTargetSize'] = 60;
CreateEventNew($TourId, "RU15X", ..., $i++, $optRMXU15);
```

#### Compound mixed teams (indoor, cumulative)

| Age category  | EvFinalTargetType | EvTargetSize | EvDistance |
| ------------- | ----------------- | ------------ | ---------- |
| Senior/U21/50 | TGT_IND_6_small10 | 40           | 18         |
| U18           | TGT_IND_1_small10 | 40           | 18         |
| U15 (no elim) | TGT_IND_1_small10 | 60           | 18         |

#### Barebow mixed teams (indoor, set system)

All ages: `TGT_IND_1_big10`, 40 cm, 18 m.

---

## 3. Implementation Sequence

| Step | File             | Action                                                      |
| ---- | ---------------- | ----------------------------------------------------------- |
| 1    | `lib.php`        | Add `$PL_MIXED_CLASS_NAMES` array after `$PL_CLASS_NAMES`   |
| 2    | `lib.php`        | Add mixed team bindings to `InsertStandardEvents()`         |
| 3    | `Setup_3_PL.php` | Add mixed team events section (R, C, B) before target faces |
| 4    | `Setup_6_PL.php` | Add mixed team events section (R, C, B) before target faces |
| 5    | Both setup files | Verify `$i` counter resets to 1 for mixed team event block  |

---

## 4. Edge Cases & Notes

- **No mixed teams in Setup_1_PL.php** — REQUIREMENTS §1 specifies 1440 Round as
  qualification only with no mixed team section.
- **U15 mixed teams** exist in types 3 and 6 but have `EvFinalFirstPhase = 0`
  (no elimination bracket).
- **No U12 mixed teams** — REQUIREMENTS do not list U12 in mixed team event codes.
- **U24 only in Recurve** — mixed team code `RU24X` exists but there are no
  `CU24X` or `BU24X` codes (U24 is R-only per division eligibility).
- **Event $i counter** — mixed team events use their own `$i = 1` counter,
  separate from individual and team counters.
- **Event naming** — format: `"Łuk {division} - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane"`.
- **Target faces** — mixed teams use the same target faces as individual
  elimination for their category. No separate `CreateTargetFace()` calls needed;
  existing face definitions apply via the division+class regex patterns.

## 5. Verification

After implementation, confirm:

1. All event codes from §1.2 are created in Setup_3 and Setup_6
2. `EvMixedTeam = 1` on every mixed team event
3. `EvMaxTeamPerson = 2` on every mixed team event
4. `EvElimArrows = 4`, `EvElimSO = 2`, `EvFinArrows = 4`, `EvFinSO = 2`
5. `EvFinalFirstPhase = 8` for all except U15 (which is 0)
6. `InsertStandardEvents` creates correct class-event bindings (Team=1 for W, Team=2 for M)
7. Event names use Polish labels with `zespoły mieszane` suffix
8. Indoor target types match individual elimination faces per category
