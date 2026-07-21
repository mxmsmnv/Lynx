<?php namespace ProcessWire;

/**
 * Language + multilingual translation helpers for Lynx.
 * Part of the Lynx class via trait composition.
 */
trait LynxTranslations {

    public function getSupportedLanguages() {
        $raw = (string) ($this->supportedLanguages ?: 'en');
        $langs = array();
        foreach(explode(',', $raw) as $lang) {
            $lang = $this->normalizeLang($lang);
            if($lang && !in_array($lang, $langs, true)) $langs[] = $lang;
        }
        $default = $this->normalizeLang($this->defaultLanguage ?: 'en');
        if($default && !in_array($default, $langs, true)) array_unshift($langs, $default);
        return $langs ?: array('en');
    }

    protected function normalizeLang($lang) {
        $lang = strtolower(trim((string) $lang));
        $lang = str_replace('_', '-', $lang);
        $lang = preg_replace('/[^a-z0-9-]/', '', $lang);
        if($lang === '') $lang = 'en';
        return substr($lang, 0, 16);
    }

    protected function isSupportedLang($lang) {
        return in_array($this->normalizeLang($lang), $this->getSupportedLanguages(), true);
    }

    protected function encodeTranslations($translations) {
        if(is_string($translations)) {
            $decoded = json_decode($translations, true);
            $translations = is_array($decoded) ? $decoded : array();
        }
        if(!is_array($translations)) $translations = array();
        $clean = array();
        $san = $this->wire('sanitizer');
        foreach($translations as $lang => $data) {
            $lang = $this->normalizeLang($lang);
            if(!is_array($data) || !$this->isSupportedLang($lang)) continue;
            $data = $this->sanitizeTranslationData($data, $san);
            if($data) $clean[$lang] = $data;
        }
        return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }

    /** Encode block translations with type-aware handling for nested URLs. */
    protected function encodeBlockTranslations($type, $translations) {
        if(is_string($translations)) {
            $decoded = json_decode($translations, true);
            $translations = is_array($decoded) ? $decoded : array();
        }
        if(!is_array($translations)) $translations = array();
        $san = $this->wire('sanitizer');
        $clean = array();
        foreach($translations as $lang => $data) {
            $lang = $this->normalizeLang($lang);
            if(!is_array($data) || !$this->isSupportedLang($lang)) continue;
            $row = array();
            if(array_key_exists('title', $data)) $row['title'] = $san->text($data['title']);
            if(array_key_exists('data', $data)) {
                $row['data'] = $this->sanitizeBlockTranslationData($type, $data['data']);
            }
            if($row) $clean[$lang] = $row;
        }
        return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }

    protected function sanitizeTranslationData(array $data, $san) {
        $out = array();
        foreach($data as $key => $value) {
            $key = is_int($key) || ctype_digit((string) $key) ? (int) $key : $san->fieldName($key);
            if($key === '') continue;
            if(is_array($value)) {
                $value = $this->sanitizeTranslationData($value, $san);
                if($value) $out[$key] = $value;
            } else {
                $value = $san->textarea((string) $value);
                if($value !== '') $out[$key] = $value;
            }
        }
        return $out;
    }

    protected function decodeTranslations($json) {
        if(is_array($json)) return $json;
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : array();
    }

    protected function translatedRow(array $row, $lang, array $fields, $baseLang = '') {
        $lang = $this->normalizeLang($lang);
        // Link and block rows do not carry their own language. Callers rendering
        // profile relations must provide the parent profile's base language so
        // a translation into the global default language is not skipped.
        $base = $this->normalizeLang($baseLang ?: ($row['lang'] ?? $this->defaultLanguage));
        if($lang === $base) return $row;
        $translations = $this->decodeTranslations($row['translations'] ?? '');
        if(empty($translations[$lang]) || !is_array($translations[$lang])) return $row;
        foreach($fields as $field) {
            if(!array_key_exists($field, $translations[$lang])) continue;
            if(is_array($row[$field] ?? null) && is_array($translations[$lang][$field])) {
                $row[$field] = $this->mergeTranslationData($row[$field], $translations[$lang][$field]);
            } else {
                $row[$field] = $translations[$lang][$field];
            }
        }
        return $row;
    }

    protected function mergeTranslationData(array $base, array $translation) {
        foreach($translation as $key => $value) {
            if(is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeTranslationData($base[$key], $value);
            } elseif($value !== '' && $value !== null) {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    protected function profileLang(array $profile, $lang = '') {
        $lang = $this->normalizeLang($lang ?: ($profile['lang'] ?? $this->defaultLanguage));
        return $this->isSupportedLang($lang) ? $lang : $this->normalizeLang($profile['lang'] ?? $this->defaultLanguage);
    }

    protected function languageOptionsHtml($selected) {
        $san = $this->wire('sanitizer');
        $out = '';
        foreach($this->getSupportedLanguages() as $lang) {
            $sel = $lang === $selected ? ' selected' : '';
            $out .= "<option value='" . $san->entities($lang) . "'$sel>" . strtoupper($san->entities($lang)) . "</option>";
        }
        return $out;
    }

    protected function availableLanguages(array $row) {
        $langs = array($this->normalizeLang($row['lang'] ?? $this->defaultLanguage));
        foreach(array_keys($this->decodeTranslations($row['translations'] ?? '')) as $lang) {
            $lang = $this->normalizeLang($lang);
            if($this->isSupportedLang($lang) && !in_array($lang, $langs, true)) $langs[] = $lang;
        }
        return $langs;
    }
}
