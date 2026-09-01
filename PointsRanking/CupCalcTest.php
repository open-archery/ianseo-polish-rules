<?php

require_once __DIR__ . '/CupCalc.php';

/**
 * Pure tests for the cup aggregation, the per-series tie-break chain and the
 * snapshot diff. No database stubbing needed.
 */
final class CupCalcTest extends PlTestCase
{
    /** One stored round row. */
    private function row($round, $category, $identity, $points, $place, $qual = 0, $classification = 'ind', $name = null, $club = 'Klub')
    {
        return [
            'round' => $round,
            'classification' => $classification,
            'category' => $category,
            'identity' => $identity,
            'name' => $name ?? ('Zawodnik ' . $identity),
            'club_name' => $club,
            'place' => $place,
            'points' => $points,
            'qual' => $qual,
        ];
    }

    /** Rank one category's rows straight from stored rows. */
    private function rank(array $rows, $classification, $category, array $barrages = [], $barrageAllowed = true)
    {
        $built = pl_cup_build_classifications($rows, $barrages, [], $barrageAllowed);
        foreach ($built[$classification]['sections'] as $section) {
            if ($section['category'] === $category) {
                return $section['rows'];
            }
        }
        return [];
    }

    // --- Aggregation -------------------------------------------------------

    public function testAggregatesPointsAcrossRounds()
    {
        $rows = pl_cup_aggregate([
            $this->row(1, 'RM', 'L1', 25, 1, 640),
            $this->row(2, 'RM', 'L1', 21, 2, 655),
            $this->row(4, 'RM', 'L1', 18, 3, 630),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(64, $rows[0]['total']);
        $this->assertSame([1 => 25, 2 => 21, 4 => 18], $rows[0]['rounds']);
        $this->assertSame(1, $rows[0]['best_place']);
        $this->assertSame(655, $rows[0]['best_qual']);
    }

    public function testMissingRoundLeavesAGapAndDoesNotReduceTheSum()
    {
        $rows = pl_cup_aggregate([
            $this->row(1, 'RM', 'L1', 25, 1),
            $this->row(2, 'RM', 'L1', 21, 2),
            $this->row(4, 'RM', 'L1', 18, 3),
        ]);

        $this->assertArrayNotHasKey(3, $rows[0]['rounds']);
        $this->assertSame(64, $rows[0]['total']);
    }

    public function testSingleRoundStarterIsStillClassified()
    {
        $ranked = $this->rank([$this->row(1, 'RM', 'L1', 13, 5)], 'ind', 'RM');

        $this->assertCount(1, $ranked);
        $this->assertSame(13, $ranked[0]['total']);
        $this->assertSame(1, $ranked[0]['rank']);
    }

    public function testDisplayDataComesFromTheMostRecentRound()
    {
        $rows = pl_cup_aggregate([
            $this->row(1, 'RM', 'L1', 25, 1, 0, 'ind', 'Stare Nazwisko', 'Stary Klub'),
            $this->row(2, 'RM', 'L1', 21, 2, 0, 'ind', 'Nowe Nazwisko', 'Nowy Klub'),
        ]);

        $this->assertSame('Nowe Nazwisko', $rows[0]['name']);
        $this->assertSame('Nowy Klub', $rows[0]['club_name']);
    }

    public function testMixedRowsAreIdentifiedByClubAcrossChangedPairs()
    {
        $rows = pl_cup_aggregate([
            $this->row(2, 'RMX', 'KLUB1', 25, 1, 1300, 'mix', 'Anna K. / Jan N.'),
            $this->row(4, 'RMX', 'KLUB1', 21, 2, 1280, 'mix', 'Ewa Z. / Jan N.'),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(46, $rows[0]['total']);
    }

    // --- Tie-break map -----------------------------------------------------

    public function testTiebreakRulePerSeries()
    {
        $this->assertSame(['steps' => ['place', 'qual'], 'terminator' => 'SHARED'], pl_cup_tiebreak_rule('ind', 'RU21M'));
        $this->assertSame(['steps' => ['place', 'qual'], 'terminator' => 'BARRAGE'], pl_cup_tiebreak_rule('ind', 'RU18W'));
        $this->assertSame(['steps' => ['qual'], 'terminator' => 'BARRAGE'], pl_cup_tiebreak_rule('ind', 'BM'));
        $this->assertSame(['steps' => [], 'terminator' => 'SHARED'], pl_cup_tiebreak_rule('ind', 'RM'));
        $this->assertSame(['steps' => [], 'terminator' => 'SHARED'], pl_cup_tiebreak_rule('ind', 'CU21W'));
        $this->assertSame(['steps' => [], 'terminator' => 'SHARED'], pl_cup_tiebreak_rule('mix', 'RMX'));
        $this->assertSame(['steps' => [], 'terminator' => 'SHARED'], pl_cup_tiebreak_rule('ind', 'RU15M'));
    }

    // --- Tie-breaking (spec scenarios) -------------------------------------

    public function testJuniorSeriesBestPlaceDecides()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU21M', 'A', 25, 3),
            $this->row(2, 'RU21M', 'A', 22, 2),
            $this->row(1, 'RU21M', 'B', 25, 3),
            $this->row(2, 'RU21M', 'B', 22, 3),
        ], 'ind', 'RU21M');

        $this->assertSame(['A', 'B'], array_column($ranked, 'identity'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
        $this->assertSame('', $ranked[0]['tie_mark']);
    }

    public function testJuniorSeriesQualificationScoreDecides()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU21M', 'A', 25, 2, 638),
            $this->row(1, 'RU21M', 'B', 25, 2, 645),
        ], 'ind', 'RU21M');

        $this->assertSame(['B', 'A'], array_column($ranked, 'identity'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
    }

    public function testBarebowSkipsThePlaceStep()
    {
        $ranked = $this->rank([
            $this->row(1, 'BM', 'A', 25, 2, 520),
            $this->row(1, 'BM', 'B', 25, 4, 533),
        ], 'ind', 'BM');

        $this->assertSame(['B', 'A'], array_column($ranked, 'identity'));
    }

    public function testSeniorTieStandsRegardlessOfPlaceAndScore()
    {
        $ranked = $this->rank([
            $this->row(1, 'RM', 'A', 25, 1, 660),
            $this->row(1, 'RM', 'B', 25, 8, 600),
        ], 'ind', 'RM');

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['SHARED', 'SHARED'], array_column($ranked, 'tie_mark'));
    }

    public function testCompoundTieStands()
    {
        $ranked = $this->rank([
            $this->row(1, 'CU21W', 'A', 21, 2, 690),
            $this->row(1, 'CU21W', 'B', 21, 4, 680),
        ], 'ind', 'CU21W');

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['SHARED', 'SHARED'], array_column($ranked, 'tie_mark'));
    }

