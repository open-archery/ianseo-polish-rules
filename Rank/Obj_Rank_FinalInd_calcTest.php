<?php

/**
 * calcFromPhase() bronze-phase tests.
 *
 * This repo's own checkout (what CI clones) has no Common/ core tree at all
 * — it only exists on a developer's co-located local ianseo install — so the
 * real core parent (Common/Rank/Obj_Rank_FinalInd.php) can't be require_once'd
 * here under any path. A minimal stand-in gives calcFromPhase() the
 * $tournament property it reads, without pulling in the real DB-backed
 * constructor. namePhase() is shimmed in tests/bootstrap.php for the same
 * reason.
 */

if (!class_exists('Obj_Rank_FinalInd')) {
    abstract class Obj_Rank_FinalInd
    {
        protected $tournament;
        protected $opts;

        public function __construct($opts)
        {
            $this->opts = $opts;
            $this->tournament = $opts['tournament'];
        }
    }
}

require_once __DIR__ . '/Obj_Rank_FinalInd_calc.php';

final class Obj_Rank_FinalInd_calcTest extends PlTestCase
{
    private function callCalcFromPhase(Obj_Rank_FinalInd_calc $rank, $event, $realphase)
    {
        $method = new ReflectionMethod($rank, 'calcFromPhase');
        $method->setAccessible(true);
        return $method->invoke($rank, $event, $realphase);
    }

    /**
     * 3-entrant bracket: one semifinalist has a bye (no opponent, so no
     * semifinal is ever played on that side), the other semifinal produces
     * exactly one real loser. The bronze match therefore has no second
     * participant to pair against — it was never a real 0-0 tie between two
     * shooters, just a phase with a single occupant. That lone semifinal
     * loser must still get 3rd place.
     */
    public function testLoneSemifinalLoserGetsThirdWhenBronzeHasNoOpponentDueToBye()
    {
        FakeDb::on('/SELECT EvFinalFirstPhase FROM Events/', [
            ['EvFinalFirstPhase' => 2],
        ]);
        FakeDb::on('/f\.FinScore=0 AND f\.FinSetScore=0/', [
            ['AthId' => 5745, 'EvWinnerFinalRank' => 1, 'EvCodeParent' => ''],
        ]);

        $rank = new Obj_Rank_FinalInd_calc(['tournament' => 42]);
        $result = $this->callCalcFromPhase($rank, 'JML', 1);

        $this->assertTrue($result);
        $this->assertNotEmpty(FakeDb::executed('/UPDATE Individuals\s+SET\s+IndRankFinal=3,.*AND IndId=5745/s'));
    }

    /** Existing behaviour: two real semifinal losers still share 3rd. */
    public function testBothSemifinalLosersShareThirdWhenBronzeIs00Tie()
    {
        FakeDb::on('/SELECT EvFinalFirstPhase FROM Events/', [
            ['EvFinalFirstPhase' => 2],
        ]);
        FakeDb::on('/f\.FinScore=0 AND f\.FinSetScore=0/', [
            ['AthId' => 100, 'EvWinnerFinalRank' => 1, 'EvCodeParent' => ''],
            ['AthId' => 200, 'EvWinnerFinalRank' => 1, 'EvCodeParent' => ''],
        ]);

        $rank = new Obj_Rank_FinalInd_calc(['tournament' => 42]);
        $result = $this->callCalcFromPhase($rank, 'JML', 1);

        $this->assertTrue($result);
        $this->assertNotEmpty(FakeDb::executed('/UPDATE Individuals\s+SET\s+IndRankFinal=3,.*AND IndId=100/s'));
        $this->assertNotEmpty(FakeDb::executed('/UPDATE Individuals\s+SET\s+IndRankFinal=3,.*AND IndId=200/s'));
    }
}
