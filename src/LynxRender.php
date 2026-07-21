<?php namespace ProcessWire;

/**
 * Front-end rendering: embeddable render(), portfolio blocks, the full public
 * HTML page, dynamic per-profile CSS, and the Alpine.js client variant.
 * Part of the Lynx class via trait composition.
 */
trait LynxRender {

    protected $publicCspNonce = '';

    protected function publicCspNonce() {
        if($this->publicCspNonce === '') {
            try {
                $this->publicCspNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            } catch(\Throwable $e) {
                $this->publicCspNonce = hash('sha256', uniqid('', true));
            }
        }
        return $this->publicCspNonce;
    }

    /** Security headers for standalone public profile responses. */
    protected function sendPublicSecurityHeaders() {
        if(headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        // YouTube requires an HTTP Referer (or equivalent client identity) for
        // embedded playback and reports error 153 when it is suppressed.
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        $isPreview = (bool) $this->wire('input')->get('lynxpreview');
        $isAnonymous = !$this->wire('user')->isLoggedin();
        if($isAnonymous && !$isPreview) {
            // ProcessWire starts a session before path hooks run. Public
            // profiles do not need that cookie, and a cacheable anonymous
            // response is substantially friendlier to browsers and CDNs.
            header_remove('Set-Cookie');
            header_remove('Pragma');
            header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        }
        $nonce = $this->publicCspNonce();
        header("Content-Security-Policy: default-src 'none'; base-uri 'none'; object-src 'none'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            . "font-src 'self' https://fonts.bunny.net data:; "
            . "script-src 'nonce-$nonce'; script-src-attr 'none'; "
            . "connect-src 'self'; "
            . "img-src 'self' https: http: data:; frame-src https://www.youtube.com https://player.vimeo.com; "
            . "frame-ancestors 'self'; form-action 'none'");
    }

    /** Compress only final standalone public responses when the client asks. */
    protected function encodePublicResponse($html) {
        if(!is_string($html) || $html === '' || !function_exists('gzencode')) return $html;
        if(ini_get('zlib.output_compression')) return $html;
        $accepted = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
        if(strpos($accepted, 'gzip') === false) return $html;
        $encoded = gzencode($html, 6);
        if($encoded === false) return $html;
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        header('Content-Length: ' . strlen($encoded));
        return $encoded;
    }

    /** Small dependency-free link icon used by standalone public pages. */
    protected function renderLinkIcon($icon) {
        $icons = array(
            'user' => '●', 'github' => 'GH', 'code' => '&lt;/&gt;', 'link' => '↗',
            'envelope' => '@', 'instagram' => '◎', 'twitter' => 'X', 'facebook' => 'f',
            'youtube-play' => '▶', 'music' => '♪', 'linkedin' => 'in',
            'telegram' => '➤', 'whatsapp' => '☎',
        );
        $icon = strtolower((string) $icon);
        if($icon === '') return '';
        $glyph = $icons[$icon] ?? '↗';
        return "<span class='lynx-link-icon' aria-hidden='true'>$glyph</span>";
    }

    /** Ask GitHub's avatar endpoint for an image near its rendered size. */
    protected function optimizedPublicImageUrl($url, $width = 0) {
        $url = (string) $url;
        if($width > 0 && preg_match('~^https://avatars\\.githubusercontent\\.com/~i', $url)) {
            $url = preg_replace('/([?&])s=\\d+/i', '$1', $url);
            $url = rtrim($url, '?&');
            $url .= (strpos($url, '?') === false ? '?' : '&') . 's=' . max(32, min(1024, (int) $width));
        }
        return $url;
    }

    /**
     * Render a profile's links list. Use in your own templates:
     *   echo $modules->get('Lynx')->render('my-slug');
     */
    public function render($slug, array $options = array()) {
        $profile = is_array($slug) ? $slug : $this->getProfileBySlug($slug);
        if(!$profile) return '';
        $lang = $this->profileLang($profile, $options['lang'] ?? '');
        $baseLang = $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage);
        $profile = $this->translatedRow($profile, $lang, array('title', 'bio', 'seo_title', 'seo_description', 'og_image'), $baseLang);
        $links = array_map(fn($l) => $this->translatedRow($l, $lang, array('label'), $baseLang), $this->getLinks($profile['id'], true));
        $san = $this->wire('sanitizer');
        $accent = $san->entities($profile['accent']);

        $out = "<div class='lynx-profile' lang='" . $san->entities($lang) . "' style='--lynx-accent:$accent'>";
        if($profile['avatar']) {
            $avatar = $this->optimizedPublicImageUrl($profile['avatar'], 128);
            $out .= "<img class='lynx-avatar' src='" . $san->entities($avatar)
                . "' width='96' height='96' decoding='async' fetchpriority='high' alt='' />";
        }
        $out .= "<h1 class='lynx-title'>" . $san->entities($profile['title']) . "</h1>";
        if($profile['bio']) {
            $out .= "<p class='lynx-bio'>" . nl2br($san->entities($profile['bio'])) . "</p>";
        }
        $out .= "<div class='lynx-links'>";
        foreach($links as $link) {
            $href = $this->trackClicks
                ? $this->publicPath('go/' . (int) $link['id'])
                : $san->entities($link['url']);
            $icon = $this->renderLinkIcon($link['icon'] ?? '');
            $out .= "<a class='lynx-link' href='$href' rel='noopener noreferrer'>$icon"
                  . $san->entities($link['label']) . "</a>";
        }
        $out .= "</div>";

        // portfolio blocks
        $blocks = array_map(fn($b) => $this->translatedRow($b, $lang, array('title', 'data'), $baseLang), $this->getBlocks($profile['id'], true));
        foreach($blocks as $b) $out .= $this->renderBlock($b);

        $out .= "</div>";
        return $out;
    }

