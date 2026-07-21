<?php namespace ProcessWire;

/** Per-profile click statistics workspace. */
trait LynxManagerStats {

    /* ---------------------------------------------------------------------
     * Click stats (per profile)
     * ------------------------------------------------------------------- */

    public function ___executeStats() {
        $modules = $this->wire('modules');
        $lynx = $this->lynx();
        $san = $this->wire('sanitizer');
        $id = (int) $this->wire('input')->get('id');
        $profile = $id ? $lynx->getProfile($id) : null;
        if(!$profile) { $this->error('Profile not found'); $this->wire('session')->redirect('../'); }

        $profileTitle = $profile['title'] ?: $profile['slug'];
        $this->setManagerChrome('Stats: ' . $profileTitle, 'Stats', "stats/?id={$id}");

        $stats = $lynx->getClickStats($id, 30);

        $totalClicks = array_sum(array_column($stats['perLink'], 'clicks'));
        $cards = array(
            array((int) $profile['views'], $this->_('Profile views')),
            array($totalClicks, $this->_('Total clicks')),
            array(count($stats['perLink']), $this->_('Links')),
        );
        $out = $this->header('stats/');
        $out .= $this->workspaceHead(
            $this->_('Profile stats'),
            $profileTitle,
            "<a class='uk-button uk-button-default' href='" . $this->editorUrl() . $san->entities($profile['slug']) . "'><i class='fa fa-pencil'></i> " . $this->_('Edit') . "</a>"
        );
        $out .= "<div class='uk-grid-small uk-child-width-1-3@s pw-stat-grid uk-margin' uk-grid>";
        foreach($cards as $c) {
            $out .= "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
                . "<span class='uk-text-meta'>{$c[1]}</span>"
                . "<strong class='pw-stat-value'>" . (int) $c[0] . "</strong></div></div>";
        }
        $out .= "</div>";

        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->setSortable(false);
        $table->headerRow(array($this->_('Link'), $this->_('Clicks')));
        uasort($stats['perLink'], fn($a, $b) => $b['clicks'] <=> $a['clicks']);
        foreach($stats['perLink'] as $row) {
            $table->row(array($san->entities($row['label'] ?: '(untitled)'), (string) $row['clicks']));
        }
        $out .= "<h3 class='uk-h4 uk-margin-top'>" . $this->_('Clicks by link') . "</h3><div class='pw-table-panel'>" . $table->render() . "</div>";

        $daily = $stats['daily'];
        $max = $daily ? max($daily) : 0;
        $dailyTotal = 0;
        $lastDayClicks = 0;
        $startDay = date('M j', time() - 29 * 86400);
        $endDay = date('M j', time());
        $dailyTable = $modules->get('MarkupAdminDataTable');
        $dailyTable->setEncodeEntities(false);
        $dailyTable->setSortable(false);
        $dailyTable->headerRow(array($this->_('Date'), $this->_('Clicks')));
        for($d = 29; $d >= 0; $d--) {
            $day = date('Y-m-d', time() - $d * 86400);
            $n = (int) ($daily[$day] ?? 0);
            $dailyTotal += $n;
            if($d === 0) $lastDayClicks = $n;
            $dailyTable->row(array($san->entities($day), (string) $n));
        }
        $out .= "<h3 class='uk-h4 uk-margin-top'>" . $this->_('Daily clicks (30 days)') . "</h3>"
            . "<div class='uk-card uk-card-default uk-card-body uk-card-small uk-margin-bottom'>"
            . "<div class='uk-grid-small uk-child-width-1-4@m uk-child-width-1-2@s' uk-grid>"
            . "<div><span class='uk-text-meta'>" . $this->_('Range') . "</span><div><strong>" . $san->entities($startDay) . " - " . $san->entities($endDay) . "</strong></div></div>"
            . "<div><span class='uk-text-meta'>" . $this->_('Total') . "</span><div><strong>" . (int) $dailyTotal . "</strong></div></div>"
            . "<div><span class='uk-text-meta'>" . $this->_('Peak') . "</span><div><strong>" . (int) $max . "</strong></div></div>"
            . "<div><span class='uk-text-meta'>" . $this->_('Today') . "</span><div><strong>" . (int) $lastDayClicks . "</strong></div></div>"
            . "</div></div>"
            . "<div class='pw-table-panel'>" . $dailyTable->render() . "</div>";

        return $out . $this->footer();
    }
}
