<?php namespace ProcessWire;

/** Profile export, import, and demo installation workspaces. */
trait LynxManagerTransfer {

    /* ---------------------------------------------------------------------
     * Export
     * ------------------------------------------------------------------- */

    public function ___executeExport() {
        $this->setManagerChrome('Export profiles', 'Export', 'export/');
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $lynx = $this->lynx();

        // Direct download
        if($input->get('download')) {
            $pid = (int) $input->get('id') ?: null;
            $data = $lynx->exportProfiles($pid);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $name = 'lynx-export-' . date('Ymd-His') . '.json';
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$name\"");
            header('Content-Length: ' . strlen($json));
            echo $json;
            exit;
        }

        $profiles = $lynx->getProfiles();
        $base = $this->managerBaseUrl();
        $san = $this->wire('sanitizer');

        $out = $this->header('export/');
        $out .= $this->workspaceHead(
            $this->_('Export profiles'),
            $this->_('Download all profiles as a portable JSON file, or export a single profile.'),
            "<a class='uk-button uk-button-primary' href='{$base}export/?download=1'><i class='fa fa-download'></i> "
                . sprintf($this->_('Export all (%d)'), count($profiles)) . "</a>"
        );

        if($profiles) {
            $table = $modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->setSortable(false);
            $table->headerRow(array($this->_('Profile'), $this->_('Slug'), ''));
            foreach($profiles as $p) {
                $dl = "<a href='{$base}export/?download=1&id={$p['id']}'>" . $this->_('download') . "</a>";
                $table->row(array($san->entities($p['title'] ?: '(untitled)'), $san->entities($p['slug']), $dl));
            }
            $out .= "<div class='pw-table-panel'>" . $table->render() . "</div>";
        } else {
            $out .= "<div class='pw-empty-state uk-margin-top'><h4 class='uk-h4'>" . $this->_('No profiles to export') . "</h4>"
                . "<p class='uk-text-muted'>" . $this->_('Create or import profiles before exporting.') . "</p></div>";
        }
        return $out . $this->footer();
    }

    /* ---------------------------------------------------------------------
     * Import
     * ------------------------------------------------------------------- */

    public function ___executeImport() {
        $this->setManagerChrome('Import profiles', 'Import', 'import/');
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $lynx = $this->lynx();

        if($input->post('submit_import')) {
            if(!$this->validManagerCsrf()) return '';
            $json = '';
            if(!empty($_FILES['import_file']['tmp_name']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
                if((int) ($_FILES['import_file']['size'] ?? 0) > 5 * 1024 * 1024) {
                    $this->error($this->_('Import file exceeds the 5 MB limit.'));
                } else {
                    $json = file_get_contents($_FILES['import_file']['tmp_name']);
                }
            } elseif(trim($input->post('import_json'))) {
                $json = $input->post('import_json');
            }
            if(strlen((string) $json) > 5 * 1024 * 1024) {
                $this->error($this->_('Import data exceeds the 5 MB limit.'));
                $json = '';
            }
            $data = json_decode($json, true);
            if(!is_array($data)) {
                $this->error('Could not parse JSON.');
            } else {
                $overwrite = (bool) $input->post('overwrite');
                $res = $lynx->importProfiles($data, $overwrite);
                if($res['imported']) $this->message("Imported {$res['imported']} profile(s).");
                if($res['skipped'])  $this->warning("Skipped {$res['skipped']} entr(ies).");
                foreach($res['errors'] as $e) $this->error($e);
                $this->wire('session')->redirect('../');
            }
        }

        $form = $modules->get('InputfieldForm');
        $form->method = 'post';
        $form->action = './';
        $form->attr('enctype', 'multipart/form-data');

        $f = $modules->get('InputfieldMarkup');
        $f->name = 'file_wrap';
        $f->label = 'Upload JSON file';
        $f->description = 'Use a JSON export created by Lynx Manager.';
        $f->value = "<input type='file' name='import_file' accept='.json,application/json' class='uk-input'>";
        $form->add($f);

        $f = $modules->get('InputfieldTextarea');
        $f->name = 'import_json';
        $f->label = '…or paste JSON';
        $f->description = 'Paste the full JSON export when uploading a file is not convenient.';
        $f->notes = 'If both file and pasted JSON are provided, the uploaded file is used.';
        $f->rows = 8;
        $form->add($f);

        $f = $modules->get('InputfieldToggle');
        $f->name = 'overwrite';
        $f->label = 'Overwrite profiles with matching slugs';
        $f->description = 'Off: matching slugs are imported under a new -2/-3 slug. On: existing profile and its links are replaced.';
        $f->notes = 'Only enable this when you intentionally want the import to replace current profile content.';
        $f->value = 0;
        $form->add($f);

        $f = $modules->get('InputfieldHidden');
        $f->name = 'submit_import'; $f->value = 1;
        $form->add($f);

        $b = $modules->get('InputfieldSubmit');
        $b->name = 'submit_import'; $b->value = 'Import'; $b->icon = 'upload';
        $form->add($b);

        return $this->header('import/')
            . $this->workspaceHead(
                $this->_('Import profiles'),
                $this->_('Upload or paste a Lynx JSON export. Use overwrite only when replacing known profiles.')
            )
            . $form->render() . $this->footer();
    }

    /* ---------------------------------------------------------------------
     * Demo data
     * ------------------------------------------------------------------- */

    public function ___executeDemo() {
        $this->setManagerChrome('Install demo profiles', 'Install demo', 'demo/');
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $lynx = $this->lynx();
        $san = $this->wire('sanitizer');
        $demo = $lynx->demoProfilesExport();
        $profiles = $demo['profiles'] ?? array();

        if($input->post('submit_demo')) {
            if(!$this->validManagerCsrf()) return '';
            $overwrite = (bool) $input->post('overwrite');
            $res = $lynx->importProfiles($demo, $overwrite);
            if($res['imported']) $this->message("Imported {$res['imported']} demo profile(s).");
            if($res['skipped']) $this->warning("Skipped {$res['skipped']} entr(ies).");
            foreach($res['errors'] as $e) $this->error($e);
            $this->wire('session')->redirect('../');
        }

        $languages = array();
        $themes = array();
        $links = 0;
        $blocks = 0;
        foreach($profiles as $profile) {
            $languages[] = strtoupper($profile['lang'] ?? '');
            foreach(array_keys($profile['translations'] ?? array()) as $translatedLang) {
                $languages[] = strtoupper($translatedLang);
            }
            $themes[] = $profile['theme'] ?? '';
            $links += count($profile['links'] ?? array());
            $blocks += count($profile['blocks'] ?? array());
        }
        $languages = array_values(array_unique(array_filter($languages)));
        $themes = array_values(array_unique(array_filter($themes)));

        $out = $this->header('demo/')
            . $this->workspaceHead(
                $this->_('Install demo profiles'),
                $this->_('Create a ready-to-edit set of multilingual public profiles with links, blocks, scheduling, theme settings and open-source fonts.')
            );

        $out .= "<div class='uk-grid-small uk-child-width-1-2@s uk-child-width-1-4@m pw-stat-grid uk-margin-medium-bottom' uk-grid uk-height-match='target: > div > .uk-card'>";
        $out .= "<div><div class='uk-card uk-card-default uk-card-body'><span class='uk-text-meta'>" . $this->_('Profiles') . "</span><span class='pw-stat-value'>" . count($profiles) . "</span></div></div>";
        $out .= "<div><div class='uk-card uk-card-default uk-card-body'><span class='uk-text-meta'>" . $this->_('Languages') . "</span><span class='pw-stat-value'>" . count($languages) . "</span></div></div>";
        $out .= "<div><div class='uk-card uk-card-default uk-card-body'><span class='uk-text-meta'>" . $this->_('Links') . "</span><span class='pw-stat-value'>" . $links . "</span></div></div>";
        $out .= "<div><div class='uk-card uk-card-default uk-card-body'><span class='uk-text-meta'>" . $this->_('Blocks') . "</span><span class='pw-stat-value'>" . $blocks . "</span></div></div>";
        $out .= "</div>";

        $out .= "<div class='uk-grid-medium uk-margin-medium-bottom' uk-grid uk-height-match='target: > div > .uk-card'>";
        $out .= "<div class='uk-width-2-3@m'><div class='uk-card uk-card-default uk-card-body'>";
        $out .= "<h4 class='uk-margin-small-bottom'>" . $this->_('What gets installed') . "</h4>";
        $out .= "<p class='uk-text-muted uk-margin-small-top'>" . $this->_('The demo package is designed for testing a production-shaped setup: different regions, base languages, themes, fonts, translated labels and mixed content blocks.') . "</p>";
        $out .= "<div class='uk-flex uk-flex-wrap uk-margin-small-top'>";
        foreach($languages as $lang) {
            $out .= "<span class='uk-label uk-margin-small-right uk-margin-small-bottom'>" . $san->entities($lang) . "</span>";
        }
        foreach($themes as $theme) {
            $out .= "<span class='uk-label uk-label-success uk-margin-small-right uk-margin-small-bottom'>" . $san->entities($theme) . "</span>";
        }
        $out .= "</div></div></div>";
        $out .= "<div class='uk-width-1-3@m'><div class='uk-card uk-card-default uk-card-body'>";
        $out .= "<h4 class='uk-margin-small-bottom'>" . $this->_('Import behavior') . "</h4>";
        $out .= "<p class='uk-text-muted uk-margin-small-top'>" . $this->_('With overwrite off, existing slugs are preserved and imported copies receive -2, -3 suffixes. Enable overwrite only when refreshing demo content on a development site.') . "</p>";
        $out .= "</div></div></div>";

        $out .= "<div class='pw-table-panel uk-margin-medium-bottom'><table class='uk-table uk-table-divider uk-table-small uk-table-middle'>";
        $out .= "<thead><tr>"
            . "<th>" . $this->_('Profile') . "</th>"
            . "<th>" . $this->_('Region') . "</th>"
            . "<th>" . $this->_('Lang') . "</th>"
            . "<th>" . $this->_('Theme') . "</th>"
            . "<th>" . $this->_('Font') . "</th>"
            . "<th>" . $this->_('Background') . "</th>"
            . "<th class='uk-text-right'>" . $this->_('Links') . "</th>"
            . "<th class='uk-text-right'>" . $this->_('Blocks') . "</th>"
            . "</tr></thead><tbody>";
        foreach($profiles as $profile) {
            $background = $profile['bg_type'] ?? '';
            if($background === '') $background = $this->_('Theme default');
            $profileLangs = array(strtoupper($profile['lang'] ?? ''));
            foreach(array_keys($profile['translations'] ?? array()) as $translatedLang) {
                $profileLangs[] = strtoupper($translatedLang);
            }
            $out .= "<tr>"
                . "<td><strong>" . $san->entities($profile['title'] ?? '') . "</strong><br><span class='uk-text-meta'>/" . $san->entities($profile['slug'] ?? '') . "</span></td>"
                . "<td>" . $san->entities($profile['demo_region'] ?? '') . "</td>"
                . "<td><span class='uk-label'>" . $san->entities(implode(' → ', array_filter($profileLangs))) . "</span></td>"
                . "<td>" . $san->entities($profile['theme'] ?? '') . "</td>"
                . "<td>" . $san->entities($profile['font'] ?? '') . "</td>"
                . "<td>" . $san->entities($background) . "</td>"
                . "<td class='uk-text-right'>" . count($profile['links'] ?? array()) . "</td>"
                . "<td class='uk-text-right'>" . count($profile['blocks'] ?? array()) . "</td>"
                . "</tr>";
        }
        $out .= "</tbody></table></div>";

        $form = $modules->get('InputfieldForm');
        $form->method = 'post';
        $form->action = './';

        $f = $modules->get('InputfieldMarkup');
        $f->name = 'demo_intro';
        $f->skipLabel = true;
        $f->value = "<p>" . $this->_('Install these profiles into the current site. They remain editable in Lynx Manager and the front-end editor after import.') . "</p>";
        $form->add($f);

        $f = $modules->get('InputfieldToggle');
        $f->name = 'overwrite';
        $f->label = 'Overwrite matching demo slugs';
        $f->description = 'Off: matching slugs are imported under a new -2/-3 slug. On: existing demo profiles and their links/blocks are replaced.';
        $f->notes = 'Use overwrite only when refreshing demo content on a development site.';
        $f->value = 0;
        $form->add($f);

        $f = $modules->get('InputfieldHidden');
        $f->name = 'submit_demo'; $f->value = 1;
        $form->add($f);

        $b = $modules->get('InputfieldSubmit');
        $b->name = 'submit_demo'; $b->value = 'Install demo profiles'; $b->icon = 'magic';
        $form->add($b);

        return $out . $form->render() . $this->footer();
    }
}
