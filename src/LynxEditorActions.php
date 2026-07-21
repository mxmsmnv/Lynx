<?php namespace ProcessWire;

/** Front-end editor routing, persistence, ownership, and upload actions. */
trait LynxEditorActions {

    /** True if the current user may use the front-end editor. */
    protected function editorUserOk() {
        $u = $this->wire('user');
        return $u->isLoggedin() && $u->hasPermission('lynx-admin');
    }

    /** Redirect guests to login, then back to the editor. */
    protected function requireLogin() {
        $here = $this->wire('input')->url;
        $loginUrl = $this->wire('config')->urls->admin . 'login/?login=1';
        $this->wire('session')->redirect($loginUrl);
    }

    /** Router for /edit and /edit/{slug}. */
    protected function editorRoute($slug) {
        if(!$this->editorUserOk()) { $this->requireLogin(); return ''; }

        $input = $this->wire('input');

        // Handle a save POST (JSON body) before rendering
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            if(!$this->validCsrf()) { http_response_code(403); return json_encode(array('error' => 'Invalid CSRF token')); }
            $body = $this->jsonBody();
            if(!is_array($body)) { http_response_code(400); return json_encode(array('error' => 'Bad request')); }
            return json_encode($this->editorSave($body));
        }

        if($slug === '') return $this->editorDashboard();

