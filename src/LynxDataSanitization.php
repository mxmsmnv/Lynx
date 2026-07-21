<?php namespace ProcessWire;

/** Shared profile, link, and block sanitization helpers. */
trait LynxDataSanitization {

    /** Parse a date string or epoch into a unix timestamp (0 = none). */
    protected function toTs($val) {
        if(empty($val)) return 0;
        if(is_numeric($val)) return (int) $val;
        $t = strtotime($val);
        return $t ? $t : 0;
    }

    /** Strip dangerous constructs and visual effects from user CSS. */
    protected function sanitizeCss($css) {
        $css = (string) $css;
        $css = preg_replace('#</?\s*style[^>]*>#i', '', $css);
        $css = preg_replace('#expression\s*\(#i', '', $css);
        $css = preg_replace('#javascript\s*:#i', '', $css);
        $css = preg_replace('#@import[^;]+;#i', '', $css);
        $css = preg_replace('#[^{}]*:(?:hover|active)[^{]*\{[^{}]*\}#i', '', $css);
        $css = preg_replace('#@keyframes\s+[^{]+\{(?:[^{}]|\{[^{}]*\})*\}#i', '', $css);
        $css = preg_replace('#\b(?:box-shadow|text-shadow|filter|backdrop-filter|transition|transition-property|transition-duration|transition-delay|transition-timing-function|transform|animation|animation-name|animation-duration|animation-delay|animation-timing-function|animation-iteration-count|animation-direction|animation-fill-mode|animation-play-state)\s*:[^;{}]+;?#i', '', $css);
        return $css;
    }

    /** Sanitize a public URL while allowing site-relative asset paths when useful. */
    protected function sanitizePublicUrl($url, $allowRelative = true) {
        $url = trim((string) $url);
        if($url === '') return '';
        $url = $this->wire('sanitizer')->url($url, array('allowRelative' => $allowRelative));
        if($url === '') return '';
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if($allowRelative && $scheme === '') {
            return str_starts_with($url, '//') ? '' : $url;
        }
        return in_array($scheme, array('http', 'https'), true) ? $url : '';
    }

