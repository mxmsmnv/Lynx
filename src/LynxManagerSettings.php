<?php namespace ProcessWire;

/** Unified Lynx and LynxManager settings workspace. */
trait LynxManagerSettings {

    /* ---------------------------------------------------------------------
     * Unified settings
     * ------------------------------------------------------------------- */

    public function ___executeSettings() {
        $this->setManagerChrome('Lynx settings', 'Settings', 'settings/');
        $modules = $this->wire('modules');
        $input = $this->wire('input');

        if($input->post('submit_settings')) {
            if(!$this->validManagerCsrf()) return '';
            if($this->saveUnifiedSettings()) {
                $this->message($this->_('Lynx settings saved.'));
                $this->wire('session')->redirect('./');
            }
        }

        $form = $this->settingsForm((array) $modules->getConfig('Lynx'), (array) $modules->getConfig('LynxManager'));

        return $this->header('settings/')
            . $this->workspaceHead(
                $this->_('Lynx settings'),
                $this->_('Manage public routes, API behavior, analytics, languages, profile defaults and custom themes in one place.')
            )
            . $form->render()
            . $this->footer();
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    /** Reject raw manager POST handlers unless ProcessWire's CSRF token is valid. */
    protected function validManagerCsrf() {
        if($this->wire('session')->CSRF->hasValidToken()) return true;
        $this->error($this->_('Invalid CSRF token.'));
        http_response_code(403);
        return false;
    }

    protected function settingsForm(array $lynxData, array $managerData) {
        $modules = $this->wire('modules');
        $lynx = $this->lynx();
        $form = $modules->get('InputfieldForm');
        $form->method = 'post';
        $form->action = './';

        $routing = $modules->get('InputfieldFieldset');
        $routing->label = $this->_('Public routes, API and analytics');
        $routing->description = $this->_('These settings control where profiles are served and what public data is available.');
        $form->add($routing);

        $this->addText($routing, 'lynx_rootUrl', $this->_('Public root segment'), $lynxData['rootUrl'] ?? 'l', 100,
            $this->_('Profiles are served at /{segment}/{slug}. Leave blank to serve them from /{slug}.'));
        $this->addToggle($routing, 'lynx_enablePublic', $this->_('Enable public pages'), $lynxData['enablePublic'] ?? 1, 33,
            $this->_('When off, profile routes return not found while admin management and stored data remain available.'));
        $this->addToggle($routing, 'lynx_enableApi', $this->_('Enable REST API'), $lynxData['enableApi'] ?? 1, 33,
            $this->_('When on, active public profiles are exposed through /{root}/api endpoints.'));
        $this->addToggle($routing, 'lynx_trackClicks', $this->_('Track link clicks'), $lynxData['trackClicks'] ?? 1, 34,
            $this->_('Records redirect clicks for analytics. Public preview requests do not count as views.'));
        $this->addText($routing, 'lynx_defaultLanguage', $this->_('Default profile language'), $lynxData['defaultLanguage'] ?? 'en', 50,
            $this->_('ISO language code used when a profile does not specify its own base language.'));
        $this->addText($routing, 'lynx_supportedLanguages', $this->_('Supported public languages'), $lynxData['supportedLanguages'] ?? 'en,de,fr,nl,it,es,pt,ru,pl,cs,fi,bg,zh,ka,ja', 50,
            $this->_('Comma-separated ISO language codes. Profiles can be served at /{root}/{lang}/{slug}.'),
            $this->_('Keep the default language in this list. Unsupported language requests fall back to the profile base language.'));
        $this->addInteger($routing, 'lynx_clickRetention', $this->_('Click history retention (days)'), $lynxData['clickRetention'] ?? 90, 50,
            $this->_('Detailed click rows older than this are purged daily via LazyCron. Per-link totals are kept. 0 = keep forever.'));
        $this->addInteger($routing, 'lynx_cacheTtl', $this->_('Public page cache (seconds)'), $lynxData['cacheTtl'] ?? 0, 50,
            $this->_('Cache rendered public pages for anonymous visitors via WireCache. 0 = disabled. Pages are invalidated when profile content changes.'));

        $home = $modules->get('InputfieldMarkup');
        $home->name = 'lynx_homepage_embed';
        $home->label = $this->_('Use one profile as the homepage');
        $home->description = $this->_('For a one-profile website, render a Lynx profile directly from a ProcessWire template such as site/templates/home.php.');
        $home->notes = $this->_('Replace max with the profile slug you want to publish on the homepage. Public Lynx routes can stay enabled or be used only for editor previews.');
        $home->value = "<pre><code>&lt;?php namespace ProcessWire;\n\n\$lynx = \$modules-&gt;get('Lynx');\necho \$lynx-&gt;render('max');</code></pre>";
        $routing->add($home);

        $appearance = $modules->get('InputfieldFieldset');
        $appearance->label = $this->_('Profile defaults and themes');
        $appearance->description = $this->_('These defaults apply to newly created profiles. Existing profiles keep their own selections.');
        $form->add($appearance);

        $theme = $modules->get('InputfieldSelect');
        $theme->name = 'manager_defaultTheme';
        $theme->label = $this->_('Default theme for new profiles');
        $theme->description = $this->_('Used only when creating a new profile. Existing profiles keep their selected theme.');
        $theme->columnWidth = 50;
        foreach($lynx->getThemes() as $id => $t) $theme->addOption($id, $t['label']);
        $theme->value = $managerData['defaultTheme'] ?? 'default';
        $appearance->add($theme);

        $font = $modules->get('InputfieldSelect');
        $font->name = 'manager_defaultFont';
        $font->label = $this->_('Default font for new profiles');
        $font->description = $this->_('Open-source font loaded from Bunny Fonts on public pages. System UI makes no external font request.');
        $font->columnWidth = 50;
        foreach($lynx->getFonts() as $id => $fontData) $font->addOption($id, $fontData['label']);
        $font->value = $managerData['defaultFont'] ?? '';
        $appearance->add($font);

        $this->addText($appearance, 'manager_defaultAccent', $this->_('Default accent color'), $managerData['defaultAccent'] ?? '#1e87f0', 50,
            $this->_('Hex color used by themes that reference var(--lynx-accent).'), $this->_('Use a full hex value such as #1e87f0.'));
        $this->addText($appearance, 'manager_brandName', $this->_('Brand name'), $managerData['brandName'] ?? '', 50,
            $this->_('Shown as a small footer credit on public profile pages when filled.'), $this->_('Leave empty for no global footer credit.'));

        $custom = $modules->get('InputfieldTextarea');
        $custom->name = 'manager_customThemes';
        $custom->label = $this->_('Custom themes JSON');
        $custom->description = $this->_('Add project themes without editing PHP. Theme IDs must be unique and should use letters, numbers or underscores.');
        $custom->notes = $this->_('Use a JSON map with styles.tailwind selector utilities or styles.css. More blueprints live in blueprints/themes/.');
        $custom->rows = 8;
        $custom->value = $managerData['customThemes'] ?? '';
        $appearance->add($custom);

        $hidden = $modules->get('InputfieldHidden');
        $hidden->name = 'submit_settings';
        $hidden->value = 1;
        $form->add($hidden);

        $submit = $modules->get('InputfieldSubmit');
        $submit->name = 'submit_save_settings';
        $submit->value = $this->_('Save settings');
        $submit->icon = 'save';
        $form->add($submit);

        return $form;
    }

    protected function saveUnifiedSettings() {
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $san = $this->wire('sanitizer');
        $lynx = $this->lynx();
        $lynxConfig = (array) $modules->getConfig('Lynx');
        $managerConfig = (array) $modules->getConfig('LynxManager');

        $root = trim((string) $input->post('lynx_rootUrl'), '/');
        $root = $root === '' ? '' : $san->pageName($root, true);
        $defaultLanguage = $this->cleanLanguageCode($input->post('lynx_defaultLanguage') ?: 'en');
        $supportedLanguages = $this->cleanLanguageList($input->post('lynx_supportedLanguages') ?: $defaultLanguage, $defaultLanguage);

        $lynxConfig['rootUrl'] = $root;
        $lynxConfig['enablePublic'] = (int) (bool) $input->post('lynx_enablePublic');
        $lynxConfig['enableApi'] = (int) (bool) $input->post('lynx_enableApi');
        $lynxConfig['trackClicks'] = (int) (bool) $input->post('lynx_trackClicks');
        $lynxConfig['defaultLanguage'] = $defaultLanguage;
        $lynxConfig['supportedLanguages'] = $supportedLanguages;
        $lynxConfig['clickRetention'] = max(0, (int) $input->post('lynx_clickRetention'));
        $lynxConfig['cacheTtl'] = max(0, (int) $input->post('lynx_cacheTtl'));

        $defaultTheme = $san->fieldName($input->post('manager_defaultTheme') ?: 'default');
        if(!isset($lynx->getThemes()[$defaultTheme])) $defaultTheme = 'default';
        $defaultFont = $san->fieldName($input->post('manager_defaultFont') ?: '');
        if(!isset($lynx->getFonts()[$defaultFont])) $defaultFont = '';
        $accent = trim((string) $input->post('manager_defaultAccent'));
        if(!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = '#1e87f0';
        $customThemes = trim((string) $input->post('manager_customThemes'));
        if($customThemes !== '' && !is_array(json_decode($customThemes, true))) {
            $this->error($this->_('Custom themes JSON could not be parsed.'));
            return false;
        }

        $managerConfig['defaultTheme'] = $defaultTheme;
        $managerConfig['defaultFont'] = $defaultFont;
        $managerConfig['defaultAccent'] = strtolower($accent);
        $managerConfig['brandName'] = $san->text($input->post('manager_brandName') ?: '');
        $managerConfig['customThemes'] = $customThemes;

        $modules->saveConfig('Lynx', $lynxConfig);
        $modules->saveConfig('LynxManager', $managerConfig);
        $lynx->clearPublicPageCache();
        return true;
    }

    protected function cleanLanguageCode($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z-]/', '', $value);
        return $value !== '' ? $value : 'en';
    }

    protected function cleanLanguageList($value, $defaultLanguage) {
        $langs = array();
        foreach(explode(',', (string) $value) as $lang) {
            $lang = $this->cleanLanguageCode($lang);
            if($lang !== '' && !in_array($lang, $langs, true)) $langs[] = $lang;
        }
        if(!in_array($defaultLanguage, $langs, true)) array_unshift($langs, $defaultLanguage);
        return implode(',', $langs);
    }

    protected function addText($parent, $name, $label, $value, $width = 100, $description = '', $notes = '') {
        $f = $this->wire('modules')->get('InputfieldText');
        $f->name = $name;
        $f->label = $label;
        $f->value = $value;
        $f->columnWidth = $width;
        if($description !== '') $f->description = $description;
        if($notes !== '') $f->notes = $notes;
        $parent->add($f);
        return $f;
    }

    protected function addInteger($parent, $name, $label, $value, $width = 100, $description = '', $notes = '') {
        $f = $this->wire('modules')->get('InputfieldInteger');
        $f->name = $name;
        $f->label = $label;
        $f->value = $value;
        $f->columnWidth = $width;
        if($description !== '') $f->description = $description;
        if($notes !== '') $f->notes = $notes;
        $parent->add($f);
        return $f;
    }

    protected function addToggle($parent, $name, $label, $value, $width = 100, $description = '', $notes = '') {
        $f = $this->wire('modules')->get('InputfieldToggle');
        $f->name = $name;
        $f->label = $label;
        $f->value = (int) (bool) $value;
        $f->columnWidth = $width;
        if($description !== '') $f->description = $description;
        if($notes !== '') $f->notes = $notes;
        $parent->add($f);
        return $f;
    }
}
