# Lynx — Technical Documentation

This reference covers Lynx **1.0.0** (ProcessWire module version **100**).

Lynx is an open-source link-in-bio engine for ProcessWire. This document covers
the data model, public API, routing, multilingual output, theming, the front-end editor, and the
companion Lynx Manager module. For a marketing-level overview see `README.md`;
for version history see `CHANGELOG.md`.

The two modules:

- **Lynx** — the data and rendering engine (custom tables, routing, REST API,
  server- and client-side rendering, multilingual public output, and the
  front-end editor).
- **Lynx Manager** — the admin Process module: dashboard, global defaults,
  theme gallery, per-profile click stats, and JSON export/import. Lynx itself
  has no admin page; all management happens here or in the front-end editor.
  Its UI follows the ProcessWire AdminThemeUikit "Konkat" design system
  ([pw-design-system](https://github.com/mxmsmnv/pw-design-system)): native
  UIkit markup, a `uk-tab` section nav, an inner `pw-wrap` workspace, module
  heads, action groups, stat values, table panels, empty states, and `--pw-*`
  theme tokens, with the module-workspace bridge scoped under
  `.pw-module-workspace`.

### Code layout

`Lynx.module.php` is a thin shell: module info, configuration, lifecycle and
the routing hooks. The implementation is composed from focused traits under
`src/` (all part of the `Lynx` class):

| File | Responsibility |
|---|---|
| `src/LynxSchema.php` | install / uninstall / upgrade |
| `src/LynxData.php` | thin data-layer composition facade |
| `src/Lynx*Data.php` | profile, link, media, block, sanitization, analytics and cache persistence concerns |
| `src/LynxContent.php` | themes, fonts, social presets, block types (memoized) |
| `src/LynxTranslations.php` | language + translation helpers |
| `src/LynxRouting.php` | public route dispatch, view counting, page cache |
| `src/LynxApi.php` | REST API, CSRF, JSON helpers, public projection |
| `src/LynxRender.php` | `render()`, blocks, full page, dynamic CSS, Alpine variant |
| `src/LynxEditor.php` | front-end editor composition facade |
| `src/LynxEditorActions.php` / `src/LynxEditorView.php` | editor routing, persistence, uploads and presentation |
| `src/LynxImportExport.php` | JSON export/import |
| `src/LynxDemoProfiles.php` | built-in multilingual demo catalogue |

`LynxManager.module.php` is the thin Process module composition shell. Its
admin workspaces are grouped by responsibility in `src/LynxManagerUi.php`,
`src/LynxManagerDashboard.php`, `src/LynxManagerStats.php`,
`src/LynxManagerTransfer.php`, `src/LynxManagerThemes.php`, and
`src/LynxManagerSettings.php`.

Front-end assets are static files under `assets/`: `public.css` (base public
styles), `editor.css` + `editor.js` (the front-end editor), and
`admin-links.js` (the admin link-row reorder/preset helper). Standalone public
profiles inline the small `public.css` payload to remove a render-blocking
request; embeds continue loading the reusable static stylesheet.

Theme blueprints live under `blueprints/themes/`. Each theme is one JSON file.
`blueprints/theme-utilities.json` contains the Tailwind-like utility whitelist
used by theme JSON files.

ProcessWire language packs live under `languages/`. They use the same CSV import
shape as the Cookie module: English source string, translated string,
description, source file, and hash.

---

## 1. Data model

All data lives in four custom tables. No public profile pages are created in
the tree. Link/block/click relations use cascading foreign keys on new installs;
upgrades add them when legacy data contains no orphan rows.

### lynx_profiles
One row per bio page.

| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| user_id | INT | owner; used for per-user scoping |
| slug | VARCHAR(128) UNIQUE | public URL segment |
| lang | VARCHAR(16) | base language code |
| title | VARCHAR(255) | display name |
| bio | TEXT | short description |
| avatar | VARCHAR(255) | URL (may point at an uploaded file) |
| theme | VARCHAR(64) | theme id (see Theming) |
| font | VARCHAR(64) | font id from the curated open-source font list |
| accent | VARCHAR(16) | hex color |
| seo_title | VARCHAR(255) | falls back to title |
| seo_description | VARCHAR(512) | meta + OG description |
| og_image | VARCHAR(255) | falls back to avatar |
| noindex | TINYINT | adds robots noindex |
| custom_css | TEXT | sanitized; gated by permission |
| bg_type | VARCHAR(16) | '', color, gradient, image |
| bg_value | VARCHAR(512) | matches bg_type |
| active | TINYINT | inactive profiles 404 publicly |
| views | INT | incremented on each public render |
| translations | MEDIUMTEXT | JSON keyed by language code |
| created / modified | INT | unix timestamps |

### lynx_links
One row per link. Ordered by `sort`.

| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| profile_id | INT | |
| label | VARCHAR(255) | |
| url | VARCHAR(1024) | |
| icon | VARCHAR(64) | Font Awesome 4.7 name |
| is_social | TINYINT | flagged as a social link |
| start_date / end_date | INT | scheduling window (0 = unbounded) |
| sort | INT | display order |
| active | TINYINT | |
| clicks | INT | running total |
| translations | MEDIUMTEXT | JSON keyed by language code |

### lynx_clicks
One row per tracked click. Purged on a retention schedule.

| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| link_id | INT | |
| ts | INT | unix timestamp |
| ref | VARCHAR(255) | HTTP referer (truncated) |

### lynx_blocks
One row per portfolio block. `data` is a JSON blob whose shape depends on `type`.

| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| profile_id | INT | |
| type | VARCHAR(32) | gallery, project, video, quote |
| title | VARCHAR(255) | optional section heading |
| translations | MEDIUMTEXT | JSON keyed by language code |
| data | MEDIUMTEXT | JSON, see below |
| sort | INT | display order |
| active | TINYINT | |

Block translations may include partial nested `data` values. At render time
translated `data` is merged into the base block data, so a translated caption or
quote does not need to repeat image URLs, embed URLs, links, or other structural
fields. Nested translated values are sanitized by the same type-aware URL and
text policy as base block data, then revalidated at the public output boundary.

#### Block `data` shapes

```jsonc
// gallery
{ "images": [ { "src": "...", "caption": "...", "link": "..." } ], "columns": 3 }

// project
{ "items": [ { "heading": "...", "body": "...", "image": "...",
               "link": "...", "linkLabel": "View" } ] }

// video
{ "videos": [ { "url": "https://youtu.be/...", "embed": "https://www.youtube.com/embed/...",
                "caption": "..." } ] }

// quote
{ "quotes": [ { "text": "...", "author": "...", "role": "..." } ] }
```

`embed` for videos is derived on save by `videoEmbedUrl()` (YouTube and Vimeo
are recognized); unrecognized URLs are dropped.

---

## 2. Routing

All public routing is handled by a `ProcessPageView::pageNotFound` hook, so the
module needs no template pages. The root segment (default `l`) is configurable.

| URL | Method | Purpose |
|---|---|---|
| `/{root}/{slug}` | GET | Rendered public profile page |
| `/{root}/{lang}/{slug}` | GET | Rendered public profile page localized to a supported language |
| `/{root}/go/{linkId}` | GET | Click-tracking redirect to the link target |
| `/{root}/api/profiles` | GET | JSON list of active profiles |
| `/{root}/api/profiles/{slug}` | GET | One profile with links + blocks |
| `/{root}/api/profiles/{lang}/{slug}` | GET | One localized profile with links + blocks |
| `/{root}/api/reorder` | POST | Reorder links (admin + CSRF) |
| `/{root}/edit` | GET | Front-end editor dashboard (login required) |
| `/{root}/edit/{slug}` | GET | Front-end visual editor (login + ownership) |
| `/{root}/edit/{slug}` | POST | Save editor changes (JSON body + CSRF) |
| `/{root}/upload` | POST | AJAX image upload (login + CSRF) |

`{slug}` is sanitized as a page name. `{lang}` must be listed in
`supportedLanguages`; unsupported language segments are treated as normal slugs.
Inactive profiles are treated as not found on the public route. The segments
`api`, `go`, `edit` and `upload` are reserved sub-routes, so profiles cannot use
them as slugs (`reservedSlugs()` enforces this on save). Route shapes are
strict: unexpected trailing segments are returned as not found.

The public route increments the profile's view counter once per request.
Requests carrying `?lynxpreview=1` (used by the editor's live preview) render
normally but do not count as views.

Profiles can enable several translation languages in the front-end editor. Each
enabled language has its own profile, link, and block fields; missing values
fall back to the base language. Profiles with more than one language in
`availableLanguages()` render a language switcher at the bottom of the
standalone page. The base language links to `/{root}/{slug}` and translations
link to `/{root}/{lang}/{slug}`. The same URLs are emitted as `rel="alternate"`
/ `hreflang` metadata in the document head. Profiles with only one available
language render no switcher.

---

## 3. REST API

`GET /{root}/api/profiles` returns active public projections with
runtime/identity fields omitted. Add `?lang=ru` to localize profile fields where translations exist.
`GET /{root}/api/profiles/{slug}` returns a single active profile with embedded
`links` (active + in-schedule) and `blocks` (active). Use either
`?lang=ru` or `/{root}/api/profiles/ru/{slug}` for localized single-profile
responses. Responses are JSON with `JSON_UNESCAPED_UNICODE`.

`POST /{root}/api/reorder` expects `{ "ids": [3,1,2] }`, requires the
`lynx-admin` permission and a valid CSRF token (header `X-XSRF-Token` or a
`csrf` body field). It is intended for headless tooling; the editors save order
on submit and do not need it.

---

## 4. Rendering

Three rendering paths share the same `.lynx-*` markup and CSS:

- **`render($slugOrProfile, $options)`** — returns the inner profile HTML
  (avatar, title, bio, links, portfolio blocks). For embedding in your own
  templates. Click tracking is applied automatically when enabled.
- **`renderPage($profile)`** (internal) — wraps `render()` in a full HTML
  document with SEO/OG/Twitter meta, the theme, background override, and custom
  CSS. Used by the public route.
- **`renderAlpine($slug, $options)`** — a self-contained Alpine.js widget that
  fetches the profile from the REST API and renders client-side. It includes
  the Lynx public stylesheet, Font Awesome 4.7, and Alpine.js by default. Pass
  `['noCdn' => true]` if Alpine is already on the page, `['noCss' => true]` if
  your template already loads `assets/public.css`, or
  `['noFontAwesome' => true]` if icons are already available.

Portfolio blocks are rendered by `renderBlock()` into semantic markup
(`<section class="lynx-block lynx-block-{type}">`). Galleries use CSS grid,
videos use a responsive 16:9 iframe wrapper.

### Single-profile homepage

For a one-profile website, render a Lynx profile directly from
`site/templates/home.php`:

```php
<?php namespace ProcessWire;

$lynx = $modules->get('Lynx');
echo $lynx->render('max');
```

Replace `max` with the profile slug. The normal public root can stay enabled
for `/l/{slug}` routes, or be treated as an editor/admin preview path while the
homepage is the canonical public page.

To embed Lynx inside a larger ProcessWire template instead of making the whole
homepage the profile, use the Alpine widget:

```php
echo $lynx->renderAlpine('max');
```

---

## 5. Theming & appearance

Each profile selects a theme from `getThemes()`. Built-ins are loaded from
`blueprints/themes/*.json` files with `"kind": "builtIn"` and are currently
`default`, `dark`, `glass`, `mono`, `pill`. Lynx Manager can append
site-specific themes from its `customThemes` JSON setting.

Files with `"kind": "customExample"` are examples and are not automatically
enabled. Copy one from `blueprints/themes/` into the Lynx Manager `Custom themes
JSON` setting, rename the id if needed, and save the module settings.

```json
{
  "studio": {
    "label": "Studio",
    "styles": {
      "tailwind": {
        "body": "bg-neutral-50 text-neutral-900",
        ".lynx-link": "bg-white text-neutral-900 border border-neutral-200",
        ".lynx-project,.lynx-quote": "bg-white text-neutral-900 border border-neutral-200"
      }
    }
  }
}
```

Custom theme IDs are sanitized as field names, built-in IDs cannot be
overridden, and `styles.tailwind` utilities are compiled from
`blueprints/theme-utilities.json`. Raw `styles.css` is still supported and is
passed through the same sanitizer
used for per-profile custom CSS.

When creating a theme:

- Use `body` for page-level background and text color.
- Use `.lynx-link` for public link buttons.
- Use `.lynx-bio` for profile subtitle/bio color.
- Use `.lynx-project`, `.lynx-quote`, `.lynx-gallery`, `.lynx-video` for content
  blocks.
- Use CSS variables such as `var(--lynx-accent)` and `var(--lynx-radius-md)` so
  profile accent and radius settings still work.
- Keep CSS flat and static. Lynx strips interactive visual effects, imports,
  script-like values and unsafe declarations.

On top of the selected theme:

- **Font** (`font`): a curated open-source font id. Public pages load the
  selected family from Bunny Fonts and fall back to system UI when empty.
- **Background override** (`bg_type` + `bg_value`): solid color, CSS gradient,
  or image URL. Values are filtered before being injected into CSS.
- **Custom CSS** (`custom_css`): appended after the theme. Sanitized on save —
  `<style>` tags, `@import`, `expression()` and `javascript:` are removed — but
  it still executes on the public page, so editing requires the
  `lynx-customcss` permission.

`pageCss($profile)` assembles base styles + theme + background + custom CSS.

---

## 6. Front-end editor

A no-admin-UI editing experience at `/{root}/edit/`:

- **Auth.** `editorUserOk()` requires a logged-in user with `lynx-admin`.
  Guests are redirected to the admin login. (After logging in, the user
  re-opens the editor URL; PW does not currently bounce back automatically.)
- **Ownership.** `ownsProfile()` allows superusers and the profile's `user_id`
  owner. Other admins get a Forbidden response.
- **Dashboard** (`/edit`) lists the user's profiles and creates new ones (a
  blank profile via the `create` save action with a unique slug).
- **Editor** (`/edit/{slug}`) is a split view: a vanilla-JS form on the left and
  a live `<iframe>` preview of the public page on the right. Edits mark the
  state dirty and autosave after ~1.2s; the preview reloads on save. The profile
  slug can be changed after creation. Empty, reserved, and duplicate values are
  rejected; a successful rename updates the editor and public URLs immediately.
- **Translations** are edited in the same split view. The editor exposes one
  panel per supported non-base language for profile title/bio/SEO text, link
  labels, portfolio block titles, and common nested block content such as
  gallery/video captions, project copy/buttons, and quote text/attribution.
  Saving prunes empty translation values.
- **Saving** posts the full state as JSON to the same URL. The server replaces
  the profile's links, blocks, and translations wholesale from the payload.
  Custom CSS is only written if the user has `lynx-customcss`.
- **Uploads** post to `/{root}/upload` with `profile_id` (multipart, CSRF
  header). The server verifies ownership and decoded image content. Files land
  in `/site/assets/files/lynx/` with a profile-scoped hashed filename; unused
  scoped files are removed after a successful save.

The editor JS is intentionally framework-free (no Alpine/React) to keep the
editing surface dependency-light. Sortable.js is used for drag reordering of
links and blocks when the CDN script is available; if it fails to load, the
editor still works without drag-and-drop. The editor logic and chrome styles
are served as static files (`assets/editor.js`, `assets/editor.css`); the page
injects state, the theme/font/preset catalogues and a translated label map into
`window.LYNX`.

---

## 7. Permissions

| Permission | Grants |
|---|---|
| `lynx-admin` | Manage profiles/links/blocks; use both modules and the front-end editor |
| `lynx-customcss` | View/edit raw per-profile custom CSS |

Without `lynx-customcss`, existing custom CSS is preserved but hidden and locked
in both the admin and front-end editors.

---

## 8. Configuration (Lynx module)

| Setting | Default | Purpose |
|---|---|---|
| rootUrl | `l` | public URL segment; leave blank to serve profiles from the site root |
| enablePublic | on | toggle public pages |
| enableApi | on | toggle REST API |
| trackClicks | on | route links through `/go/` and record clicks |
| clickRetention | 90 | days to keep detailed click rows (0 = forever) |
| cacheTtl | 0 | seconds to cache rendered public pages for anonymous visitors (0 = off) |
| defaultLanguage | `en` | base language for new profiles |
| supportedLanguages | `en,de,fr,nl,it,es,pt,ru,pl,cs,fi,bg,zh,ka,ja` | comma-separated public language codes |

Old click rows are purged daily via a `LazyCron::everyDay` hook
(`purgeOldClicks()`); per-link totals are never lost.

When `cacheTtl > 0`, rendered public pages are stored in WireCache for
anonymous, non-preview requests and invalidated automatically whenever a
profile, its links or blocks are saved or deleted. View counting and the
front-end editor's live preview (`?lynxpreview=1`, which never increments
views) bypass the cache. Saving global Lynx/Lynx Manager settings clears all
rendered profile entries. Canonical Open Graph URLs are derived from the
normalized profile route rather than whichever URL first warmed the cache.

### Lynx Manager configuration

The preferred editing surface is `Setup > Lynx Manager > Settings`, which
combines Lynx public/runtime settings and Lynx Manager appearance defaults in one
screen.

| Setting | Default | Purpose |
|---|---|---|
| defaultTheme | `default` | theme used for new profiles |
| defaultFont | empty | open-source font used for new profiles |
| defaultAccent | `#1e87f0` | accent color used for new profiles |
| brandName | empty | optional footer credit on public pages |
| customThemes | empty | JSON map of custom theme IDs to `{ "label", "styles": { "tailwind" } }` or `{ "label", "styles": { "css" } }`; examples live in `blueprints/themes/` |

The Theme gallery renders each built-in and custom theme as an isolated
`iframe srcdoc` profile using the real Lynx public CSS and theme CSS. Isolation
keeps theme `body` rules from leaking into ProcessWire Admin while still showing
the avatar, profile copy, links and content-block styling together.

---

## 9. Export / import

- `exportProfiles($id = null)` returns a portable array (`lynx_export`,
  `version`, `profiles[]`) with each profile's links and blocks, stripped of
  runtime/identity fields.
- `importProfiles($data, $overwrite = false)` recreates profiles. On slug
  collision it either suffixes `-2`, `-3`, … (default) or replaces the existing
  profile and its links/blocks (`$overwrite = true`). Returns
  `[imported, skipped, errors]`. Each profile is imported in its own transaction;
  overwrite preserves the existing owner and rolls back fully on failure.
- `demoProfilesExport()` returns the built-in unofficial ProcessWire community
  showcase used by Lynx Manager's **Install demo** action. It contains ten
  profiles based on public information about notable long-time contributors:
  Ryan Cramer, Maxim Semenov, Robin Sallis, Bernhard Baumrock, Teppo Koivula,
  Adrian Jones, Peter Knight, Alexander Abelt (wbmnfktr), Jonathan Lahijani,
  and Jan Ploch (jploch).
- Public author pages, GitHub profiles and repositories are used as source
  links. GitHub avatars are loaded remotely; contributor images are not bundled
  or redistributed. Every profile carries an explicit unofficial-showcase
  notice and avoids invented personal quotations. Demo profiles are crawlable
  by default so a temporary showcase can be audited as a complete public page;
  enable `noindex` in the editor when the installation should stay private.
- Base and editorial demo translations cover English, German, Finnish,
  Russian, French, Spanish and Chinese. These translation choices demonstrate
  Lynx multilingual rendering and do not claim that contributors personally use
  every language shown on their profile.
- Every demo profile includes all four content-block types: gallery, project,
  video and quote. The shared video is the original 2010 open-source release
  video linked from ProcessWire's official history page.

Community showcase source index:

| Contributor | ProcessWire source | Public code/profile source | Featured project |
|---|---|---|---|
| Ryan Cramer | https://processwire.com/about/team/ryan/ | https://github.com/ryancramerdesign | https://github.com/processwire/processwire |
| Maxim Semenov | https://processwire.com/modules/author/maxim-semenov/ | https://github.com/mxmsmnv | https://github.com/mxmsmnv/Ichiban |
| Robin Sallis | https://processwire.com/modules/author/robin-s/ | https://github.com/Toutouwai | https://github.com/Toutouwai/HannaCodeDialog |
| Bernhard Baumrock | https://processwire.com/modules/author/bernhardbaumrock/ | https://github.com/BernhardBaumrock | https://github.com/BernhardBaumrock/RockMigrations |
| Teppo Koivula | https://processwire.com/modules/author/teppo/ | https://github.com/teppokoivula | https://github.com/teppokoivula/SearchEngine |
| Adrian Jones | https://processwire.com/modules/author/adrian-jones/ | https://github.com/adrianbj | https://github.com/adrianbj/TracyDebugger |
| Peter Knight | https://processwire.com/modules/author/peter-knight/ | https://github.com/PeterKnightDigital | https://github.com/PeterKnightDigital/SeoNeo |
| Alexander Abelt (wbmnfktr) | https://processwire.com/talk/profile/3893-wbmnfktr/ | https://alexanderabelt.com/ · https://about.me/acabelt · https://www.alexander-abelt.de/ · https://github.com/acabelt | https://processwire.recipes/ |
| Jonathan Lahijani | https://processwire.com/modules/author/jonathan-lahijani/ | https://github.com/jlahijani | https://processwire.com/modules/inputfield-page-modal-select/ |
| Jan Ploch (jploch) | https://processwire.com/modules/author/jploch/ · https://processwire.com/blog/posts/pw-3.0.255/ | https://www.janploch.de/ · https://page-grid.com/legal-notice/ · https://konkat.studio/ · https://github.com/jploch | https://github.com/jploch/FieldtypePageGrid |

Locations are taken from public ProcessWire, GitHub or personal profiles.
Ryan's Atlanta location and portrait come from his official ProcessWire team
page. Alexander Abelt's personal website, about.me profile and GitHub profile
connect his public identity with the acabelt account; the ProcessWire handle is
retained in the showcase title and stable public slug.

Lynx Manager exposes export/import as a download endpoint and an upload/paste
form, plus a one-click demo installer for first-run onboarding.

---

## 10. Programmatic use

```php
$lynx = $modules->get('Lynx');

// create a profile
$id = $lynx->saveProfile([
    'slug' => 'jane', 'title' => 'Jane Doe', 'theme' => 'glass', 'active' => 1,
]);

// add a link
$lynx->saveLink(['profile_id' => $id, 'label' => 'Shop', 'url' => 'https://…']);

// add a gallery block
$lynx->saveBlock([
    'profile_id' => $id, 'type' => 'gallery', 'title' => 'Work',
    'data' => ['columns' => 3, 'images' => [
        ['src' => 'https://…/a.jpg', 'caption' => 'A'],
    ]],
]);

// render anywhere
echo $lynx->render('jane');
```

---

## 11. Security notes

- All SQL uses prepared statements; all output is entity-encoded via the
  sanitizer.
- Public media fields (avatar, OG image, background image and block media URLs)
  are sanitized as URLs before rendering.
- Mutating endpoints and manager actions (`reorder`, editor save/upload,
  import, demo and settings) require auth + a valid ProcessWire CSRF token.
- Custom CSS is filtered but executes on the public page; it is gated behind a
  dedicated permission. Treat `lynx-customcss` as a trusted capability.
- Video embeds are restricted to YouTube and Vimeo by `videoEmbedUrl()`;
  arbitrary iframe sources are not accepted.
- Uploaded files are ownership-scoped, extension-, size- and decoded-image
  validated and stored under `/site/assets/files/lynx/` with a per-profile cap.
- Standalone public pages send a restrictive CSP, `nosniff`, no-referrer and
  permissions-policy headers. CDN assets are version-pinned and use SRI.