    /** Public link targets additionally support explicit contact schemes. */
    protected function sanitizeLinkUrl($url) {
        $url = trim((string) $url);
        if($url === '') return '';
        $url = $this->wire('sanitizer')->url($url, array('allowRelative' => false));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, array('http', 'https', 'mailto', 'tel'), true) ? $url : '';
    }

    /** Sanitize per-type block data into a clean storable structure. */
    protected function sanitizeBlockData($type, $data) {
        $san = $this->wire('sanitizer');
        if(!is_array($data)) $data = array();
        $url = fn($u) => $this->sanitizePublicUrl($u ?? '', true);

        switch($type) {
            case 'gallery':
                $images = array();
                foreach(($data['images'] ?? array()) as $img) {
                    $src = $url(is_array($img) ? ($img['src'] ?? '') : $img);
                    if(!$src) continue;
                    $images[] = array(
                        'src' => $src,
                        'caption' => $san->text(is_array($img) ? ($img['caption'] ?? '') : ''),
                        'link' => $url(is_array($img) ? ($img['link'] ?? '') : ''),
                    );
                }
                return array('images' => $images, 'columns' => max(1, min(4, (int) ($data['columns'] ?? 3))));

            case 'project':
                $items = array();
                foreach(($data['items'] ?? array()) as $it) {
                    if(!is_array($it)) continue;
                    $heading = $san->text($it['heading'] ?? '');
                    $body = $san->textarea($it['body'] ?? '');
                    $image = $url($it['image'] ?? '');
                    $link = $url($it['link'] ?? '');
                    if($heading === '' && $body === '' && $image === '') continue;
                    $items[] = array('heading' => $heading, 'body' => $body, 'image' => $image,
                        'link' => $link, 'linkLabel' => $san->text($it['linkLabel'] ?? 'View'));
                }
                return array('items' => $items);

            case 'video':
                $videos = array();
                foreach(($data['videos'] ?? array()) as $v) {
                    $raw = is_array($v) ? ($v['url'] ?? '') : $v;
                    $embed = $this->videoEmbedUrl($raw);
                    if(!$embed) continue;
                    $videos[] = array('url' => $this->sanitizePublicUrl($raw, false),
                        'embed' => $embed, 'caption' => $san->text(is_array($v) ? ($v['caption'] ?? '') : ''));
                }
                return array('videos' => $videos);

            case 'quote':
                $quotes = array();
                foreach(($data['quotes'] ?? array()) as $q) {
                    if(!is_array($q)) continue;
                    $text = $san->textarea($q['text'] ?? '');
                    if($text === '') continue;
                    $quotes[] = array('text' => $text, 'author' => $san->text($q['author'] ?? ''),
                        'role' => $san->text($q['role'] ?? ''));
                }
                return array('quotes' => $quotes);
        }
        return array();
    }

    /** Sanitize partial translated block data without requiring base fields. */
    protected function sanitizeBlockTranslationData($type, $data) {
        $san = $this->wire('sanitizer');
        if(!is_array($data)) return array();
        $url = fn($u) => $this->sanitizePublicUrl($u ?? '', true);
        $cleanRows = function($rows, callable $cleaner) {
            $out = array();
            foreach(is_array($rows) ? $rows : array() as $row) {
                if(!is_array($row)) continue;
                $clean = $cleaner($row);
                if($clean) $out[] = $clean;
            }
            return $out;
        };

        switch($type) {
            case 'gallery':
                $out = array();
                if(array_key_exists('columns', $data)) $out['columns'] = max(1, min(4, (int) $data['columns']));
                if(array_key_exists('images', $data)) {
                    $out['images'] = $cleanRows($data['images'], function($img) use ($san, $url) {
                        $row = array();
                        if(array_key_exists('src', $img)) $row['src'] = $url($img['src']);
                        if(array_key_exists('caption', $img)) $row['caption'] = $san->text($img['caption']);
                        if(array_key_exists('link', $img)) $row['link'] = $url($img['link']);
                        return $row;
                    });
                }
                return $out;

            case 'project':
                if(!array_key_exists('items', $data)) return array();
                return array('items' => $cleanRows($data['items'], function($item) use ($san, $url) {
                    $row = array();
                    if(array_key_exists('heading', $item)) $row['heading'] = $san->text($item['heading']);
                    if(array_key_exists('body', $item)) $row['body'] = $san->textarea($item['body']);
                    if(array_key_exists('image', $item)) $row['image'] = $url($item['image']);
                    if(array_key_exists('link', $item)) $row['link'] = $url($item['link']);
                    if(array_key_exists('linkLabel', $item)) $row['linkLabel'] = $san->text($item['linkLabel']);
                    return $row;
                }));

            case 'video':
                if(!array_key_exists('videos', $data)) return array();
                return array('videos' => $cleanRows($data['videos'], function($video) use ($san) {
                    $row = array();
                    if(array_key_exists('url', $video)) {
                        $url = $this->sanitizePublicUrl($video['url'], false);
                        $embed = $this->videoEmbedUrl($url);
                        if($embed !== '') {
                            $row['url'] = $url;
                            $row['embed'] = $embed;
                        }
                    }
                    if(array_key_exists('caption', $video)) $row['caption'] = $san->text($video['caption']);
                    return $row;
                }));

            case 'quote':
                if(!array_key_exists('quotes', $data)) return array();
                return array('quotes' => $cleanRows($data['quotes'], function($quote) use ($san) {
                    $row = array();
                    if(array_key_exists('text', $quote)) $row['text'] = $san->textarea($quote['text']);
                    if(array_key_exists('author', $quote)) $row['author'] = $san->text($quote['author']);
                    if(array_key_exists('role', $quote)) $row['role'] = $san->text($quote['role']);
                    return $row;
                }));
        }
        return array();
    }

    /** Convert a YouTube/Vimeo URL into an embeddable URL, or '' if unrecognized. */
    public function videoEmbedUrl($url) {
        $url = trim((string) $url);
        if($url === '') return '';
        // YouTube
        if(preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Vimeo
        if(preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return '';
    }
}
