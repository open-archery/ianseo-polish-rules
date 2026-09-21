## Purpose

Generates PDF diplomas (§1.7 Nagrody: places 1-8 in individual and team events) for a
tournament's individual, team, and mixed-team results, from both qualification and finals
standings, with tournament- and per-event-level text customization.

## ADDED Requirements

### Requirement: Athlete names print given name before surname
Every diploma (individual, team member listing, and the single custom diploma) SHALL display an
athlete's full name as given name followed by surname.

#### Scenario: Individual diploma
- **WHEN** an individual diploma is generated for an athlete
- **THEN** the printed name reads "{given name} {surname}" (e.g. "Jan Nowak"), not
  "{surname} {given name}"

#### Scenario: Team member listing
- **WHEN** a team diploma lists its members
- **THEN** each member's name reads "{given name} {surname}"

### Requirement: Date and location line is omitted when unset
The diploma's bottom date/location line SHALL be built from whichever of "Data" and "Miejsce" are
configured, never printing an empty value or a dangling separator.

#### Scenario: Both blank
- **WHEN** the tournament's diploma config has both Dates and Location empty
- **THEN** the diploma prints no date/location line at all

#### Scenario: Only one set
- **WHEN** exactly one of Dates or Location is configured
- **THEN** the diploma prints only that value, with no leading/trailing separator punctuation

#### Scenario: Both set
- **WHEN** both Dates and Location are configured
- **THEN** the diploma prints "{Location}, {Dates}" as today

### Requirement: An intentionally blank date/location is preserved
The configuration page SHALL distinguish "no configuration saved yet for this tournament" from "a
configuration exists with Dates/Location deliberately left blank," and only pre-fill from the
tournament's own session data in the former case.

#### Scenario: First visit, nothing saved yet
- **WHEN** an admin opens the diploma configuration page for a tournament with no
  `PLDiplomaConfig` row
- **THEN** the Dates and Location fields are pre-filled from the tournament's own dates/location

#### Scenario: Saved with Dates/Location blank
- **WHEN** an admin saves the diploma configuration with Dates and/or Location left empty
- **THEN** reopening the configuration page shows those fields empty, not re-filled with the
  tournament's dates/location

### Requirement: Custom diploma shows the correct category text
The single custom diploma (`PrnCustomDipl.php`) SHALL print the configured category text for the
selected event, never a literal representation of the underlying data structure.

#### Scenario: Event has a saved per-event override
- **WHEN** an admin generates a custom diploma for an event that has a saved
  "Tekst na dyplomie" override
- **THEN** the diploma prints that override text as the category line, not the word "Array" or
  any other non-text value

#### Scenario: Event has no saved override
- **WHEN** an admin generates a custom diploma for an event with no saved override
- **THEN** the diploma falls back to that event's default category text

### Requirement: Custom diploma includes championship titles when enabled
The single custom diploma SHALL apply the same title-generation rule as the individual and team
diploma batches.

#### Scenario: Titles enabled, eligible place
- **WHEN** the tournament's diploma config has titles enabled, the custom diploma's rank is 1-3,
  and the selected event has title text configured
- **THEN** the diploma prints the championship title phrase

#### Scenario: Titles disabled
- **WHEN** the tournament's diploma config has titles disabled
- **THEN** the custom diploma prints no title phrase, regardless of rank

### Requirement: Default place range is 1-8
A tournament with no saved diploma configuration SHALL default its place range to 1-8 (§1.7.2/
§1.7.4), while remaining fully admin-configurable to any range.

#### Scenario: First visit, nothing saved yet
- **WHEN** an admin opens the diploma configuration page for a tournament with no
  `PLDiplomaConfig` row
- **THEN** the "Dyplomy od miejsca"/"Dyplomy do miejsca" fields show 1 and 8

#### Scenario: Admin overrides the range
- **WHEN** an admin saves a different place range (e.g. 1-3)
- **THEN** that saved range is used for diploma generation and shown on later visits, not the
  1-8 default

### Requirement: Category line states competition type, category name, and bow type
Every diploma's category line SHALL read
`w konkurencji {typeWord}{category name},\nw kategorii {bow-type phrase}` — a forced line break
before "w kategorii" rather than relying on width-based wrapping — where `{typeWord}` is
"indywidualnej " for individual events, "zespołowej " for team events, or "mikstów" (with a
trailing space only when followed by a category name) for mixed-team events. This composed line
applies unless the event has a saved "Tekst na dyplomie" override, which fully replaces it as
today (and is not subject to the forced line break).

#### Scenario: Individual event, gendered Senior category
- **WHEN** an individual diploma is generated for a Senior class event (e.g. recurve women)
- **THEN** the category line reads "w konkurencji indywidualnej kobiet," on one line and
  "w kategorii łuków klasycznych" on the next (or "mężczyzn" for the men's class)

#### Scenario: Individual event, age-class category
- **WHEN** an individual diploma is generated for a non-Senior age class (e.g. U21 women, barebow)
- **THEN** the category line reads "w konkurencji indywidualnej juniorek," on one line and
  "w kategorii łuków barebow" on the next

#### Scenario: Team event
- **WHEN** a team diploma is generated for a class event (e.g. recurve men)
- **THEN** the category line reads "w konkurencji zespołowej mężczyzn," on one line and
  "w kategorii łuków klasycznych" on the next

#### Scenario: Mixed team event, Senior
- **WHEN** a mixed-team diploma is generated for the Senior class
- **THEN** the category line reads "w konkurencji mikstów," on one line and "w kategorii
  {bow-type phrase}" on the next, with no category name

#### Scenario: Mixed team event, non-Senior age
- **WHEN** a mixed-team diploma is generated for a non-Senior age class (e.g. U21)
- **THEN** the category line reads "w konkurencji mikstów juniorów," on one line and "w kategorii
  {bow-type phrase}" on the next

#### Scenario: U10 class bow-type override
- **WHEN** a diploma of any competition type is generated for a U10 class event
- **THEN** the bow-type phrase reads "łuków popularnych", regardless of the event's underlying
  division code

#### Scenario: Per-event override still wins
- **WHEN** an event has a saved "Tekst na dyplomie" value
- **THEN** the diploma prints that value as its category line instead of the composed sentence