    public function testMixedTieStands()
    {
        $ranked = $this->rank([
            $this->row(1, 'RMX', 'KLUB1', 25, 1, 1300, 'mix'),
            $this->row(1, 'RMX', 'KLUB2', 25, 2, 1290, 'mix'),
        ], 'mix', 'RMX');

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['SHARED', 'SHARED'], array_column($ranked, 'tie_mark'));
    }

    public function testBarrageDueWhenEveryStatedStepIsEqual()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU18W', 'A', 25, 1, 610),
            $this->row(1, 'RU18W', 'B', 25, 1, 610),
        ], 'ind', 'RU18W');

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['BARRAGE', 'BARRAGE'], array_column($ranked, 'tie_mark'));
        $this->assertSame([0, 0], array_column($ranked, 'tie_group'));
    }

    // --- Recorded baraż outcomes -------------------------------------------

    public function testRecordedOutcomeSeparatesTheTiedRows()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU18M', 'A', 25, 1, 610),
            $this->row(1, 'RU18M', 'B', 25, 1, 610),
        ], 'ind', 'RU18M', ['ind|RU18M|B' => 1, 'ind|RU18M|A' => 2]);

        $this->assertSame(['B', 'A'], array_column($ranked, 'identity'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
        $this->assertSame([true, true], array_column($ranked, 'barrage_resolved'));
        $this->assertSame(['', ''], array_column($ranked, 'tie_mark'));
    }

    public function testPartiallyRecordedGroupKeepsTheUnrecordedRowsTied()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU18M', 'A', 25, 1, 610),
            $this->row(1, 'RU18M', 'B', 25, 1, 610),
            $this->row(1, 'RU18M', 'C', 25, 1, 610),
        ], 'ind', 'RU18M', ['ind|RU18M|B' => 1]);

        $this->assertSame('B', $ranked[0]['identity']);
        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertTrue($ranked[0]['barrage_resolved']);
        $this->assertSame([2, 2], [$ranked[1]['rank'], $ranked[2]['rank']]);
        $this->assertSame(['BARRAGE', 'BARRAGE'], [$ranked[1]['tie_mark'], $ranked[2]['tie_mark']]);
    }

    public function testOutcomeIsIgnoredBeforeTheFinalRoundIsStored()
    {
        $ranked = $this->rank([
            $this->row(1, 'BW', 'A', 25, 1, 500),
            $this->row(1, 'BW', 'B', 25, 1, 500),
        ], 'ind', 'BW', ['ind|BW|B' => 1, 'ind|BW|A' => 2], false);

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['BARRAGE', 'BARRAGE'], array_column($ranked, 'tie_mark'));
    }

    public function testOutcomeIsIgnoredForASeriesWithoutABarrage()
    {
        $ranked = $this->rank([
            $this->row(1, 'RM', 'A', 25, 1, 660),
            $this->row(1, 'RM', 'B', 25, 1, 660),
        ], 'ind', 'RM', ['ind|RM|B' => 1, 'ind|RM|A' => 2]);

        $this->assertSame([1, 1], array_column($ranked, 'rank'));
        $this->assertSame(['SHARED', 'SHARED'], array_column($ranked, 'tie_mark'));
    }

    public function testOutcomeHasNoEffectOnceTheTieDisappears()
    {
        $ranked = $this->rank([
            $this->row(1, 'RU18M', 'A', 25, 1, 610),
            $this->row(1, 'RU18M', 'B', 21, 2, 610),
        ], 'ind', 'RU18M', ['ind|RU18M|B' => 1, 'ind|RU18M|A' => 2]);

        $this->assertSame(['A', 'B'], array_column($ranked, 'identity'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
        $this->assertSame([false, false], array_column($ranked, 'barrage_resolved'));
    }

    public function testOutcomeStillAppliesAfterAnUnrelatedRoundChanges()
    {
        $barrages = ['ind|RU18M|B' => 1, 'ind|RU18M|A' => 2];
        $rows = [
            $this->row(1, 'RU18M', 'A', 25, 1, 610),
            $this->row(1, 'RU18M', 'B', 25, 1, 610),
            // Round 3 re-imported: both rows gained the same amount, tie intact.
            $this->row(3, 'RU18M', 'A', 10, 9, 600),
            $this->row(3, 'RU18M', 'B', 10, 9, 600),
        ];

        $ranked = $this->rank($rows, 'ind', 'RU18M', $barrages);

        $this->assertSame(['B', 'A'], array_column($ranked, 'identity'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
    }

    // --- Snapshot diff -----------------------------------------------------

    public function testDiffIsZeroForIdenticalRows()
    {
        $rows = [$this->row(4, 'RM', 'L1', 25, 1, 640), $this->row(4, 'RM', 'L2', 21, 2, 630)];

        $this->assertSame(0, pl_cup_diff_snapshot($rows, $rows));
    }

    public function testDiffCountsChangedAddedAndRemovedRows()
    {
        $stored = [$this->row(4, 'RM', 'L1', 25, 1, 640), $this->row(4, 'RM', 'L2', 21, 2, 630)];
        $changed = [$this->row(4, 'RM', 'L1', 21, 2, 640), $this->row(4, 'RM', 'L2', 21, 2, 630)];
        $added = array_merge($stored, [$this->row(4, 'RM', 'L3', 18, 3, 620)]);
        $removed = [$stored[0]];

        $this->assertSame(1, pl_cup_diff_snapshot($changed, $stored));
        $this->assertSame(1, pl_cup_diff_snapshot($added, $stored));
        $this->assertSame(1, pl_cup_diff_snapshot($removed, $stored));
    }

    // --- Sectioning --------------------------------------------------------

    public function testSectionsUseTheCategoryMetadataLabelAndOrder()
    {
        $built = pl_cup_build_classifications(
            [$this->row(1, 'RM', 'A', 25, 1), $this->row(1, 'BM', 'B', 25, 1)],
            [],
            ['ind' => [
                'RM' => ['label' => 'Łuk klasyczny Senior', 'order' => [0, 1]],
                'BM' => ['label' => 'Barebow Senior', 'order' => [2, 1]],
            ], 'mix' => []],
            true
        );

        $this->assertSame(['Łuk klasyczny Senior', 'Barebow Senior'], array_column($built['ind']['sections'], 'label'));
        $this->assertSame([], $built['mix']['sections']);
    }
}
