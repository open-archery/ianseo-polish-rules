<?php

namespace PL\Tests\PointsRanking;

if (PHP_SAPI !== 'cli') {
    exit;
}

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/PointsRankingCalc.php';

final class PointsRankingCalcTest extends \PHPUnit\Framework\TestCase
{
    private const BRACKETS = [[1, 1, 25], [2, 2, 21], [3, 3, 18], [9, 16, 5], [17, 32, 1]];

    // --- pl_points_bracket ----------------------------------------------

    public function testBracketFirstPlace(): void
    {
        $this->assertSame(25, \pl_points_bracket(self::BRACKETS, 1));
    }

    public function testBracketLastPlaceOfSharedRange(): void
    {
        $this->assertSame(1, \pl_points_bracket(self::BRACKETS, 32));
    }

    public function testBracketInsideSharedRange(): void
    {
        $this->assertSame(5, \pl_points_bracket(self::BRACKETS, 12));
    }

    public function testBracketFirstPlaceOutsideEveryBracket(): void
    {
        $this->assertSame(0, \pl_points_bracket(self::BRACKETS, 33));
    }

    public function testBracketZeroForNoResult(): void
    {
        $this->assertSame(0, \pl_points_bracket(self::BRACKETS, 0));
    }

    #[DataProvider('dsqPlaces')]
    public function testBracketZeroForDsq(int $place): void
    {
        $this->assertSame(0, \pl_points_bracket(self::BRACKETS, $place));
    }

    public static function dsqPlaces(): array
    {
        return ['DSQ' => [29999], 'DNS' => [29998], 'DNF' => [30000]];
    }

    // --- pl_points_apply_cutoff ------------------------------------------

    public function testCutoffFiresWhenStartersBelowMaxRankTo(): void
    {
        $rows = [
            ['category' => 'RU21M', 'place' => 1, 'points' => 25],
            ['category' => 'RU21M', 'place' => 12, 'points' => 5],
        ];
        $result = \pl_points_apply_cutoff($rows, 15, ['RU21M' => 12]);

        $this->assertSame(25, $result[0]['points']);
        $this->assertSame(0, $result[1]['points']);
    }

    public function testCutoffDoesNotFireWithSufficientStarters(): void
    {
        $rows = [
            ['category' => 'RU21M', 'place' => 1, 'points' => 25],
            ['category' => 'RU21M', 'place' => 15, 'points' => 1],
        ];
        $result = \pl_points_apply_cutoff($rows, 15, ['RU21M' => 16]);

        $this->assertSame(1, $result[1]['points']);
    }

    public function testCutoffZeroesEveryRowTiedAtWorstPlace(): void
    {
        $rows = [
            ['category' => 'RU21M', 'place' => 1, 'points' => 25],
            ['category' => 'RU21M', 'place' => 12, 'points' => 5],
            ['category' => 'RU21M', 'place' => 12, 'points' => 5],
        ];
        $result = \pl_points_apply_cutoff($rows, 15, ['RU21M' => 12]);

        $this->assertSame(0, $result[1]['points']);
        $this->assertSame(0, $result[2]['points']);
    }

    public function testCutoffZeroesTheRealLastPlaceNotADsqSentinel(): void
    {
        // A DSQ row (place >= 29999) must not be picked as "worst place" — the
        // real last-place finisher (12) is the one the cutoff should zero.
        $rows = [
            ['category' => 'RU21M', 'place' => 1, 'points' => 25],
            ['category' => 'RU21M', 'place' => 12, 'points' => 5],
            ['category' => 'RU21M', 'place' => 29999, 'points' => 0],
        ];
        $result = \pl_points_apply_cutoff($rows, 15, ['RU21M' => 12]);

        $this->assertSame(0, $result[1]['points']); // real last place, zeroed
        $this->assertSame(0, $result[2]['points']); // DSQ, already 0
    }

    public function testCutoffIsPerCategory(): void
    {
        $rows = [
            ['category' => 'RU21M', 'place' => 12, 'points' => 5],
            ['category' => 'RU21W', 'place' => 12, 'points' => 5],
        ];
        // Only RU21M is short of starters.
        $result = \pl_points_apply_cutoff($rows, 15, ['RU21M' => 12, 'RU21W' => 16]);

        $this->assertSame(0, $result[0]['points']);
        $this->assertSame(5, $result[1]['points']);
    }

