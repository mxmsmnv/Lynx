<?php namespace ProcessWire;

/**
 * Static content catalogues for Lynx: block types, themes, fonts and social
 * presets. Results that never change within a request are memoized.
 * Part of the Lynx class via trait composition.
 */
trait LynxContent {

    /** Supported block types: type => label. */
    public function getBlockTypes() {
        if($this->blockTypesCache === null) {
            $this->blockTypesCache = array(
                'gallery' => $this->_('Image gallery'),
                'project' => $this->_('Project card'),
                'video'   => $this->_('Video embed'),
                'quote'   => $this->_('Testimonial / quote'),
            );
        }
        return $this->blockTypesCache;
    }

    /** Built-in visual themes: id => [label, CSS]. Merged with custom themes. */
    public function getThemes() {
        if($this->themesCache !== null) return $this->themesCache;
        $themes = $this->themeBlueprints('builtIn');
        if(!$themes) $themes = $this->fallbackThemeBlueprints();
        foreach($this->customThemes() as $id => $theme) $themes[$id] = $theme;
        $this->themesCache = $themes;
        return $themes;
    }

    /** Return a sanitized theme map from blueprints/themes/*.json. */
    public function themeBlueprints($section = 'builtIn') {
        $dir = dirname(__DIR__) . '/blueprints/themes';
        if(!is_dir($dir)) return $this->legacyThemeBlueprints($section);

        $items = array();
        foreach(glob($dir . '/*.json') ?: array() as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if(!is_array($data)) continue;
            $kind = $data['kind'] ?? 'builtIn';
            if($kind !== $section) continue;
            $id = $data['id'] ?? basename($file, '.json');
            $items[$id] = $data;
        }
        ksort($items, SORT_NATURAL);
        return $this->normalizeThemeBlueprints($items);
    }

    protected function normalizeThemeBlueprints(array $items) {
        $themes = array();
        $san = $this->wire('sanitizer');
        foreach($items as $id => $theme) {
            if(!is_array($theme)) continue;
            $id = $san->fieldName($id);
            if($id === '') continue;
            $label = $san->text($theme['label'] ?? $id);
            $styles = is_array($theme['styles'] ?? null) ? $theme['styles'] : $theme;
            $css = $this->themeStylesCss($styles);
            $themes[$id] = array(
                'label' => $label ?: $id,
                'css' => $css,
                'description' => $san->text($theme['description'] ?? ''),
                'tags' => $this->sanitizeThemeTags($theme['tags'] ?? array()),
                'preview' => is_array($theme['preview'] ?? null) ? $theme['preview'] : array(),
            );
        }
        return $themes;
    }

    protected function legacyThemeBlueprints($section) {
        $file = dirname(__DIR__) . '/blueprints/themes.json';
        if(!is_file($file)) return array();
        $data = json_decode((string) file_get_contents($file), true);
        if(!is_array($data) || !isset($data[$section]) || !is_array($data[$section])) return array();
        return $this->normalizeThemeBlueprints($data[$section]);
    }

    protected function themeStylesCss(array $styles) {
        $css = $this->sanitizeCss($styles['css'] ?? '');
        if($css === '' && !empty($styles['tailwind']) && is_array($styles['tailwind'])) {
            $css = $this->tailwindThemeCss($styles['tailwind']);
        }
        return $css;
    }

    protected function sanitizeThemeTags($tags) {
        if(!is_array($tags)) return array();
        $out = array();
        $san = $this->wire('sanitizer');
        foreach($tags as $tag) {
            $tag = $san->fieldName((string) $tag);
            if($tag !== '') $out[] = $tag;
        }
        return array_values(array_unique($out));
    }

