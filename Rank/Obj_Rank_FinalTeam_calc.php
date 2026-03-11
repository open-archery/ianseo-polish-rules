<?php
/**
 * Obj_Rank_FinalTeam_calc — Poland (PL) override
 *
 * Identical to the core implementation except calcFromPhase(), which applies
 * PZŁucz §2.6.5 rules:
 *
 *  1. Unique sequential positions for ALL phases >= 4 (quarterfinals and below),
 *     not just the quarterfinals as in the default engine.
 *  2. Secondary tiebreaker is team qualification rank (TeRank ASC) instead of
 *     cumulative score.  The Teams table is joined to supply this value.
 *
 * Bronze-medal-match no-shoot detection is NOT implemented here (deferred).
 */
	class Obj_Rank_FinalTeam_calc extends Obj_Rank_FinalTeam
	{
	/**
	 * writeRow()
	 * Updates TeRankFinal in Teams.
	 * Skips teams with TeIrmTypeFinal >= 15 (DSQ/withdrawn — core convention).
	 */
		protected function writeRow($id, $subteam, $event, $rank)
		{
			$date = date('Y-m-d H:i:s');
			$q = "
				UPDATE Teams
				SET
					TeRankFinal={$rank},
					TeTimeStampFinal='{$date}'
				WHERE
					TeTournament={$this->tournament}
					AND TeEvent='{$event}'
					AND TeCoId={$id}
					AND TeSubTeam='{$subteam}'
					AND TeIrmTypeFinal<15
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
	 * Assigns TeRankFinal to teams that did not advance past qualification.
	 */
		protected function calcFromAbs($event)
		{
			$date = date('Y-m-d H:i:s');

			// Check whether the event uses a round-robin qualification stage
			$q = safe_r_sql("
				SELECT MAX(RrPartSourceRank) AS NumQualified
				FROM RoundRobinParticipants
				WHERE RrPartSourceLevel=0
				  AND RrPartTournament={$this->tournament}
				  AND RrPartEvent='{$event}'
				  AND RrPartTeam=1
			");
			if (($r = safe_fetch($q)) and $r->NumQualified)
				$Field = $r->NumQualified;
			else
				$Field = 'EvNumQualified';

			$q = "
				UPDATE Teams
				INNER JOIN Events ON TeEvent=EvCode AND TeTournament=EvTournament AND TeFinEvent=1
				SET
					TeRankFinal=IF(TeRank > {$Field}, TeRank, 0),
					TeTimeStampFinal='{$date}'
				WHERE
					TeTournament={$this->tournament}
					AND EvCode='{$event}'
					AND EvTeamEvent=1
			";
			return (safe_w_sql($q) !== false);
		}

	/**
	 * calcFromPhase()
	 *
	 * PL modification (§2.6.5): unique sequential positions for ALL phases >= 4,
	 * sorted by match score DESC then team qualification rank (TeRank) ASC.
	 *
	 * All other behaviour (gold/bronze/semi handling, EvWinnerFinalRank offset,
	 * parent-event chains, SubCodes) is preserved from the core implementation.
	 *
	 * @param string $event     Event code
	 * @param int    $realphase Raw phase number (from Grids.GrPhase)
	 * @return bool
	 */
		protected function calcFromPhase($event, $realphase)
		{
			$date = date('Y-m-d H:i:s');

			// Reset TeRankFinal for all teams competing in this phase
			$q = "
				UPDATE Teams
				INNER JOIN TeamFinals
					ON TeCoId=TfTeam AND TeSubTeam=TfSubTeam
					AND TeTournament=TfTournament AND TeEvent=TfEvent AND TeFinEvent=1
				INNER JOIN IrmTypes ON IrmId=TeIrmTypeFinal AND IrmShowRank=1
				INNER JOIN Grids ON TfMatchNo=GrMatchNo AND GrPhase={$realphase}
				SET
					TeRankFinal=0,
					TeTimeStampFinal='{$date}'
				WHERE
					GrPhase={$realphase}
					AND TeTournament={$this->tournament}
					AND TeEvent='{$event}'
					AND TeFinEvent=1
			";
			$r = safe_w_sql($q);
			if (!$r)
				return false;

			// Fetch all losers for this phase.
			// PL changes:
			//   • LEFT JOIN Teams AS te to obtain the team qualification rank.
			//   • ORDER BY uses te.TeRank ASC as secondary tiebreaker instead of
			//     cumulative score.
			$q = "
				SELECT
					EvWinnerFinalRank, EvCodeParent, SubCodes, EvFinalFirstPhase,
					tf.TfTeam    AS TeamId,     tf.TfSubTeam  AS SubTeam,
					tf2.TfTeam   AS OppTeamId,  tf2.TfSubTeam AS OppSubTeam,
					te.TeRank    AS TeamQualRank,
					IF(EvMatchMode=0, tf.TfScore, tf.TfSetScore)  AS Score,
					tf.TfScore   AS CumScore,   tf.TfTie       AS Tie,
					IF(EvMatchMode=0, tf2.TfScore, tf2.TfSetScore) AS OppScore,
					tf2.TfScore  AS OppCumScore, tf2.TfTie     AS OppTie
				FROM
					TeamFinals AS tf
					INNER JOIN TeamFinals AS tf2
						ON tf.TfEvent=tf2.TfEvent
						AND tf.TfMatchNo=IF((tf.TfMatchNo % 2)=0, tf2.TfMatchNo-1, tf2.TfMatchNo+1)
						AND tf.TfTournament=tf2.TfTournament
					INNER JOIN Grids ON tf.TfMatchNo=GrMatchNo
					INNER JOIN Events
						ON tf.TfEvent=EvCode
						AND tf.TfTournament=EvTournament
						AND EvTeamEvent=1
					LEFT JOIN Teams AS te
						ON te.TeTournament=tf.TfTournament
						AND te.TeCoId=tf.TfTeam
						AND te.TeSubTeam=tf.TfSubTeam
						AND te.TeEvent=tf.TfEvent
						AND te.TeFinEvent=1
					LEFT JOIN (
						SELECT
							GROUP_CONCAT(DISTINCT CONCAT(EvCode, '@', EvFinalFirstPhase)) SubCodes,
							EvCodeParent SubMainCode,
							EvFinalFirstPhase SubFirstPhase
						FROM Events
						WHERE EvCodeParent!=''
						  AND EvTeamEvent=1
						  AND EvTournament={$this->tournament}
						GROUP BY EvCodeParent, EvFinalFirstPhase
					) Secondary ON SubMainCode=EvCode AND SubFirstPhase=GrPhase/2
				WHERE
					tf.TfTournament={$this->tournament}
					AND tf.TfEvent='{$event}'
					AND GrPhase={$realphase}
					AND tf.TfTeam > 0
					AND (tf2.TfWinLose=1
					     OR (tf.TfIrmType>0 AND tf.TfIrmType<20
					         AND tf2.TfIrmType>0 AND tf2.TfIrmType<20))
				ORDER BY
					IF(EvMatchMode=0, tf.TfScore, tf.TfSetScore) DESC,
					te.TeRank ASC
			";
			$rs = safe_r_sql($q);

			if ($rs)
			{
				if (safe_num_rows($rs) > 0)
				{
					$myRow = safe_fetch($rs);

					// Normalise phase identifier for non-standard bracket sizes (1/24, 1/48, …)
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
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->OppTeamId, 'subteam' => $myRow->OppSubTeam, 'rank' => $myRow->EvWinnerFinalRank);
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->TeamId,    'subteam' => $myRow->SubTeam,    'rank' => $myRow->EvWinnerFinalRank + 1);
						}
						else // $phase == 1
						{
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->OppTeamId, 'subteam' => $myRow->OppSubTeam, 'rank' => $myRow->EvWinnerFinalRank + 2);
							$toWrite[] = array('event' => $EventToUse, 'id' => $myRow->TeamId,    'subteam' => $myRow->SubTeam,    'rank' => $myRow->EvWinnerFinalRank + 3);
						}

						foreach ($toWrite as $values)
						{
							$x = $this->writeRow($values['id'], $values['subteam'], $values['event'], $values['rank']);
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
						// sorted by match score DESC, then team qualification rank ASC.

						if ($realphase == 4)
						{
							$pos = max(4, 8 - safe_num_rows($rs));
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
							if (!($myRow->Score == $scoreOld && $myRow->TeamQualRank == $qualRankOld))
								$rank = $pos;

							$scoreOld    = $myRow->Score;
							$qualRankOld = $myRow->TeamQualRank;

							$x = $this->writeRow($myRow->TeamId, $myRow->SubTeam, $event, $rank + $myRow->EvWinnerFinalRank - 1);
							if ($x === false)
								return false;

							$myRow = safe_fetch($rs);
						}
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
	 * Orchestrates team ranking calculation across all phases for each event.
	 * Teams do not have pre-final elimination rounds, so cases -1 and -2
	 * are intentional no-ops.
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
						case -1:
							// Teams have no pre-final elimination rounds
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