        $profile = $this->getProfileBySlug($slug);
        if(!$profile) { $this->wire('session')->redirect($this->publicPath('edit/')); return ''; }
        return $this->editorPage($profile);
    }

    /** List the user's profiles with quick links to edit / view / create. */
    protected function editorDashboard() {
        $san = $this->wire('sanitizer');
        $user = $this->wire('user');
        // superusers see all; others see their own
        $profiles = $user->isSuperuser() ? $this->getProfiles() : $this->getProfiles($user->id);
        $linkCounts = $this->linkCountsByProfile();
        $blockCounts = $this->blockCountsByProfile();
        $clickTotals = $this->clickTotalsByProfile();
        $totalViews = 0;
        $totalClicks = 0;
        $activeCount = 0;

        $rows = '';
        foreach($profiles as $p) {
            $id = (int) $p['id'];
            $s = $san->entities($p['slug']);
            $t = $san->entities($p['title'] ?: $p['slug']);
            $profilePath = $this->publicPath($s);
            $editPath = $this->publicPath("edit/$s");
            $views = (int) ($p['views'] ?? 0);
            $clicks = (int) ($clickTotals[$id] ?? 0);
            $links = (int) ($linkCounts[$id] ?? 0);
            $blocks = (int) ($blockCounts[$id] ?? 0);
            $totalViews += $views;
            $totalClicks += $clicks;
            if(!empty($p['active'])) $activeCount++;
            $statusClass = !empty($p['active']) ? 'is-on' : 'is-off';
            $statusLabel = $san->entities(!empty($p['active']) ? $this->_('Active') : $this->_('Paused'));
            $meta = sprintf($this->_('%d links · %d blocks · %d views · %d clicks'), $links, $blocks, $views, $clicks);
            $rows .= "<div class='card profile-card'><div class='profile-main'><span class='status-dot $statusClass'>$statusLabel</span>"
                . "<strong>$t</strong><br><a class='muted profile-url' href='$profilePath' target='_blank' rel='noopener noreferrer'>$profilePath</a>"
                . "<div class='profile-meta'>" . $san->entities($meta) . "</div></div>"
                . "<div class='profile-actions'><a class='btn' href='$editPath'>" . $san->entities($this->_('Edit')) . "</a> "
                . "<a class='btn ghost' href='$profilePath' target='_blank' rel='noopener noreferrer'>" . $san->entities($this->_('View')) . "</a></div></div>";
        }
        if(!$rows) $rows = "<div class='empty-state'><strong>" . $san->entities($this->_("You don't have any pages yet.")) . "</strong>"
            . "<p class='muted'>" . $san->entities($this->_('Create the first profile, then add links, blocks, languages and a theme.')) . "</p></div>";

        $editBase = rtrim($this->publicPath('edit/'), '/') . '/';
        $newForm = "<form method='post' action='$editBase' id='newform'>"
            . "<input class='inp' name='__newslug' placeholder='your-name' required pattern='[a-z0-9]+(?:-[a-z0-9]+)*' aria-label='" . $san->entities($this->_('Profile slug')) . "'>"
            . "<button class='btn' type='button' onclick='createPage()'>" . $san->entities($this->_('Create new page')) . "</button></form>";

        $token = $this->csrfMeta();
        $cssUrl = $san->entities($this->assetUrl('editor.css'));
        $managerUrl = $san->entities($this->editorManagerUrl());
        $statCards = "<div class='stat-grid'>"
            . "<div class='stat-card'><span class='muted'>" . $san->entities($this->_('Profiles')) . "</span><strong>" . count($profiles) . "</strong></div>"
            . "<div class='stat-card'><span class='muted'>" . $san->entities($this->_('Active')) . "</span><strong>$activeCount</strong></div>"
            . "<div class='stat-card'><span class='muted'>" . $san->entities($this->_('Views')) . "</span><strong>$totalViews</strong></div>"
            . "<div class='stat-card'><span class='muted'>" . $san->entities($this->_('Clicks')) . "</span><strong>$totalClicks</strong></div>"
            . "</div>";
        return "<!doctype html><html lang='en'><head><meta charset='utf-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1'>$token"
            . "<title>" . $san->entities($this->_('My pages')) . "</title><link rel='stylesheet' href='$cssUrl'></head><body class='dash'>"
            . "<div class='wrap'><div class='dash-head'><div><h1>" . $san->entities($this->_('My pages')) . "</h1>"
            . "<p class='muted'>" . $san->entities($this->_('Edit public profiles, check their status and jump back to Lynx Manager when you need admin tools.')) . "</p></div>"
            . "<a class='btn ghost' href='$managerUrl'>" . $san->entities($this->_('Open admin')) . "</a></div>"
            . "$statCards<div class='create-panel'><div><strong>" . $san->entities($this->_('Create profile')) . "</strong>"
            . "<p class='muted'>" . $san->entities($this->_('Use a short lowercase slug. You can edit the profile details on the next screen.')) . "</p></div>$newForm</div>"
            . "<div class='list-head'><h2>" . $san->entities($this->_('Profiles')) . "</h2><span class='muted'>" . count($profiles) . "</span></div>"
            . "<div class='list'>$rows</div></div>"
            . "<script>
function createPage(){
  var slug=document.querySelector('[name=__newslug]').value.trim().toLowerCase().replace(/[^a-z0-9-]+/g,'-');
  if(!slug)return;
  fetch('$editBase',{method:'POST',headers:{'Content-Type':'application/json','X-XSRF-Token':window.__lynxCsrf},
    body:JSON.stringify({create:true,slug:slug})}).then(r=>r.json()).then(d=>{
    if(d.slug){location.href='$editBase'+d.slug;}else{alert(d.error||'Error');}
  });
}
</script></body></html>";
    }

    protected function editorManagerUrl() {
        $page = $this->wire('pages')->get("template=admin,name=lynx-manager");
        if($page && $page->id) return $page->url;
        return $this->wire('config')->urls->admin . 'setup/lynx-manager/';
    }

    /** Persist editor changes / create. Returns a result array. */
    protected function editorSave(array $body) {
        $user = $this->wire('user');

        // create a blank profile
        if(!empty($body['create'])) {
            $slug = $this->uniqueSlug($body['slug'] ?? 'page');
            $id = $this->saveProfile(array(
                'slug' => $slug,
                'title' => '',
                'active' => 1,
                'user_id' => $user->id,
                'lang' => $this->defaultLanguage,
                'font' => $this->managerConfig('defaultFont', ''),
            ));
            return array('ok' => true, 'slug' => $slug, 'id' => $id);
        }

        $id = (int) ($body['id'] ?? 0);
        $profile = $id ? $this->getProfile($id) : null;
        if(!$profile) return array('error' => 'Profile not found');
        if(!$this->ownsProfile($profile)) return array('error' => 'Forbidden');

        $requestedSlug = trim((string) ($body['slug'] ?? $profile['slug']));
        $slug = $this->wire('sanitizer')->pageName($requestedSlug, true);
        if($slug === '') return array('error' => $this->_('Profile slug cannot be empty.'));
        if(in_array($slug, $this->reservedSlugs(), true)) {
            return array('error' => sprintf($this->_('The slug “%s” is reserved.'), $slug));
        }
        $slugOwner = $this->getProfileBySlug($slug);
        if($slugOwner && (int) $slugOwner['id'] !== $id) {
            return array('error' => sprintf($this->_('The slug “%s” is already in use.'), $slug));
        }

        $db = $this->wire('database');
        try {
            $db->beginTransaction();
            // profile fields (custom CSS only if permitted)
            $canCss = $user->hasPermission('lynx-customcss');
            $this->saveProfile(array(
            'id'     => $id,
            'slug'   => $slug,
            'lang'   => $body['lang'] ?? $profile['lang'],
            'title'  => $body['title'] ?? '',
            'bio'    => $body['bio'] ?? '',
            'avatar' => $body['avatar'] ?? '',
            'accent' => $body['accent'] ?? '#1e87f0',
            'theme'  => $body['theme'] ?? 'default',
            'font'   => $body['font'] ?? ($profile['font'] ?? ''),
            'seo_title'       => $profile['seo_title'],
            'seo_description' => $body['seo_description'] ?? $profile['seo_description'],
            'og_image'        => $profile['og_image'],
            'noindex'         => $profile['noindex'],
            'custom_css'      => $canCss ? ($body['custom_css'] ?? $profile['custom_css']) : $profile['custom_css'],
            'bg_type'         => $body['bg_type'] ?? $profile['bg_type'],
            'bg_value'        => $body['bg_value'] ?? $profile['bg_value'],
            'translations'    => $body['translations'] ?? ($profile['translations'] ?? ''),
            'active' => isset($body['active']) ? $body['active'] : $profile['active'],
            ));

        // Upsert links so existing IDs and click history survive editor saves.
        $existingLinks = array();
        foreach($this->getLinks($id) as $row) $existingLinks[(int) $row['id']] = $row;
        $seenLinks = array();
        foreach(($body['links'] ?? array()) as $i => $l) {
            $linkId = (int) ($l['id'] ?? 0);
            if(empty($l['label']) && empty($l['url'])) {
                if($linkId && isset($existingLinks[$linkId])) $this->deleteLink($linkId, $id);
                continue;
            }
            $old = $linkId && isset($existingLinks[$linkId]) ? $existingLinks[$linkId] : array();
            $save = array(
                'profile_id' => $id,
                'label' => $l['label'] ?? '',
                'url'   => $l['url'] ?? '',
                'icon'  => $l['icon'] ?? '',
                'is_social' => !empty($l['is_social']) ? 1 : 0,
                'start_date' => $l['start_date'] ?? ($old['start_date'] ?? 0),
                'end_date' => $l['end_date'] ?? ($old['end_date'] ?? 0),
                'translations' => $l['translations'] ?? ($old['translations'] ?? array()),
                'sort'  => $i,
                'active' => isset($l['active']) ? $l['active'] : ($old['active'] ?? 1),
            );
            if($old) $save['id'] = $linkId;
            $savedId = $this->saveLink($save);
            $seenLinks[(int) $savedId] = true;
        }
        foreach(array_keys($existingLinks) as $linkId) {
            if(empty($seenLinks[$linkId])) $this->deleteLink($linkId, $id);
        }

        // Upsert portfolio blocks for the same reason.
        $existingBlocks = array();
        foreach($this->getBlocks($id) as $row) $existingBlocks[(int) $row['id']] = $row;
        $seenBlocks = array();
        foreach(($body['blocks'] ?? array()) as $i => $b) {
            $blockId = (int) ($b['id'] ?? 0);
            if(empty($b['type'])) {
                if($blockId && isset($existingBlocks[$blockId])) $this->deleteBlockForProfile($blockId, $id);
                continue;
            }
            $old = $blockId && isset($existingBlocks[$blockId]) ? $existingBlocks[$blockId] : array();
            $save = array(
                'profile_id' => $id,
                'type' => $b['type'],
                'title' => $b['title'] ?? '',
                'data' => $b['data'] ?? array(),
                'translations' => $b['translations'] ?? ($old['translations'] ?? array()),
                'sort' => $i,
                'active' => isset($b['active']) ? $b['active'] : ($old['active'] ?? 1),
            );
            if($old) $save['id'] = $blockId;
            $savedId = $this->saveBlock($save);
            $seenBlocks[(int) $savedId] = true;
        }
        foreach(array_keys($existingBlocks) as $blockId) {
            if(empty($seenBlocks[$blockId])) $this->deleteBlockForProfile($blockId, $id);
        }

            $db->commit();
            $this->cleanupProfileUploads($id);
            return array('ok' => true, 'slug' => $slug);
        } catch(\Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            $this->wire('log')->save('lynx', 'Editor save failed: ' . $e->getMessage());
            return array('error' => 'Save failed');
        }
    }

    /** True if current user owns or may edit this profile. */
    protected function ownsProfile(array $profile) {
        $u = $this->wire('user');
        return $u->isSuperuser() || (int) $profile['user_id'] === (int) $u->id;
    }

    /** Handle AJAX image upload. Returns JSON. */
    protected function handleUpload() {
        header('Content-Type: application/json; charset=utf-8');
        if(!$this->editorUserOk()) { http_response_code(403); return json_encode(array('error' => 'Forbidden')); }
        if($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validCsrf()) { http_response_code(403); return json_encode(array('error' => 'Invalid request')); }
        if(empty($_FILES['file']['name'])) { http_response_code(400); return json_encode(array('error' => 'No file')); }
        $uploadError = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if($uploadError !== UPLOAD_ERR_OK) {
            $messages = array(
                UPLOAD_ERR_INI_SIZE => 'Image exceeds the server upload limit',
                UPLOAD_ERR_FORM_SIZE => 'Image exceeds the upload limit',
                UPLOAD_ERR_PARTIAL => 'Image upload was interrupted',
                UPLOAD_ERR_NO_FILE => 'No image was selected',
                UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is unavailable',
                UPLOAD_ERR_CANT_WRITE => 'Server could not save the image',
                UPLOAD_ERR_EXTENSION => 'Image upload was blocked by the server',
            );
            http_response_code(400);
            return json_encode(array('error' => $messages[$uploadError] ?? 'Image upload failed'));
        }
        if((int) ($_FILES['file']['size'] ?? 0) > 8 * 1024 * 1024) {
            http_response_code(413);
            return json_encode(array('error' => 'Image exceeds the 8 MB upload limit'));
        }
        $profileId = (int) $this->wire('input')->post('profile_id');
        $profile = $profileId ? $this->getProfile($profileId) : null;
        if(!$profile || !$this->ownsProfile($profile)) { http_response_code(403); return json_encode(array('error' => 'Forbidden')); }

        $dir = $this->avatarPath();
        if(!is_dir($dir)) wireMkdir($dir, true);
        if(count(glob($dir . $profileId . '-*') ?: array()) >= 100) {
            http_response_code(429);
            return json_encode(array('error' => 'Upload limit reached'));
        }
        $u = new WireUpload('file');
        $this->wire($u);
        $u->setMaxFiles(1);
        $u->setOverwrite(false);
        $u->setDestinationPath($dir);
        $u->setValidExtensions(array('jpg', 'jpeg', 'png', 'gif', 'webp'));
        $u->setMaxFileSize(8 * 1024 * 1024);
        $files = $u->execute();
        if(empty($files)) {
            $errors = $u->getErrors();
            if($errors) $this->wire('log')->save('lynx', 'Image upload failed: ' . implode('; ', $errors));
            http_response_code(400);
            return json_encode(array('error' => 'Image could not be uploaded. Use JPG, PNG, GIF or WebP up to 8 MB.'));
        }

        // prefix with a short hash to avoid collisions
        $orig = $dir . $files[0];
        if(@getimagesize($orig) === false) {
            @unlink($orig);
            http_response_code(400);
            return json_encode(array('error' => 'Invalid image'));
        }
        $name = $profileId . '-' . substr(hash('sha256', $files[0] . microtime(true)), 0, 12)
            . '-' . $this->wire('sanitizer')->filename($files[0]);
        @rename($orig, $dir . $name);
        return json_encode(array('ok' => true, 'url' => $this->avatarUrl() . $name));
    }

    /** Remove profile-scoped uploads that are no longer referenced after save. */
    protected function cleanupProfileUploads($profileId) {
        $profileId = (int) $profileId;
        $profile = $this->getProfile($profileId);
        if(!$profile) return;
        $used = array();
        $collect = function($value) use (&$collect, &$used) {
            if(is_array($value)) {
                foreach($value as $item) $collect($item);
                return;
            }
            if(!is_string($value)) return;
            if(strpos($value, $this->avatarUrl()) === 0) $used[basename($value)] = true;
        };
        $collect($profile);
        $collect($this->decodeTranslations($profile['translations'] ?? ''));
        foreach($this->getBlocks($profileId) as $block) {
            $collect($block['data']);
            $collect($this->decodeTranslations($block['translations'] ?? ''));
        }
        foreach(glob($this->avatarPath() . $profileId . '-*') ?: array() as $file) {
            if(empty($used[basename($file)]) && is_file($file)) @unlink($file);
        }
    }
}
