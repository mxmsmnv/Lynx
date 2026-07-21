<?php namespace ProcessWire;

/** Shared ProcessWire manager chrome and presentation helpers. */
trait LynxManagerUi {

    /* ---------------------------------------------------------------------
     * Design-system chrome (pw-wrap panel + uk-tab section nav)
     * ------------------------------------------------------------------- */

    /** Admin page URL for this module (works before $page is the manager page). */
    protected function managerBaseUrl() {
        $page = $this->wire('page');
        return ($page && $page->id) ? $page->url : ($this->wire('config')->urls->admin . 'setup/lynx-manager/');
    }

    /** Public URL of a manager asset with a cache-busting file timestamp. */
    protected function managerAssetUrl($file) {
        $config = $this->wire('config');
        $dir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
        $root = rtrim($config->paths->root, '/') . '/';
        $base = strpos($dir, $root) === 0
            ? $config->urls->root . substr($dir, strlen($root))
            : $config->urls->siteModules . 'Lynx/';
        $path = dirname(__DIR__) . '/assets/' . $file;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';
        return $base . 'assets/' . $file . $version;
    }

    /** Load the scoped pw-design-system module workspace bridge. */
    protected function managerAssets() {
        $this->wire('config')->styles->add($this->managerAssetUrl('manager.css'));
    }

    /** Set ProcessWire headline and breadcrumbs in the same shape as Ichiban. */
    protected function setManagerChrome($headline, $label = '', $path = '') {
        $this->headline($headline);
        $this->browserTitle($headline);
        $base = $this->managerBaseUrl();
        if($label === '') {
            $this->breadcrumb($base, 'Lynx Manager');
        } else {
            $this->breadcrumb($base, 'Lynx Manager');
            if($path !== '') $this->breadcrumb($base . $path, $label);
        }
    }

    /** Native AdminThemeUikit section nav (uk-tab), theme-aware via --pw-* tokens. */
    protected function sectionNav($current = '') {
        $base = $this->managerBaseUrl();
        $items = array(
            ''        => $this->_('Dashboard'),
            'export/' => $this->_('Export'),
            'import/' => $this->_('Import'),
            'demo/'   => $this->_('Install demo'),
            'themes/' => $this->_('Theme gallery'),
            'settings/' => $this->_('Settings'),
        );
        $out = "<ul class='uk-tab pw-module-tabs uk-margin-medium-bottom' aria-label='Lynx Manager sections'>";
        foreach($items as $seg => $label) {
            $active = $current === $seg ? ' uk-active' : '';
            $out .= "<li class='$active'><a href='{$base}{$seg}'>" . $label . "</a></li>";
        }
        return $out . "</ul>";
    }

    /** Open the native module workspace and render the section nav. */
    protected function header($current = '') {
        $this->managerAssets();
        return "<div class='pw-wrap pw-module-workspace'>" . $this->sectionNav($current);
    }

    /** Close the scoped panel. */
    protected function footer() {
        return "</div>";
    }

    /** Native design-system page head: title, context and 1-2 primary actions. */
    protected function workspaceHead($title, $description = '', $actions = '') {
        $san = $this->wire('sanitizer');
        $out = "<div class='pw-module-head'><div><h3 class='uk-h3'>" . $san->entities($title) . "</h3>";
        if($description !== '') $out .= "<p class='uk-text-muted'>" . $san->entities($description) . "</p>";
        $out .= "</div>";
        if($actions !== '') $out .= "<div class='pw-module-actions'>$actions</div>";
        return $out . "</div>";
    }

    protected function statusPill($on, $onLabel = 'On', $offLabel = 'Off') {
        $san = $this->wire('sanitizer');
        $label = $on ? $onLabel : $offLabel;
        $class = $on ? 'uk-label-success' : 'uk-label-danger';
        return "<span class='uk-label {$class}'>" . $san->entities($label) . "</span>";
    }

    protected function dashboardRows(array $rows, $htmlLabels = false) {
        $san = $this->wire('sanitizer');
        $out = "<ul class='uk-list uk-list-divider uk-margin-remove'>";
        foreach($rows as $row) {
            $label = $htmlLabels ? (string) $row[0] : $san->entities((string) $row[0]);
            $value = (string) $row[1];
            $out .= "<li><div class='uk-flex uk-flex-between uk-flex-middle uk-flex-wrap'>"
                . "<span class='uk-text-meta'>{$label}</span><strong>{$value}</strong></div></li>";
        }
        return count($rows) ? $out . "</ul>" : "<p class='uk-text-muted'>" . $this->_('No data yet') . "</p>";
    }

    protected function dashboardTokens(array $items) {
        $san = $this->wire('sanitizer');
        if(!$items) return "<p class='uk-text-muted'>" . $this->_('No data yet') . "</p>";
        $out = "<p class='uk-margin-remove'>";
        foreach($items as $label => $count) {
            $out .= "<span class='uk-label'>" . $san->entities((string) $label) . " " . (int) $count . "</span> ";
        }
        return $out . "</p>";
    }

    protected function profileMetricRows(array $profiles, array $linkCounts, array $clickTotals, $metric, $editorUrl, $limit = 5) {
        $san = $this->wire('sanitizer');
        $rows = array();
        foreach(array_slice($profiles, 0, $limit) as $p) {
            $id = (int) $p['id'];
            $title = $san->entities($p['title'] ?: $p['slug']);
            $slug = $san->entities($p['slug']);
            $value = $metric === 'clicks' ? (int) ($clickTotals[$id] ?? 0) : (int) $p['views'];
            $meta = (int) ($linkCounts[$id] ?? 0) . ' ' . $this->_('links');
            $rows[] = array(
                "<a href='{$editorUrl}{$slug}'>{$title}</a><br><span class='uk-text-meta'>" . $san->entities($meta) . "</span>",
                (string) $value,
            );
        }
        return $this->dashboardRows($rows, true);
    }

    protected function editorUrl() {
        return rtrim($this->lynx()->publicPath('edit/'), '/') . '/';
    }
}
