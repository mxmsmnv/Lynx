<?php namespace ProcessWire;

/** Link persistence and ordering. */
trait LynxLinkData {

    /* ----- Links ----- */

    public function getLinks($profileId, $activeOnly = false) {
        $db = $this->wire('database');
        $sql = "SELECT * FROM " . self::TABLE_LINKS . " WHERE profile_id=:p";
        $params = array(':p' => (int) $profileId);
        if($activeOnly) {
            // active flag + scheduling window (0 = unbounded)
            $sql .= " AND active=1
                      AND (start_date = 0 OR start_date <= :now1)
                      AND (end_date = 0 OR end_date >= :now2)";
            $params[':now1'] = time();
            $params[':now2'] = time();
        }
        $sql .= " ORDER BY sort, id";
        $q = $db->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveLink(array $data) {
        $db = $this->wire('database');
        $san = $this->wire('sanitizer');
        $fields = array(
            'profile_id' => (int) ($data['profile_id'] ?? 0),
            'label'      => $san->text($data['label'] ?? ''),
            'url'        => $this->sanitizeLinkUrl($data['url'] ?? ''),
            'icon'       => $san->fieldName($data['icon'] ?? ''),
            'is_social'  => empty($data['is_social']) ? 0 : 1,
            'translations' => $this->encodeTranslations($data['translations'] ?? array()),
            'start_date' => $this->toTs($data['start_date'] ?? 0),
            'end_date'   => $this->toTs($data['end_date'] ?? 0),
            'sort'       => (int) ($data['sort'] ?? 0),
            'active'     => empty($data['active']) ? 0 : 1,
        );
        if(!empty($data['id'])) {
            $oldProfileId = $fields['profile_id'];
            if(!$oldProfileId) {
                $q = $db->prepare("SELECT profile_id FROM " . self::TABLE_LINKS . " WHERE id=:id");
                $q->execute(array(':id' => (int) $data['id']));
                $oldProfileId = (int) $q->fetchColumn();
            }
            $cols = array('label','url','icon','is_social','translations','start_date','end_date','sort','active');
            $sets = array();
            foreach($cols as $k) $sets[] = "$k=:$k";
            $sql = "UPDATE " . self::TABLE_LINKS . " SET " . implode(',', $sets) . " WHERE id=:id";
            if($fields['profile_id']) $sql .= " AND profile_id=:profile_id";
            $q = $db->prepare($sql);
            $q->bindValue(':id', (int) $data['id']);
            if($fields['profile_id']) $q->bindValue(':profile_id', $fields['profile_id']);
            foreach($cols as $k) $q->bindValue(":$k", $fields[$k]);
            $q->execute();
            $this->clearProfileCache($fields['profile_id']);
            if($oldProfileId && $oldProfileId !== $fields['profile_id']) $this->clearProfileCache($oldProfileId);
            return (int) $data['id'];
        }
        $cols = array_keys($fields);
        $sql = "INSERT INTO " . self::TABLE_LINKS . " (" . implode(',', $cols) . ") VALUES (:" . implode(',:', $cols) . ")";
        $q = $db->prepare($sql);
        foreach($fields as $k => $v) $q->bindValue(":$k", $v);
        $q->execute();
        $this->clearProfileCache($fields['profile_id']);
        return (int) $db->lastInsertId();
    }

    public function deleteLink($id, $profileId = null) {
        $id = (int) $id;
        $db = $this->wire('database');
        $params = array(':id' => $id);
        $scope = '';
        if($profileId !== null) {
            $scope = " AND profile_id=:p";
            $params[':p'] = (int) $profileId;
        }
        $exists = $db->prepare("SELECT id FROM " . self::TABLE_LINKS . " WHERE id=:id$scope");
        $exists->execute($params);
        if(!$exists->fetchColumn()) return false;
        if($profileId === null) {
            $q = $db->prepare("SELECT profile_id FROM " . self::TABLE_LINKS . " WHERE id=:id");
            $q->execute(array(':id' => $id));
            $profileId = (int) $q->fetchColumn();
        }
        $db->prepare("DELETE FROM " . self::TABLE_CLICKS . " WHERE link_id=:id")->execute(array(':id' => $id));
        $db->prepare("DELETE FROM " . self::TABLE_LINKS . " WHERE id=:id")->execute(array(':id' => $id));
        if($profileId !== null) $this->clearProfileCache($profileId);
        return true;
    }

    /** Persist new link order from an array of link IDs. */
    public function reorderLinks(array $orderedIds, $profileId = null) {
        $db = $this->wire('database');
        $sql = "UPDATE " . self::TABLE_LINKS . " SET sort=:s WHERE id=:id";
        if($profileId !== null) $sql .= " AND profile_id=:p";
        $q = $db->prepare($sql);
        foreach(array_values($orderedIds) as $i => $id) {
            $params = array(':s' => $i, ':id' => (int) $id);
            if($profileId !== null) $params[':p'] = (int) $profileId;
            $q->execute($params);
        }
        if($profileId !== null) $this->clearProfileCache($profileId);
    }
}