    /** Compile a small safe Tailwind-like utility subset for theme blueprints. */
    protected function tailwindThemeCss(array $selectors) {
        $css = '';
        $map = $this->tailwindUtilityMap();
        foreach($selectors as $selector => $classes) {
            $selector = trim((string) $selector);
            if(!preg_match('/^(body|\\.lynx[-a-z0-9_,. ]+)$/', $selector)) continue;
            $rules = array();
            foreach(preg_split('/\\s+/', (string) $classes) as $class) {
                $class = trim($class);
                if($class === '' || !isset($map[$class])) continue;
                foreach($map[$class] as $prop => $value) $rules[$prop] = $value;
            }
            if($rules) {
                $css .= $selector . '{';
                foreach($rules as $prop => $value) $css .= $prop . ':' . $value . ';';
                $css .= '}';
            }
        }
        return $css;
    }

    protected function tailwindUtilityMap() {
        $file = dirname(__DIR__) . '/blueprints/theme-utilities.json';
        if(is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if(is_array($data)) {
                $map = array();
                foreach($data as $class => $rules) {
                    if(!is_array($rules) || !preg_match('/^[a-z0-9:-]+$/', (string) $class)) continue;
                    $clean = array();
                    foreach($rules as $prop => $value) {
                        $prop = trim((string) $prop);
                        $value = trim((string) $value);
                        if(!preg_match('/^[a-z-]+$/', $prop)) continue;
                        if($value === '' || preg_match('/[{};]/', $value)) continue;
                        $clean[$prop] = $value;
                    }
                    if($clean) $map[$class] = $clean;
                }
                if($map) return $map;
            }
        }
        return array(
            'bg-white' => array('background' => '#fff'),
            'bg-black' => array('background' => '#000'),
            'bg-neutral-50' => array('background' => '#fafafa'),
            'bg-neutral-900' => array('background' => '#171717'),
            'bg-neutral-950' => array('background' => '#0a0a0a'),
            'bg-zinc-900' => array('background' => '#18181b'),
            'bg-violet-500' => array('background' => '#8b5cf6'),
            'bg-violet-700' => array('background' => '#6d28d9'),
            'bg-accent' => array('background' => 'var(--lynx-accent)'),
            'text-white' => array('color' => '#fff'),
            'text-black' => array('color' => '#000'),
            'text-neutral-100' => array('color' => '#f5f5f5'),
            'text-neutral-400' => array('color' => '#a3a3a3'),
            'text-neutral-700' => array('color' => '#404040'),
            'text-neutral-900' => array('color' => '#171717'),
            'text-violet-100' => array('color' => '#ede9fe'),
            'text-accent' => array('color' => 'var(--lynx-accent)'),
            'border' => array('border' => '1px solid #e5e7eb'),
            'border-0' => array('border' => 'none'),
            'border-2' => array('border' => '2px solid currentColor'),
            'border-neutral-200' => array('border-color' => '#e5e5e5'),
            'border-neutral-700' => array('border-color' => '#404040'),
            'border-neutral-800' => array('border-color' => '#262626'),
            'border-violet-400' => array('border-color' => '#a78bfa'),
            'border-accent' => array('border-color' => 'var(--lynx-accent)'),
            'rounded-md' => array('border-radius' => 'var(--lynx-radius-md)'),
            'font-mono' => array('font-family' => "'SFMono-Regular',Menlo,monospace"),
            'font-sans' => array('font-family' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif"),
        );
    }

    protected function fallbackThemeBlueprints() {
        return array(
            'default' => array('label' => 'Default (light)', 'css' => ''),
        );
    }

    protected function customThemes() {
        $raw = $this->managerConfig('customThemes', '');
        if(!is_string($raw) || trim($raw) === '') return array();
        $data = json_decode($raw, true);
        if(!is_array($data)) return array();

        $themes = array();
        $san = $this->wire('sanitizer');
        $builtInIds = array_keys($this->themeBlueprints('builtIn') ?: $this->fallbackThemeBlueprints());
        foreach($data as $id => $theme) {
            if(!is_array($theme)) continue;
            $id = $san->fieldName($id);
            if($id === '' || in_array($id, $builtInIds, true)) continue;
            $normalized = $this->normalizeThemeBlueprints(array($id => $theme));
            if(empty($normalized[$id]['css'])) continue;
            $themes[$id] = $normalized[$id];
        }
        return $themes;
    }

    /** Curated open-source fonts. Bunny Fonts serves the same CSS v1 API shape as Google Fonts. */
    public function getFonts() {
        if($this->fontsCache !== null) return $this->fontsCache;
        $this->fontsCache = array(
            '' => array('label' => 'System UI', 'family' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif", 'bunny' => ''),
            'inter' => array('label' => 'Inter', 'family' => "'Inter', sans-serif", 'bunny' => 'Inter:wght@400;500;600;700'),
            'roboto' => array('label' => 'Roboto', 'family' => "'Roboto', sans-serif", 'bunny' => 'Roboto:wght@400;500;700'),
            'open-sans' => array('label' => 'Open Sans', 'family' => "'Open Sans', sans-serif", 'bunny' => 'Open Sans:wght@400;600;700'),
            'lato' => array('label' => 'Lato', 'family' => "'Lato', sans-serif", 'bunny' => 'Lato:wght@400;700'),
            'montserrat' => array('label' => 'Montserrat', 'family' => "'Montserrat', sans-serif", 'bunny' => 'Montserrat:wght@400;600;700'),
            'poppins' => array('label' => 'Poppins', 'family' => "'Poppins', sans-serif", 'bunny' => 'Poppins:wght@400;600;700'),
            'dm-sans' => array('label' => 'DM Sans', 'family' => "'DM Sans', sans-serif", 'bunny' => 'DM Sans:wght@400;500;700'),
            'manrope' => array('label' => 'Manrope', 'family' => "'Manrope', sans-serif", 'bunny' => 'Manrope:wght@400;500;700'),
            'work-sans' => array('label' => 'Work Sans', 'family' => "'Work Sans', sans-serif", 'bunny' => 'Work Sans:wght@400;500;700'),
            'source-sans-3' => array('label' => 'Source Sans 3', 'family' => "'Source Sans 3', sans-serif", 'bunny' => 'Source Sans 3:wght@400;600;700'),
            'ibm-plex-sans' => array('label' => 'IBM Plex Sans', 'family' => "'IBM Plex Sans', sans-serif", 'bunny' => 'IBM Plex Sans:wght@400;500;700'),
            'public-sans' => array('label' => 'Public Sans', 'family' => "'Public Sans', sans-serif", 'bunny' => 'Public Sans:wght@400;500;700'),
            'space-grotesk' => array('label' => 'Space Grotesk', 'family' => "'Space Grotesk', sans-serif", 'bunny' => 'Space Grotesk:wght@400;500;700'),
            'rubik' => array('label' => 'Rubik', 'family' => "'Rubik', sans-serif", 'bunny' => 'Rubik:wght@400;500;700'),
            'nunito' => array('label' => 'Nunito', 'family' => "'Nunito', sans-serif", 'bunny' => 'Nunito:wght@400;600;700'),
            'karla' => array('label' => 'Karla', 'family' => "'Karla', sans-serif", 'bunny' => 'Karla:wght@400;500;700'),
            'merriweather' => array('label' => 'Merriweather', 'family' => "'Merriweather', serif", 'bunny' => 'Merriweather:wght@400;700'),
            'playfair-display' => array('label' => 'Playfair Display', 'family' => "'Playfair Display', serif", 'bunny' => 'Playfair Display:wght@400;600;700'),
            'source-serif-4' => array('label' => 'Source Serif 4', 'family' => "'Source Serif 4', serif", 'bunny' => 'Source Serif 4:wght@400;600;700'),
            'ibm-plex-serif' => array('label' => 'IBM Plex Serif', 'family' => "'IBM Plex Serif', serif", 'bunny' => 'IBM Plex Serif:wght@400;600;700'),
            'fira-code' => array('label' => 'Fira Code', 'family' => "'Fira Code', monospace", 'bunny' => 'Fira Code:wght@400;500;700'),
            'jetbrains-mono' => array('label' => 'JetBrains Mono', 'family' => "'JetBrains Mono', monospace", 'bunny' => 'JetBrains Mono:wght@400;500;700'),
            'noto-sans' => array('label' => 'Noto Sans', 'family' => "'Noto Sans', sans-serif", 'bunny' => 'Noto Sans:wght@400;600;700'),
            'noto-serif' => array('label' => 'Noto Serif', 'family' => "'Noto Serif', serif", 'bunny' => 'Noto Serif:wght@400;700'),
            'noto-sans-arabic' => array('label' => 'Noto Sans Arabic', 'family' => "'Noto Sans Arabic', sans-serif", 'bunny' => 'Noto Sans Arabic:wght@400;600;700'),
            'noto-sans-devanagari' => array('label' => 'Noto Sans Devanagari', 'family' => "'Noto Sans Devanagari', sans-serif", 'bunny' => 'Noto Sans Devanagari:wght@400;600;700'),
            'noto-sans-jp' => array('label' => 'Noto Sans JP', 'family' => "'Noto Sans JP', sans-serif", 'bunny' => 'Noto Sans JP:wght@400;500;700'),
            'noto-sans-sc' => array('label' => 'Noto Sans SC', 'family' => "'Noto Sans SC', sans-serif", 'bunny' => 'Noto Sans SC:wght@400;500;700'),
            'noto-sans-georgian' => array('label' => 'Noto Sans Georgian', 'family' => "'Noto Sans Georgian', sans-serif", 'bunny' => 'Noto Sans Georgian:wght@400;600;700'),
            'noto-sans-thai' => array('label' => 'Noto Sans Thai', 'family' => "'Noto Sans Thai', sans-serif", 'bunny' => 'Noto Sans Thai:wght@400;600;700'),
        );
        return $this->fontsCache;
    }

    protected function normalizeFont($font) {
        $font = strtolower(trim((string) $font));
        $font = preg_replace('/[^a-z0-9-]/', '', $font);
        return isset($this->getFonts()[$font]) ? $font : '';
    }

    protected function fontFamily($font) {
        $fonts = $this->getFonts();
        $font = $this->normalizeFont($font);
        return $fonts[$font]['family'] ?? $fonts['']['family'];
    }

    protected function fontStylesheetUrl($font) {
        $fonts = $this->getFonts();
        $font = $this->normalizeFont($font);
        $bunny = $fonts[$font]['bunny'] ?? '';
        if($bunny === '') return '';
        return 'https://fonts.bunny.net/css?family=' . str_replace('%20', '+', rawurlencode($bunny)) . '&display=swap';
    }

    /** Social network presets: key => [label, icon, urlPrefix]. */
    public function getSocialPresets() {
        return array(
            'instagram' => array('Instagram', 'instagram', 'https://instagram.com/'),
            'x'         => array('X / Twitter', 'twitter', 'https://x.com/'),
            'facebook'  => array('Facebook', 'facebook', 'https://facebook.com/'),
            'youtube'   => array('YouTube', 'youtube-play', 'https://youtube.com/@'),
            'tiktok'    => array('TikTok', 'music', 'https://tiktok.com/@'),
            'linkedin'  => array('LinkedIn', 'linkedin', 'https://linkedin.com/in/'),
            'github'    => array('GitHub', 'github', 'https://github.com/'),
            'telegram'  => array('Telegram', 'telegram', 'https://t.me/'),
            'whatsapp'  => array('WhatsApp', 'whatsapp', 'https://wa.me/'),
            'email'     => array('Email', 'envelope', 'mailto:'),
        );
    }
}
