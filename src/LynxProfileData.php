<?php namespace ProcessWire;

/** Profile persistence and aggregate profile metrics. */
trait LynxProfileData {

    /* ----- Profiles ----- */

    public function getProfiles($userId = null) {
        $db = $this->wire('database');
        if($userId !== null) {
            $q = $db->prepare("SELECT * FROM " . self::TABLE_PROFILES . " WHERE user_id=:u ORDER BY title");
            $q->execute(array(':u' => (int) $userId));
        } else {
            $q = $db->query("SELECT * FROM " . self::TABLE_PROFILES . " ORDER BY title");
        }
        return $q->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getProfile($id) {
        $db = $this->wire('database');
        $q = $db->prepare("SELECT * FROM " . self::TABLE_PROFILES . " WHERE id=:id");
        $q->execute(array(':id' => (int) $id));
        return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getProfileBySlug($slug) {
        $db = $this->wire('database');
        $q = $db->prepare("SELECT * FROM " . self::TABLE_PROFILES . " WHERE slug=:s");
        $q->execute(array(':s' => $this->wire('sanitizer')->pageName($slug)));
        return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Aggregate link counts keyed by profile id (single query, avoids N+1). */
    public function linkCountsByProfile() {
        $q = $this->wire('database')->query(
            "SELECT profile_id, COUNT(*) AS n FROM " . self::TABLE_LINKS . " GROUP BY profile_id");
        return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Aggregate block counts keyed by profile id (single query, avoids N+1). */
    public function blockCountsByProfile() {
        $q = $this->wire('database')->query(
            "SELECT profile_id, COUNT(*) AS n FROM " . self::TABLE_BLOCKS . " GROUP BY profile_id");
        return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Aggregate click totals keyed by profile id (single query, avoids N+1). */
    public function clickTotalsByProfile() {
        $q = $this->wire('database')->query(
            "SELECT profile_id, COALESCE(SUM(clicks),0) AS n FROM " . self::TABLE_LINKS . " GROUP BY profile_id");
        return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Slugs reserved by public sub-routes; profiles may not use them. */
    public function reservedSlugs() {
        return array('api', 'go', 'edit', 'upload');
    }

    public function saveProfile(array $data) {
        $db = $this->wire('database');
        $san = $this->wire('sanitizer');
        $now = time();
        $isUpdate = !empty($data['id']);
        $old = $isUpdate ? $this->getProfile((int) $data['id']) : null;
        if($isUpdate && !$old) {
            throw new \InvalidArgumentException('Profile not found.');
        }
        $defaultTheme = $this->managerConfig('defaultTheme', 'default');
        $defaultAccent = $this->managerConfig('defaultAccent', '#1e87f0');
        $defaultFont = $this->managerConfig('defaultFont', '');
        $bgType = $san->fieldName($data['bg_type'] ?? '');
        $bgValue = $bgType === 'image'
            ? $this->sanitizePublicUrl($data['bg_value'] ?? '', true)
            : $san->text(substr($data['bg_value'] ?? '', 0, 512));

        $fields = array(
            // Updating content must never implicitly transfer ownership to the
            // current editor/importer. Ownership changes are always explicit.
            'user_id' => array_key_exists('user_id', $data)
                ? (int) $data['user_id']
                : (int) ($old['user_id'] ?? $this->wire('user')->id),
            'slug'    => $san->pageName($data['slug'] ?? '', true),
            'lang'    => $this->normalizeLang($data['lang'] ?? $this->defaultLanguage),
            'title'   => $san->text($data['title'] ?? ''),
            'bio'     => $san->textarea($data['bio'] ?? ''),
            'avatar'  => $this->sanitizePublicUrl($data['avatar'] ?? '', true),
            'theme'   => $san->fieldName($data['theme'] ?? $defaultTheme),
            'font'    => $this->normalizeFont($data['font'] ?? $defaultFont),
            'accent'  => preg_replace('/[^#0-9a-fA-F]/', '', $data['accent'] ?? $defaultAccent),
            'seo_title'       => $san->text($data['seo_title'] ?? ''),
            'seo_description' => $san->text(substr($data['seo_description'] ?? '', 0, 512)),
            'og_image'        => $this->sanitizePublicUrl($data['og_image'] ?? '', true),
            'noindex'         => empty($data['noindex']) ? 0 : 1,
            'custom_css'      => $this->sanitizeCss($data['custom_css'] ?? ''),
            'bg_type'         => $bgType,
            'bg_value'        => $bgValue,
            'translations'    => $this->encodeTranslations($data['translations'] ?? array()),
            'active'  => empty($data['active']) ? 0 : 1,
        );

        if($fields['slug'] === '') $fields['slug'] = $this->uniqueSlug('profile');
        if(in_array($fields['slug'], $this->reservedSlugs(), true)) {
            throw new \InvalidArgumentException("Slug '{$fields['slug']}' is reserved and cannot be used.");
        }
        $existing = $this->getProfileBySlug($fields['slug']);
        if($existing && (!$isUpdate || (int) $existing['id'] !== (int) $data['id'])) {
            throw new \InvalidArgumentException("Slug '{$fields['slug']}' is already in use.");
        }

        if($isUpdate) {
            $fields['modified'] = $now;
            $sets = array();
            foreach($fields as $k => $v) $sets[] = "$k=:$k";
            $sql = "UPDATE " . self::TABLE_PROFILES . " SET " . implode(',', $sets) . " WHERE id=:id";
            $q = $db->prepare($sql);
            $fields['id'] = (int) $data['id'];
            foreach($fields as $k => $v) $q->bindValue(":$k", $v);
            $q->execute();
            if($old && $old['slug'] !== $fields['slug']) $this->clearProfileCacheBySlug($old['slug']);
            $this->clearProfileCacheBySlug($fields['slug']);
            return (int) $data['id'];
        }

        $fields['created'] = $now;
        $fields['modified'] = $now;
        $cols = array_keys($fields);
        $sql = "INSERT INTO " . self::TABLE_PROFILES . " (" . implode(',', $cols) . ") VALUES (:" . implode(',:', $cols) . ")";
        $q = $db->prepare($sql);
        foreach($fields as $k => $v) $q->bindValue(":$k", $v);
        $q->execute();
        $this->clearProfileCacheBySlug($fields['slug']);
        return (int) $db->lastInsertId();
    }

    public function deleteProfile($id) {
        $db = $this->wire('database');
        $id = (int) $id;
        $profile = $this->getProfile($id);
        $db->beginTransaction();
        try {
            // Keep explicit deletes for compatibility with existing installs
            // that predate the foreign keys added by v119.
            $db->prepare("DELETE c FROM " . self::TABLE_CLICKS . " c
                JOIN " . self::TABLE_LINKS . " l ON l.id = c.link_id
                WHERE l.profile_id = :p")->execute(array(':p' => $id));
            $db->prepare("DELETE FROM " . self::TABLE_LINKS . " WHERE profile_id=:p")->execute(array(':p' => $id));
            $db->prepare("DELETE FROM " . self::TABLE_BLOCKS . " WHERE profile_id=:p")->execute(array(':p' => $id));
            $db->prepare("DELETE FROM " . self::TABLE_PROFILES . " WHERE id=:id")->execute(array(':id' => $id));
            $db->commit();
        } catch(\Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Remove managed files only after the database transaction commits.
        if($profile && !empty($profile['avatar']) && strpos($profile['avatar'], $this->avatarUrl()) === 0) {
            $f = $this->avatarPath() . basename($profile['avatar']);
            if(is_file($f)) @unlink($f);
        }
        foreach(glob($this->avatarPath() . $id . '-*') ?: array() as $f) {
            if(is_file($f)) @unlink($f);
        }
        if($profile) $this->clearProfileCacheBySlug($profile['slug']);
    }
}
