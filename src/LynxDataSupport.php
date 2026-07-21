<?php namespace ProcessWire;

/** Slug, manager-config, and public-cache helpers. */
trait LynxDataSupport {

    /* ----- Slugs / helpers / cache ----- */

    /** Return a slug not currently in use (and not reserved), suffixing -2, -3, ... */
    public function uniqueSlug($slug) {
        $slug = $this->wire('sanitizer')->pageName($slug, true);
        if($slug === '') $slug = 'profile';
        $taken = function($s) {
            return (bool) $this->getProfileBySlug($s) || in_array($s, $this->reservedSlugs(), true);
        };
        if(!$taken($slug)) return $slug;
        $n = 2;
        while($taken($slug . "-$n")) $n++;
        return $slug . "-$n";
    }

    protected function managerConfig($key, $default = null) {
        $modules = $this->wire('modules');
        if(!method_exists($modules, 'getConfig')) return $default;
        try {
            $value = $modules->getConfig('LynxManager', $key);
            return $value === null || $value === '' || $value === false ? $default : $value;
        } catch(\Throwable $e) {
            return $default;
        }
    }

    /** WireCache key for a rendered public page. */
    protected function cacheKey($slug, $lang) {
        return 'lynx_page__' . $lang . '__' . $this->wire('sanitizer')->pageName($slug);
    }

    /** Invalidate cached public pages for a profile by slug (no-op when cache disabled). */
    protected function clearProfileCacheBySlug($slug) {
        if((int) $this->cacheTtl <= 0 || !$slug) return;
        $cache = $this->wire('cache');
        $cache->delete($this->cacheKey($slug, ''));
        foreach($this->getSupportedLanguages() as $l) $cache->delete($this->cacheKey($slug, $l));
    }

    /** Clear every rendered public profile after global presentation/route changes. */
    public function clearPublicPageCache() {
        $this->wire('cache')->delete('lynx_page__*');
    }

    /** Invalidate cached public pages for a profile by id. */
    protected function clearProfileCache($profileId) {
        if((int) $this->cacheTtl <= 0 || !$profileId) return;
        $p = $this->getProfile($profileId);
        if($p) $this->clearProfileCacheBySlug($p['slug']);
    }
}
