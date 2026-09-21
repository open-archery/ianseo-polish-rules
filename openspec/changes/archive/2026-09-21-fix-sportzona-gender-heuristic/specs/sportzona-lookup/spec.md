## ADDED Requirements

### Requirement: Gender heuristic exception table
The adapter SHALL derive gender from `firstName` by checking a static exception table before falling back to the "ends with letter `a` (case-insensitive) → female, otherwise male" heuristic described in §4.4. A name matching an entry in the female-exception list SHALL be classified female regardless of its ending; a name matching an entry in the male-exception list SHALL be classified male regardless of its ending. Matching SHALL be case-insensitive and SHALL be evaluated against the normalized given name (see "Given name comma normalization"), not the raw `firstName` field.

The female-exception list SHALL include at minimum: Angeliki, Abigail, Ariel, Dinah, Elizabeth, Kendall, Madeleine, Miriam, Nelly, Nicole, Nikol, Noemi, Sophie, Vivienne, Zerin.

The male-exception list SHALL include at minimum: Barnaba, Bonawentura, Ilia, Illia, Jarema, Kosma, Kuba, Mykyta, Nikita.

Any given name not matching either list SHALL fall back to the existing "ends with `a`" heuristic unchanged.

#### Scenario: Female exception overrides the ends-with-a default
- **WHEN** the adapter derives gender for the given name "Angeliki"
- **THEN** it SHALL return female, even though the name does not end in "a"

#### Scenario: Male exception overrides the ends-with-a default
- **WHEN** the adapter derives gender for the given name "Kosma"
- **THEN** it SHALL return male, even though the name ends in "a"

#### Scenario: Ukrainian male exception overrides the ends-with-a default
- **WHEN** the adapter derives gender for the given name "Illia" or "Nikita" or "Mykyta" or "Ilia"
- **THEN** it SHALL return male, even though each name ends in "a"

#### Scenario: Unlisted name still uses the ends-with-a heuristic
- **WHEN** the adapter derives gender for a given name that matches neither exception list
- **THEN** it SHALL classify by whether the name ends with the letter "a", as before

#### Scenario: Exception matching is case-insensitive
- **WHEN** the adapter derives gender for the given name "ANGELIKI" or "kosma"
- **THEN** it SHALL still match the corresponding exception entry

### Requirement: Given name comma normalization
When the raw `firstName` value from Sportzona contains a comma (a data-entry artifact where two given names were entered into one field), the adapter SHALL use only the trimmed text before the first comma as the effective given name. This normalized value SHALL be used both as the `GivenName` output field and as the input to gender derivation. A `firstName` with no comma SHALL be used unchanged, including one containing space-separated multiple given names (e.g. "Jan Maciej"), which is not treated as an artifact.

#### Scenario: Comma-joined given names are reduced to the first name
- **WHEN** the raw `firstName` is `"Artur,  Damian"`
- **THEN** the adapter SHALL emit `GivenName` as `"Artur"` and derive gender from `"Artur"`

#### Scenario: Comma with no surrounding whitespace is still split correctly
- **WHEN** the raw `firstName` is `"Marcin,Artur"`
- **THEN** the adapter SHALL emit `GivenName` as `"Marcin"`

#### Scenario: Trailing comma with an empty second segment
- **WHEN** the raw `firstName` is `"Józef,"`
- **THEN** the adapter SHALL emit `GivenName` as `"Józef"`

#### Scenario: Space-separated double given names are left untouched
- **WHEN** the raw `firstName` is `"Jan Maciej"`
- **THEN** the adapter SHALL emit `GivenName` as `"Jan Maciej"` unchanged
