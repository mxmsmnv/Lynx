<?php namespace ProcessWire;

/**
 * Public front-end routing for Lynx. Wired from init() via a path hook and a
 * ProcessPageView::pageNotFound hook so the module needs no template pages.
 * Part of the Lynx class via trait composition.
 */
trait LynxRouting {

    /**
     * A URL hook and the page-not-found fallback can both run for the same
     * request. Keep the first non-null result so mutations, redirects and view
     * counters are never dispatched twice.
     */
    protected $lynxRouteHandled = false;
    protected $lynxRouteResult = null;

    public function hookRoute(HookEvent $e) {
        if($this->lynxRouteHandled) {
            $e->replace = true;
            $e->return = $this->lynxRouteResult === true ? '' : $this->lynxRouteResult;
            return;
        }
        $url = trim($this->input->url, '/');
        $parts = $url === '' ? array() : explode('/', $url);
        $root = trim($this->rootUrl, '/');

        if($root !== '') {
            if(!isset($parts[0]) || $parts[0] !== $root) return;
            array_shift($parts);
        }
        $out = $this->dispatchRoute($parts);
        if($out === null) return;
        $this->lynxRouteHandled = true;
        $this->lynxRouteResult = $out;
        $e->replace = true;
        $e->return = $out === true ? '' : $out;
    }

    public function hookPathRoute(HookEvent $e) {
        if($this->lynxRouteHandled) {
            $e->return = $this->lynxRouteResult;
            return;
        }
        $url = trim($e->arguments(0), '/');
        $parts = $url === '' ? array() : explode('/', $url);
        $root = trim($this->rootUrl, '/');

        if($root !== '') {
            if(!isset($parts[0]) || $parts[0] !== $root) return;
            array_shift($parts);
        }
        $out = $this->dispatchRoute($parts);
        if($out === null) return;
        $this->lynxRouteHandled = true;
        $this->lynxRouteResult = $out;
        $e->return = $out;
    }

    protected function dispatchRoute(array $parts) {
        // /{root}/api/...  -> REST API
        if(isset($parts[0]) && $parts[0] === 'api') {
            if(!$this->enableApi) return null;
            array_shift($parts);
            return $this->apiDispatch($parts);
        }

        // /{root}/go/{linkId}  -> click tracking redirect
        if(isset($parts[0]) && $parts[0] === 'go' && count($parts) === 2) {
            $this->trackAndRedirect((int) $parts[1]);
            return true;
        }

        // /{root}/edit            -> editor dashboard (list own profiles)
        // /{root}/edit/{slug}     -> front-end editor for one profile
        if(isset($parts[0]) && $parts[0] === 'edit') {
            if(count($parts) > 2) return null;
            return $this->editorRoute(isset($parts[1]) ? $parts[1] : '');
        }

        // /{root}/upload          -> AJAX image upload (POST, auth + CSRF)
        if(isset($parts[0]) && $parts[0] === 'upload') {
            if(count($parts) !== 1) return null;
            return $this->handleUpload();
        }

        // /{root}/{slug}  -> public profile page
        if(!$this->enablePublic) return null;
        [$lang, $slug] = $this->routeLangAndSlug($parts);
        if($slug === '') return null;

        $profile = $this->getProfileBySlug($slug);
        if(!$profile || !$profile['active']) return null;
        $this->sendPublicSecurityHeaders();

        // The front-end editor's live-preview iframe loads the page with
        // ?lynxpreview=1; such requests must not inflate the view counter.
        $isPreview = (bool) $this->wire('input')->get('lynxpreview');
        if(!$isPreview) $this->incViews($profile['id']);

        // Optional WireCache of the rendered HTML (config: cacheTtl seconds).
        // Disabled by default; only used for anonymous, non-preview requests.
        $ttl = (int) $this->cacheTtl;
        if($ttl > 0 && !$isPreview && !$this->wire('user')->isLoggedin()) {
            $key = $this->cacheKey($profile['slug'], $this->profileLang($profile, $lang));
            $cache = $this->wire('cache');
            $html = $cache->get($key);
            if($html === null || $html === '') {
                $html = $this->renderPage($profile, $lang);
                $cache->save($key, $html, $ttl);
            }
            return $this->encodePublicResponse($html);
        }

        return $this->encodePublicResponse($this->renderPage($profile, $lang));
    }

    protected function routeLangAndSlug(array $parts) {
        if(count($parts) < 1 || count($parts) > 2) return array('', '');
        if(count($parts) === 2 && !$this->isSupportedLang($parts[0])) return array('', '');
        $slug = isset($parts[0]) ? $parts[0] : '';
        $lang = '';
        if(isset($parts[1]) && $this->isSupportedLang($parts[0])) {
            $lang = $this->normalizeLang($parts[0]);
            $slug = $parts[1];
        }
        return array($lang, $slug);
    }
}
