<?php
/**
 * Pure calculation helpers for the live qualification view.
 *
 * No DB access here — see LiveQualificationData.php for the query that feeds
 * these functions their input.
 */

/**
 * Counts arrows already shot in a fixed-width, right-padded arrow string
 * (QuD{n}ArrowString): everything up to the trailing run of spaces.
 */
function pl_live_qual_arrows_shot(string $arrowString): int
{
    return strlen(rtrim($arrowString));
}

/**
 * An entry is "lacking results" when it trails the session+distance's current
 * max arrows-shot by a full end or more (e.g. 30 vs 24 with 6 arrows/end).
 * A smaller gap is ordinary shoot-order stagger within a target, not a stall.
 */
function pl_live_qual_is_behind(int $arrowsShot, int $maxArrows, int $arrowsPerEnd): bool
{
    if ($arrowsPerEnd <= 0) {
        return false;
    }

    return ($maxArrows - $arrowsShot) >= $arrowsPerEnd;
}
