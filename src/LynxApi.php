<?php namespace ProcessWire;

/**
 * REST API + shared request helpers (JSON body, CSRF, public projection).
 * Part of the Lynx class via trait composition.
 */
trait LynxApi {

    protected function jsonBody() {
        if($this->jsonBodyLoaded) return $this->jsonBodyCache;
        $body = json_decode(file_get_contents('php://input'), true);
        $this->jsonBodyLoaded = true;
        $this->jsonBodyCache = is_array($body) ? $body : null;
        return $this->jsonBodyCache;
    }

    /** Public-facing projection of a profile row (+ optional links/blocks). */
    protected function publicProfile(array $profile, $includeRelations = false, $lang = '') {
        $lang = $this->profileLang($profile, $lang ?: $this->wire('input')->get('lang'));
        $baseLang = $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage);
        $profile = $this->translatedRow($profile, $lang, array('title', 'bio', 'seo_title', 'seo_description', 'og_image'), $baseLang);
        $out = array(
            'slug' => $profile['slug'],
            'lang' => $lang,
            'base_lang' => $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage),
            'available_langs' => $this->availableLanguages($profile),
            'title' => $profile['title'],
            'bio' => $profile['bio'],
            'avatar' => $profile['avatar'],
            'theme' => $profile['theme'],
            'font' => $this->normalizeFont($profile['font'] ?? ''),
            'accent' => $profile['accent'],
            'seo_title' => $profile['seo_title'] ?? '',
            'seo_description' => $profile['seo_description'] ?? '',
            'og_image' => $profile['og_image'] ?? '',
            'noindex' => (int) ($profile['noindex'] ?? 0),
            'bg_type' => $profile['bg_type'] ?? '',
            'bg_value' => $profile['bg_value'] ?? '',
        );
        if($includeRelations) {
            $out['links'] = array_map(function($l) use ($lang, $baseLang) {
                $l = $this->translatedRow($l, $lang, array('label'), $baseLang);
                return array(
                    'id' => (int) $l['id'],
                    'label' => $l['label'],
                    'url' => $l['url'],
                    'icon' => $l['icon'],
                    'is_social' => (int) $l['is_social'],
                );
            }, $this->getLinks($profile['id'], true));
            $out['blocks'] = array_map(function($b) use ($lang, $baseLang) {
                $b = $this->translatedRow($b, $lang, array('title', 'data'), $baseLang);
                return array(
                    'type' => $b['type'],
                    'title' => $b['title'],
                    // Revalidate after merging translations so nested URLs can
                    // never bypass the block type's public URL policy.
                    'data' => $this->sanitizeBlockData($b['type'], $b['data']),
                );
            }, $this->getBlocks($profile['id'], true));
        }
        return $out;
    }

    protected function apiDispatch(array $parts) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            // /api/profiles
            if(empty($parts) || $parts[0] === 'profiles') {
                if(count($parts) > 3 || (count($parts) === 3 && !$this->isSupportedLang($parts[1]))) {
                    return $this->json(array('error' => 'Not found'), 404);
                }
                if(isset($parts[1])) {
                    $lang = $this->wire('input')->get('lang');
                    $slug = $parts[1];
                    if(isset($parts[2]) && $this->isSupportedLang($parts[1])) {
                        $lang = $parts[1];
                        $slug = $parts[2];
                    }
                    $profile = $this->getProfileBySlug($slug) ?: $this->getProfile($slug);
                    if(!$profile || !$profile['active']) return $this->json(array('error' => 'Not found'), 404);
                    return $this->json($this->publicProfile($profile, true, $lang));
                }
                $lang = $this->wire('input')->get('lang');
                $list = array_filter($this->getProfiles(), fn($p) => $p['active']);
                return $this->json(array_values(array_map(fn($p) => $this->publicProfile($p, false, $lang), $list)));
            }
            // POST /api/reorder  { ids: [..] }  (admin only + CSRF token)
            if($parts[0] === 'reorder') {
                if(count($parts) !== 1) return $this->json(array('error' => 'Not found'), 404);
                if($_SERVER['REQUEST_METHOD'] !== 'POST') return $this->json(array('error' => 'Method not allowed'), 405);
                if(!$this->wire('user')->hasPermission('lynx-admin')) return $this->json(array('error' => 'Forbidden'), 403);
                if(!$this->validCsrf()) return $this->json(array('error' => 'Invalid CSRF token'), 403);
                $body = $this->jsonBody();
                if(!is_array($body['ids'] ?? null)) return $this->json(array('error' => 'Bad request'), 400);
                $profileId = isset($body['profile_id']) ? (int) $body['profile_id'] : null;
                $ids = array_values(array_filter(array_map('intval', $body['ids'])));
                if(!$ids) return $this->json(array('error' => 'Bad request'), 400);
                if($profileId === null) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $q = $this->wire('database')->prepare("SELECT DISTINCT profile_id FROM " . self::TABLE_LINKS . " WHERE id IN ($placeholders)");
                    $q->execute($ids);
                    $profileIds = array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
                    if(count($profileIds) !== 1) return $this->json(array('error' => 'Bad request'), 400);
                    $profileId = $profileIds[0];
                }
                $profile = $this->getProfile($profileId);
                if(!$profile || !$this->ownsProfile($profile)) return $this->json(array('error' => 'Forbidden'), 403);
                $this->reorderLinks($ids, $profileId);
                return $this->json(array('ok' => true));
            }
            return $this->json(array('error' => 'Unknown endpoint'), 404);
        } catch(\Throwable $ex) {
            $this->wire('log')->save('lynx', 'API error: ' . $ex->getMessage());
            return $this->json(array('error' => 'Server error'), 500);
        }
    }

    protected function json($data, $status = 200) {
        http_response_code($status);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function requireLynxAdmin() {
        if($this->wire('user')->hasPermission('lynx-admin')) return true;
        throw new WirePermissionException('You do not have lynx-admin permission.');
    }

    /** Validate the PW CSRF token sent via header or JSON body. */
    protected function validCsrf() {
        $session = $this->wire('session');
        $tokenName = $session->CSRF->getTokenName();
        $tokenValue = $session->CSRF->getTokenValue();
        // header (X-XSRF-Token) preferred; fall back to a body field
        $sent = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
        if(!$sent) {
            $body = $this->jsonBody();
            $sent = is_array($body) ? ($body['csrf'] ?? '') : '';
        }
        return is_string($sent) && hash_equals($tokenValue, $sent);
    }
}
