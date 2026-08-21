<?php
/**
 * Presets.php — read-only PZŁucz points-ranking presets.
 *
 * Presets are plain PHP constants, not seeded DB rows (see design D1): a
 * point-value change in a future annex takes effect on the next code update,
 * with no migration.
 *
 * Shape of one preset:
 *   'name'            => string
 *   'scope'           => ['divisions' => string[], 'classes' => string[]]   // [] = all
 *   'classifications' => [key => [
 *       'subject'  => 'IND'|'TEAM'|'MIXED',
 *       'source'   => 'QUAL'|'ELIM',
 *       'cutoff'   => bool,
 *       'brackets' => [[rank_from, rank_to, points], ...],
 *   ]]
 *   'reports' => [
 *       ['kind' => 'SEPARATE', 'classification' => key, 'label' => string],
 *       ['kind' => 'COMBINED', 'classifications' => [key, ...], 'cap' => int, 'label' => string],
 *       ['kind' => 'CLUB', 'label' => string],
 *       ['kind' => 'VOIVODESHIP', 'label' => string],
 *   ]
 *   'three_of_four'     => bool               // drop the worst of 4 qualifiers (D4)
 *   'one_team_per_club' => bool                // score TeSubTeam = 0 only (D4)
 *   'min_participation' => null|['clubs' => int, 'voivodeships' => int]
 *
 * Bracket tables transcribed verbatim from the spec (2026 annexes, July 2026
 * update) — see openspec/changes/points-ranking/specs/points-ranking/spec.md
 * "Preset definitions (read-only)" for the source tables and the two
 * documented annex-typo corrections already applied here.
 */