    // --- pl_points_split_team --------------------------------------------

    public function testSplitTeamOfThree(): void
    {
        $result = \pl_points_split_team(9, [101, 102, 103], false);

        $this->assertEqualsWithDelta(['101' => 3, '102' => 3, '103' => 3], $result['members'], 0.0001);
        $this->assertFalse($result['warning']);
    }

    public function testSplitMixedTeamHalvesBetweenTwoMembers(): void
    {
        $result = \pl_points_split_team(25, [201, 202], false);

        $this->assertEqualsWithDelta(12.5, $result['members'][201], 0.0001);
        $this->assertEqualsWithDelta(12.5, $result['members'][202], 0.0001);
    }

    public function testThreeOfFourDropsWorstQualifier(): void
    {
        $parentRanks = [1 => 5, 2 => 3, 3 => 8, 4 => 2];
        $result = \pl_points_split_team(9, [1, 2, 3, 4], true, $parentRanks);

        $this->assertSame(0, $result['members'][3]); // worst rank (8) dropped
        $this->assertEqualsWithDelta(3, $result['members'][1], 0.0001);
        $this->assertEqualsWithDelta(3, $result['members'][2], 0.0001);
        $this->assertEqualsWithDelta(3, $result['members'][4], 0.0001);
    }

    public function testThreeOfFourTieBrokenByHigherEntryId(): void
    {
        $parentRanks = [10 => 5, 20 => 5, 30 => 1, 40 => 2]; // 10 and 20 tied worst
        $result = \pl_points_split_team(9, [10, 20, 30, 40], true, $parentRanks);

        $this->assertSame(0, $result['members'][20]); // higher entry id dropped
        $this->assertArrayNotHasKey('warning_dropped', $result);
        $this->assertNotSame(0, $result['members'][10]);
    }

    public function testThreeOfFourDropsMemberWithNoParentRankEntry(): void
    {
        // Member 3 has no row in $parentRanks at all (e.g. never shot the parent
        // individual event) — must be treated as the worst qualifier, not the best.
        $parentRanks = [1 => 5, 2 => 3, 4 => 2];
        $result = \pl_points_split_team(9, [1, 2, 3, 4], true, $parentRanks);

        $this->assertSame(0, $result['members'][3]);
        $this->assertNotSame(0, $result['members'][1]); // rank 5, the worst *present* rank, must survive
    }

    public function testThreeOfFourDropsMemberWithZeroParentRank(): void
    {
        // Member 3 has a row but rank 0 ("no result"), which must also count as worst.
        $parentRanks = [1 => 5, 2 => 3, 3 => 0, 4 => 2];
        $result = \pl_points_split_team(9, [1, 2, 3, 4], true, $parentRanks);

        $this->assertSame(0, $result['members'][3]);
        $this->assertNotSame(0, $result['members'][1]);
    }

    public function testThreeOfFourDoesNotApplyToRosterOfThree(): void
    {
        $result = \pl_points_split_team(9, [1, 2, 3], true, [1 => 5, 2 => 3, 3 => 1]);

        $this->assertCount(3, $result['members']);
        foreach ($result['members'] as $share) {
            $this->assertEqualsWithDelta(3, $share, 0.0001);
        }
    }

    public function testEmptyRosterCreditsNoMembersAndWarns(): void
    {
        $result = \pl_points_split_team(9, [], true);

        $this->assertSame([], $result['members']);
        $this->assertTrue($result['warning']);
    }

    // --- pl_points_combine -------------------------------------------------

    public function testCombineDropsLowestValueAboveCap(): void
    {
        $result = \pl_points_combine(['tea' => 11, 'ind' => 7, 'mix' => 9.5], 2);

        $this->assertEqualsWithDelta(20.5, $result['total'], 0.0001);
        $this->assertArrayHasKey('ind', $result['dropped']);
        $this->assertArrayNotHasKey('ind', $result['kept']);
    }

    public function testCombineCapZeroIsUnlimited(): void
    {
        $result = \pl_points_combine(['ind' => 9, 'tea' => 3], 0);

        $this->assertSame(12, $result['total']);
        $this->assertCount(2, $result['kept']);
        $this->assertSame([], $result['dropped']);
    }

