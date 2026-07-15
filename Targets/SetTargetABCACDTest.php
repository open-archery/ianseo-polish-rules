<?php

namespace PL\Tests\Targets;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/Fun_SetTargetABCACD.php';

final class SetTargetABCACDTest extends \PlTestCase
{
    // --- pl_abc_acd_build_slots ---------------------------------------------

    public function testOddBossGetsAbcSlots(): void
    {
        $this->assertSame(['1A', '1B', '1C'], \pl_abc_acd_build_slots(1, 1));
    }

    public function testEvenBossGetsAcdSlots(): void
    {
        $this->assertSame(['2A', '2C', '2D'], \pl_abc_acd_build_slots(2, 2));
    }

    public function testRangeOneToThree(): void
    {
        $this->assertSame(
            ['1A', '1B', '1C', '2A', '2C', '2D', '3A', '3B', '3C'],
            \pl_abc_acd_build_slots(1, 3)
        );
    }

    public function testParityIsFieldGlobalNotRangeRelative(): void
    {
        // Boss 6 is even -> ACD regardless of range starting at 6, not 1.
        $this->assertSame(
            ['6A', '6C', '6D', '7A', '7B', '7C'],
            \pl_abc_acd_build_slots(6, 7)
        );
    }

    // --- pl_abc_acd_assign ---------------------------------------------------

    private function athlete(string $id, string $club): array
    {
        return ['id' => $id, 'name' => "Athlete $id", 'club' => $club, 'clubName' => $club];
    }

    public function testEmptyClubsProduceNoAssignments(): void
    {
        [$assignments, $unassigned] = \pl_abc_acd_assign([], \pl_abc_acd_build_slots(1, 3));

        $this->assertSame([], $assignments);
        $this->assertSame([], $unassigned);
    }

