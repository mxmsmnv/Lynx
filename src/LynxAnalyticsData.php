<?php namespace ProcessWire;

/** View and click tracking, statistics, and retention. */
trait LynxAnalyticsData {

    protected function incViews($profileId) {
        $this->wire('database')->prepare("UPDATE " . self::TABLE_PROFILES . " SET views=views+1 WHERE id=:id")
            ->execute(array(':id' => (int) $profileId));
    }

    protected function trackAndRedirect($linkId) {
        $db = $this->wire('database');
        $q = $db->prepare("SELECT l.url FROM " . self::TABLE_LINKS . " l
            JOIN " . self::TABLE_PROFILES . " p ON p.id = l.profile_id
            WHERE l.id=:id AND l.active=1 AND p.active=1
              AND (l.start_date = 0 OR l.start_date <= :now1)
              AND (l.end_date = 0 OR l.end_date >= :now2)");
        $now = time();
        $q->execute(array(':id' => (int) $linkId, ':now1' => $now, ':now2' => $now));
        $url = $q->fetchColumn();
        if(!$url) { $this->wire('session')->redirect('/'); return; }
        if($this->trackClicks) {
            $db->prepare("UPDATE " . self::TABLE_LINKS . " SET clicks=clicks+1 WHERE id=:id")
                ->execute(array(':id' => (int) $linkId));
            $db->prepare("INSERT INTO " . self::TABLE_CLICKS . " (link_id, ts, ref) VALUES (:l,:t,:r)")
                ->execute(array(
                    ':l' => (int) $linkId,
                    ':t' => time(),
                    ':r' => substr($this->wire('sanitizer')->text($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
                ));
        }
        $this->wire('session')->redirect($url, false);
    }

    /**
     * Handle an avatar file upload for a profile. Returns the public URL or ''.
     * Files are stored in /site/assets/files/lynx/{profileId}-{name}.
     */

    /* ----- Clicks / stats / retention ----- */

    /** Aggregate click stats for a profile (per link + daily totals). */
    public function getClickStats($profileId, $days = 30) {
        $db = $this->wire('database');
        $since = time() - ($days * 86400);

        $links = $this->getLinks($profileId);
        $perLink = array();
        foreach($links as $l) $perLink[(int) $l['id']] = array('label' => $l['label'], 'clicks' => (int) $l['clicks']);

        // daily totals across this profile's links
        $q = $db->prepare(
            "SELECT FROM_UNIXTIME(c.ts, '%Y-%m-%d') AS d, COUNT(*) AS n
             FROM " . self::TABLE_CLICKS . " c
             JOIN " . self::TABLE_LINKS . " l ON l.id = c.link_id
             WHERE l.profile_id = :p AND c.ts >= :since
             GROUP BY d ORDER BY d"
        );
        $q->execute(array(':p' => (int) $profileId, ':since' => $since));
        $daily = $q->fetchAll(\PDO::FETCH_KEY_PAIR);

        return array('perLink' => $perLink, 'daily' => $daily);
    }

    /** LazyCron handler: delete detailed click rows past the retention window. */
    public function purgeOldClicks(?HookEvent $e = null) {
        $days = (int) $this->clickRetention;
        if($days <= 0) return 0;
        $cutoff = time() - ($days * 86400);
        $q = $this->wire('database')->prepare("DELETE FROM " . self::TABLE_CLICKS . " WHERE ts < :c");
        $q->execute(array(':c' => $cutoff));
        return $q->rowCount();
    }
}