const PL_POINTS_PRESETS = [

    // 1 — Młodzieżowe Mistrzostwa Polski (U24)
    'mmp' => [
        'name' => 'Młodzieżowe Mistrzostwa Polski',
        'scope' => ['divisions' => [], 'classes' => []],
        'classifications' => [
            'ind' => [
                'subject' => 'IND', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 25], [2, 2, 21], [3, 4, 17], [5, 5, 10], [6, 6, 5],
                    [7, 8, 4], [9, 10, 3], [11, 12, 2], [13, 16, 1],
                ],
            ],
            'tea' => [
                'subject' => 'TEAM', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 25], [2, 2, 21], [3, 4, 17], [5, 5, 11], [6, 6, 5],
                    [7, 7, 4], [8, 8, 1],
                ],
            ],
            'mix' => [
                'subject' => 'MIXED', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 25], [2, 2, 21], [3, 4, 17], [5, 5, 10], [6, 6, 5],
                    [7, 7, 4], [8, 8, 1],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'VOIVODESHIP', 'label' => 'Klasyfikacja województw'],
            ['kind' => 'CLUB', 'label' => 'Klasyfikacja klubowa'],
            ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea', 'mix'], 'cap' => 3, 'label' => 'Podsumowanie punktacji'],
        ],
        'three_of_four' => true,
        'one_team_per_club' => false,
        'min_participation' => null,
    ],

    // 2 — Mistrzostwa Polski Juniorów
    'mpj' => [
        'name' => 'Mistrzostwa Polski Juniorów',
        'scope' => ['divisions' => [], 'classes' => []],
        'classifications' => [
            'ind' => [
                'subject' => 'IND', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 15], [2, 2, 12], [3, 4, 10], [5, 5, 8], [6, 6, 7],
                    [7, 7, 6], [8, 8, 5], [9, 12, 4], [13, 15, 2],
                ],
            ],
            'tea' => [
                'subject' => 'TEAM', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 33], [2, 2, 27], [3, 4, 22], [5, 5, 10], [6, 7, 8],
                    [8, 8, 4], [9, 10, 3],
                ],
            ],
            'mix' => [
                'subject' => 'MIXED', 'source' => 'ELIM', 'cutoff' => true,
                // Annex prints overlapping "6-7" then "7-10" — corrected to "8-10" (see spec).
                'brackets' => [
                    [1, 1, 24], [2, 2, 19], [3, 4, 16], [5, 5, 8], [6, 7, 7],
                    [8, 10, 3], [11, 12, 1],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'VOIVODESHIP', 'label' => 'Klasyfikacja województw'],
            ['kind' => 'CLUB', 'label' => 'Klasyfikacja klubowa'],
            ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea', 'mix'], 'cap' => 3, 'label' => 'Podsumowanie punktacji'],
        ],
        'three_of_four' => true,
        'one_team_per_club' => false,
        'min_participation' => null,
    ],

    // 3 — Puchar Polski — runda
    'pp' => [
        'name' => 'Puchar Polski - runda',
        'scope' => ['divisions' => [], 'classes' => []],
        'classifications' => [
            'ind' => [
                'subject' => 'IND', 'source' => 'ELIM', 'cutoff' => false,
                'brackets' => [
                    [1, 1, 25], [2, 2, 21], [3, 3, 18], [4, 4, 15], [5, 5, 13],
                    [6, 6, 12], [7, 7, 11], [8, 8, 10], [9, 16, 5], [17, 32, 1],
                ],
            ],
            'mix' => [
                'subject' => 'MIXED', 'source' => 'ELIM', 'cutoff' => false,
                // No 17-32 row: at most 16 pairs take part in a mixed bracket.
                'brackets' => [
                    [1, 1, 25], [2, 2, 21], [3, 3, 18], [4, 4, 15], [5, 5, 13],
                    [6, 6, 12], [7, 7, 11], [8, 8, 10], [9, 16, 5],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'SEPARATE', 'classification' => 'ind', 'label' => 'Klasyfikacja indywidualna'],
            ['kind' => 'SEPARATE', 'classification' => 'mix', 'label' => 'Klasyfikacja mikstów'],
        ],
        'three_of_four' => false,
        'one_team_per_club' => false,
        'min_participation' => null,
    ],

    // 4 — Międzywojewódzkie Mistrzostwa Młodzików
    'mmm' => [
        'name' => 'Międzywojewódzkie Mistrzostwa Młodzików',
        'scope' => ['divisions' => [], 'classes' => []],
        'classifications' => [
            'ind' => [
                'subject' => 'IND', 'source' => 'QUAL', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 5], [2, 2, 4], [3, 4, 3], [5, 9, 2], [10, 20, 1],
                ],
            ],
            'tea' => [
                'subject' => 'TEAM', 'source' => 'QUAL', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 5], [2, 2, 4], [3, 4, 3], [5, 6, 2],
                ],
            ],
            'mix' => [
                'subject' => 'MIXED', 'source' => 'QUAL', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 5], [2, 2, 4], [3, 4, 3], [5, 8, 2],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'VOIVODESHIP', 'label' => 'Klasyfikacja województw'],
            ['kind' => 'CLUB', 'label' => 'Klasyfikacja klubowa'],
            ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea', 'mix'], 'cap' => 2, 'label' => 'Podsumowanie punktacji'],
        ],
        // Declared 3-person club rosters, no 4th-athlete rule (design D4).
        'three_of_four' => false,
        'one_team_per_club' => false,
        'min_participation' => ['clubs' => 3, 'voivodeships' => 2],
    ],

    // 5 — Ogólnopolska Olimpiada Młodzieży
    'oom' => [
        'name' => 'Ogólnopolska Olimpiada Młodzieży',
        'scope' => ['divisions' => [], 'classes' => []],
        'classifications' => [
            'ind' => [
                'subject' => 'IND', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 12], [2, 2, 10], [3, 4, 8], [5, 6, 6], [7, 10, 5],
                    [11, 16, 4], [17, 24, 3], [25, 32, 2], [33, 64, 1],
                ],
            ],
            'tea' => [
                'subject' => 'TEAM', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 27], [2, 2, 22], [3, 4, 18], [5, 5, 10], [6, 7, 8],
                    [8, 8, 7], [9, 10, 6],
                ],
            ],
            'mix' => [
                'subject' => 'MIXED', 'source' => 'ELIM', 'cutoff' => true,
                'brackets' => [
                    [1, 1, 19], [2, 2, 16], [3, 4, 12], [5, 5, 8], [6, 8, 6],
                    [9, 9, 4], [10, 13, 2],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'VOIVODESHIP', 'label' => 'Klasyfikacja województw'],
            ['kind' => 'CLUB', 'label' => 'Klasyfikacja klubowa'],
            ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea', 'mix'], 'cap' => 2, 'label' => 'Podsumowanie punktacji'],
        ],
        'three_of_four' => true,
        'one_team_per_club' => false,
        'min_participation' => null,
    ],

    // 6 — Mistrzostwa Krajowego Zrzeszenia LZS
    'lzs' => [
        'name' => 'Mistrzostwa Krajowego Zrzeszenia LZS',
        'scope' => [
            'divisions' => ['R'],
            'classes' => ['U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W'],
        ],
        'classifications' => [
            // One table shared by ind and tea.
            'ind' => [
                'subject' => 'IND', 'source' => 'QUAL', 'cutoff' => false,
                'brackets' => [
                    [1, 1, 9], [2, 2, 7], [3, 3, 6], [4, 4, 5], [5, 5, 4],
                    [6, 6, 3], [7, 7, 2], [8, 8, 1],
                ],
            ],
            'tea' => [
                'subject' => 'TEAM', 'source' => 'QUAL', 'cutoff' => false,
                'brackets' => [
                    [1, 1, 9], [2, 2, 7], [3, 3, 6], [4, 4, 5], [5, 5, 4],
                    [6, 6, 3], [7, 7, 2], [8, 8, 1],
                ],
            ],
        ],
        'reports' => [
            ['kind' => 'VOIVODESHIP', 'label' => 'Klasyfikacja województw'],
            ['kind' => 'CLUB', 'label' => 'Klasyfikacja klubowa'],
            ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea'], 'cap' => 0, 'label' => 'Podsumowanie punktacji'],
            ['kind' => 'SEPARATE', 'classification' => 'tea', 'label' => 'Klasyfikacja drużynowa'],
        ],
        'three_of_four' => false,
        'one_team_per_club' => true,
        'min_participation' => null,
    ],

];
