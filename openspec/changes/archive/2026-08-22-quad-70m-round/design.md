## Context

`Setup_3_PL.php` (TourType 3, "70m Round") is called by ianseo core's `GetSetupFile()` at tournament creation/reset time, with `$TourId`, `$TourType`, and `$SubRule` available as globals. Today the file hardcodes a single structure: every class shoots its distance(s) across 2 sessions (`CreateDistanceNew()` calls with 2-leg arrays), `tourDetNumDist = '2'`, and a tournament-wide `$DistanceInfoArray = array(array(6,6), array(6,6))` fed into `CreateDistanceInformation()`.

`sets.php` currently registers exactly one sub-rule, `Poland-Full`, for every allowed type (1, 3, 6). There is no branching on `$SubRule` anywhere in the PL module today — this change introduces the first one.

Pattern precedent: `FR/sets.php` registers ~10 named sub-rules under its single TourType 3 entry (`SetFRTAE-Valides`, `SetFRCoupeFrance`, `SetFRD12026`, etc.), all handled inside one `Setup_3_FR.php` that branches on `$SubRule`. This change follows the same shape.

## Goals / Non-Goals

**Goals:**
- Add `Poland-4x70m` as a second sub-rule under TourType 3 only.
- When selected, every class/division in `Setup_3_PL.php` shoots its existing distance leg(s) with double the session count (per §2.11.1.1/§2.11.1.2 "Podwójna runda"): R Senior/U24/U21 → 70m×4, R U18/50+ → 60m×4, R U15 → 40m,40m,20m,20m, C/B (all) → 50m×4.
- Reuse 100% of the existing distance/target/event/elimination configuration — only the session count changes.

**Non-Goals:**
- No new TourType ID, no new files beyond the two edits below.
- No change to elimination/finals structure, target faces, event codes, or team best-3-of-4 scoring.
- No new rank-override or diploma-title file for the sub-rule — rank/diploma resolution already falls back from `_{Type}_{SubRule}` → `_{Type}` → `_{Family}` (see ianseo-internals.md §Rank override resolution), and nothing in this change needs a sub-rule-specific override.
- No fix for the pre-existing mismatch between `$DistanceInfoArray`'s uniform `(6,6)` entries and U15's regulatory 12×3 end structure — that inconsistency already exists in `Poland-Full` today; this change preserves it symmetrically rather than fixing unrelated behavior.
- No UI translation for the new sub-rule label (accepted: renders as `[[Poland-4x70m]]@[lang]@[Install]`, same as `Poland-Full` today).

## Decisions

### Decision 1: Branch inside the existing `Setup_3_PL.php`, not a new file

**Choice:** `$isDouble = (isset($subRuleName) && $subRuleName === 'Poland-4x70m');` near the top of the script; everything downstream reads that flag. `$SubRule` itself is a raw 1-based dropdown position from the real tournament-creation form (`Tournament/index.php`), not the rule name — `$subRuleName` is the actual looked-up string, reachable because `GetSetupFile()`'s `require_once` shares its own local scope with the required setup file.

**Alternative considered:** A separate `Setup_3_PL_4x70m.php`. Rejected — ianseo's setup-file resolution keys only on `{Type}_{Lang}`, never on `$SubRule` (confirmed: `FR` handles ~10 sub-rules from one file). A second file would never be picked up.

### Decision 2: A single leg-doubling helper wraps every `CreateDistanceNew()` call

**Choice:** Add a local helper in `Setup_3_PL.php`:

```php
function pl_double_legs($legs, $isDouble) {
    if (!$isDouble) return $legs;

    $counts = array();
    $order  = array();
    foreach ($legs as $leg) {
        $meters = $leg[1];
        if (!isset($counts[$meters])) {
            $counts[$meters] = 0;
            $order[] = $meters;
        }
        $counts[$meters]++;
    }

    $out = array();
    foreach ($order as $meters) {
        for ($i = 1; $i <= $counts[$meters] * 2; $i++) {
            $out[] = array($meters . 'm-' . $i, $meters);
        }
    }
    return $out;
}
```

Wrap the existing distances argument at each of the 14 `CreateDistanceNew()` call sites, e.g.:

```php
CreateDistanceNew($TourId, $TourType, 'RM', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
```

This groups legs by meters value (in first-appearance order) and relabels each group sequentially: `[70m-1, 70m-2]` (a single 70m group of 2) becomes `[70m-1, 70m-2, 70m-3, 70m-4]`, and U15's `[40m, 20m]` (two distinct groups of 1) becomes `[40m-1, 40m-2, 20m-1, 20m-2]` — **without a special case for U15**. Grouping by meters rather than doubling each leg in place is what keeps the two U15 distances from interleaving.

**Alternative considered:** Hand-write a second full set of `CreateDistanceNew()` calls for the doubled variant (mirroring how `optR`/`optC`/`optB` already get mutated inline for U15/U18 today). Rejected — duplicates ~25 lines of per-class distance data that would need to stay in sync with the `Poland-Full` list forever; the wrapper keeps a single source of truth.

### Decision 3: `$DistanceInfoArray` doubles via `array_merge`, `$tourDetNumDist` becomes conditional

**Choice:**
```php
$tourDetNumDist    = $isDouble ? '4' : '2';
$DistanceInfoArray = array(array(6, 6), array(6, 6));
if ($isDouble) { $DistanceInfoArray = array_merge($DistanceInfoArray, $DistanceInfoArray); }
```
`$tourDetMaxDistScore` stays `'360'` (a per-session cap: 6 ends × 6 arrows × 10 = 360, unaffected by session count).

### Decision 4: `sets.php` registration is additive, scoped to type 3 only

**Choice:**
```php
foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = array('Poland-Full');
}
$SetType['PL']['rules']['3'][] = 'Poland-4x70m';
```
Types 1 and 6 keep only `Poland-Full`.

## Risks / Trade-offs

**[Risk] `EvMatchArrowsNo`/`EvFinalAthTarget` (hardcoded `240`) don't obviously correspond to the 72- or 144-arrow qualification total** → Pre-existing in `Poland-Full`, unrelated to session-count doubling, left untouched. Out of scope for this change.

**[Risk] Mechanical wrapping across all 14 `CreateDistanceNew()` call sites — easy to miss one** → Verification: after implementation, grep `Setup_3_PL.php` for `CreateDistanceNew(` and confirm every call's distances argument is wrapped in `pl_double_legs(...)`.

**[Trade-off] `pl_double_legs()` is a local, unexported helper (not `lib.php`)** → Scoped to this one file since no other setup script needs session-doubling today; matches the file's existing style of keeping distance logic inline rather than in shared `lib.php`.

## Migration Plan

None. No DB schema change, no auto-install table. `Poland-4x70m` becomes selectable in the tournament-creation dropdown immediately once `sets.php` is updated; existing `Poland-Full` tournaments are unaffected (setup scripts only run at tournament creation/reset).
