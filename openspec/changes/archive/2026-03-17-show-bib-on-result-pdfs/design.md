## Context

ianseo renders result PDFs by loading "chunk" files via `PdfChunkLoader()` (`Common/pdf/PdfChunkLoader.php`). This function already supports module-level overrides: before falling back to the core chunk at `Common/pdf/chunks/<file>`, it checks `Modules/Sets/<localRule>/pdf/chunks/<file>`. No core change is needed to intercept the rendering.

The two affected chunks are:
- `RankIndividual.inc.php` — rendered for Individual Finals ranking (`Final/Individual/PrnRanking.php`)
- `DivClasIndividual.inc.php` — rendered for Individual Qualification ranking (`Qualification/PrnIndividual.php`)

The athlete's licence number is already present in both rank data objects as `$item['bib']` (mapped from `EnCode`). No data layer or rank object changes are required.

## Goals / Non-Goals

**Goals:**
- Display `$item['bib']` as a "Nr lic." column on Individual Qualification and Finals PDFs for PL tournaments.
- Zero impact on non-PL tournaments (override only activates when `ToLocRule = 'PL'`).

**Non-Goals:**
- Team, bracket, or club ranking PDFs.
- Making bib visibility configurable per-tournament.
- Modifying the data retrieval layer.

## Decisions

### Decision: PdfChunkLoader override, not new print files

**Chosen**: Place override chunks at `Modules/Sets/PL/pdf/chunks/`.

**Alternatives considered**:
- *New PL-specific print files in menu*: Would require duplicating all routing/output logic from core `PrnRanking.php` / `PrnIndividual.php`, and would add a second "Individual Ranking" menu item alongside the core one. Brittle if core print files gain new features.
- *Core modification*: Not allowed — ianseo core is read-only.

**Rationale**: The chunk override mechanism exists precisely for this use case. The override files inherit all routing, ACL, session, and output handling from the core print files; only the table layout differs.

### Decision: Steal width from the athlete name column

The PDF page is 190mm wide. Both core chunks use a dynamically-sized athlete column as the main "flex" column. Adding a fixed 16mm "Nr lic." column means reducing the athlete column by 16mm.

- Finals (`RankIndividual`): athlete column = `40 + (12*(7 - NumPhases - ElimCols))`. After change: `24 + (12*(7 - NumPhases - ElimCols))`.
- Qualification (`DivClasIndividual`): athlete column = `37 + addSize`. After change: `21 + addSize`.

At the minimum (many phases/elims), the finals athlete column can drop to ~16mm, which is tight. However in practice PL events use at most 3 phases + 1 elim col, yielding `24 + 36 = 60mm` — comfortably readable.

### Decision: Hardcode column header label "Nr lic."

The core `section['meta']['fields']` map does not include a `bib` label. Rather than extending the rank objects (which live in core), the label is hardcoded in the chunk as `"Nr lic."`. This is consistent with how ianseo uses Polish shorthand directly in print templates.

## Risks / Trade-offs

- **Core chunk updates**: If ianseo upstream changes `RankIndividual.inc.php` or `DivClasIndividual.inc.php`, the PL override will not pick up those changes automatically. Mitigation: keep the override files as minimal diffs from the originals, and note the base version in a comment.
- **Narrow athlete column**: With 7+ phases (rare in Polish competitions), the athlete column becomes very narrow. Mitigation: the `DivClasIndividual` chunk uses a wider layout by default; the finals chunk is most at risk but 7-phase brackets don't occur in PZŁucz events.

## Migration Plan

1. Create `Modules/Sets/PL/pdf/chunks/` directory.
2. Create `RankIndividual.inc.php` and `DivClasIndividual.inc.php` override files.
3. No DB changes, no config changes, no deploy steps — the override activates automatically for all PL tournaments.
4. Rollback: delete the two override files; core chunks resume for all PL tournaments.

## Open Questions

None.
