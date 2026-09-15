## ADDED Requirements

### Requirement: Category presets selected as sub-rules

The setup scripts SHALL support named category presets, registered per TourType in `sets.php` and selected by the organiser from ianseo's sub-rule dropdown when creating the tournament. A preset names the divisions and/or classes the competition uses; omitting an axis means "all" for that axis. When a preset is selected, the setup script SHALL create only the divisions/classes it names — in only the divisions those classes are eligible for — and only the individual/team events those surviving division×class combinations compete in. `Poland-Full`-equivalent (the unfiltered default) SHALL remain available on every TourType and SHALL create the full set exactly as an unfiltered setup would. A preset SHALL NOT change distances, target faces, end structure, elimination cut counts or finals configuration for the categories it does create.

Sub-rule values SHALL reuse ianseo's existing translated `Install.php` keys wherever their meaning matches (`SetAllClass` for "everything", `SetSeniorClass` for "Senior class only", `SetYouthClass` for the broadest youth-only cut available on that TourType, `SetMasterClass` for the Masters preset); presets with no matching core vocabulary use module-specific keys, which SHALL be accepted even though ianseo renders them without a translated label.

#### Scenario: Senior-only preset
- **WHEN** an organiser creates a TourType 3 tournament and selects the Senior-only sub-rule
- **THEN** only M and W classes are created, in every division they are eligible for (R, C, B)
- **AND** no U24, U21, U18, U15, U12, PU12 or Masters category is created

#### Scenario: Recurve-only youth preset
- **WHEN** an organiser selects a Recurve-only youth sub-rule naming U21 and U18
- **THEN** U21M/U21W/U18M/U18W are created in division R only
- **AND** no Compound or Barebow class is created for U21 or U18, even though both are normally eligible in C and B

#### Scenario: Default preset is unchanged
- **WHEN** an organiser selects the unfiltered default sub-rule
- **THEN** the divisions, classes and events created are identical to what setup created before presets existed for that TourType

### Requirement: PZŁucz Masters age-band classes (TourType 3)

TourType 3 (Single-Distance Round) SHALL offer a dedicated Masters sub-rule creating five age-band classes per gender (40-49, 50-59, 60-69, 70+, 80+) in divisions R, C and B. 40-49/50-59/60-69 are closed decade ranges; 70+ and 80+ are both open-ended and deliberately overlap — an 80-or-older archer may choose to enter either band. Distances: R shoots 70m (40-49, 50-59), 60m (60-69), 50m (70+, 80+); C and B shoot 50m for every band. Target faces: R and B use the 122cm full face at every band; C uses the 80cm 6-ring face for bands 40-49/50-59/60-69 and the 80cm full face for bands 70+/80+. Every Masters class SHALL have an elimination phase, following the same set/cumulative match rules as the corresponding adult R/C/B classes in §2 of this spec. This preset SHALL NOT be offered on any other TourType.

#### Scenario: Masters band creation
- **WHEN** an organiser creates a TourType 3 tournament and selects the Masters sub-rule
- **THEN** 10 classes are created (5 bands × 2 genders), each eligible in divisions R, C and B
- **AND** the 70+ and 80+ Compound classes use the 80cm full face, not the 6-ring face used by the younger three bands
- **AND** every Masters class has an elimination phase configured

#### Scenario: Masters not offered elsewhere
- **WHEN** an organiser creates a TourType 1, 6, 16 or 37 tournament
- **THEN** the Masters sub-rule does not appear in the sub-rule dropdown

### Requirement: PU12 simplified-recurve class

TourTypes 3 and 6 SHALL support a `PU12M`/`PU12W` class (age 9-12, same bracket as `U12M`/`U12W`), division R only, representing simplified/recreational recurve equipment as a distinct category from standard `U12M`/`U12W`. On TourType 3, PU12 shoots 2×10m; on TourType 6 (indoor), PU12 shoots at 10m. Both use the 122cm full face. PU12 SHALL have no elimination phase. PU12 SHALL NOT be created on TourType 1, 16 or 37.

