<?php
/**
 * Obj_Rank_DivClass_calc — Poland (PL) override
 *
 * Extends the core DivClass qualification ranking to expose each athlete's
 * year of birth (EnDob) as $item['birthdate'], used by the PL PDF chunk
 * to render the "Rok ur." column.
 *
 * The core Obj_Rank_DivClass::read() does not select EnDob. This subclass
 * calls parent::read() and then runs a single supplementary query to fetch
 * EnDob for all athletes in the result, stitching it into each item.
 *
 * NOTE: Because Obj_RankFactory loads only the first _calc file it finds
 * (PL before Common), this file must also include calculate() and
 * calculateSubClass() from the Common implementation — otherwise the
 * no-op stub in Obj_Rank_DivClass would be used and no ranks would be
 * written to the database.
 */
class Obj_Rank_DivClass_calc extends Obj_Rank_DivClass
{
	/**
	 * calculate()
	 * Copied from Common/Rank/Obj_Rank_DivClass_calc.php.
	 * Writes QuClRank (and per-distance QuDnRank) to the Qualifications table.
	 */
	public function calculate()
	{
		$dd = ($this->opts['dist']>0 ? 'D' . $this->opts['dist'] : '');

		$filter=$this->safeFilter();

		$orderBy="CONCAT(EnDivision,EnClass), Qu{$dd}Score DESC,Qu{$dd}Gold DESC, Qu{$dd}Xnine DESC ";

		$q="
			SELECT
				EnTournament,EnId,EnCountry,CONCAT(EnDivision,EnClass) AS MyEvent,ToType,
				Qu{$dd}Score AS Score,Qu{$dd}Gold AS Gold ,Qu{$dd}Xnine AS XNine, Qu{$dd}Hits AS Hits
			FROM Entries
			inner JOIN Tournament ON EnTournament=ToId
			INNER JOIN Qualifications ON EnId=QuId
		    inner join IrmTypes on IrmId=QuIrmType and IrmShowRank=1
			WHERE
				EnTournament={$this->tournament} AND
				EnAthlete=1 AND
				EnStatus <=1  AND
				EnIndClEvent='1' AND
				(Qu{$dd}Score>0 or Qu{$dd}Hits>0)
				{$filter}
			ORDER BY
				{$orderBy}
		";
		$r=safe_r_sql($q);

		$myEv='';

		$rank=1;
		$pos=0;

		$scoreOld=0;
		$goldOld=0;
		$xNineOld=0;

		while ($myRow=safe_fetch($r)) {
			if ($myRow->MyEvent!=$myEv) {
				$rank=1;
				$pos=0;

				$scoreOld=0;
				$goldOld=0;
				$xNineOld=0;
			}

			++$pos;

			if (!($myRow->Score==$scoreOld && $myRow->Gold==$goldOld  && $myRow->XNine==$xNineOld))
				$rank = $pos;


			$date=date('Y-m-d H:i:s');

			$q 	= "UPDATE Qualifications "
				. "SET Qu" . ($dd=='' ? 'Cl' : '') . $dd . "Rank=" . StrSafe_DB($rank) . ", "
				. "QuTimestamp='{$date}' "
				. "WHERE QuId=" . $myRow->EnId . " "
			;
			safe_w_sql($q);

			if(empty($dd) and $myRow->Hits%3 == 0) {
				$q = "INSERT INTO QualOldPositions (QopId, QopHits, QopClRank) "
					. "VALUES(" . $myRow->EnId . "," . $myRow->Hits . "," . $rank . ") "
					. "ON DUPLICATE KEY UPDATE QopClRank=" . $rank;
				safe_w_sql($q);
				$q = "DELETE FROM QualOldPositions WHERE QopId=" . $myRow->EnId . " AND QopHits>" . $myRow->Hits;
				safe_w_sql($q);
			}

			$myEv=$myRow->MyEvent;
			$scoreOld=$myRow->Score;
			$goldOld=$myRow->Gold;
			$xNineOld=$myRow->XNine;
		}

		if(!$dd) $this->calculateSubClass();
		return true;
	}

