<?php namespace ProcessWire;

/** Front-end editor CSRF metadata, translations, and page rendering. */
trait LynxEditorView {

    /** A <meta> tag + JS var carrying the CSRF token for editor fetches. */
    protected function csrfMeta() {
        $session = $this->wire('session');
        $val = $this->wire('sanitizer')->entities($session->CSRF->getTokenValue());
        return "<meta name='lynx-csrf' content='$val'><script>window.__lynxCsrf='$val';</script>";
    }

    /** Translated strings handed to the editor JS via window.LYNX.i18n. */
    protected function editorI18n() {
        return array(
            'profile' => $this->_('Profile'),
            'baseLang' => $this->_('Base language'),
            'profileSlug' => $this->_('Profile slug'),
            'slugNote' => $this->_('Changing the slug changes the public URL. Use lowercase letters, numbers and hyphens.'),
            'slugRequired' => $this->_('Profile slug cannot be empty.'),
            'displayName' => $this->_('Display name'),
            'shortBio' => $this->_('Short bio'),
            'profileNote' => $this->_('Core identity, language and visual style for the public page.'),
            'avatar' => $this->_('Avatar'),
            'avatarPlaceholder' => $this->_('image URL or upload'),
            'upload' => $this->_('Upload'),
            'accent' => $this->_('Accent color'),
            'theme' => $this->_('Theme'),
            'font' => $this->_('Font'),
            'background' => $this->_('Background'),
            'backgroundType' => $this->_('Background type'),
            'backgroundNone' => $this->_('Theme default'),
            'backgroundColor' => $this->_('Color'),
            'backgroundImage' => $this->_('Image URL'),
            'backgroundGradient' => $this->_('Gradient CSS'),
            'backgroundValue' => $this->_('Background value'),
            'backgroundNote' => $this->_('Use a color, uploaded image URL, or CSS gradient for the public page background.'),
            'links' => $this->_('Links'),
            'linksNote' => $this->_('Primary destinations shown before content blocks. Drag to reorder.'),
            'addLink' => $this->_('Add link'),
            'socialPresets' => $this->_('Social presets'),
            'portfolio' => $this->_('Portfolio'),
            'portfolioNote' => $this->_('Add richer sections below the link list: images, projects, video or quotes.'),
            'appearance' => $this->_('Appearance'),
            'appearanceNote' => $this->_('Theme, accent and open-source font for the public page.'),
            'translations' => $this->_('Translations'),
            'translationsNote' => $this->_('Add and edit as many profile languages as needed. Empty fields fall back to the base language.'),
            'language' => $this->_('Language'),
            'addLanguage' => $this->_('Add language'),
            'removeLanguage' => $this->_('Remove language'),
            'removeLanguageConfirm' => $this->_('Remove this language and all of its profile, link and block translations?'),
            'noTranslations' => $this->_('No translation languages added yet.'),
            'translated' => $this->_('Translated'),
            'draft' => $this->_('Draft'),
            'open' => $this->_('Open'),
            'profileTitle' => $this->_('Profile title'),
            'profileBio' => $this->_('Profile bio'),
            'seoTitle' => $this->_('SEO title'),
            'seoDesc' => $this->_('SEO description'),
            'linkLabels' => $this->_('Link labels'),
            'untitledLink' => $this->_('Untitled link'),
            'blockTitles' => $this->_('Block titles'),
            'label' => $this->_('Label'),
            'icon' => $this->_('icon'),
            'remove' => $this->_('Remove'),
            'sectionTitle' => $this->_('Section title (optional)'),
            'columns' => $this->_('Columns'),
            'caption' => $this->_('caption'),
            'addImage' => $this->_('Add image'),
            'image' => $this->_('Image'),
            'heading' => $this->_('Heading'),
            'description' => $this->_('Description'),
            'linkUrl' => $this->_('Link URL'),
            'button' => $this->_('Button'),
            'addProject' => $this->_('Add project'),
            'videoUrl' => $this->_('YouTube / Vimeo URL'),
            'addVideo' => $this->_('Add video'),
            'quoteText' => $this->_('Quote text'),
            'author' => $this->_('Author'),
            'role' => $this->_('Role'),
            'addQuote' => $this->_('Add quote'),
            'addSocial' => $this->_('Add which social?'),
            'unknown' => $this->_('Unknown'),
            'uploading' => $this->_('Uploading…'),
            'uploadFail' => $this->_('Upload failed'),
            'unsaved' => $this->_('Unsaved changes'),
            'saving' => $this->_('Saving…'),
            'saved' => $this->_('Saved'),
            'error' => $this->_('Error'),
            'netErr' => $this->_('Network error'),
        );
    }

