<?php
/**
 * Obj_Rank_FinalInd_calc — Poland (PL) override
 *
 * Identical to the core implementation except calcFromPhase(), which applies
 * PZŁucz §2.6.5–§2.6.6 rules:
 *
 *  1. No-bronze-match detection: when the bronze match result is 0-0 (not shot),
 *     both semifinal losers are awarded shared 3rd place instead of 3rd/4th.
 *  2. Unique sequential positions for ALL phases >= 4 (quarterfinals and below),
 *     not just the quarterfinals as in the default engine.
 *  3. Tiebreaking within a phase uses three criteria (§2.6.6.2):
 *       a. Average arrow value in the match (FinScore / arrows, shoot-off excluded)
 *       b. Average arrow value in the shoot-off (0 if no shoot-off)
 *       c. Qualification score (IndScore)
 *  4. FinAverageMatch and FinAverageTie are written to Finals for every phase,
 *     matching ianseo rev 114 core behaviour.
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
	 * PL modification (§2.6.5–§2.6.6):
	 *  - No-bronze detection: when the bronze phase losers query returns 0 rows,
	 *    check if both bronze-match athletes scored 0 (match not shot). If so,
	 *    assign both shared 3rd place.
	 *  - Unique sequential positions for ALL phases >= 4, sorted by:
	 *      1. Average arrow value in the match (FinScore / arrows shot, descending)
	 *      2. Average arrow value in the shoot-off (0 if none, descending)
	 *      3. Qualification score (IndScore, descending)
	 *  - FinAverageMatch and FinAverageTie are written to Finals for all phases.
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
			// Arrow-count helpers (DiEndArrows, DiArrows), arrow content fields, and
			// qualification score are fetched here; averages are computed in PHP.
			// ORDER BY is match number only — sorting for sub-ranking is done in PHP.
			$q = "
				SELECT
					EvWinnerFinalRank, SubCodes, EvCodeParent,
					GrPhase, EvFinalFirstPhase,
					IF((EvMatchArrowsNo & GrBitPhase)=0, EvFinArrows, EvElimArrows) AS DiEndArrows,
					IF((EvMatchArrowsNo & GrBitPhase)=0, EvFinArrows*EvFinEnds, EvElimArrows*EvElimEnds) AS DiArrows,
					LEAST(f.FinMatchNo, f2.FinMatchNo) AS MatchNo,
					f.FinAthlete   AS AthId,    i.IndRank   AS AthRank,
					f2.FinAthlete  AS OppAthId, i2.IndRank  AS OppAthRank,
					f.FinIrmType   AS IrmType,  f2.FinIrmType AS OppIrmType,
					f.FinScore     AS Score,
					f.FinArrowstring  AS Arrowstring,  f.FinSetPoints  AS SetPoints,  f.FinTiebreak  AS Tiebreak,
					f2.FinScore    AS OppScore,
					f2.FinArrowstring AS OppArrowstring, f2.FinSetPoints AS OppSetPoints, f2.FinTiebreak AS OppTiebreak,
					COALESCE(qq.QuScore, 0) AS QualScore,
					f.FinMatchNo   AS RealMatchNo, f2.FinMatchNo AS OppRealMatchNo
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
					LEFT JOIN Qualifications AS qq ON qq.QuId=f.FinAthlete
				WHERE
					f.FinTournament={$this->tournament}
					AND f.FinEvent='{$event}'
					AND GrPhase={$realphase}
					AND f.FinAthlete > 0
					AND (f2.FinWinLose=1
					     OR (f.FinIrmType>0 AND f.FinIrmType<20
					         AND f2.FinIrmType>0 AND f2.FinIrmType<20))
				ORDER BY
					LEAST(f.FinMatchNo, f2.FinMatchNo)
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
						// Also write FinAverageMatch/FinAverageTie for both athletes.
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

						// Compute and store averages for both athletes in this match
						$avgMatch = round($myRow->Score / (strlen(trim($myRow->Arrowstring))
							?: (strlen(preg_replace("/\d/", "", $myRow->SetPoints))
								? (strlen(preg_replace("/\d/", "", $myRow->SetPoints)) + 1) * $myRow->DiEndArrows
								: ($myRow->DiArrows ?: 1))), 3);
						$avgTie = round(valutaArrowString($myRow->Tiebreak)
							/ (strlen(trim($myRow->Tiebreak)) ?: 1), 3);
						$oppAvgMatch = round($myRow->OppScore / (strlen(trim($myRow->OppArrowstring))
							?: (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints))
								? (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints)) + 1) * $myRow->DiEndArrows
								: ($myRow->DiArrows ?: 1))), 3);
						$oppAvgTie = round(valutaArrowString($myRow->OppTiebreak)
							/ (strlen(trim($myRow->OppTiebreak)) ?: 1), 3);

						safe_w_sql("UPDATE Finals SET FinAverageMatch='{$avgMatch}', FinAverageTie='{$avgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->RealMatchNo}'");
						safe_w_sql("UPDATE Finals SET FinAverageMatch='{$oppAvgMatch}', FinAverageTie='{$oppAvgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->OppRealMatchNo}'");

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
						// Write averages for all participants in this phase.
						while ($myRow)
						{
							$avgMatch = round($myRow->Score / (strlen(trim($myRow->Arrowstring))
								?: (strlen(preg_replace("/\d/", "", $myRow->SetPoints))
									? (strlen(preg_replace("/\d/", "", $myRow->SetPoints)) + 1) * $myRow->DiEndArrows
									: ($myRow->DiArrows ?: 1))), 3);
							$avgTie = round(valutaArrowString($myRow->Tiebreak)
								/ (strlen(trim($myRow->Tiebreak)) ?: 1), 3);
							$oppAvgMatch = round($myRow->OppScore / (strlen(trim($myRow->OppArrowstring))
								?: (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints))
									? (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints)) + 1) * $myRow->DiEndArrows
									: ($myRow->DiArrows ?: 1))), 3);
							$oppAvgTie = round(valutaArrowString($myRow->OppTiebreak)
								/ (strlen(trim($myRow->OppTiebreak)) ?: 1), 3);

							safe_w_sql("UPDATE Finals SET FinAverageMatch='{$avgMatch}', FinAverageTie='{$avgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->RealMatchNo}'");
							safe_w_sql("UPDATE Finals SET FinAverageMatch='{$oppAvgMatch}', FinAverageTie='{$oppAvgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->OppRealMatchNo}'");

							$myRow = safe_fetch($rs);
						}
					}
					else
					{
						// PL §2.6.6: unique sequential positions for ALL phases >= 4.
						// Sort by: avg match arrow value DESC, avg shoot-off arrow value DESC,
						// qualification score DESC. Uses PHP-side sorting to apply the
						// arrow-count derivation formula (cannot be expressed cleanly in SQL).

						if ($realphase == 4)
						{
							// QF: always start at position 4 (the 4 slots that advanced past QF).
							// Using safe_num_rows() to adjust the start caused ranks 7-8 instead
							// of 5-6 when byes reduced the real loser count below 4.
							$pos = 4;
						}
						elseif ($realphase > 4)
						{
							$pos = numMatchesByPhase($phase) + SavedInPhase($phase);
						}
						else
						{
							return false;
						}

						// Collect all losers, compute averages, write to Finals
						$matchData = array();
						while ($myRow)
						{
							$arrows = strlen(trim($myRow->Arrowstring))
								?: (strlen(preg_replace("/\d/", "", $myRow->SetPoints))
									? (strlen(preg_replace("/\d/", "", $myRow->SetPoints)) + 1) * $myRow->DiEndArrows
									: ($myRow->DiArrows ?: 1));
							$avgMatch = round($myRow->Score / $arrows, 3);
							$avgTie   = round(valutaArrowString($myRow->Tiebreak)
								/ (strlen(trim($myRow->Tiebreak)) ?: 1), 3);

							$oppArrows = strlen(trim($myRow->OppArrowstring))
								?: (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints))
									? (strlen(preg_replace("/\d/", "", $myRow->OppSetPoints)) + 1) * $myRow->DiEndArrows
									: ($myRow->DiArrows ?: 1));
							$oppAvgMatch = round($myRow->OppScore / $oppArrows, 3);
							$oppAvgTie   = round(valutaArrowString($myRow->OppTiebreak)
								/ (strlen(trim($myRow->OppTiebreak)) ?: 1), 3);

							safe_w_sql("UPDATE Finals SET FinAverageMatch='{$avgMatch}', FinAverageTie='{$avgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->RealMatchNo}'");
							safe_w_sql("UPDATE Finals SET FinAverageMatch='{$oppAvgMatch}', FinAverageTie='{$oppAvgTie}' WHERE FinTournament={$this->tournament} AND FinEvent='{$EventToUse}' AND FinMatchNo='{$myRow->OppRealMatchNo}'");

							$matchData[] = array(
								'id'         => $myRow->AthId,
								'avgMatch'   => $avgMatch,
								'avgTie'     => $avgTie,
								'qualScore'  => (int) $myRow->QualScore,
								'winnerRank' => $myRow->EvWinnerFinalRank,
								'irmType'    => $myRow->IrmType,
							);

							$myRow = safe_fetch($rs);
						}

						// Sort: avg match score DESC, avg shoot-off score DESC, qual score DESC
						usort($matchData, function ($a, $b) {
							if ($a['avgMatch'] !== $b['avgMatch']) return $b['avgMatch'] <=> $a['avgMatch'];
							if ($a['avgTie']   !== $b['avgTie'])   return $b['avgTie']   <=> $a['avgTie'];
							return $b['qualScore'] <=> $a['qualScore'];
						});

						// Assign unique sequential ranks; share only when all three criteria identical
						$rank = $pos + 1;
						$prev = null;

						foreach ($matchData as $m)
						{
							++$pos;

							if ($prev === null
								|| $m['avgMatch']  !== $prev['avgMatch']
								|| $m['avgTie']    !== $prev['avgTie']
								|| $m['qualScore'] !== $prev['qualScore'])
							{
								$rank = $pos;
							}

							// DSQ in QF goes to last place (preserved core behaviour)
							if ($phase == 4 && $m['irmType'] == 15)
								$rank = 8;

							$x = $this->writeRow($m['id'], $event, $rank + $m['winnerRank'] - 1);
							if ($x === false)
								return false;

							$prev = $m;
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
							AND f.FinAthlete > 0
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