    /** Render a single portfolio block to HTML. */
    protected function renderBlock(array $block) {
        $san = $this->wire('sanitizer');
        $d = is_array($block['data']) ? $block['data'] : $this->decodeBlockData($block['data']);
        // Translation merging happens after storage sanitization. Validate the
        // complete merged structure once more at the final output boundary.
        $d = $this->sanitizeBlockData($block['type'], $d);
        $title = $block['title'] !== '' ? "<h2 class='lynx-block-title'>" . $san->entities($block['title']) . "</h2>" : '';
        $out = "<section class='lynx-block lynx-block-" . $san->entities($block['type']) . "'>$title";

        switch($block['type']) {
            case 'gallery':
                $cols = (int) ($d['columns'] ?? 3);
                $out .= "<div class='lynx-gallery' style='grid-template-columns:repeat($cols,1fr)'>";
                foreach(($d['images'] ?? array()) as $img) {
                    $src = $san->entities($this->optimizedPublicImageUrl($img['src'] ?? '', 160));
                    if(!$src) continue;
                    $cap = !empty($img['caption']) ? "<span class='lynx-cap'>" . $san->entities($img['caption']) . "</span>" : '';
                    // The visible caption already names the image/link, so an
                    // empty alt avoids announcing the same text twice.
                    $imgTag = "<img loading='lazy' decoding='async' width='320' height='320' src='$src' alt=''>";
                    $cell = "<figure class='lynx-gitem'>$imgTag$cap</figure>";
                    $out .= !empty($img['link'])
                        ? "<a href='" . $san->entities($img['link']) . "' rel='noopener noreferrer'>$cell</a>"
                        : $cell;
                }
                $out .= "</div>";
                break;

            case 'project':
                $out .= "<div class='lynx-projects'>";
                foreach(($d['items'] ?? array()) as $it) {
                    $imgUrl = $this->optimizedPublicImageUrl($it['image'] ?? '', 192);
                    $img = $imgUrl !== '' ? "<img loading='lazy' decoding='async' fetchpriority='low' width='96' height='96' class='lynx-pimg' src='" . $san->entities($imgUrl) . "' alt=''>" : '';
                    $h = !empty($it['heading']) ? "<h3>" . $san->entities($it['heading']) . "</h3>" : '';
                    $body = !empty($it['body']) ? "<p>" . nl2br($san->entities($it['body'])) . "</p>" : '';
                    $link = !empty($it['link'])
                        ? "<a class='lynx-plink' href='" . $san->entities($it['link']) . "' rel='noopener noreferrer'>"
                          . $san->entities($it['linkLabel'] ?: 'View') . "</a>"
                        : '';
                    $out .= "<article class='lynx-project'>$img<div class='lynx-pbody'>$h$body$link</div></article>";
                }
                $out .= "</div>";
                break;

            case 'video':
                $out .= "<div class='lynx-videos'>";
                foreach(($d['videos'] ?? array()) as $v) {
                    $embed = $san->entities($v['embed'] ?? '');
                    if(!$embed) continue;
                    $cap = !empty($v['caption']) ? "<span class='lynx-cap'>" . $san->entities($v['caption']) . "</span>" : '';
                    $frameTitle = $san->entities($v['caption'] ?? 'Embedded video');
                    $rawEmbed = html_entity_decode($embed, ENT_QUOTES, 'UTF-8');
                    if(preg_match('~^https://www\\.youtube\\.com/embed/([A-Za-z0-9_-]+)~', $rawEmbed, $match)) {
                        $videoId = $match[1];
                        $playUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1';
                        $thumb = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
                        $srcdoc = "<style>*{box-sizing:border-box}html,body,a{margin:0;width:100%;height:100%;display:block;background:#111}img{width:100%;height:100%;object-fit:cover}span{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:68px;height:48px;border-radius:14px;background:#e11;color:#fff;font:700 28px/48px sans-serif;text-align:center}</style><a href='" . $playUrl . "' aria-label='" . ($frameTitle ?: 'Play video') . "'><img src='" . $thumb . "' alt=''><span>▶</span></a>";
                        $out .= "<div class='lynx-video'><div class='lynx-video-frame'>"
                            . "<iframe srcdoc='" . $san->entities($srcdoc) . "' loading='lazy' title='$frameTitle' "
                            . "referrerpolicy='strict-origin-when-cross-origin' allow='autoplay; encrypted-media; picture-in-picture' allowfullscreen></iframe>"
                            . "</div>$cap</div>";
                    } else {
                        $out .= "<div class='lynx-video'><div class='lynx-video-frame'>"
                            . "<iframe src='$embed' loading='lazy' title='$frameTitle' referrerpolicy='strict-origin-when-cross-origin' "
                            . "allow='autoplay; encrypted-media; picture-in-picture' allowfullscreen></iframe></div>$cap</div>";
                    }
                }
                $out .= "</div>";
                break;

            case 'quote':
                foreach(($d['quotes'] ?? array()) as $q) {
                    $text = !empty($q['text']) ? $san->entities($q['text']) : '';
                    if($text === '') continue;
                    $who = '';
                    if(!empty($q['author'])) {
                        $who = "<cite>" . $san->entities($q['author'])
                            . (!empty($q['role']) ? ", <span class='lynx-qrole'>" . $san->entities($q['role']) . "</span>" : '')
                            . "</cite>";
                    }
                    $out .= "<blockquote class='lynx-quote'><p>" . nl2br($text) . "</p>$who</blockquote>";
                }
                break;
        }
        $out .= "</section>";
        return $out;
    }

