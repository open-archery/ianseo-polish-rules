## ADDED Requirements

### Requirement: Category presets selected as sub-rules

The setup scripts SHALL support named category presets, registered per TourType in `sets.php` and selected by the organiser from ianseo's sub-rule dropdown when creating the tournament.

A preset names the divisions and the age classes the competition uses. When a preset is selected, the setup script SHALL create:

- only the divisions the preset names,
- only the classes the preset names, and only in the divisions those classes are eligible for,
- only the individual and team events those division/class combinations compete in.

It SHALL NOT create categories or events outside the preset, so that no manual deletion is needed after setup.

`Poland-Full` SHALL remain the default preset and SHALL create the full set of divisions, classes and events exactly as it does today. A preset SHALL NOT change distances, target faces, end structure, elimination cut counts or finals configuration for the categories it does create — those follow the TourType, not the preset.

The set of presets is fixed in the module. There is no runtime editing of presets.

#### Scenario: Youth preset

- **WHEN** an organiser creates a tournament and selects a preset naming the U15 and U12 classes
- **THEN** only U15 and U12 classes are created, in the divisions those classes are eligible for
- **AND** only the events those classes compete in exist
- **AND** no senior, U24, U21, U18 or Master category is created

#### Scenario: Division preset

- **WHEN** an organiser selects a preset naming divisions C and B with all their classes
- **THEN** no Recurve division, class or event is created
- **AND** every eligible C and B class is created

#### Scenario: Default preset is unchanged

- **WHEN** an organiser selects `Poland-Full`
- **THEN** the divisions, classes and events created are identical to those created before presets existed

#### Scenario: A preset does not alter the format

- **WHEN** two tournaments of the same TourType are created with different presets
- **THEN** for any category present in both, the distances, target faces, end structure and elimination configuration are identical