	/**
	 * calculateSubClass()
	 * Copied from Common/Rank/Obj_Rank_DivClass_calc.php.
	 * Writes QuSubClassRank to the Qualifications table.
	 */
	function calculateSubClass() {
		$filter=$this->safeFilter();

		$orderBy="CONCAT(EnDivision,EnClass,EnSubClass), QuScore DESC,QuGold DESC, QuXnine DESC ";

		$q="
			SELECT
				EnTournament,EnId,EnSubClass,EnCountry,CONCAT(EnDivision,EnClass,EnSubClass) AS MyEvent,ToType,
				QuScore AS Score,QuGold AS Gold ,QuXnine AS XNine, QuHits as Hits
			FROM
				Entries
				LEFT JOIN
					Tournament
				ON EnTournament=ToId
				INNER JOIN
					Qualifications
				ON EnId=QuId
			WHERE
				EnTournament={$this->tournament} AND
				EnAthlete=1 AND
				EnStatus <=1  AND
				EnIndClEvent='1' AND
				EnSubClass!='' AND
				QuScore<>0
				{$filter}
			ORDER BY
				{$orderBy}
		";
		$r=safe_r_sql($q);

		$myEv='';

		$rank=1;
		$pos=0;

		$scoreOld=0;
		$goldOld=0;
		$xNineOld=0;

		while ($myRow=safe_fetch($r))
		{
			if ($myRow->MyEvent!=$myEv)
			{
				$rank=1;
				$pos=0;

				$scoreOld=0;
				$goldOld=0;
				$xNineOld=0;
			}

			++$pos;

			if (!($myRow->Score==$scoreOld && $myRow->Gold==$goldOld  && $myRow->XNine==$xNineOld))
			{
				$rank = $pos;
			}

			$date=date('Y-m-d H:i:s');

			$q
				= "UPDATE Qualifications "
				. "SET "
					. "QuSubClassRank=" . StrSafe_DB($rank) . ", "
					. "QuTimestamp='{$date}' "
				. "WHERE "
					. "QuId=" . $myRow->EnId . " "
			;

			safe_w_sql($q);

			if($myRow->Hits%3 == 0) {
				$q = "UPDATE QualOldPositions "
					. "SET QopSubClassRank=" . $rank . " "
					. "WHERE QopId=" . $myRow->EnId . " AND QopHits=" . $myRow->Hits;
				safe_w_sql($q);
			}
			$myEv=$myRow->MyEvent;
			$scoreOld=$myRow->Score;
			$goldOld=$myRow->Gold;
			$xNineOld=$myRow->XNine;
		}

		return true;
	}

	/**
	 * read()
	 * Delegates to parent, then enriches every item with 'birthdate'.
	 */
	public function read()
	{
		parent::read();
		$this->enrichWithBirthdate();
	}

	/**
	 * enrichWithBirthdate()
	 * Fetches EnDob for all athletes in the result in a single query
	 * and adds 'birthdate' to each item array.
	 */
	private function enrichWithBirthdate()
	{
		if (empty($this->data['sections']))
			return;

		// Collect all unique athlete IDs
		$ids = array();
		foreach ($this->data['sections'] as $section)
		{
			foreach ($section['items'] as $item)
				$ids[] = (int)$item['id'];
		}

		if (empty($ids))
			return;

		$idList = implode(',', array_unique($ids));
		$q = safe_r_sql("SELECT EnId, EnDob FROM Entries WHERE EnId IN ({$idList})");
		$dobs = array();
		while ($row = safe_fetch($q))
			$dobs[$row->EnId] = $row->EnDob;
		safe_free_result($q);

		// Stitch birthdate into each item
		foreach ($this->data['sections'] as &$section)
		{
			foreach ($section['items'] as &$item)
				$item['birthdate'] = isset($dobs[$item['id']]) ? $dobs[$item['id']] : '0';
		}
		unset($section);
	}
}
