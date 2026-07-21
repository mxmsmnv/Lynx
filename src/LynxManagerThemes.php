<?php namespace ProcessWire;

/** Theme gallery and isolated theme previews. */
trait LynxManagerThemes {

    /* ---------------------------------------------------------------------
     * Theme gallery
     * ------------------------------------------------------------------- */

    public function ___executeThemes() {
        $this->setManagerChrome('Theme gallery', 'Theme gallery', 'themes/');
        $lynx = $this->lynx();
        $san = $this->wire('sanitizer');

        $themes = $lynx->getThemes();
        $profiles = $lynx->getProfiles();
        $builtIns = array_keys($lynx->themeBlueprints('builtIn'));
        if(!$builtIns) $builtIns = array('default', 'dark', 'glass', 'mono', 'pill');
        $themeCounts = array();
        foreach($profiles as $profile) {
            $theme = (string) ($profile['theme'] ?: 'default');
            $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;
        }
        $defaultTheme = (string) ($this->defaultTheme ?: 'default');
        $customCount = 0;
        foreach(array_keys($themes) as $themeId) if(!in_array($themeId, $builtIns, true)) $customCount++;

        $settingsUrl = $this->managerBaseUrl() . 'settings/';
        $actions = "<a class='uk-button uk-button-primary' href='" . $this->editorUrl() . "'><i class='fa fa-plus'></i> " . $this->_('New profile') . "</a>"
            . "<a class='uk-button uk-button-default' href='{$settingsUrl}'><i class='fa fa-sliders'></i> " . $this->_('Theme settings') . "</a>";

        $out = $this->header('themes/');
        $out .= $this->workspaceHead(
            $this->_('Theme gallery'),
            $this->_('Preview built-in and custom themes. Choose one for each profile in Appearance.'),
            $actions
        );

        $summaryRows = array(
            array($this->_('Available themes'), (string) count($themes)),
            array($this->_('Built-in'), (string) (count($themes) - $customCount)),
            array($this->_('Custom'), (string) $customCount),
            array($this->_('Default for new profiles'), "<code>" . $san->entities($defaultTheme) . "</code>"),
        );
        $out .= "<div class='uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom'>"
            . $this->dashboardRows($summaryRows) . "</div>";

        $out .= "<div class='uk-grid-small uk-child-width-1-3@m' uk-grid uk-height-match='target: > div > .uk-card'>";
        foreach($themes as $id => $t) {
            $origin = in_array($id, $builtIns, true) ? $this->_('Built-in') : $this->_('Custom');
            $usage = (int) ($themeCounts[$id] ?? 0);
            $meta = "<span class='uk-label'>" . $san->entities($origin) . "</span> "
                . "<span class='uk-label'>" . $usage . " " . $this->_('profiles') . "</span>";
            if($id === $defaultTheme) $meta .= " <span class='uk-label uk-label-success'>" . $this->_('Default') . "</span>";
            foreach(($t['tags'] ?? array()) as $tag) {
                $meta .= " <span class='uk-label'>" . $san->entities($tag) . "</span>";
            }

            $previewData = is_array($t['preview'] ?? null) ? $t['preview'] : array();
            $accent = (string) ($previewData['accent'] ?? '#1e87f0');
            if(!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#1e87f0';
            $sampleTitle = (string) ($previewData['sampleTitle'] ?? 'Jane Demo');
            $sampleBio = (string) ($previewData['sampleBio'] ?? 'Links, blocks and multilingual profile');

            $out .= "<div><div class='uk-card uk-card-default uk-card-body uk-card-small lynx-theme-card'>"
                . "<h4 class='uk-h5 uk-margin-remove-bottom'>" . $san->entities($t['label']) . "</h4>"
                . "<code class='uk-text-small'>" . $san->entities($id) . "</code>"
                . (!empty($t['description']) ? "<p class='uk-text-meta uk-margin-small-top'>" . $san->entities($t['description']) . "</p>" : "")
                . "<p class='uk-margin-small'>$meta</p>"
                . $this->themePreviewFrame($id, $t, $sampleTitle, $sampleBio, $accent)
                . "<p class='uk-text-meta uk-margin-small-top uk-margin-remove-bottom'>"
                . $this->_('Live preview') . " · <code>" . $san->entities($accent) . "</code></p>"
                . "<div class='pw-module-actions uk-margin-top'>"
                . "<a class='uk-button uk-button-small uk-button-primary' href='" . $this->editorUrl() . "'><i class='fa fa-pencil'></i> " . $this->_('Use in profile') . "</a>"
                . "<a class='uk-button uk-button-small uk-button-default' href='{$settingsUrl}'><i class='fa fa-sliders'></i> " . $this->_('Settings') . "</a>"
                . "</div>"
                . "</div></div>";
        }
        $out .= "</div>";
        return $out . $this->footer();
    }

    /** Render a self-contained, isolated public-profile demo for one theme. */
    protected function themePreviewFrame($id, array $theme, $sampleTitle, $sampleBio, $accent) {
        $san = $this->wire('sanitizer');
        $cssFile = dirname(__DIR__) . '/assets/public.css';
        $baseCss = is_file($cssFile) ? (string) file_get_contents($cssFile) : '';
        $themeCss = (string) ($theme['css'] ?? '');
        $label = (string) ($theme['label'] ?? $id);
        $compactCss = "body{min-height:100vh;padding:26px 18px;background-attachment:scroll}"
            . ".lynx-profile{max-width:460px}.lynx-avatar{width:64px;height:64px;margin:0 auto;display:grid;place-items:center;background:var(--lynx-accent);color:#fff;font-weight:700;border:3px solid rgba(255,255,255,.85)}"
            . ".lynx-title{font-size:1.2rem;margin:10px 0 3px}.lynx-bio{margin-bottom:16px;font-size:.88rem}"
            . ".lynx-links{gap:8px}.lynx-link{padding:11px 14px}.lynx-block{margin:18px 0 0}"
            . ".lynx-quote{padding:12px 14px;font-size:.86rem}.lynx-quote p{margin-bottom:5px}";
        $doc = "<!doctype html><html lang='en'><head><meta charset='utf-8'>"
            . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
            . "<style>$baseCss\n$compactCss\n$themeCss</style></head>"
            . "<body><main class='lynx-profile' style='--lynx-accent:" . $san->entities($accent) . "'>"
            . "<div class='lynx-avatar' aria-hidden='true'>LY</div>"
            . "<h1 class='lynx-title'>" . $san->entities($sampleTitle) . "</h1>"
            . "<p class='lynx-bio'>" . $san->entities($sampleBio) . "</p>"
            . "<div class='lynx-links'>"
            . "<a class='lynx-link' href='#' tabindex='-1'><i aria-hidden='true'>↗</i> Portfolio</a>"
            . "<a class='lynx-link' href='#' tabindex='-1'><i aria-hidden='true'>✦</i> Latest project</a>"
            . "</div><section class='lynx-block'>"
            . "<blockquote class='lynx-quote'><p>Build quietly, ship clearly.</p><cite>Lynx demo</cite></blockquote>"
            . "</section></main></body></html>";
        return "<iframe class='lynx-theme-preview' title='" . $san->entities(sprintf($this->_('%s theme preview'), $label))
            . "' sandbox srcdoc='" . $san->entities($doc) . "'></iframe>";
    }
}