#### Scenario: PU12 alongside U12
- **WHEN** an organiser creates a TourType 3 or 6 tournament with the unfiltered default sub-rule
- **THEN** both U12M/U12W and PU12M/PU12W are created, as distinct classes with distinct distances
- **AND** neither has an elimination phase

#### Scenario: PU12 excluded from Children's Round and Double Round
- **WHEN** an organiser creates a TourType 16 or 37 tournament
- **THEN** no PU12 class is created

### Requirement: U12 extended to TourType 3

TourType 3 SHALL create `U12M`/`U12W` (division R only, 2×15m, 122cm full face, no elimination) in addition to its existing classes. This class did not previously exist on TourType 3.

#### Scenario: U12 on the Single-Distance Round
- **WHEN** an organiser creates a TourType 3 tournament with the unfiltered default sub-rule
- **THEN** U12M/U12W are created at 2×15m with no elimination phase

## MODIFIED Requirements

### Requirement: 1440 Round bow types and divisions (`Setup_1_PL.php`)

TourType 1 SHALL create divisions R and C only — division B (Barebow) SHALL NOT be created on this TourType. Division C's distances SHALL mirror division R's per-class distance table exactly (90/70/50/30m for M/U24M/U21M; 70/60/50/30m for W/U24W/U21W/U18M; 60/50/40/30m for U18W), replacing the previous flat 4×50m Compound distance. Compound's target face remains the 80cm 6-ring face at these new distances. No Master class exists on this TourType (see "Flat Master class removed" below).

**BREAKING**: A `Poland-Full` TourType 1 tournament created after this change has no Barebow division and different Compound distances than one created before it.

#### Scenario: 1440 divisions after this change
- **WHEN** an organiser creates a TourType 1 tournament with the unfiltered default sub-rule
- **THEN** only divisions R and C are created
- **AND** Compound's per-class distances equal Recurve's per-class distances for the same class

### Requirement: 1440 Round sub-rules

TourType 1 SHALL offer sub-rules for: everything (R+C, all classes), Senior-only (R+C, M/W), Recurve-only U24, Recurve-only U21, and Recurve-only U18 — in addition to the unfiltered default.

#### Scenario: Recurve-only U21 on the 1440 Round
- **WHEN** an organiser selects the Recurve-only U21 sub-rule on a TourType 1 tournament
- **THEN** only U21M/U21W in division R are created
- **AND** no Compound U21 class is created, even though Compound U21 exists in the unfiltered default

### Requirement: Target face labels in Polish

Every `CreateTargetFace()` label created by this module SHALL use this module's established Polish division vocabulary (`Łuk klasyczny`, `Łuk bloczkowy`, `Łuk barebow`) rather than the English bow-type words previously used in some labels (e.g. `Setup_6_PL.php`'s "Triple 40 cm (R Senior/U24/U21/Master)" family, `Setup_1_PL.php`'s "Recurve domyślna"/"Compound domyślna"/"Barebow domyślna").

#### Scenario: Indoor target face labels
- **WHEN** an organiser views the target face list for a TourType 6 tournament
- **THEN** every label uses "Łuk klasyczny"/"Łuk bloczkowy"/"Łuk barebow" instead of "Recurve"/"Compound"/"Barebow"

## REMOVED Requirements

### Requirement: Flat Master class (50M/50W)

**Reason**: Not how PZŁucz Masters competitions are actually run — replaced by the five-band Masters preset (TourType 3 only; see "PZŁucz Masters age-band classes" above). No replacement is defined for TourType 1 or 6; those TourTypes simply lose the Master category.

**Migration**: A tournament created before this change with `50M`/`50W` classes is unaffected — this only changes what new setups create. There is no automated migration for existing tournaments; none is in scope.

#### Scenario: No flat Master class anywhere
- **WHEN** an organiser creates a tournament of any TourType with the unfiltered default sub-rule
- **THEN** no class coded `50M` or `50W` is created
- **AND** TourType 3's Masters sub-rule creates the five age-band classes instead, if selected
