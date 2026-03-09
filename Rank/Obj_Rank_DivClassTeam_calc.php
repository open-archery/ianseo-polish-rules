<?php
/**
 * Poland-specific team qualification calculator:
 * - Teams may have 3 or 4 athletes.
 * - For qualification totals, only the best 3 athletes count.
 * - Recomputes TeScore/TeGold/TeXnine/TeHits from TeamComponent -> Qualifications
 *   selecting top 3 by QuScore, then QuGold, then QuXnine.
 * - Then applies default ranking (by TeScore, TeGold, TeXnine).
 */
class Obj_Rank_DivClassTeam_calc extends Obj_Rank_DivClassTeam
{
    public function calculate()
    {
        $filter = $this->safeFilter();

        // 1) Recompute team totals as sum of best 3 of up to 4 components
        //    We update all relevant Teams rows for this tournament and filters.
        //    Join DivClass only if filtering by div/cl is in effect (handled in the WHERE via $filter).
        $teamsSql = "
            SELECT TeTournament, TeCoId, TeSubTeam, TeEvent
            FROM Tournament
            INNER JOIN Teams ON ToId=TeTournament AND TeFinEvent=0
            INNER JOIN IrmTypes ON IrmId=TeIrmType AND IrmShowRank=1
            LEFT JOIN (
                SELECT CONCAT(DivId, ClId) DivClass, Divisions.*, Classes.*
                FROM Divisions INNER JOIN Classes ON DivTournament=ClTournament
                WHERE DivAthlete AND ClAthlete
            ) AS DivClass ON TeEvent=DivClass AND TeTournament=DivTournament
            WHERE ToId={$this->tournament}
            {$filter}
        ";

        $rt = safe_r_sql($teamsSql);
        if ($rt && safe_num_rows($rt) > 0) {
            while ($t = safe_fetch($rt)) {
                $compSql = "
                    SELECT QuScore, QuGold, QuXnine, QuHits
                    FROM TeamComponent
                    INNER JOIN Qualifications ON TcId=QuId
                    WHERE TcTournament={$this->tournament}
                      AND TcFinEvent=0
                      AND TcCoId={$t->TeCoId}
                      AND TcSubTeam={$t->TeSubTeam}
                      AND TcEvent=" . StrSafe_DB($t->TeEvent) . "
                    ORDER BY QuScore DESC, QuGold DESC, QuXnine DESC
                    LIMIT 4
                ";

                $rc = safe_r_sql($compSql);
                $sumScore = 0; $sumGold = 0; $sumXnine = 0; $sumHits = 0; $counted = 0;
                if ($rc && safe_num_rows($rc) > 0) {
                    while (($row = safe_fetch($rc)) && $counted < 3) {
                        $sumScore += (int)$row->QuScore;
                        $sumGold  += (int)$row->QuGold;
                        $sumXnine += (int)$row->QuXnine;
                        $sumHits  += isset($row->QuHits) ? (int)$row->QuHits : 0;
                        $counted++;
                    }
                }

                $date = date('Y-m-d H:i:s');
                $upd = "
                    UPDATE Teams
                    SET TeScore={$sumScore}, TeGold={$sumGold}, TeXnine={$sumXnine}, TeHits={$sumHits}, TeTimeStamp='{$date}'
                    WHERE TeTournament={$this->tournament}
                      AND TeCoId={$t->TeCoId}
                      AND TeSubTeam={$t->TeSubTeam}
                      AND TeFinEvent=0
                      AND TeEvent=" . StrSafe_DB($t->TeEvent) . "
                ";
                safe_w_sql($upd);
            }
        }

        // 2) Rank teams by totals (default behavior)
        $orderBy = "TeEvent, TeScore DESC, TeGold DESC, TeXnine DESC, TeSubTeam ";
        $q = "
            SELECT ToId, TeCoId, TeSubTeam, TeEvent, TeScore, TeGold, TeXnine
            FROM Tournament
            INNER JOIN Teams ON ToId=TeTournament AND TeFinEvent=0
            INNER JOIN IrmTypes ON IrmId=TeIrmType AND IrmShowRank=1
            LEFT JOIN (
                SELECT CONCAT(DivId, ClId) DivClass, Divisions.*, Classes.*
                FROM Divisions INNER JOIN Classes ON DivTournament=ClTournament
                WHERE DivAthlete AND ClAthlete
            ) AS DivClass ON TeEvent=DivClass AND TeTournament=DivTournament
            WHERE ToId={$this->tournament} AND TeScore<>0
            {$filter}
            ORDER BY {$orderBy}
        ";

        $r = safe_r_sql($q);
        if (!$r) return false;

        $myEv = '';
        $myTeam = '';
        $rank = 1; $pos = 0;
        $scoreOld = 0; $goldOld = 0; $xNineOld = 0;

        if (safe_num_rows($r) > 0) {
            while ($row = safe_fetch($r)) {
                if ($myEv != $row->TeEvent) {
                    $myEv = $row->TeEvent;
                    $rank = 1; $pos = 0;
                    $scoreOld = 0; $goldOld = 0; $xNineOld = 0;
                    $myTeam = '';
                }

                if ($myTeam != $row->TeCoId) {
                    $myTeam = $row->TeCoId;
                    ++$pos;
                    if (!($row->TeScore == $scoreOld && $row->TeGold == $goldOld && $row->TeXnine == $xNineOld)) {
                        $rank = $pos;
                    }
                    $date = date('Y-m-d H:i:s');
                    $u = "
                        UPDATE Teams
                        SET TeRank={$rank}, TeTimeStamp='{$date}'
                        WHERE TeTournament={$this->tournament}
                          AND TeCoId={$row->TeCoId}
                          AND TeSubTeam={$row->TeSubTeam}
                          AND TeFinEvent=0
                          AND TeEvent='{$row->TeEvent}'
                    ";
                    safe_w_sql($u);
                }

                $scoreOld = $row->TeScore;
                $goldOld = $row->TeGold;
                $xNineOld = $row->TeXnine;
            }
        }

        return true;
    }
}