    /** Full standalone HTML page for public routing. View counting happens in the router. */
    protected function renderPage(array $profile, $lang = '') {
        $san = $this->wire('sanitizer');
        $lang = $this->profileLang($profile, $lang);
        $profile = $this->translatedRow($profile, $lang, array('title', 'bio', 'seo_title', 'seo_description', 'og_image'));

        $pageTitle = $san->entities($profile['seo_title'] ?: ($profile['title'] ?: $profile['slug']));
        $desc = $san->entities($profile['seo_description'] ?: strip_tags($profile['bio'] ?? ''));
        $ogImage = $san->entities($profile['og_image'] ?: $profile['avatar']);
        $url = $this->canonicalProfileUrl($profile, $lang);

        $body = $this->render($profile, array('lang' => $lang));
        $brand = $this->managerConfig('brandName', '');
        if($brand !== '') {
            $body .= "<footer class='lynx-brand'>" . $san->entities($brand) . "</footer>";
        }
        $body .= $this->renderLanguageSwitcher($profile, $lang);
        $css = $this->pageCss($profile);
        $baseCssPath = dirname(__DIR__) . '/assets/public.css';
        $baseCss = is_file($baseCssPath) ? file_get_contents($baseCssPath) : '';
        $fontUrl = $this->fontStylesheetUrl($profile['font'] ?? '');
        $nonce = $san->entities($this->publicCspNonce());
        $fontLink = $fontUrl
            ? "<link rel='preconnect' href='https://fonts.bunny.net' crossorigin><link rel='stylesheet' media='print' data-lynx-font href='" . $san->entities($fontUrl) . "'>"
            : '';
        $fontLoader = $fontUrl
            ? "<script nonce='$nonce'>document.querySelector('[data-lynx-font]').media='all'</script>"
              . "<noscript><link rel='stylesheet' href='" . $san->entities($fontUrl) . "'></noscript>"
            : '';

        // SEO / Open Graph / Twitter cards
        $meta = "";
        if($desc) $meta .= "<meta name='description' content='$desc'>";
        if(!empty($profile['noindex'])) $meta .= "<meta name='robots' content='noindex,nofollow'>";
        $meta .= "<meta property='og:type' content='profile'>"
              . "<meta property='og:title' content='$pageTitle'>"
              . ($desc ? "<meta property='og:description' content='$desc'>" : '')
              . "<meta property='og:url' content='" . $san->entities($url) . "'>"
              . ($ogImage ? "<meta property='og:image' content='$ogImage'>" : '')
              . "<meta name='twitter:card' content='" . ($ogImage ? 'summary_large_image' : 'summary') . "'>"
              . "<meta name='twitter:title' content='$pageTitle'>"
              . ($desc ? "<meta name='twitter:description' content='$desc'>" : '')
              . ($ogImage ? "<meta name='twitter:image' content='$ogImage'>" : '');
        foreach($this->availableLanguages($profile) as $availableLang) {
            $alternateUrl = $this->canonicalProfileUrl($profile, $availableLang);
            $meta .= "<link rel='alternate' hreflang='" . $san->entities($availableLang)
                . "' href='" . $san->entities($alternateUrl) . "'>";
        }

        return "<!doctype html><html lang='" . $san->entities($lang) . "'><head><meta charset='utf-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
            . "<link rel='icon' href=\"data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%231e87f0'/><path d='M18 16h9v24h19v8H18z' fill='white'/></svg>\">"
            . "<title>$pageTitle</title>"
            . $meta
            . $fontLink
            . (($baseCss !== '' || $css !== '') ? "<style>$baseCss\n$css</style>" : '')
            . "</head><body>$body$fontLoader</body></html>";
    }