    /** The actual editor screen: form (left) + live preview iframe (right). */
    protected function editorPage(array $profile) {
        if(!$this->ownsProfile($profile)) {
            return "<!doctype html><meta charset='utf-8'><p style='font:16px sans-serif;padding:40px'>"
                . $this->wire('sanitizer')->entities($this->_("You don't have access to this page.")) . "</p>";
        }
        $san = $this->wire('sanitizer');
        $root = trim($this->rootUrl, '/');
        $token = $this->csrfMeta();
        $cssUrl = $san->entities($this->assetUrl('editor.css'));
        $jsUrl = $san->entities($this->assetUrl('editor.js'));

        // initial state as JSON
        $state = array(
            'id' => (int) $profile['id'],
            'slug' => $profile['slug'],
            'lang' => $profile['lang'] ?: $this->normalizeLang($this->defaultLanguage),
            'title' => $profile['title'],
            'bio' => $profile['bio'],
            'avatar' => $profile['avatar'],
            'accent' => $profile['accent'] ?: '#1e87f0',
            'theme' => $profile['theme'] ?: 'default',
            'font' => $profile['font'] ?? $this->managerConfig('defaultFont', ''),
            'bg_type' => $profile['bg_type'],
            'bg_value' => $profile['bg_value'],
            'seo_description' => $profile['seo_description'],
            'translations' => $this->decodeTranslations($profile['translations'] ?? ''),
            'links' => array(),
            'blocks' => array(),
        );
        foreach($this->getLinks($profile['id']) as $l) {
            $state['links'][] = array(
                'id' => (int) $l['id'],
                'label' => $l['label'],
                'url' => $l['url'],
                'icon' => $l['icon'],
                'is_social' => (int) $l['is_social'],
                'start_date' => (int) $l['start_date'],
                'end_date' => (int) $l['end_date'],
                'active' => (int) $l['active'],
                'translations' => $this->decodeTranslations($l['translations'] ?? ''),
            );
        }
        foreach($this->getBlocks($profile['id']) as $b) {
            $state['blocks'][] = array(
                'id' => (int) $b['id'],
                'type' => $b['type'],
                'title' => $b['title'],
                'data' => $b['data'],
                'active' => (int) $b['active'],
                'translations' => $this->decodeTranslations($b['translations'] ?? ''),
            );
        }
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $stateJson = json_encode($state, $jsonFlags);
        $themesJson = json_encode($this->getThemes(), $jsonFlags);
        $fontsJson = json_encode($this->getFonts(), $jsonFlags);
        $presetsJson = json_encode($this->getSocialPresets(), $jsonFlags);
        $blockTypesJson = json_encode($this->getBlockTypes(), $jsonFlags);
        $languagesJson = json_encode($this->getSupportedLanguages(), $jsonFlags);
        $i18nJson = json_encode($this->editorI18n(), $jsonFlags);
        // ?lynxpreview=1 keeps the live preview from inflating the view counter
        $previewUrl = $this->publicPath($profile['slug']) . "?lynxpreview=1";
        $openUrl = $this->publicPath($profile['slug']);
        $dashboardUrl = $this->publicPath('edit/');

        return "<!doctype html><html lang='en'><head><meta charset='utf-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>$token"
            . "<title>" . $san->entities(sprintf($this->_('Editing: %s'), $profile['title'] ?: $profile['slug'])) . "</title>"
            . "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' integrity='sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN' crossorigin='anonymous'>"
            . "<script src='https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js' integrity='sha384-BSxuMLxX+FCbTdYec3TbXlnMGEEM2QXTFdtDaveen71o+jswm2J36+xFqp8k4VHM' crossorigin='anonymous'></script>"
            . "<link rel='stylesheet' href='$cssUrl'></head><body>"
            . "<div id='app'>"
            . "<div class='ed-workspace'>"
            . "<aside class='appearance-rail' id='appearance' aria-label='" . $san->entities($this->_('Appearance')) . "'></aside>"
            . "<div class='ed-pane'>"
            . "<div class='ed-head'><a href='$dashboardUrl' class='back'>&larr; " . $san->entities($this->_('My pages')) . "</a>"
            . "<div class='ed-actions'><span id='savestate' class='muted'></span>"
            . "<button class='btn' id='savebtn'>" . $san->entities($this->_('Save')) . "</button></div></div>"
            . "<div id='form'></div></div></div>"
            . "<div class='splitter' id='splitter' role='separator' aria-orientation='vertical' title='" . $san->entities($this->_('Resize editor and preview')) . "'></div>"
            . "<div class='pv-pane'><div class='pv-bar'><span class='pv-bar-title'>" . $san->entities($this->_('Live preview')) . "</span> "
            . "<a href='$openUrl' id='open-profile' target='_blank' rel='noopener noreferrer' class='muted'>" . $san->entities($this->_('open')) . " &nearr;</a></div>"
            . "<iframe id='preview' src='$previewUrl'></iframe></div>"
            . "</div>"
            . "<script>window.LYNX={root:'$root',state:$stateJson,themes:$themesJson,"
            . "fonts:$fontsJson,presets:$presetsJson,blockTypes:$blockTypesJson,languages:$languagesJson,i18n:$i18nJson};</script>"
            . "<script src='$jsUrl'></script></body></html>";
    }
}
