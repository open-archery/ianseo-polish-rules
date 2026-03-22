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
 */
class Obj_Rank_DivClass_calc extends Obj_Rank_DivClass
{
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
