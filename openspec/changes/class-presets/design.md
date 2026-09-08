# Design — category presets as sub-rules

## Open gaps (decide before implementing)

| # | Gap | Effect if left open |
| - | --- | --- |
| 1 | The actual preset list. Nobody has supplied it. | Nothing to ship; the mechanism is useless without presets. |
| 2 | Presets need a division axis as well as a class axis (a Compound+Barebow cup is a division cut, a youth event is a class cut). | Half the real competitions cannot be expressed. |
| 3 | Whether a preset trims target faces (assumed no). | Unused faces stay in the tournament — harmless but untidy. |
| 4 | Operator-editable presets. Deferred. | A new preset needs a code change and a deploy. |
| 5 | The same class filter is needed by `add-setup-types-16-37`. | Two incompatible filters if both are built separately. |

## ianseo hook point

`GetSetupFile($TourId, $ToType, $Lang, $SubRule, $subRuleName)` already passes the selected sub-rule into the setup script twice: `$SubRule` as the 1-based dropdown position and `$subRuleName` as the registered string. `Setup_3_PL.php` already branches on `$subRuleName` for `Poland-4x70m`, so the plumbing exists and needs no core change.

`sets.php` registers the options: `$SetType['PL']['rules']['3'][] = 'Poland-Youth';` and so on, per TourType.

## Preset shape

A preset is a data structure in `lib.php`, not scattered conditionals:

```
'Poland-Youth' => [
    'divisions' => ['R', 'C'],
    'classes'   => ['U15M', 'U15W', 'U12M', 'U12W'],
]
```

Both keys are filters against the full set. Omitting a key means "all" — so a division-only preset (the Compound+Barebow cup) names divisions and leaves classes unset, and a class-only preset does the reverse. This is what gap #2 asks for; the alternative, one flat list of division/class pairs, is more precise and much longer to write.

`CreateStandardDivisions()`, `CreateStandardClasses()` and `InsertStandardEvents()` each take the resolved preset and skip anything outside it. The existing eligibility matrix (which classes exist in which divisions) still applies on top — a preset can only ever remove categories, never add one that PZŁucz rules do not allow.

`Poland-Full` resolves to a preset with neither key set, so it is the same code path with nothing filtered — which is how the "output unchanged" requirement gets tested.

## Interaction with add-setup-types-16-37

That change needs exactly this filter for its TourType 16 youth round, where it is not a preset the organiser picks but a property of the type. Both uses want the same `lib.php` mechanism: the setup script resolves a preset (from `$subRuleName`, or hardcoded for type 16) and passes it down. Whichever change is implemented first builds it; the second one consumes it.

## Files

- **Modified:** `sets.php`, `lib.php`, `Setup_1_PL.php`, `Setup_3_PL.php`, `Setup_6_PL.php`, `LibTest.php`
- **Menu:** none — the preset appears in ianseo's own tournament-creation form
- **DB:** none
