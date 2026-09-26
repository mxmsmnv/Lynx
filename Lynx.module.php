<?php namespace ProcessWire;

require_once __DIR__ . '/src/LynxSchema.php';
require_once __DIR__ . '/src/LynxData.php';
require_once __DIR__ . '/src/LynxContent.php';
require_once __DIR__ . '/src/LynxTranslations.php';
require_once __DIR__ . '/src/LynxRouting.php';
require_once __DIR__ . '/src/LynxApi.php';
require_once __DIR__ . '/src/LynxRender.php';
require_once __DIR__ . '/src/LynxEditor.php';
require_once __DIR__ . '/src/LynxImportExport.php';

/**
 * Lynx - Link-in-bio profiles for ProcessWire
 *
 * Multi-profile bio link pages backed by custom DB tables.
 * Provides an admin UI (Process), URL-routed public pages, a REST API,
 * and a render() method for use in your own templates.
 *
 * The implementation is composed from focused traits under src/ and the
 * front-end assets (editor JS/CSS, public CSS) live under assets/.
 *
 * @author Maxim Semenov (smnv.org)
 * @license MIT
 */
class Lynx extends Process implements Module, ConfigurableModule {

    use LynxSchema;
    use LynxData;
    use LynxContent;
    use LynxTranslations;
    use LynxRouting;
    use LynxApi;
    use LynxRender;
    use LynxEditor;
    use LynxImportExport;

    const TABLE_PROFILES = 'lynx_profiles';
    const TABLE_LINKS    = 'lynx_links';
    const TABLE_CLICKS   = 'lynx_clicks';
    const TABLE_BLOCKS   = 'lynx_blocks';

    protected $jsonBodyCache = null;
    protected $jsonBodyLoaded = false;

    /** Per-request memoization for static catalogues. */
    protected $themesCache = null;
    protected $blockTypesCache = null;
    protected $fontsCache = null;

    public static function getModuleInfo() {
        return array(
            'title'      => 'Lynx',
            'summary'    => 'Multi-profile link-in-bio pages with admin UI, REST API and render method.',
            'version'    => 101,
            'author'     => 'Maxim Semenov',
            'icon'       => 'link',
            'requires'   => array('ProcessWire>=3.0.244', 'PHP>=8.3'),
            // Keep the module loadable for guest users so public routing hooks work.
            // Admin execute methods enforce lynx-admin explicitly.
            'permission'        => 'page-view',
            'permissions'       => array(
                'lynx-admin'     => 'Manage Lynx profiles',
                'lynx-customcss' => 'Edit raw custom CSS on Lynx profiles',
            ),
            'useNavJSON'        => false,
            'singular'          => true,
            'autoload'          => true,
        );
    }

    /* ---------------------------------------------------------------------
     * Paths / asset URLs
     * ------------------------------------------------------------------- */

    /** Filesystem dir for uploaded avatars */
    protected function avatarPath() {
        return $this->wire('config')->paths->files . 'lynx/';
    }
    protected function avatarUrl() {
        return $this->wire('config')->urls->files . 'lynx/';
    }

    /**
     * Public web URL of this module's directory (with trailing slash).
     * Defined here (not in a trait) so __DIR__ resolves to the module root.
     */
    protected function moduleUrl() {
        $config = $this->wire('config');
        $dir = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
        $root = rtrim($config->paths->root, '/') . '/';
        if(strpos($dir, $root) === 0) {
            return $config->urls->root . substr($dir, strlen($root));
        }
        return $config->urls->siteModules . 'Lynx/';
    }

    /** Public URL of a file under assets/. */
    protected function assetUrl($file) {
        $path = __DIR__ . '/assets/' . $file;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';
        return $this->moduleUrl() . 'assets/' . $file . $version;
    }

    /** Public route path with rootUrl applied. Blank rootUrl serves paths from site root. */
    public function publicPath($path = '') {
        $root = trim((string) $this->rootUrl, '/');
        $path = trim((string) $path, '/');
        $siteBase = rtrim((string) $this->wire('config')->urls->root, '/');
        $route = $root === '' ? '' : '/' . $root;
        if($path === '') return ($siteBase . $route ?: '') . '/';
        return $siteBase . $route . '/' . $path;
    }

    /* ---------------------------------------------------------------------
     * Config
     * ------------------------------------------------------------------- */

    public function __construct() {
        $this->set('rootUrl', 'l');        // public root segment, e.g. /l/{slug}; blank = /{slug}
        $this->set('enablePublic', 1);
        $this->set('enableApi', 1);
        $this->set('trackClicks', 1);
        $this->set('clickRetention', 90);  // days to keep detailed click rows (0 = forever)
        $this->set('cacheTtl', 0);         // seconds to cache rendered public pages (0 = off)
        $this->set('defaultLanguage', 'en');
        $this->set('supportedLanguages', 'en,de,fr,nl,it,es,pt,ru,pl,cs,fi,bg,zh,ka,ja');
        parent::__construct();
    }

    public static function getModuleConfigInputfields(array $data) {
        $inputfields = new InputfieldWrapper();
        $modules = wire('modules');
        $san = wire('sanitizer');
        $page = wire('pages')->get("template=admin,name=lynx-manager");
        $settingsUrl = ($page && $page->id)
            ? rtrim($page->url, '/') . '/settings/'
            : wire('config')->urls->admin . 'setup/lynx-manager/settings/';

        $f = $modules->get('InputfieldMarkup');
        $f->name = 'lynxSettingsMoved';
        $f->label = 'Lynx settings';
        $f->value = "<p>All Lynx route, API, analytics, language, default profile and theme settings now live in the Lynx Manager workspace.</p>"
            . "<p><a class='uk-button uk-button-primary' href='" . $san->entities($settingsUrl) . "'>"
            . "<i class='fa fa-sliders'></i> Open Lynx settings</a></p>";
        $inputfields->add($f);

        return $inputfields;
    }

    /* ---------------------------------------------------------------------
     * Init / public routing
     * ------------------------------------------------------------------- */

    public function init() {
        parent::init();

        // Daily purge of old click rows (runs everywhere, incl. admin)
        if((int) $this->clickRetention > 0) {
            $this->wire('modules')->get('LazyCron');
            $this->addHook('LazyCron::everyDay', $this, 'purgeOldClicks');
        }

        // Public + API routing on the front-end is wired via a page-not-found
        // hook so the module works without creating any template pages.
        if($this->page && $this->page->template == 'admin') return;
        $root = trim($this->rootUrl, '/');
        if($root !== '') {
            $this->addHook("#^/?" . preg_quote($root, '#') . "(?:/.*)?/?$#", $this, 'hookPathRoute');
        } else {
            $this->addHook("#^/?(?:api|go|edit|upload)(?:/.*)?/?$#", $this, 'hookPathRoute');
            $this->addHook("#^/?(?:[a-z0-9_-]+/)?[a-z0-9_-]+/?$#i", $this, 'hookPathRoute');
        }
        $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'hookRoute');
    }
}
