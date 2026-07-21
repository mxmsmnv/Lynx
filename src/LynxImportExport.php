<?php namespace ProcessWire;

require_once __DIR__ . '/LynxDemoProfiles.php';

/**
 * Portable JSON export/import of profiles (links + blocks + translations)
 * and the built-in demo profiles used for first-run onboarding.
 * Part of the Lynx class via trait composition.
 */
trait LynxImportExport {

    use LynxDemoProfiles;

    /** Export one profile (or all if null) to a portable array. */
    public function exportProfiles($profileId = null) {
        $profiles = $profileId ? array($this->getProfile($profileId)) : $this->getProfiles();
        $out = array('lynx_export' => 1, 'version' => self::getModuleInfo()['version'], 'profiles' => array());
        foreach($profiles as $p) {
            if(!$p) continue;
            $links = $this->getLinks($p['id']);
            $blocks = $this->getBlocks($p['id']);
            // strip runtime/identity fields
            foreach(array('id','user_id','views','created','modified') as $k) unset($p[$k]);
            $clean = array();
            foreach($links as $l) {
                foreach(array('id','profile_id','clicks') as $k) unset($l[$k]);
                $clean[] = $l;
            }
            $cleanBlocks = array();
            foreach($blocks as $b) {
                foreach(array('id','profile_id') as $k) unset($b[$k]);
                $cleanBlocks[] = $b;
            }
            $p['links'] = $clean;
            $p['blocks'] = $cleanBlocks;
            $out['profiles'][] = $p;
        }
        return $out;
    }

    /**
     * Import profiles from an export array. Returns [imported, skipped, errors].
     * Slug collisions are suffixed with -2, -3, ... unless $overwrite is true.
     */
    public function importProfiles(array $data, $overwrite = false) {
        $res = array('imported' => 0, 'skipped' => 0, 'errors' => array());
        if(empty($data['profiles']) || !is_array($data['profiles'])) {
            $res['errors'][] = 'No profiles found in import data';
            return $res;
        }
        if(count($data['profiles']) > 500) {
            $res['errors'][] = 'Import is limited to 500 profiles per request';
            return $res;
        }
        foreach($data['profiles'] as $p) {
            if(empty($p['slug'])) { $res['skipped']++; continue; }
            $slug = $p['slug'];
            $existing = $this->getProfileBySlug($slug);
            if($existing && !$overwrite) {
                $slug = $this->uniqueSlug($slug);
            }
            $p['slug'] = $slug;
            if($existing && $overwrite) $p['id'] = $existing['id'];
            $links = $p['links'] ?? array();
            $blocks = $p['blocks'] ?? array();
            unset($p['links'], $p['blocks']);
            if(!is_array($links) || !is_array($blocks)) {
                $res['errors'][] = "Slug '$slug': links and blocks must be arrays";
                continue;
            }
            if(count($links) > 1000 || count($blocks) > 1000) {
                $res['errors'][] = "Slug '$slug': relation limit exceeded";
                continue;
            }
            $db = $this->wire('database');
            try {
                $db->beginTransaction();
                $pid = $this->saveProfile($p);
                if($existing && $overwrite) {
                    $db->prepare("DELETE FROM " . self::TABLE_CLICKS . " WHERE link_id IN (SELECT id FROM " . self::TABLE_LINKS . " WHERE profile_id=:p)")
                        ->execute(array(':p' => $pid));
                    $db->prepare("DELETE FROM " . self::TABLE_LINKS . " WHERE profile_id=:p")
                        ->execute(array(':p' => $pid));
                    $db->prepare("DELETE FROM " . self::TABLE_BLOCKS . " WHERE profile_id=:p")
                        ->execute(array(':p' => $pid));
                }
                foreach($links as $i => $l) {
                    $l['profile_id'] = $pid;
                    $l['sort'] = $l['sort'] ?? $i;
                    $this->saveLink($l);
                }
                foreach($blocks as $i => $b) {
                    $b['profile_id'] = $pid;
                    $b['sort'] = $b['sort'] ?? $i;
                    $this->saveBlock($b);
                }
                $db->commit();
                $res['imported']++;
            } catch(\Throwable $e) {
                if($db->inTransaction()) $db->rollBack();
                $res['errors'][] = "Slug '$slug': " . $e->getMessage();
            }
        }
        return $res;
    }

}