    /** Visible links to every language actually available for this profile. */
    protected function renderLanguageSwitcher(array $profile, $currentLang) {
        $languages = $this->availableLanguages($profile);
        if(count($languages) < 2) return '';
        $san = $this->wire('sanitizer');
        $currentLang = $this->profileLang($profile, $currentLang);
        $baseLang = $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage);
        $links = '';
        foreach($languages as $lang) {
            $path = ($lang === $baseLang ? '' : $lang . '/') . $profile['slug'];
            $current = $lang === $currentLang ? " aria-current='page'" : '';
            $links .= "<a class='lynx-language' href='" . $san->entities($this->publicPath($path))
                . "' hreflang='" . $san->entities($lang) . "' rel='alternate'$current>"
                . strtoupper($san->entities($lang)) . "</a>";
        }
        return "<nav class='lynx-languages' aria-label='Languages'>$links</nav>";
    }

    protected function currentRequestUrl() {
        $config = $this->wire('config');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        // Use PW's validated host rather than the raw Host header (host-injection safe).
        $host = $config->httpHost ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = $_SERVER['REQUEST_URI'] ?? $this->wire('input')->url;
        return $scheme . '://' . $host . $uri;
    }

    /** Stable absolute URL independent of query-string and cache warm order. */
    protected function canonicalProfileUrl(array $profile, $lang = '') {
        $config = $this->wire('config');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $config->httpHost ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $lang = $this->profileLang($profile, $lang);
        $base = $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage);
        $path = ($lang !== $base ? $lang . '/' : '') . $profile['slug'];
        return $scheme . '://' . $host . $this->publicPath($path);
    }

    /**
     * Dynamic per-profile CSS appended after the static base stylesheet:
     * font-family override, theme, background override, and custom CSS.
     */
    protected function pageCss(array $profile = array()) {
        $out = '';

        // font-family override (base stylesheet falls back to system UI)
        $font = $this->normalizeFont($profile['font'] ?? '');
        if($font !== '') $out .= ":root{--lynx-font:" . $this->fontFamily($font) . "}";

        // theme
        $themeId = $profile['theme'] ?? 'default';
        $themes = $this->getThemes();
        if(isset($themes[$themeId])) $out .= "\n" . $themes[$themeId]['css'];

        // background override
        if(!empty($profile['bg_type']) && !empty($profile['bg_value'])) {
            $v = $profile['bg_value'];
            if($profile['bg_type'] === 'color') {
                $v = preg_replace('/[^#0-9a-zA-Z(),.%\s]/', '', $v);
                $out .= "\nbody{background:$v}";
            } elseif($profile['bg_type'] === 'image') {
                $v = str_replace(array('"', "'", ')', '<', '>'), '', $v);
                $out .= "\nbody{background-image:url('$v')}";
            } elseif($profile['bg_type'] === 'gradient') {
                $v = preg_replace('/[^#0-9a-zA-Z(),.%\s-]/', '', $v);
                $out .= "\nbody{background:$v}";
            }
        }

        // per-profile custom CSS (already sanitized on save)
        if(!empty($profile['custom_css'])) $out .= "\n/* custom */\n" . $profile['custom_css'];

        return $out;
    }

    /**
     * Alpine.js front-end variant. Outputs a self-contained widget that
     * fetches the profile from the REST API and renders it client-side.
     * Use in a template: echo $modules->get('Lynx')->renderAlpine('my-slug', ['lang' => 'ru']);
     */
    public function renderAlpine($slug, array $options = array()) {
        $san = $this->wire('sanitizer');
        $slug = $san->pageName($slug);
        $lang = !empty($options['lang']) && $this->isSupportedLang($options['lang'])
            ? $this->normalizeLang($options['lang'])
            : '';
        $apiPath = $lang ? "$lang/$slug" : $slug;
        $api = $san->entities($this->publicPath('api/profiles/' . $apiPath));
        $go = $san->entities($this->publicPath('go/'));
        $track = $this->trackClicks ? 'true' : 'false';
        $includeCdn = empty($options['noCdn']);
        $includeCss = empty($options['noCss']);
        $includeIcons = empty($options['noFontAwesome']);
        $langAttr = $lang ? " lang='" . $san->entities($lang) . "'" : '';

        $alpine = $includeCdn
            ? "<script defer src='https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js' integrity='sha384-9Ax3MmS9AClxJyd5/zafcXXjxmwFhZCdsT6HJoJjarvCaAkJlk5QDzjLJm+Wdx5F' crossorigin='anonymous'></script>"
            : '';
        $css = $includeCss
            ? "<link rel='stylesheet' href='" . $san->entities($this->assetUrl('public.css')) . "'>"
            : '';
        $icons = $includeIcons
            ? "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' integrity='sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN' crossorigin='anonymous'>"
            : '';

        return $css . $icons . $alpine . "
<div class='lynx-profile'$langAttr x-data=\"lynxProfile('$api', '$go', $track)\" x-init='load()'
     :style=\"p ? ('--lynx-accent:' + p.accent) : ''\">
  <template x-if='p'>
    <div>
      <template x-if='p.avatar'><img class='lynx-avatar' :src='p.avatar' alt=''></template>
      <h1 class='lynx-title' x-text='p.title'></h1>
      <p class='lynx-bio' x-show='p.bio' x-text='p.bio'></p>
      <div class='lynx-links'>
        <template x-for='link in p.links' :key='link.id'>
          <a class='lynx-link' :href='href(link)' rel='noopener noreferrer'>
            <i class='fa' :class=\"'fa-' + link.icon\" x-show='link.icon'></i>
            <span x-text='link.label'></span>
          </a>
        </template>
      </div>
    </div>
  </template>
  <p x-show='!p && !error' class='lynx-bio'>Loading…</p>
  <p x-show='error' class='lynx-bio' x-text='error'></p>
</div>
<script>
function lynxProfile(api, go, track){
  return {
    p: null, error: '',
    async load(){
      try {
        const r = await fetch(api, {headers:{'Accept':'application/json'}});
        if(!r.ok) throw new Error('Profile not found');
        this.p = await r.json();
      } catch(e){ this.error = e.message; }
    },
    href(link){ return track ? (go + link.id) : link.url; }
  }
}
</script>";
    }
}
