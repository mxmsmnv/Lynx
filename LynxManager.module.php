<?php namespace ProcessWire;

require_once __DIR__ . '/src/LynxManagerUi.php';
require_once __DIR__ . '/src/LynxManagerDashboard.php';
require_once __DIR__ . '/src/LynxManagerStats.php';
require_once __DIR__ . '/src/LynxManagerTransfer.php';
require_once __DIR__ . '/src/LynxManagerThemes.php';
require_once __DIR__ . '/src/LynxManagerSettings.php';

/**
 * LynxManager - admin dashboard & tools for Lynx
 *
 * A thin Process module sitting on top of the Lynx engine. Provides an
 * overview dashboard, global defaults, theme preview, per-profile click stats,
 * and JSON export/import of profiles. All data access is delegated to Lynx.
 *
 * The admin UI follows the ProcessWire AdminThemeUikit "Konkat" design system
 * (github.com/mxmsmnv/pw-design-system): native UIkit markup, a uk-tab section
 * nav, a pw-wrap panel, and ProcessWire Inputfield forms.
 *
 * @author Maxim Semenov (smnv.org)
 * @license MIT
 */
class LynxManager extends Process implements Module, ConfigurableModule {

    use LynxManagerUi;
    use LynxManagerDashboard;
    use LynxManagerStats;
    use LynxManagerTransfer;
    use LynxManagerThemes;
    use LynxManagerSettings;

    public static function getModuleInfo() {
        return array(
            'title'    => 'Lynx Manager',
            'summary'  => 'Dashboard, global settings and export/import tools for the Lynx link-in-bio module.',
            'version'  => 100,
            'author'   => 'Maxim Semenov',
            'icon'     => 'sliders',
            'requires' => array('Lynx', 'ProcessWire>=3.0.244', 'PHP>=8.3'),
            'page'     => array(
                'name'   => 'lynx-manager',
                'title'  => 'Lynx Manager',
                'parent' => 'setup',
            ),
            'permission'  => 'lynx-admin',
            'singular'    => true,
            'autoload'    => false,
        );
    }

    public function __construct() {
        $this->set('defaultTheme', 'default');
        $this->set('defaultFont', '');
        $this->set('defaultAccent', '#1e87f0');
        $this->set('brandName', '');
        $this->set('customThemes', '');
        parent::__construct();
    }

    /** @return Lynx */
    protected function lynx() {
        return $this->wire('modules')->get('Lynx');
    }

    /* ---------------------------------------------------------------------
     * Module config (global defaults)
     * ------------------------------------------------------------------- */

    public static function getModuleConfigInputfields(array $data) {
        $inputfields = new InputfieldWrapper();
        $modules = wire('modules');
        $san = wire('sanitizer');
        $page = wire('pages')->get("template=admin,name=lynx-manager");
        $settingsUrl = ($page && $page->id)
            ? rtrim($page->url, '/') . '/settings/'
            : wire('config')->urls->admin . 'setup/lynx-manager/settings/';

        $f = $modules->get('InputfieldMarkup');
        $f->name = 'lynxManagerSettingsMoved';
        $f->label = 'Lynx Manager settings';
        $f->value = "<p>These settings now live in the Lynx Manager workspace so routes, API, defaults, languages and themes are managed in one place.</p>"
            . "<p><a class='uk-button uk-button-primary' href='" . $san->entities($settingsUrl) . "'>"
            . "<i class='fa fa-sliders'></i> Open Lynx settings</a></p>";
        $inputfields->add($f);

        return $inputfields;
    }

}
