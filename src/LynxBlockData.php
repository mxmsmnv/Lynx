<?php namespace ProcessWire;

/** Portfolio block persistence and ordering. */
trait LynxBlockData {

    /* ----- Portfolio blocks ----- */

    public function getBlocks($profileId, $activeOnly = false) {
        $db = $this->wire('database');
        $sql = "SELECT * FROM " . self::TABLE_BLOCKS . " WHERE profile_id=:p";
        if($activeOnly) $sql .= " AND active=1";
        $sql .= " ORDER BY sort, id";
        $q = $db->prepare($sql);
        $q->execute(array(':p' => (int) $profileId));
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
        foreach($rows as &$r) $r['data'] = $this->decodeBlockData($r['data']);
        return $rows;
    }

    public function getBlock($id) {
        $db = $this->wire('database');
        $q = $db->prepare("SELECT * FROM " . self::TABLE_BLOCKS . " WHERE id=:id");
        $q->execute(array(':id' => (int) $id));
        $r = $q->fetch(\PDO::FETCH_ASSOC);
        if(!$r) return null;
        $r['data'] = $this->decodeBlockData($r['data']);
        return $r;
    }

    protected function decodeBlockData($json) {
        $d = json_decode((string) $json, true);
        return is_array($d) ? $d : array();
    }

    /**
     * Save a portfolio block. $data['data'] is a structure whose shape depends
     * on type; it is sanitized per type before storage.
     */
    public function saveBlock(array $data) {
        $db = $this->wire('database');
        $san = $this->wire('sanitizer');
        $type = $san->fieldName($data['type'] ?? 'gallery');
        if(!isset($this->getBlockTypes()[$type])) $type = 'gallery';

        $fields = array(
            'profile_id' => (int) ($data['profile_id'] ?? 0),
            'type'       => $type,
            'title'      => $san->text($data['title'] ?? ''),
            'data'       => json_encode($this->sanitizeBlockData($type, $data['data'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'translations' => $this->encodeBlockTranslations($type, $data['translations'] ?? array()),
            'sort'       => (int) ($data['sort'] ?? 0),
            'active'     => empty($data['active']) ? 0 : 1,
        );

        if(!empty($data['id'])) {
            $oldProfileId = $fields['profile_id'];
            if(!$oldProfileId) {
                $q = $db->prepare("SELECT profile_id FROM " . self::TABLE_BLOCKS . " WHERE id=:id");
                $q->execute(array(':id' => (int) $data['id']));
                $oldProfileId = (int) $q->fetchColumn();
            }
            $cols = array('type','title','data','translations','sort','active');
            $sets = array();
            foreach($cols as $k) $sets[] = "$k=:$k";
            $sql = "UPDATE " . self::TABLE_BLOCKS . " SET " . implode(',', $sets) . " WHERE id=:id";
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
        $sql = "INSERT INTO " . self::TABLE_BLOCKS . " (" . implode(',', $cols) . ") VALUES (:" . implode(',:', $cols) . ")";
        $q = $db->prepare($sql);
        foreach($fields as $k => $v) $q->bindValue(":$k", $v);
        $q->execute();
        $this->clearProfileCache($fields['profile_id']);
        return (int) $db->lastInsertId();
    }

    public function deleteBlock($id) {
        return $this->deleteBlockForProfile($id, null);
    }

    protected function deleteBlockForProfile($id, $profileId = null) {
        $db = $this->wire('database');
        $sql = "DELETE FROM " . self::TABLE_BLOCKS . " WHERE id=:id";
        $params = array(':id' => (int) $id);
        if($profileId !== null) {
            $sql .= " AND profile_id=:p";
            $params[':p'] = (int) $profileId;
        }
        if($profileId === null) {
            $q = $db->prepare("SELECT profile_id FROM " . self::TABLE_BLOCKS . " WHERE id=:id");
            $q->execute(array(':id' => (int) $id));
            $profileId = (int) $q->fetchColumn();
        }
        $q = $db->prepare($sql);
        $q->execute($params);
        if($profileId !== null) $this->clearProfileCache($profileId);
        return $q->rowCount() > 0;
    }

    public function reorderBlocks(array $orderedIds, $profileId = null) {
        $db = $this->wire('database');
        $sql = "UPDATE " . self::TABLE_BLOCKS . " SET sort=:s WHERE id=:id";
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