    public function testCombineFewerResultsThanCapKeepsAll(): void
    {
        $result = \pl_points_combine(['ind' => 5], 2);

        $this->assertSame(5, $result['total']);
        $this->assertSame([], $result['dropped']);
    }

    public function testCombineExcludesNonPositiveValues(): void
    {
        $result = \pl_points_combine(['ind' => 0, 'tea' => 4], 2);

        $this->assertArrayNotHasKey('ind', $result['kept']);
        $this->assertSame(4, $result['total']);
    }

    // --- pl_points_rank ------------------------------------------------------

    public function testRankOrdersByPlaceWithinSamePoints(): void
    {
        $rows = [
            ['id' => 'a', 'points' => 5, 'place' => 12],
            ['id' => 'b', 'points' => 5, 'place' => 9],
        ];
        $ranked = \pl_points_rank($rows);

        $this->assertSame('b', $ranked[0]['id']);
        $this->assertSame('a', $ranked[1]['id']);
    }

    public function testRankSharesRankOnEqualPoints(): void
    {
        $rows = [
            ['id' => 'a', 'points' => 20, 'place' => 1],
            ['id' => 'b', 'points' => 20, 'place' => 2],
            ['id' => 'c', 'points' => 10, 'place' => 3],
        ];
        $ranked = \pl_points_rank($rows);

        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame(1, $ranked[1]['rank']);
        $this->assertSame(3, $ranked[2]['rank']); // next rank skips the tied slot
    }

    // --- pl_points_compute_club_totals: exact arithmetic & cap interplay ----

    public function testClubTotalIsExactWhenNothingDropped(): void
    {
        $share = 22 / 3;
        $memberRow = fn ($enId) => ['enId' => $enId, 'name' => 'X', 'code' => 'X', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => $share, 'place' => 1];
        $classified = [
            'tea' => [
                'subject' => 'TEAM',
                'athlete_rows' => [$memberRow(1), $memberRow(2), $memberRow(3)],
                'teams' => [[
                    'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł',
                    'points' => 22, 'place' => 1,
                    'members' => [1 => $share, 2 => $share, 3 => $share],
                    'roster_empty' => false,
                ]],
            ],
        ];
        $athleteCombine = \pl_points_combine_athletes($classified, ['tea'], 0); // cap 0 = unlimited, nothing dropped

        $totals = \pl_points_compute_club_totals($classified, ['tea'], $athleteCombine);

        $this->assertCount(1, $totals);
        $this->assertSame(22.0, $totals[0]['total']); // exact, not 21.999999999999996
    }

    public function testClubTotalSubtractsExactlyTheCapDroppedShare(): void
    {
        $classified = [
            'ind' => [
                'subject' => 'IND',
                'athlete_rows' => [['enId' => 1, 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 6, 'place' => 3]],
            ],
            'tea' => [
                'subject' => 'TEAM',
                'athlete_rows' => [],
                'teams' => [[
                    'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł',
                    'points' => 9, 'place' => 1,
                    'members' => [1 => 9],
                    'roster_empty' => false,
                ]],
            ],
        ];
        // Athlete 1 earned 6 (ind) and 9 (mix, not in this classified fixture) and 9 (tea);
        // cap of 2 keeps mix+tea, drops ind — 'ind' is absent from kept.
        $athleteCombine = [1 => ['kept' => ['tea' => 9, 'mix' => 9], 'dropped' => ['ind' => 6]]];

        $totals = \pl_points_compute_club_totals($classified, ['ind', 'tea'], $athleteCombine);

        // ind dropped entirely (0 contributed); tea kept in full (9, single member, no division loss).
        $this->assertSame(9, $totals[0]['total']);
    }

    public function testMemberMissingFromAthleteCombineIsNotTreatedAsCapDropped(): void
    {
        // Roster member 3 has a positive share but no attribution data at all
        // (e.g. a stale roster row with no matching Entries record) — the club
        // must still get the team's full value, not the value minus their share.
        $classified = [
            'tea' => [
                'subject' => 'TEAM',
                'athlete_rows' => [],
                'teams' => [[
                    'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł',
                    'points' => 9, 'place' => 1,
                    'members' => [1 => 3, 2 => 3, 3 => 3],
                    'roster_empty' => false,
                ]],
            ],
        ];
        // Only members 1 and 2 have attribution data; 3 is absent entirely.
        $athleteCombine = [
            1 => ['kept' => ['tea' => 3]],
            2 => ['kept' => ['tea' => 3]],
        ];

        $totals = \pl_points_compute_club_totals($classified, ['tea'], $athleteCombine);

        $this->assertSame(9, $totals[0]['total']);
    }