    public function testSingleClubLandsOnConsecutiveBossesColumnA(): void
    {
        $clubs = ['AZS' => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS'), $this->athlete('3', 'AZS')]];
        $slots = \pl_abc_acd_build_slots(1, 3);

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('1', $assignments['1A']['id']);
        $this->assertSame('2', $assignments['2A']['id']);
        $this->assertSame('3', $assignments['3A']['id']);
        $this->assertSame([], $unassigned);
    }

    public function testTwoClubsOccupyDifferentColumnsSameBosses(): void
    {
        $clubs = [
            'AZS'  => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS'), $this->athlete('3', 'AZS')],
            'LKS'  => [$this->athlete('4', 'LKS'), $this->athlete('5', 'LKS'), $this->athlete('6', 'LKS')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 3);

        [$assignments, ] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('AZS', $assignments['1A']['club']);
        $this->assertSame('AZS', $assignments['2A']['club']);
        $this->assertSame('AZS', $assignments['3A']['club']);
        $this->assertSame('LKS', $assignments['1C']['club']);
        $this->assertSame('LKS', $assignments['2C']['club']);
        $this->assertSame('LKS', $assignments['3C']['club']);

        // No boss has two athletes from the same club.
        foreach ([1, 2, 3] as $boss) {
            $clubsOnBoss = [];
            foreach ($assignments as $slot => $a) {
                if ((int)$slot === $boss) {
                    $clubsOnBoss[] = $a['club'];
                }
            }
            $this->assertSame(count($clubsOnBoss), count(array_unique($clubsOnBoss)));
        }
    }

    public function testThirdClubFillsRemainingAColumnAfterFirstClubExhausted(): void
    {
        // Boss range 1-5: A column has 5 slots. Club 0 (2 athletes) takes 1A,2A.
        // Club 2 (small) should continue in remaining A slots (3A,4A,5A) before C/B/D.
        $clubs = [
            'BIG'   => [$this->athlete('1', 'BIG'), $this->athlete('2', 'BIG')],
            'MID'   => [$this->athlete('3', 'MID'), $this->athlete('4', 'MID')],
            'SMALL' => [$this->athlete('5', 'SMALL')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 5);

        [$assignments, ] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('SMALL', $assignments['3A']['club']);
    }

    public function testColumnPriorityFallsToBThenDWhenAAndCFull(): void
    {
        // 1 boss only: A and C have exactly 1 slot each, filled by club0/club1.
        // Club 2 (1 athlete) must go to B (odd boss has B, not D).
        $clubs = [
            'C0' => [$this->athlete('1', 'C0')],
            'C1' => [$this->athlete('2', 'C1')],
            'C2' => [$this->athlete('3', 'C2')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 1); // 1A, 1B, 1C

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('C2', $assignments['1B']['club']);
        $this->assertSame([], $unassigned);
    }

    public function testColumnPriorityFallsToDOnEvenBossWhenAAndCFull(): void
    {
        $clubs = [
            'C0' => [$this->athlete('1', 'C0')],
            'C1' => [$this->athlete('2', 'C1')],
            'C2' => [$this->athlete('3', 'C2')],
        ];
        $slots = \pl_abc_acd_build_slots(2, 2); // 2A, 2C, 2D

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('C2', $assignments['2D']['club']);
        $this->assertSame([], $unassigned);
    }

    public function testClubLargerThanAnyRemainingColumnFallsBackSlotBySlot(): void
    {
        // 2 bosses (1-2): A has 2 slots (club0 takes both), C has 2 slots (club1 takes both).
        // Remaining B/D each have 1 slot. Club2 has 2 athletes -> doesn't fit B or D alone,
        // falls back slot-by-slot across remaining columns (B then D here since A/C full).
        $clubs = [
            'C0' => [$this->athlete('1', 'C0'), $this->athlete('2', 'C0')],
            'C1' => [$this->athlete('3', 'C1'), $this->athlete('4', 'C1')],
            'C2' => [$this->athlete('5', 'C2'), $this->athlete('6', 'C2')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 2); // 1A,1B,1C, 2A,2C,2D

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertSame('C2', $assignments['1B']['club']);
        $this->assertSame('C2', $assignments['2D']['club']);
        $this->assertSame([], $unassigned);
    }

    public function testOverflowAthletesReportedAsUnassigned(): void
    {
        // 1 boss (3 slots: A,B,C), but 4 athletes in a single club.
        $clubs = ['AZS' => [
            $this->athlete('1', 'AZS'),
            $this->athlete('2', 'AZS'),
            $this->athlete('3', 'AZS'),
            $this->athlete('4', 'AZS'),
        ]];
        $slots = \pl_abc_acd_build_slots(1, 1);

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots);

        $this->assertCount(1, $assignments);
        $this->assertCount(3, $unassigned);
        $this->assertSame('2', $unassigned[0]['id']);
    }

    // --- pl_abc_acd_assign with session wave tally -----------------------------

    public function testTwoClubSwapWhenSecondClubNeedsWaveOneMore(): void
    {
        $clubs = [
            'AZS' => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS')],
            'LKS' => [$this->athlete('3', 'LKS')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 2);
        // LKS shot wave2 (C/D) elsewhere in this session; AZS has no history.
        $tally = ['LKS' => ['wave1' => 0, 'wave2' => 2]];

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots, $tally);

        $this->assertSame('LKS', $assignments['1A']['club']);
        $this->assertSame('AZS', $assignments['1C']['club']);
        $this->assertSame('AZS', $assignments['2C']['club']);
        $this->assertSame([], $unassigned);
    }

    public function testEqualWaveTalliesKeepRankOrderDefault(): void
    {
        $clubs = [
            'AZS' => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS')],
            'LKS' => [$this->athlete('3', 'LKS')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 2);
        // Balanced histories (needA = 0 for both) must not swap the default.
        $tally = [
            'AZS' => ['wave1' => 1, 'wave2' => 1],
            'LKS' => ['wave1' => 2, 'wave2' => 2],
        ];

        [$assignments, ] = \pl_abc_acd_assign($clubs, $slots, $tally);

        $this->assertSame('AZS', $assignments['1A']['club']);
        $this->assertSame('AZS', $assignments['2A']['club']);
        $this->assertSame('LKS', $assignments['1C']['club']);
    }

    public function testClubsMissingFromTallyKeepRankOrderDefault(): void
    {
        $clubs = [
            'AZS' => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS')],
            'LKS' => [$this->athlete('3', 'LKS')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 2);
        // Tally only mentions an unrelated club — both present clubs default to 0.
        $tally = ['XYZ' => ['wave1' => 5, 'wave2' => 0]];

        [$assignments, ] = \pl_abc_acd_assign($clubs, $slots, $tally);

        $this->assertSame('AZS', $assignments['1A']['club']);
        $this->assertSame('LKS', $assignments['1C']['club']);
    }

    public function testSingleClubWithWaveOneHeavyHistoryGetsColumnC(): void
    {
        $clubs = ['AZS' => [$this->athlete('1', 'AZS'), $this->athlete('2', 'AZS')]];
        $slots = \pl_abc_acd_build_slots(1, 2);
        $tally = ['AZS' => ['wave1' => 2, 'wave2' => 0]];

        [$assignments, $unassigned] = \pl_abc_acd_assign($clubs, $slots, $tally);

        $this->assertSame('1', $assignments['1C']['id']);
        $this->assertSame('2', $assignments['2C']['id']);
        $this->assertArrayNotHasKey('1A', $assignments);
        $this->assertSame([], $unassigned);
    }

    public function testOverflowClubWithWaveOneHeavyHistorySearchesColumnCFirst(): void
    {
        // Same setup as testThirdClubFillsRemainingAColumnAfterFirstClubExhausted,
        // but SMALL is wave1-heavy this session, so it takes remaining C (3C)
        // instead of remaining A (3A).
        $clubs = [
            'BIG'   => [$this->athlete('1', 'BIG'), $this->athlete('2', 'BIG')],
            'MID'   => [$this->athlete('3', 'MID'), $this->athlete('4', 'MID')],
            'SMALL' => [$this->athlete('5', 'SMALL')],
        ];
        $slots = \pl_abc_acd_build_slots(1, 5);
        $tally = ['SMALL' => ['wave1' => 3, 'wave2' => 0]];

        [$assignments, ] = \pl_abc_acd_assign($clubs, $slots, $tally);

        $this->assertSame('SMALL', $assignments['3C']['club']);
        $this->assertArrayNotHasKey('3A', $assignments);
    }

    // --- pl_abc_acd_session_wave_tally -----------------------------------------

    public function testSessionWaveTallySplitsWavesByLetterPerClub(): void
    {
        \FakeDb::on('/SELECT CoCode EnCountry, QuLetter/', [
            ['EnCountry' => 'AZS', 'QuLetter' => 'A'],
            ['EnCountry' => 'AZS', 'QuLetter' => 'B'],
            ['EnCountry' => 'AZS', 'QuLetter' => 'C'],
            ['EnCountry' => 'LKS', 'QuLetter' => 'D'],
        ]);

        $tally = \pl_abc_acd_session_wave_tally(1, 1, 'RMO');

        $this->assertSame(['wave1' => 2, 'wave2' => 1], $tally['AZS']);
        $this->assertSame(['wave1' => 0, 'wave2' => 1], $tally['LKS']);
    }

    public function testSessionWaveTallyExcludesCurrentClassRows(): void
    {
        \pl_abc_acd_session_wave_tally(7, 2, 'RMO');

        $this->assertCount(1, \FakeDb::executed("/CONCAT\\(TRIM\\(EnDivision\\),TRIM\\(EnClass\\)\\) NOT LIKE 'RMO'/"));
    }

    public function testSessionWaveTallyFiltersByTournamentAndSession(): void
    {
        \pl_abc_acd_session_wave_tally(7, 2, 'RMO');

        $this->assertCount(1, \FakeDb::executed("/EnTournament='7'/"));
        $this->assertCount(1, \FakeDb::executed("/QuSession='2'/"));
    }

    public function testSessionWaveTallyExcludesUnassignedRows(): void
    {
        \pl_abc_acd_session_wave_tally(7, 2, 'RMO');

        $this->assertCount(1, \FakeDb::executed('/QuTarget!=0/'));
    }

    // --- pl_abc_acd_load_athletes --------------------------------------------

    public function testLoadAthletesGroupsByClubSortedLargestFirst(): void
    {
        \FakeDb::on('/FROM Entries/', [
            ['EnId' => 1, 'EnFirstName' => 'Jan', 'EnName' => 'Kowalski', 'EnDivision' => 'R', 'EnClass' => 'MO', 'EnCountry' => 'SMALL', 'EnClubName' => 'Small Club'],
            ['EnId' => 2, 'EnFirstName' => 'Adam', 'EnName' => 'Nowak', 'EnDivision' => 'R', 'EnClass' => 'MO', 'EnCountry' => 'BIG', 'EnClubName' => 'Big Club'],
            ['EnId' => 3, 'EnFirstName' => 'Piotr', 'EnName' => 'Zielinski', 'EnDivision' => 'R', 'EnClass' => 'MO', 'EnCountry' => 'BIG', 'EnClubName' => 'Big Club'],
        ]);

        $clubs = \pl_abc_acd_load_athletes(1, 1, 'RMO');

        $codes = array_keys($clubs);
        $this->assertSame('BIG', $codes[0]);
        $this->assertSame('SMALL', $codes[1]);
        $this->assertCount(2, $clubs['BIG']);
        $this->assertCount(1, $clubs['SMALL']);
        $this->assertSame('Nowak Adam', $clubs['BIG'][0]['name']);
        $this->assertSame('Big Club', $clubs['BIG'][0]['clubName']);
    }

    public function testLoadAthletesQueryFiltersByTournamentSessionAndEvent(): void
    {
        \pl_abc_acd_load_athletes(7, 2, 'RMO');

        $this->assertCount(1, \FakeDb::executed("/EnTournament='7'/"));
        $this->assertCount(1, \FakeDb::executed("/QuSession='2'/"));
        $this->assertCount(1, \FakeDb::executed("/CONCAT\\(TRIM\\(EnDivision\\),TRIM\\(EnClass\\)\\) LIKE 'RMO'/"));
    }

    // --- pl_abc_acd_erase -----------------------------------------------------

    public function testEraseClearsTargetLetterAndBacknoScopedToClassAndSession(): void
    {
        \pl_abc_acd_erase(7, 2, 'RMO');

        $this->assertCount(1, \FakeDb::executed("/WHERE QuTarget!=0 AND EnTournament='7' AND QuSession='2'/"));
        $this->assertCount(1, \FakeDb::executed("/SET QuTarget=0, QuLetter='', QuBacknoPrinted=0/"));
        // Both erase statements share the class/session filter.
        $this->assertCount(2, \FakeDb::executed("/CONCAT\\(TRIM\\(EnDivision\\),TRIM\\(EnClass\\)\\) LIKE 'RMO'/"));
    }

    // --- pl_abc_acd_save --------------------------------------------------------

    public function testSaveErasesBeforeWritingNewAssignments(): void
    {
        \pl_abc_acd_save(7, 2, 'RMO', ['1A' => $this->athlete('42', 'AZS')]);

        $eraseIdx = null;
        $writeIdx = null;
        foreach (\FakeDb::$queries as $i => $sql) {
            if ($eraseIdx === null && preg_match('/SET QuTarget=0/', $sql)) {
                $eraseIdx = $i;
            }
            if ($writeIdx === null && preg_match('/QuTarget=1,\s*QuLetter=.A./', $sql)) {
                $writeIdx = $i;
            }
        }

        $this->assertNotNull($eraseIdx);
        $this->assertNotNull($writeIdx);
        $this->assertLessThan($writeIdx, $eraseIdx);
    }

    public function testSaveWritesTargetLetterAndResetsBackno(): void
    {
        \pl_abc_acd_save(7, 2, 'RMO', ['3C' => $this->athlete('42', 'AZS')]);

        $this->assertCount(1, \FakeDb::executed("/QuTarget=3,\\s*QuLetter='C',\\s*QuBacknoPrinted=0\\s*WHERE QuId=42/"));
        $this->assertCount(1, \FakeDb::executed('/EnId=42/'));
    }

    public function testSaveParsesMultiDigitBossNumber(): void
    {
        \pl_abc_acd_save(7, 2, 'RMO', ['12C' => $this->athlete('99', 'AZS')]);

        $this->assertCount(1, \FakeDb::executed("/QuTarget=12,\\s*QuLetter='C'/"));
    }

    public function testSaveReturnsCountOfAssignments(): void
    {
        $saved = \pl_abc_acd_save(7, 2, 'RMO', [
            '1A' => $this->athlete('1', 'AZS'),
            '1C' => $this->athlete('2', 'LKS'),
        ]);

        $this->assertSame(2, $saved);
    }
}
