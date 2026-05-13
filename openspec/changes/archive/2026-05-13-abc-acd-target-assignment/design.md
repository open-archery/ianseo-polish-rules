## Context

ianseo's built-in auto-assignment (`Partecipants/SetTarget_auto.php`) is ianseo core and cannot be modified. For domestic PZŁucz tournaments, organisers need a staggered ABC/ACD alternating boss pattern with 3 archers per boss on a session configured as `SesAth4Target = 4`. The empty fourth position (D on odd bosses, B on even bosses) is left unassigned. Club grouping must replicate ianseo's behaviour: one club per boss position, clubs on consecutive bosses.

## Goals / Non-Goals

**Goals:**
- Single-page UI for assigning one division/class at a time to a target range.
- Slot pattern determined by boss number parity: odd = A, B, C; even = A, C, D.
- Club grouping: largest club first, one athlete per boss per club, athletes of same club on consecutive bosses.
- Preview mode (no writes) and save mode (erase then write).
- Menu entry under Participants (`PART`).

**Non-Goals:**
- Modifying ianseo core.
- Wheelchair / double-space / VI athlete handling (use ianseo's native tool for those).
- Multi-class assignment in one pass.
- Zigzag / ORIS / Field3D draw types.

## Decisions

### Slot builder: parity on absolute boss number
Boss parity is determined by the boss number itself (odd → ABC, even → ACD), not by its position within the selected range. This makes the pattern field-global and consistent regardless of which class runs first.

**Alternative considered:** range-relative (first boss in range = ABC). Rejected because two classes assigned to overlapping or abutting ranges would break the field pattern.

### Assignment algorithm: "column jump"
Mirrors ianseo's `$ArcPerButt * $nextTarget` jump:

```
slot list: 1A 1B 1C  2A 2C 2D  3A 3B 3C  4A 4C 4D ...
index:       0  1  2   3  4  5   6  7  8   9 10 11
```

For each club (sorted DESC by size), find the first available slot, then jump by `3` (boss width) for each subsequent athlete. This places same-club athletes at the same index-within-boss across consecutive bosses, satisfying the one-per-boss constraint automatically.

At each club boundary: advance to the next slot whose letter is `A`, skipping any remaining slots in the current column. This keeps club blocks compact and prevents a club with 2 athletes from consuming a full column of 5 bosses.

**Alternative considered:** filling slot-by-slot sequentially. Rejected because it distributes athletes from the same club across different bosses unpredictably.

### Erase-then-write on save
Save always erases the existing assignment for the selected class + session before writing. This avoids partial-assignment confusion. Users are expected to run per-class (the UI accepts one class at a time).

**Alternative considered:** incremental / exclude-assigned mode. Rejected to keep the implementation simple; the user can re-run for a class if they need to adjust the range.

### No new DB table
Writes go directly to ianseo's `Qualifications` and `Entries` tables using the same fields as the native tool (`QuTarget`, `QuLetter`, `QuBacknoPrinted`, `EnMainInfoUpdate`, `EnTimestamp`). No PL-prefixed table needed.

## Files

| Action   | Path |
|----------|------|
| Create   | `Modules/Sets/PL/Targets/SetTargetABCACD.php` |
| Modify   | `Modules/Sets/PL/menu.php` |

## Risks / Trade-offs

- **`SesAth4Target` must be 4** — if a session is configured as 3, ianseo's scoresheet printing and other views will not expect a `D` letter. The page should check and warn if `SesAth4Target != 4` for the selected session.
- **No randomisation of club order** — largest club always gets the A column. This is deterministic and fair for competition use, but means the biggest club is always leftmost.
- **Column skew with heterogeneous club sizes** — if one club has many more athletes than others, later clubs may start far into the target range before any same-club adjacency. This is acceptable and mirrors ianseo's behaviour.

## Open Questions

_(none — all design decisions resolved in exploration)_
