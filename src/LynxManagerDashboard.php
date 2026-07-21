<?php namespace ProcessWire;

/** Dashboard rendering and dashboard actions. */
trait LynxManagerDashboard {

    /* ---------------------------------------------------------------------
     * Dashboard
     * ------------------------------------------------------------------- */

    public function ___execute() {
        $this->setManagerChrome('Lynx Manager');
        $modules = $this->wire('modules');
        $lynx = $this->lynx();
        $san = $this->wire('sanitizer');

        $this->handleDashboardPost();

        $profiles = $lynx->getProfiles();
        $totalProfiles = count($profiles);
        // Aggregate once instead of querying links per profile (avoids N+1).
        $linkCounts = $lynx->linkCountsByProfile();
        $blockCounts = $lynx->blockCountsByProfile();
        $clickTotals = $lynx->clickTotalsByProfile();
        $totalLinks = array_sum($linkCounts);
        $totalBlocks = array_sum($blockCounts);
        $totalClicks = array_sum($clickTotals);
        $totalViews = 0;
        $activeProfiles = 0;
        $inactiveProfiles = 0;
        $langCounts = array();
        $themeCounts = array();
        foreach($profiles as $p) {
            $totalViews += (int) $p['views'];
            if(!empty($p['active'])) $activeProfiles++;
            else $inactiveProfiles++;
            $profileLangs = array(strtoupper((string) ($p['lang'] ?: 'en')));
            $translations = json_decode((string) ($p['translations'] ?? ''), true);
            foreach(array_keys(is_array($translations) ? $translations : array()) as $translatedLang) {
                $profileLangs[] = strtoupper((string) $translatedLang);
            }
            $theme = $san->entities($p['theme'] ?: 'default');
            foreach(array_unique(array_filter($profileLangs)) as $lang) {
                $lang = $san->entities($lang);
                $langCounts[$lang] = ($langCounts[$lang] ?? 0) + 1;
            }
            $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;
        }
        arsort($langCounts);
        arsort($themeCounts);
        $avgLinks = $totalProfiles ? round($totalLinks / $totalProfiles, 1) : 0;
        $avgBlocks = $totalProfiles ? round($totalBlocks / $totalProfiles, 1) : 0;
        $clickRate = $totalViews ? round($totalClicks / $totalViews * 100, 1) . '%' : '0%';
        $root = trim((string) $lynx->rootUrl, '/');
        $publicRoot = $root === '' ? $this->_('Site root') : '/' . $root . '/';
        $supportedLangs = implode(', ', array_map('strtoupper', $lynx->getSupportedLanguages()));

        // Summary cards
        $cards = array(
            array($totalProfiles, $this->_('Profiles'), 'users', $activeProfiles . ' ' . $this->_('active')),
            array($totalLinks, $this->_('Links'), 'link', $avgLinks . ' ' . $this->_('avg/profile')),
            array($totalBlocks, $this->_('Blocks'), 'th-large', $avgBlocks . ' ' . $this->_('avg/profile')),
            array($totalViews, $this->_('Views'), 'eye', $clickRate . ' ' . $this->_('click rate')),
            array($totalClicks, $this->_('Clicks'), 'bolt', $inactiveProfiles . ' ' . $this->_('hidden')),
        );
        $base = $this->managerBaseUrl();
        $editorUrl = $this->editorUrl();
        $actions = "<a class='uk-button uk-button-primary' href='{$editorUrl}'><i class='fa fa-plus'></i> " . $this->_('New profile') . "</a>"
            . "<a class='uk-button uk-button-default' href='{$base}export/'><i class='fa fa-download'></i> " . $this->_('Export') . "</a>";

        $out = $this->header('');
        $out .= $this->workspaceHead(
            $this->_('Profiles'),
            $this->_('Review public profiles, activity and publishing status.'),
            $actions
        );
        $out .= "<div class='uk-grid-small uk-child-width-1-5@m uk-child-width-1-3@s pw-stat-grid uk-margin' uk-grid>";
        foreach($cards as $c) {
            $out .= "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
                . "<span class='uk-text-meta'><i class='fa fa-{$c[2]}'></i> {$c[1]}</span>"
                . "<strong class='pw-stat-value'>" . $san->entities((string) $c[0]) . "</strong>"
                . "<span class='uk-text-meta'>" . $san->entities((string) $c[3]) . "</span></div></div>";
        }
        $out .= "</div>";

        $byViews = $profiles;
        usort($byViews, fn($a, $b) => ((int) $b['views']) <=> ((int) $a['views']));
        $byClicks = $profiles;
        usort($byClicks, fn($a, $b) => ((int) ($clickTotals[$b['id']] ?? 0)) <=> ((int) ($clickTotals[$a['id']] ?? 0)));

        $settingsRows = array(
            array($this->_('Public profiles'), $this->statusPill((bool) $lynx->enablePublic)),
            array($this->_('REST API'), $this->statusPill((bool) $lynx->enableApi)),
            array($this->_('Public root'), $san->entities($publicRoot)),
            array($this->_('Default language'), strtoupper($san->entities($lynx->defaultLanguage ?: 'en'))),
            array($this->_('Languages'), $san->entities($supportedLangs)),
        );
        $out .= "<div class='uk-grid-small uk-child-width-1-3@m uk-margin-medium-bottom' uk-grid uk-height-match='target: > div > .uk-card'>"
            . "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
            . "<h4 class='uk-h5'>" . $this->_('Publishing') . "</h4>" . $this->dashboardRows($settingsRows) . "</div></div>"
            . "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
            . "<h4 class='uk-h5'>" . $this->_('Top by views') . "</h4>" . $this->profileMetricRows($byViews, $linkCounts, $clickTotals, 'views', $editorUrl, 5) . "</div></div>"
            . "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
            . "<h4 class='uk-h5'>" . $this->_('Top by clicks') . "</h4>" . $this->profileMetricRows($byClicks, $linkCounts, $clickTotals, 'clicks', $editorUrl, 5) . "</div></div>"
            . "</div>";

        $out .= "<div class='uk-grid-small uk-child-width-1-2@m uk-margin-medium-bottom' uk-grid>"
            . "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
            . "<h4 class='uk-h5'>" . $this->_('Languages') . "</h4>" . $this->dashboardTokens($langCounts) . "</div></div>"
            . "<div><div class='uk-card uk-card-default uk-card-body uk-card-small'>"
            . "<h4 class='uk-h5'>" . $this->_('Themes') . "</h4>" . $this->dashboardTokens($themeCounts) . "</div></div>"
            . "</div>";

        // Profiles table sorted by views
        $profiles = $byViews;
        $table = $modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->setSortable(false);
        $table->headerRow(array(
            $this->_('Title'), $this->_('Slug'), $this->_('Lang'), $this->_('Theme'),
            $this->_('Links'), $this->_('Blocks'), $this->_('Views'), $this->_('Clicks'),
            $this->_('CTR'), $this->_('Updated'), $this->_('Status'), '',
        ));
        foreach($profiles as $p) {
            $links = (int) ($linkCounts[$p['id']] ?? 0);
            $blocks = (int) ($blockCounts[$p['id']] ?? 0);
            $clicks = (int) ($clickTotals[$p['id']] ?? 0);
            $views = (int) $p['views'];
            $ctr = $views ? round($clicks / $views * 100, 1) . '%' : '0%';
            $slug = $san->entities($p['slug']);
            $modified = !empty($p['modified']) ? date('Y-m-d', (int) $p['modified']) : '';
            $deleteForm = "<form class='pw-inline-form' method='post' action='./' onsubmit=\"return confirm('" . $san->entities($this->_('Delete this profile and all its links, blocks and click history?')) . "');\">"
                . $this->wire('session')->CSRF->renderInput()
                . "<input type='hidden' name='profile_id' value='" . (int) $p['id'] . "'>"
                . "<button class='uk-button uk-button-small uk-button-danger' type='submit' name='delete_profile' value='1'>" . $this->_('delete') . "</button>"
                . "</form>";
            $actions = "<div class='pw-table-actions'><a href='{$base}stats/?id={$p['id']}'>" . $this->_('stats') . "</a>"
                . "<a href='{$editorUrl}$slug'>" . $this->_('edit') . "</a>"
                . "<a target='_blank' rel='noopener noreferrer' href='" . $lynx->publicPath($slug) . "'>" . $this->_('open') . "</a>"
                . $deleteForm . "</div>";
            $table->row(array(
                $san->entities($p['title'] ?: '(untitled)') => "{$editorUrl}$slug",
                $slug,
                strtoupper($san->entities($p['lang'] ?: 'en')),
                $san->entities($p['theme']),
                (string) $links,
                (string) $blocks,
                (string) $views,
                (string) $clicks,
                $san->entities($ctr),
                $san->entities($modified),
                $this->statusPill((bool) $p['active'], $this->_('active'), $this->_('hidden')),
                $actions,
            ));
        }
        $out .= $totalProfiles ? "<div class='pw-table-panel'>" . $table->render() . "</div>"
            : "<div class='pw-empty-state uk-margin-top'><h4 class='uk-h4'>" . $this->_('No profiles yet') . "</h4>"
                . "<p class='uk-text-muted'>" . $this->_('Create your first Lynx profile to publish links, media blocks and translations.') . "</p>"
                . "<a class='uk-button uk-button-primary' href='{$editorUrl}'><i class='fa fa-plus'></i> " . $this->_('New profile') . "</a></div>";

        $out .= "<hr class='uk-margin-medium-top'>"
            . "<div class='uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-margin'>"
            . "<div><strong>" . $this->_('Need to change routes, languages, defaults or themes?') . "</strong>"
            . "<br><span class='uk-text-meta'>" . $this->_('All Lynx settings are available in one manager screen.') . "</span></div>"
            . "<a class='uk-button uk-button-default' href='{$base}settings/'><i class='fa fa-sliders'></i> " . $this->_('Open settings') . "</a>"
            . "</div>";

        return $out . $this->footer();
    }

    /** Handle destructive dashboard actions through POST + CSRF only. */
    protected function handleDashboardPost() {
        $input = $this->wire('input');
        if(!$input->post('delete_profile')) return;

        $session = $this->wire('session');
        if(!$session->CSRF->hasValidToken()) {
            $this->error($this->_('Invalid CSRF token.'));
            $session->redirect($this->managerBaseUrl());
        }

        $lynx = $this->lynx();
        $id = (int) $input->post('profile_id');
        $profile = $id ? $lynx->getProfile($id) : null;
        if(!$profile) {
            $this->error($this->_('Profile not found.'));
            $session->redirect($this->managerBaseUrl());
        }

        $title = $profile['title'] ?: $profile['slug'];
        $lynx->deleteProfile($id);
        $this->message(sprintf($this->_('Deleted profile: %s'), $title));
        $session->redirect($this->managerBaseUrl());
    }
}