    public function testEmptyRosterStillCreditsFullTeamValueToClub(): void
    {
        $classified = [
            'tea' => [
                'subject' => 'TEAM',
                'athlete_rows' => [],
                'teams' => [[
                    'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł',
                    'points' => 9, 'place' => 1, 'members' => [], 'roster_empty' => true,
                ]],
            ],
        ];

        $totals = \pl_points_compute_club_totals($classified, ['tea'], []);

        $this->assertSame(9, $totals[0]['total']);
    }

    // --- pl_points_build_reports: SEPARATE never feeds COMBINED --------------

    public function testSeparateClassificationNeverContributesToCombinedTotal(): void
    {
        $preset = [
            'reports' => [
                ['kind' => 'SEPARATE', 'classification' => 'mix', 'label' => 'Mikst'],
                ['kind' => 'COMBINED', 'classifications' => ['ind'], 'cap' => 0, 'label' => 'Indywidualnie'],
            ],
            'min_participation' => null,
        ];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 9, 'place' => 1],
            ]],
            'mix' => ['subject' => 'MIXED', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 100, 'place' => 1],
            ], 'teams' => [
                ['category' => 'RX', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 100, 'place' => 1, 'members' => [1 => 100], 'roster_empty' => false],
            ]],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => ['RX' => ['label' => 'RX', 'order' => [0, 0]]]];

        $result = \pl_points_build_reports($preset, $classified, $categories);

        $combinedReport = current(array_filter($result['reports'], fn ($r) => $r['kind'] === 'COMBINED'));
        $athleteRow = $combinedReport['sections'][0]['rows'][0];
        $this->assertSame(9, $athleteRow['points']); // not 109 — mix never enters the combine
    }

    public function testCombinedReportShowsCutoffZeroedValueCrossedOut(): void
    {
        // Individual classification zeroed this athlete's place by cutoff
        // (points=0), but the bracket would have awarded 5 (raw_points=5).
        // Team classification still credits them normally.
        $preset = [
            'reports' => [
                ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea'], 'cap' => 0, 'label' => 'Indywidualnie'],
            ],
            'min_participation' => null,
        ];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 0, 'raw_points' => 5, 'place' => 6],
            ]],
            'tea' => ['subject' => 'TEAM', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 9, 'raw_points' => 9, 'place' => 1],
            ], 'teams' => []],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => []];

        $result = \pl_points_build_reports($preset, $classified, $categories);

        $combinedReport = current(array_filter($result['reports'], fn ($r) => $r['kind'] === 'COMBINED'));
        $row = $combinedReport['sections'][0]['rows'][0];
        $this->assertSame(9, $row['points']); // only the team share counts
        $this->assertSame(5, $row['columns']['ind']['value']); // but the raw bracket value still shows
        $this->assertTrue($row['columns']['ind']['dropped']);
        $this->assertSame(6, $row['columns']['ind']['place']); // and their place in that classification
        $this->assertFalse($row['columns']['tea']['dropped']);
        $this->assertSame(1, $row['columns']['tea']['place']);
    }

    public function testCombinedReportShowsCapDroppedValueCrossedOut(): void
    {
        $preset = [
            'reports' => [
                ['kind' => 'COMBINED', 'classifications' => ['ind', 'tea', 'mix'], 'cap' => 2, 'label' => 'Indywidualnie'],
            ],
            'min_participation' => null,
        ];
        $row = fn ($points) => ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => $points, 'raw_points' => $points, 'place' => 1];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [$row(6)]],
            'tea' => ['subject' => 'TEAM', 'athlete_rows' => [$row(9)], 'teams' => []],
            'mix' => ['subject' => 'MIXED', 'athlete_rows' => [$row(8)], 'teams' => []],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => []];

        $result = \pl_points_build_reports($preset, $classified, $categories);

        $combinedReport = current(array_filter($result['reports'], fn ($r) => $r['kind'] === 'COMBINED'));
        $athleteRow = $combinedReport['sections'][0]['rows'][0];
        $this->assertSame(17, $athleteRow['points']); // tea(9) + mix(8), ind dropped by the cap
        $this->assertSame(6, $athleteRow['columns']['ind']['value']);
        $this->assertTrue($athleteRow['columns']['ind']['dropped']);
        $this->assertFalse($athleteRow['columns']['tea']['dropped']);
        $this->assertFalse($athleteRow['columns']['mix']['dropped']);
    }

    public function testEmptyReportIsOmitted(): void
    {
        $preset = [
            'reports' => [['kind' => 'SEPARATE', 'classification' => 'mix', 'label' => 'Mikst']],
            'min_participation' => null,
        ];
        $classified = ['mix' => ['subject' => 'MIXED', 'athlete_rows' => [], 'teams' => []]];
        $categories = ['individual' => [], 'team' => []];

        $result = \pl_points_build_reports($preset, $classified, $categories);

        $this->assertSame([], $result['reports']);
    }

    public function testMinParticipationWarningWhenThresholdUnmet(): void
    {
        $preset = [
            'reports' => [
                ['kind' => 'CLUB', 'label' => 'Kluby'],
                ['kind' => 'VOIVODESHIP', 'label' => 'Województwa'],
            ],
            'min_participation' => ['clubs' => 3, 'voivodeships' => 2],
        ];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 9, 'place' => 1],
                ['enId' => 2, 'name' => 'B', 'code' => 'B1', 'category' => 'RM', 'club_id' => 2, 'club_code' => 'SOK', 'club_name' => 'Sokół', 'points' => 7, 'place' => 2],
            ]],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => []];
        $voivodeshipMap = ['ORL' => 'małopolskie', 'SOK' => 'małopolskie'];

        $result = \pl_points_build_reports($preset, $classified, $categories, $voivodeshipMap);

        $this->assertNotEmpty($result['warnings']);
    }

    // --- report display order is independent of internal build order ---------

    public function testVoivodeshipDeclaredBeforeClubStillAggregatesCorrectly(): void
    {
        $preset = [
            'reports' => [
                ['kind' => 'VOIVODESHIP', 'label' => 'Województwa'],
                ['kind' => 'CLUB', 'label' => 'Kluby'],
            ],
            'min_participation' => null,
        ];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'A', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 9, 'place' => 1],
            ]],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => []];
        $voivodeshipMap = ['ORL' => 'Małopolskie'];

        $result = \pl_points_build_reports($preset, $classified, $categories, $voivodeshipMap);

        // VOIVODESHIP renders first (as declared) but must still see CLUB's totals.
        $this->assertSame('VOIVODESHIP', $result['reports'][0]['kind']);
        $this->assertSame(9, $result['reports'][0]['rows'][0]['points']);
        $this->assertSame('CLUB', $result['reports'][1]['kind']);
    }

    // --- a cutoff-zeroed / fully-capped-out athlete still gets a row ---------

    public function testAthleteZeroedEverywhereStillShownWithZeroTotal(): void
    {
        // Scored (raw_points=5) but cutoff zeroed it and there is no other
        // classification to fall back on — must still appear (Suma 0), unlike
        // an athlete who never scored anywhere at all.
        $preset = [
            'reports' => [
                ['kind' => 'COMBINED', 'classifications' => ['ind'], 'cap' => 0, 'label' => 'Indywidualnie'],
            ],
            'min_participation' => null,
        ];
        $classified = [
            'ind' => ['subject' => 'IND', 'athlete_rows' => [
                ['enId' => 1, 'name' => 'Cutoff Zeroed', 'code' => 'A1', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 0, 'raw_points' => 5, 'place' => 6],
                ['enId' => 2, 'name' => 'Never Scored', 'code' => 'A2', 'category' => 'RM', 'club_id' => 1, 'club_code' => 'ORL', 'club_name' => 'Orzeł', 'points' => 0, 'raw_points' => 0, 'place' => 40],
            ]],
        ];
        $categories = ['individual' => ['RM' => ['label' => 'RM', 'order' => [0, 0]]], 'team' => []];

        $result = \pl_points_build_reports($preset, $classified, $categories);

        $combinedReport = current(array_filter($result['reports'], fn ($r) => $r['kind'] === 'COMBINED'));
        $names = array_column($combinedReport['sections'][0]['rows'], 'name');
        $this->assertContains('Cutoff Zeroed', $names);
        $this->assertNotContains('Never Scored', $names);
    }
}
