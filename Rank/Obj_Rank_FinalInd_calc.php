<?php
/**
 * Obj_Rank_FinalInd_calc — Poland (PL) override
 *
 * Identical to the core implementation except calcFromPhase(), which applies
 * PZŁucz §2.6.5 rules:
 *
 *  1. No-bronze-match detection: when the bronze match result is 0-0 (not shot),
 *     both semifinal losers are awarded shared 3rd place instead of 3rd/4th.
 *  2. Unique sequential positions for ALL phases >= 4 (quarterfinals and below),
 *     not just the quarterfinals as in the default engine.
 *  3. Secondary tiebreaker is qualification rank (IndRank ASC) instead of
 *     cumulative score.
 */
	class Obj_Rank_FinalInd_calc extends Obj_Rank_FinalInd
	{
	/**
	 * writeRow()
	 * Updates IndRankFinal in Individuals.
	 * Skips athletes with IrmTypeFinal >= 15 (DSQ/withdrawn — core convention).
	 */
		protected function writeRow($id, $event, $rank)
		{
			$date = date('Y-m-d H:i:s');
			$q = "
				UPDATE Individuals
				SET
					IndRankFinal={$rank},
					IndTimestampFinal='{$date}'
				WHERE
					IndTournament={$this->tournament}
					AND IndEvent='{$event}'
					AND IndId={$id}
					AND IndIrmTypeFinal<15
			";
			$r = safe_w_sql($q);
			return ($r !== false);
		}

	/*
	 * **************************************************************
	 * Micro algorithms called depending on the starting point
	 * **************************************************************
	 */

	/**
	 * calcFromAbs()
	 * Assigns IndRankFinal to athletes who stopped at qualification.
	 */
		protected function calcFromAbs($event)
		{
			$date = date('Y-m-d H:i:s');

			$q = "
				UPDATE Individuals
				INNER JOIN Events ON IndEvent=EvCode AND IndTournament=EvTournament
				LEFT JOIN (
					SELECT COUNT(*) AS sqyQualified,
					       RrPartEvent AS sqyEvent,
					       RrPartTournament AS sqyTournament
					FROM RoundRobinParticipants
					WHERE RrPartSourceLevel=0
					  AND RrPartTournament={$this->tournament}
					  AND RrPartEvent='{$event}'
					  AND RrPartTeam=0
					GROUP BY RrPartSourceLevel, RrPartTournament, RrPartEvent, RrPartTeam
				) sqy ON sqyEvent=EvCode AND sqyTournament=EvTournament
				SET
					IndRankFinal=IF(IndRank > COALESCE(sqyQualified,
					                    IF(EvElim1=0 AND EvElim2=0, EvNumQualified,
					                       IF(EvElim1=0, EvElim2, EvElim1))),
					                IndRank, 0),
					IndTimestampFinal='{$date}'
				WHERE
					IndTournament={$this->tournament}
					AND EvCode='{$event}'
					AND EvTeamEvent=0
			";
			return (safe_w_sql($q) !== false);
		}

	/**
	 * calcFromElim1()
	 * Assigns IndRankFinal to athletes stopped at elimination round 1 (phase 0).
	 */
		protected function calcFromElim1($event)
		{
			$num = 0;
			$q = "SELECT EvElim2 AS Num FROM Events WHERE EvCode='{$event}' AND EvTournament={$this->tournament} AND EvTeamEvent=0";
			$r = safe_r_sql($q);
			if ($r && safe_num_rows($r) == 1)
				$num = safe_fetch($r)->Num;

			$date = date('Y-m-d H:i:s');
			$q = "
				UPDATE Individuals
				INNER JOIN Eliminations
					ON IndId=ElId AND IndTournament=ElTournament
					AND IndEvent=ElEventCode AND ElElimPhase=0
				SET
					IndRankFinal=ElRank,
					IndTimestampFinal='{$date}'
				WHERE
					ElTournament={$this->tournament}
					AND ElEventCode='{$event}'
					AND ElElimPhase=0
					AND ElRank>{$num}
			";
			return (safe_w_sql($q) !== false);
		}

	/**
	 * calcFromElim2()
	 * Assigns IndRankFinal to athletes stopped at elimination round 2 (phase 1).
	 */
		protected function calcFromElim2($event)
		{
			$num = 0;
			$q = "SELECT EvNumQualified AS Num FROM Events WHERE EvCode='{$event}' AND EvTournament={$this->tournament} AND EvTeamEvent=0";
			$r = safe_r_sql($q);
			if ($r && safe_num_rows($r) == 1)
				$num = safe_fetch($r)->Num;

			$date = date('Y-m-d H:i:s');
			$q = "
				UPDATE Individuals
				INNER JOIN Eliminations
					ON IndId=ElId AND IndTournament=ElTournament
					AND IndEvent=ElEventCode AND ElElimPhase=1
				SET
					IndRankFinal=ElRank,
					IndTimestampFinal='{$date}'
				WHERE
					ElTournament={$this->tournament}
					AND ElEventCode='{$event}'
					AND ElElimPhase=1
					AND ElRank>{$num}
			";
			return (safe_w_sql($q) !== false);
		}

	/**
	 * calcFromPhase()
	 *
	 * PL modification (§2.6.5):
	 *  - No-bronze detection: when the bronze phase losers query returns 0 rows,
	 *    check if both bronze-match athletes scored 0 (match not shot). If so,
	 *    assign both shared 3rd place.
	 *  - Unique sequential positions for ALL phases >= 4, sorted by match score
	 *    DESC then qualification rank (IndRank) ASC.
	 *
	 * All other behaviour (gold/bronze/semi handling, EvWinnerFinalRank offset,
	 * parent-event chains, SubCodes, pool phases, IRM types) is preserved from
	 * the core implementation.
	 *
	 * @param string $event     Event code
	 * @param int    $realphase Raw phase number (from Grids.GrPhase)
	 * @return bool
	 */
		protected function calcFromPhase($event, $realphase)
		{
			$date = date('Y-m-d H:i:s');

			// Reset IndRankFinal for all athletes competing in this phase
			$q = "
				UPDATE Individuals
				INNER JOIN Finals
					ON IndId=FinAthlete AND IndTournament=FinTournament AND IndEvent=FinEvent
				INNER JOIN IrmTypes ON IrmId=IndIrmTypeFinal AND IrmShowRank=1
				INNER JOIN Grids ON FinMatchNo=GrMatchNo AND GrPhase={$realphase}
				SET
					IndRankFinal=0,
					IndTimestampFinal='{$date}'
				WHERE
					GrPhase={$realphase}
					AND IndTournament={$this->tournament}
					AND IndEvent='{$event}'
			";
			$r = safe_w_sql($q);
			if (!$r)
				return false;

			// Fetch all losers for this phase.
			// PL change to ORDER BY: qualification rank (i.IndRank ASC) as secondary
			// tiebreaker instead of cumulative score.
			$q = "
				SELECT
					EvElimType, EvWinnerFinalRank, SubCodes, EvCodeParent,
					GrPhase, EvFinalFirstPhase,
					LEAST(f.FinMatchNo, f2.FinMatchNo) AS MatchNo,
					f.FinAthlete  AS AthId,    i.IndRank  AS AthRank,
					f2.FinAthlete AS OppAthId, i2.IndRank AS OppAthRank,
					f.FinIrmType  AS IrmType,  f2.FinIrmType AS OppIrmType,
					IF(EvMatchMode=0, f.FinScore, f.FinSetScore) AS Score,
					f.FinScore  AS CumScore,  f.FinTie  AS Tie,
					IF(EvMatchMode=0, f2.FinScore, f2.FinSetScore) AS OppScore,
					f2.FinScore AS OppCumScore, f2.FinTie AS OppTie
				FROM
					Finals AS f
					INNER JOIN Finals AS f2
						ON f.FinEvent=f2.FinEvent
						AND f.FinMatchNo=IF((f.FinMatchNo % 2)=0, f2.FinMatchNo-1, f2.FinMatchNo+1)
						AND f.FinTournament=f2.FinTournament
					LEFT JOIN Individuals AS i
						ON i.IndId=f.FinAthlete
						AND i.IndTournament=f.FinTournament
						AND i.IndEvent=f.FinEvent
					LEFT JOIN Individuals AS i2
						ON i2.IndId=f2.FinAthlete
						AND i2.IndTournament=f2.FinTournament
						AND i2.IndEvent=f2.FinEvent
					INNER JOIN Grids ON f.FinMatchNo=GrMatchNo
					INNER JOIN Events
						ON f.FinEvent=EvCode
						AND f.FinTournament=EvTournament
						AND EvTeamEvent=0
					LEFT JOIN (
						SELECT
							GROUP_CONCAT(DISTINCT CONCAT(EvCode, '@', EvFinalFirstPhase)) SubCodes,
							EvCodeParent SubMainCode,
							EvFinalFirstPhase SubFirstPhase
						FROM Events
						WHERE EvCodeParent!=''
						  AND EvTeamEvent=0
						  AND EvTournament={$this->tournament}
						GROUP BY EvCodeParent, EvFinalFirstPhase
					) Secondary ON SubMainCode=EvCode AND SubFirstPhase=GrPhase/2
				WHERE
					f.FinTournament={$this->tournament}
					AND f.FinEvent='{$event}'
					AND GrPhase={$realphase}
					AND f.FinAthlete > 0
					AND (f2.FinWinLose=1
					     OR (f.FinIrmType>0 AND f.FinIrmType<20
					         AND f2.FinIrmType>0 AND f2.FinIrmType<20))
				ORDER BY
					IF(EvMatchMode=0, f.FinScore, f.FinSetScore) DESC,
					i.IndRank ASC
			";
			$rs = safe_r_sql($q);

			if ($rs)
			{
				if (safe_num_rows($rs) > 0)
				{
					$myRow = safe_fetch($rs);

					// Normalise phase identifier for non-standard bracket sizes (1/24, 1/48, ...)
					$phase = namePhase($myRow->EvFinalFirstPhase, $realphase);

					// Walk up the parent-event chain (combined/sub-events)
					$EventToUse = $event;
					$ParentCode = $myRow->EvCodeParent;
					while ($ParentCode)
					{
						$EventToUse = $ParentCode;
						$t = safe_r_sql("SELECT EvCodeParent FROM Events WHERE EvCode=" . StrSafe_DB($ParentCode));
						if ($u = safe_fetch($t))
							$ParentCode = $u->EvCodeParent;
						else
							$ParentCode = '';
					}

					if ($phase == 0 || $phase == 1)
					{
						// Gold match: assign 1st and 2nd.
						// Bronze match: assign 3rd and 4th.
						$toWrite = array();

						if ($phase == 0)
						{
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->OppAthId, 'rank' => $myRow->EvWinnerFinalRank);
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->AthId,    'rank' => $myRow->EvWinnerFinalRank + 1);
						}
						else // $phase == 1
						{
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->OppAthId, 'rank' => $myRow->EvWinnerFinalRank + 2);
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->AthId,    'rank' => $myRow->EvWinnerFinalRank + 3);
						}

						foreach ($toWrite as $values)
						{
							$x = $this->writeRow($values['id'], $values['event'], $values['rank']);
							if ($x === false)
								return false;
						}
					}
					elseif ($phase == 2 || $myRow->SubCodes)
					{
						// Semifinals: rankings are assigned when bronze/gold results arrive.
						// Sub-events: ranking is handled by the parent event's calcFromPhase.
					}
					else
					{
						// PL §2.6.5: unique sequential positions for ALL phases >= 4,
						// sorted by match score DESC, then qualification rank ASC.

						if ($realphase == 4)
						{
							// QF: account for pool-elimination brackets (EvElimType 3/4) that
							// have fewer than 8 losers; start as close to the bottom as possible.
							$MaxRank = ($myRow->EvElimType == 3 || $myRow->EvElimType == 4) ? 4 : 8;
							$pos = max(4, $MaxRank - safe_num_rows($rs));
						}
						elseif ($realphase > 4)
						{
							$pos = numMatchesByPhase($phase) + SavedInPhase($phase);
						}
						else
						{
							return false;
						}

						$rank        = $pos + 1;
						$scoreOld    = 0;
						$qualRankOld = -1;

						while ($myRow)
						{
							++$pos;

							// Assign a new (higher) rank whenever score or qual-rank changes
							if (!($myRow->Score == $scoreOld && $myRow->AthRank == $qualRankOld))
								$rank = $pos;

							// DSQ in QF goes to last place (preserved core behaviour)
							if ($phase == 4 && $myRow->IrmType == 15)
								$rank = 8;

							$scoreOld    = $myRow->Score;
							$qualRankOld = $myRow->AthRank;

							// Pool-phase rank override (EvElimType 3 = WA compound pool,
							// EvElimType 4 = WA recurve pool)
							if (($myRow->EvElimType == 3 || $myRow->EvElimType == 4)
								&& $myRow->GrPhase > $myRow->EvFinalFirstPhase
								&& $myRow->MatchNo >= 8)
							{
								$rank = ($myRow->EvElimType == 3)
									? getPoolLooserRank($myRow->MatchNo)
									: getPoolLooserRankWA($myRow->MatchNo);
							}

							$x = $this->writeRow($myRow->AthId, $event, $rank + $myRow->EvWinnerFinalRank - 1);
							if ($x === false)
								return false;

							$myRow = safe_fetch($rs);
						}
					}
				}
				else
				{
					// No losers returned — may be the bronze match that was never shot (0-0 tie).
					// Guard: only attempt detection for the bronze phase (normalized phase == 1).
					// Without this guard, an unplayed gold match (also 0-0) triggers the same
					// 2-row condition and would incorrectly place both finalists at 3rd.
					$qEvt = safe_r_sql("SELECT EvFinalFirstPhase FROM Events WHERE EvCode='{$event}' AND EvTournament={$this->tournament} AND EvTeamEvent=0");
					if (!($qEvt && ($eRow = safe_fetch($qEvt)) && namePhase($eRow->EvFinalFirstPhase, $realphase) == 1))
						return true; // not the bronze phase — leave ranks empty as normal

					// Detect: both athletes in this phase scored 0 with 0 set points.
					$qDetect = "
						SELECT f.FinAthlete AS AthId, e.EvWinnerFinalRank, e.EvCodeParent
						FROM Finals AS f
							INNER JOIN Grids ON f.FinMatchNo=GrMatchNo AND GrPhase={$realphase}
							INNER JOIN Events AS e
								ON f.FinEvent=e.EvCode
								AND f.FinTournament=e.EvTournament
								AND e.EvTeamEvent=0
						WHERE f.FinTournament={$this->tournament}
							AND f.FinEvent='{$event}'
							AND f.FinScore=0 AND f.FinSetScore=0
					";
					$rsDetect = safe_r_sql($qDetect);
					if ($rsDetect && safe_num_rows($rsDetect) == 2)
					{
						// Bronze match not shot — both semifinal losers share 3rd place.
						$row1 = safe_fetch($rsDetect);
						$row2 = safe_fetch($rsDetect);

						// Resolve parent-event chain (same logic as the rows > 0 branch)
						$EventToUse = $event;
						$ParentCode = $row1->EvCodeParent;
						while ($ParentCode)
						{
							$EventToUse = $ParentCode;
							$t = safe_r_sql("SELECT EvCodeParent FROM Events WHERE EvCode=" . StrSafe_DB($ParentCode));
							if ($u = safe_fetch($t))
								$ParentCode = $u->EvCodeParent;
							else
								$ParentCode = '';
						}

						$sharedRank = $row1->EvWinnerFinalRank + 2; // shared 3rd when EvWinnerFinalRank=1
						$x = $this->writeRow($row1->AthId, $EventToUse, $sharedRank);
						if ($x === false)
							return false;
						$x = $this->writeRow($row2->AthId, $EventToUse, $sharedRank);
						if ($x === false)
							return false;
					}
				}
			}
			else
			{
				return false;
			}

			return true;
		}

	/*
	 * **************************************************************
	 * END Micro algorithms
	 * **************************************************************
	 */

	/**
	 * calculate()
	 * Orchestrates ranking calculation across all phases for each event.
	 */
		public function calculate()
		{
			if (count($this->opts['eventsC']) > 0)
			{
				foreach ($this->opts['eventsC'] as $c)
				{
					list($event, $phase) = explode('@', $c);

					$x = true;
					switch ($phase)
					{
						case -3:
							$x = $this->calcFromAbs($event);
							break;
						case -2:
							$x = $this->calcFromElim2($event);
							break;
						case -1:
							$x = $this->calcFromElim1($event);
							break;
						default:
							foreach (getPhasesId() as $p)
							{
								if ($p > $phase)
									continue;
								$x = $this->calcFromPhase($event, $p);
								if ($x === false)
									return false;
							}
							break;
					}

					if ($x === false)
						return false;
				}
			}

			return true;
		}

	}
