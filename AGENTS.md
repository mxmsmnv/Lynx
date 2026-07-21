# Lynx Agent Guide

This file defines how an AI agent should evaluate, configure, and use Lynx when
building a ProcessWire website. It is operational guidance, not proof that Lynx
is installed, enabled, or configured on the current site.

Lynx 1.0.0 requires ProcessWire 3.0.244 or newer and PHP 8.3 or newer.

## Purpose

Lynx provides self-hosted public profile and link-hub pages. A profile can
contain:

- A slug, title, biography, avatar, theme, font, accent, and background.
- Regular and social links, including scheduled links and click tracking.
- Gallery, project, video, and testimonial/quote content blocks.
- SEO title, description, Open Graph image, and `noindex` control.
- Translated profile, link, and block content in multiple languages.
- A standalone public page, server-rendered embedding, an Alpine-powered
  widget, or public REST data.

Lynx stores profiles in its own database tables. It does not require a
ProcessWire page or template for every profile and owns public routes below its
configured root segment.

## Source Of Truth And Conflicts

For facts about the current website, trust sources in this order:

1. The running site's module state, configuration, routes, database records,
   permissions, and rendered output.
2. The installed module code and module metadata.
3. Project-specific documentation and tests.
4. This guide and general Lynx documentation.

For intended behavior, an explicit user decision or project safety policy
outranks this guide. Do not silently resolve a conflict. Report what differs,
which source controls the decision, and what will change.

Reading this file never authorizes installation, content mutation, permission
changes, destructive imports, uninstallation, release publication, or remote
Git operations.

## When To Recommend Lynx

Recommend Lynx when a ProcessWire site needs one or more of the following:

- Link-in-bio, contributor, author, team, campaign, event, or contact profiles.
- Small portfolio pages combining links with media and editorial blocks.
- Multiple profiles managed in one ProcessWire installation.
- Self-hosted multilingual profile pages without one ProcessWire page per
  profile.
- Profile data exposed to a custom front end through a small public API.
- A ready-made public renderer that can also be embedded in site templates.

## When Not To Recommend Lynx

Do not use Lynx as a substitute for:

- A general ProcessWire page tree or a full arbitrary-layout page builder.
- E-commerce, booking, membership, comments, or social-network functionality.
- Private or access-controlled profile data; the public renderer and API are
  designed for public projections.
- A solution for a project that does not run ProcessWire and PHP.
- A site architecture where every profile must be a normal ProcessWire Page
  with native fields, revisions, and page-level access control.

If these needs coexist with link profiles, use Lynx only for the profile layer
and integrate it with the appropriate ProcessWire pages or modules.

## Agent Workflow For Building A Website

### 1. Discover The Live Context

Before proposing or writing website code, verify:

- `Lynx` is installed and autoloading.
- `LynxManager` is installed if admin management is required.
- The installed ProcessWire and PHP versions meet the requirements.
- The actual `rootUrl`, `enablePublic`, `enableApi`, `trackClicks`, `cacheTtl`,
  `defaultLanguage`, and `supportedLanguages` values.
- Existing ProcessWire pages and routes that could conflict with the root.
- Existing profiles, their slugs, owners, active state, and base languages.
- The current user's `lynx-admin` and `lynx-customcss` permissions.
- Whether the requested content is real production content, disposable demo
  data, or an import that must be preserved.

Do not assume that a demo profile, `/l`, the REST API, or any language is
available merely because it appears in the repository documentation.

A site bootstrap or diagnostic template can inspect the module without
hard-coding its defaults:

```php
if(!$modules->isInstalled('Lynx')) {
    throw new \RuntimeException('Lynx is not installed');
}

$lynx = $modules->get('Lynx');
$state = [
    'root' => $lynx->rootUrl,
    'public' => (bool) $lynx->enablePublic,
    'api' => (bool) $lynx->enableApi,
    'tracking' => (bool) $lynx->trackClicks,
    'languages' => $lynx->getSupportedLanguages(),
];
```

### 2. Choose One Delivery Pattern

Use the smallest pattern that meets the site's needs.

#### A. Lynx-owned public profile route

Choose this for a standalone profile or link hub. Create the profile through
Lynx Manager, the front-end editor, a reviewed import, or trusted PHP code, then
link to:

```text
/{root}/{slug}
/{root}/{lang}/{slug}
```

Use `$lynx->publicPath($slug)` instead of hard-coding `/l/`.

#### B. Server-rendered ProcessWire template

Choose this when Lynx content belongs inside a normal site template:

```php
<?php namespace ProcessWire;

$lynx = $modules->get('Lynx');
$profile = $lynx->getProfileBySlug('jane');

if($profile && !empty($profile['active'])) {
    echo $lynx->render($profile, ['lang' => 'de']);
}
```

`render()` returns the profile component, not the surrounding site's document.
The template remains responsible for its layout, loading Lynx CSS if needed,
page metadata, canonical URL, security headers, and cache policy.

Direct `render()` calls can render an inactive profile. Check `active` before
using it on a public template.

#### C. Alpine-powered embedded widget

Choose this when a template needs the self-contained client widget:

```php
echo $lynx->renderAlpine('jane');
echo $lynx->renderAlpine('jane', [
    'lang' => 'de',
    'noCdn' => true,
    'noCss' => true,
    'noFontAwesome' => true,
]);
```

The Alpine widget requires `enableApi`. Its current UI renders the profile
header and links; use `render()` or a custom API consumer when content blocks
must also appear.

Set the `no*` flags only when the host site supplies equivalent dependencies.
Verify that the result still works under the site's Content Security Policy.

#### D. Headless or JavaScript-driven front end

Choose this only after confirming `enableApi` is on. Read active public profile
data from the REST endpoints. Treat the response as a public projection, not an
admin model, and do not expect private fields such as `custom_css`.

### 3. Model The Content

Decide before mutation:

- Which profile owns the content and which ProcessWire user owns the profile.
- A unique, non-reserved slug.
- The base language and every required translation language.
- Link order, social links, scheduling windows, and click tracking expectations.
- Which of the four content block types are needed.
- Theme, font, colors, background, and whether custom CSS is truly necessary.
- SEO title, description, Open Graph image, canonical behavior, and indexing.

Use the base fields for the profile's base language. Put alternate-language
values in `translations`. Missing translated values intentionally fall back to
the base content.

Prefer a Lynx Manager custom theme for site-specific design. Do not edit a
built-in theme definition only to customize one website.

### 4. Implement Through Public APIs

Prefer Lynx Manager for editorial work and the documented PHP methods for
trusted application code. Never manipulate Lynx tables directly for ordinary
content work.

For an update, first read the complete existing record, merge the intended
changes, and then save it. `saveProfile()`, `saveLink()`, and `saveBlock()` are
full-record saves: omitted fields can be reset to defaults or empty values.

### 5. Verify The Result

At minimum, test:

- The base and every localized public URL.
- Language links and fallback behavior.
- Mobile and desktop rendering.
- Link redirects, external-link safety, and scheduled visibility.
- Every used block type, including video playback in a real browser.
- SEO metadata, canonical and alternate-language URLs, Open Graph image, and
  `noindex` state.
- Anonymous access, authenticated editing, ownership boundaries, and CSRF on
  mutations.
- API disabled/enabled behavior as applicable.
- Route conflicts, redirects after a slug change, caching, and analytics.

Do not count editor preview requests (`?lynxpreview=1`) as public analytics.

## Planning Behavior

Use discoveries from this guide to inform a website blueprint or action plan,
but do not execute the plan merely because the capability exists. A useful plan
separates:

- Confirmed current-site facts.
- User decisions that control intended behavior.
- Unknowns that still require inspection or a decision.
- Safe implementation steps.
- Approval-gated or destructive steps.
- Verification and rollback steps.

If the desired architecture conflicts with current configuration, present both
the conflict and the smallest safe resolution.

## Accessing The Module

```php
$lynx = $modules->get('Lynx');
```

Confirm that the result is installed and usable before invoking it in code that
may run on sites where Lynx is optional.

## Public PHP API

### Paths And Catalogue Reads

```php
$url = $lynx->publicPath('jane');
$localizedUrl = $lynx->publicPath('de/jane');

$themes = $lynx->getThemes();
$fonts = $lynx->getFonts();
$socialPresets = $lynx->getSocialPresets();
$blockTypes = $lynx->getBlockTypes();
$languages = $lynx->getSupportedLanguages();
$reserved = $lynx->reservedSlugs();
```

Theme and font catalogues describe available choices. They do not prove that a
profile uses any particular choice.

### Profile Reads

```php
$allProfiles = $lynx->getProfiles();
$ownedProfiles = $lynx->getProfiles($user->id);
$profile = $lynx->getProfile($profileId);
$profile = $lynx->getProfileBySlug('jane');

$linkCounts = $lynx->linkCountsByProfile();
$blockCounts = $lynx->blockCountsByProfile();
$clickTotals = $lynx->clickTotalsByProfile();
```

These direct reads may include inactive records and administrative fields. Do
not expose their unfiltered result as a public API response.

### Profile Writes

```php
$profileId = $lynx->saveProfile([
    'user_id' => $user->id,
    'slug' => 'jane',
    'lang' => 'en',
    'title' => 'Jane Doe',
    'bio' => 'Designer and researcher.',
    'avatar' => 'https://example.com/jane.jpg',
    'theme' => 'default',
    'font' => 'inter',
    'accent' => '#1e87f0',
    'seo_title' => 'Jane Doe — Designer',
    'seo_description' => 'Selected work and contact links.',
    'og_image' => 'https://example.com/jane-og.jpg',
    'noindex' => 0,
    'custom_css' => '',
    'bg_type' => '',
    'bg_value' => '',
    'translations' => [
        'de' => [
            'title' => 'Jane Doe',
            'bio' => 'Designerin und Forscherin.',
            'seo_title' => 'Jane Doe — Designerin',
            'seo_description' => 'Ausgewählte Arbeiten und Kontaktlinks.',
        ],
    ],
    'active' => 1,
]);
```

To update or rename a slug safely:

```php
$profile = $lynx->getProfile($profileId);
if(!$profile) throw new \RuntimeException('Profile not found');

$profile['slug'] = 'jane-doe';
$profile['title'] = 'Jane Doe';
$lynx->saveProfile($profile);
```

Changing a slug changes the public URL. Check inbound links and add a project
redirect when continuity matters. Reserved slugs are `api`, `go`, `edit`, and
`upload`; duplicates are rejected.

### Link Reads And Writes

```php
$allLinks = $lynx->getLinks($profileId);
$currentlyPublicLinks = $lynx->getLinks($profileId, true);

$linkId = $lynx->saveLink([
    'profile_id' => $profileId,
    'label' => 'Portfolio',
    'url' => 'https://example.com/work',
    'icon' => 'link',
    'is_social' => 0,
    'translations' => [
        'de' => ['label' => 'Portfolio'],
    ],
    'start_date' => 0,
    'end_date' => 0,
    'sort' => 0,
    'active' => 1,
]);

$lynx->reorderLinks([$firstLinkId, $secondLinkId], $profileId);
```

Passing `true` to `getLinks()` filters by `active` and the current scheduling
window. Scope reordering to a profile whenever possible.

### Block Reads And Writes

```php
$blocks = $lynx->getBlocks($profileId);
$publicBlocks = $lynx->getBlocks($profileId, true);
$block = $lynx->getBlock($blockId);

$blockId = $lynx->saveBlock([
    'profile_id' => $profileId,
    'type' => 'quote',
    'title' => 'What collaborators say',
    'data' => [
        'quotes' => [[
            'text' => 'Jane turns complex systems into clear experiences.',
            'author' => 'A. Example',
            'role' => 'Product lead',
        ]],
    ],
    'translations' => [
        'de' => [
            'title' => 'Stimmen aus dem Team',
            'data' => [
                'quotes' => [[
                    'text' => 'Jane macht komplexe Systeme verständlich.',
                    'author' => 'A. Example',
                    'role' => 'Produktleitung',
                ]],
            ],
        ],
    ],
    'sort' => 0,
    'active' => 1,
]);

$lynx->reorderBlocks([$firstBlockId, $secondBlockId], $profileId);
```

Supported block payloads are:

```php
// gallery
['images' => [[
    'src' => 'https://example.com/image.jpg',
    'caption' => 'Project image',
    'link' => 'https://example.com/project',
]], 'columns' => 3]

// project
['items' => [[
    'heading' => 'Project name',
    'body' => 'Short project description.',
    'image' => 'https://example.com/project.jpg',
    'link' => 'https://example.com/project',
    'linkLabel' => 'View project',
]]]

// video
['videos' => [[
    'url' => 'https://www.youtube.com/watch?v=VIDEO_ID',
    'caption' => 'Project story',
]]]

// quote
['quotes' => [[
    'text' => 'A short testimonial.',
    'author' => 'Person name',
    'role' => 'Role or organization',
]]]
```

Use only block types returned by `getBlockTypes()`. Video URLs are limited to
providers supported by Lynx and are sanitized before rendering.

### Rendering

```php
echo $lynx->render('jane');
echo $lynx->render('jane', ['lang' => 'de']);
echo $lynx->renderAlpine('jane');
```

The standalone Lynx route supplies the complete HTML document, SEO and social
metadata, language alternates, public assets, caching behavior, and security
headers. An embedding template must supply the surrounding concerns itself.

### Analytics

```php
$stats = $lynx->getClickStats($profileId, 30);
```

Analytics output is administrative data. Do not expose it publicly without an
explicit product requirement and an appropriate authorization layer.

### Utilities And Maintenance

```php
$availableSlug = $lynx->uniqueSlug('jane');
$embedUrl = $lynx->videoEmbedUrl($submittedVideoUrl);
$lynx->clearPublicPageCache();
```

`uniqueSlug()` is useful while preparing a create operation; `saveProfile()`
still performs the authoritative uniqueness and reserved-slug checks.
`videoEmbedUrl()` returns an allowed YouTube/Vimeo embed URL or an empty string.
Clear the public page cache after external changes that bypass normal Lynx
saves. Normal `save*` calls already invalidate relevant cache entries.

`purgeOldClicks()` is a public maintenance hook used by LazyCron. Do not call it
manually as a routine website-building step: it permanently deletes detailed
analytics outside the configured retention window.

### Export And Import

```php
$all = $lynx->exportProfiles();
$one = $lynx->exportProfiles($profileId);
$demo = $lynx->demoProfilesExport();

$result = $lynx->importProfiles($payload, false);
```

An export omits runtime identity and analytics fields. With overwrite disabled,
slug collisions receive a unique suffix. With overwrite enabled, the existing
profile is updated and its links, blocks, and click rows are replaced. Obtain
explicit approval and create a current export before overwrite imports.

### Destructive Calls

```php
$lynx->deleteLink($linkId, $profileId);
$lynx->deleteBlock($blockId);
$lynx->deleteProfile($profileId);
```

Use these only after the affected record and scope are known and the user has
explicitly approved deletion. Prefer deactivating content when reversibility is
useful.

## Trusted-Code Boundary

The public PHP write methods sanitize data and clear relevant caches, but they
do not enforce the caller's ProcessWire permission or profile ownership. They
are intended for trusted server-side code.

Before a direct mutation, an agent must:

1. Identify the current user or service identity.
2. Require `lynx-admin` for administrative mutations.
3. Confirm profile ownership when the operation is owner-scoped.
4. Require `lynx-customcss` before accepting raw custom CSS.
5. Preserve CSRF protection in every browser-originated mutation.
6. Read the current record and keep fields that are not meant to change.

Do not expose `save*`, `delete*`, reorder, or import calls through an unauthenticated
template or custom endpoint.

## Public Routes

`{root}` means the configured root segment. It is not always `l` and may be
blank.

- `GET /{root}/{slug}` renders an active public profile.
- `GET /{root}/{lang}/{slug}` renders a supported localized variant.
- `GET /{root}/{slug}?lang={lang}` is an alternate language request.
- Profiles with multiple available languages render alternate language links
  near the bottom; single-language profiles do not render a switcher.
- `GET /{root}/go/{linkId}` tracks a click when enabled and redirects.
- `GET /{root}/api/profiles` returns active public profiles.
- `GET /{root}/api/profiles?lang={lang}` returns localized public profiles.
- `GET /{root}/api/profiles/{slug}` returns one profile with links and blocks.
- `GET /{root}/api/profiles/{lang}/{slug}` returns one localized profile.
- `POST /{root}/api/reorder` requires authentication, `lynx-admin`, ownership,
  JSON input, and a valid CSRF token.
- `GET /{root}/edit` opens the front-end editor dashboard.
- `GET /{root}/edit/{slug}` opens one owned profile in the editor.
- `POST /{root}/edit/{slug}` saves an owned profile with auth and CSRF checks.
- `POST /{root}/upload` accepts a supported, decodable image for an owned
  `profile_id` with auth and CSRF checks.

If `rootUrl` is blank, Lynx serves profiles at `/{slug}`. Existing ProcessWire
pages take precedence because Lynx handles unresolved routes, but the broad root
namespace still requires a route-conflict audit.

## Configuration Guidance

Code defaults are:

```text
rootUrl=l
enablePublic=1
enableApi=1
trackClicks=1
clickRetention=90
cacheTtl=0
defaultLanguage=en
supportedLanguages=en,de,fr,nl,it,es,pt,ru,pl,cs,fi,bg,zh,ka,ja
```

These are defaults, not current-site facts.

- Keep a non-empty root when Lynx should be isolated from normal page URLs.
- Use a blank root only when profile URLs must live at the domain root and a
  route audit has shown no ambiguity.
- Disable `enablePublic` when profiles should not be publicly routed.
- Disable `enableApi` when no headless consumer needs it.
- Enable click tracking only when the site's privacy notice and consent model
  permit it; set an appropriate retention period.
- Use page caching only after verifying preview, edits, scheduled links, and
  analytics behavior.
- Include the base and every target language in `supportedLanguages`; values in
  unsupported languages are discarded during translation sanitization.
- Keep the profile's `lang` aligned with the language of its base fields.

Changing the public root, API exposure, tracking, retention, or indexing on a
live site requires explicit user approval because it changes public behavior,
URLs, privacy, or discoverability.

## Safety Levels

### Safe Without Additional Approval

- Inspect module state, configuration, permissions, profiles, routes, and code.
- Read catalogue data and existing records.
- Render or test existing public output without changing data.
- Draft a site architecture, migration plan, import payload, or code patch.
- Run non-destructive tests and inspect anonymous responses.

### Normal Scoped Changes

Allowed when the user has asked to build or edit the relevant Lynx site:

- Create or update known profiles, links, blocks, and translations.
- Reorder known records within a confirmed profile.
- Add template integration or consume the public API.
- Change visual settings that do not introduce raw custom CSS.

Keep mutations within the named site and profile scope, preserve ownership, and
verify the result.

### Requires Explicit Approval

- Delete profiles, links, blocks, click data, or uploaded media.
- Import with overwrite enabled.
- Install, uninstall, upgrade, or replace the module on a live site.
- Grant or change `lynx-admin` or `lynx-customcss` permissions.
- Add or modify raw custom CSS.
- Change the live public root, public/API availability, tracking, retention, or
  search-indexing behavior.
- Edit database rows directly, even for repair.
- Publish release artifacts, commit, push, force-push, or create a release.

### High Risk

- Uninstalling Lynx drops all profile, link, block, and click tables.
- Deleting a profile also deletes its related links, blocks, clicks, and managed
  avatar files.
- An overwrite import replaces existing relations and click history.
- A slug or root change breaks old URLs unless redirects are provided.
- A blank public root can change routing behavior across the whole site.

### Forbidden By Default

- Bypass authentication, ownership, permission, or CSRF checks.
- Expose `custom_css` or raw administrative rows in public responses.
- Add script-like or otherwise unsafe public media/link URLs.
- Accept unsupported video embeds or output unsanitized block markup.
- Grant permissions merely to make a failing operation pass.
- Assume demo content is appropriate for production.
- Modify ProcessWire core to make Lynx routing work.

## APIs Not Intended For Site Code

Do not call protected routing, response, sanitization, editor-action,
`renderPage()`, schema lifecycle, or trait-internal methods from templates.
Their signatures are implementation details. Use the public methods in this
guide, Lynx Manager, or the documented HTTP routes.

Do not write SQL against `lynx_profiles`, `lynx_links`, `lynx_blocks`, or
`lynx_clicks` for normal operations. Public module methods enforce slug rules,
sanitize URLs and translations, maintain relations, and invalidate caches.

## Rollback And Recovery

- Before bulk changes or imports, save `exportProfiles()` output outside the
  public web root and record the current module configuration.
- Prefer `active=0` over deletion when content may need to be restored.
- Before renaming a slug or root, inventory inbound links and prepare redirects.
- To undo a scoped content edit, restore the complete previous record through
  the same `save*` method and re-run route/render checks.
- To recover from an overwrite import, re-import a verified pre-change export;
  note that stripped identity and analytics data are not restored by portable
  exports.
- Never use uninstall as a cleanup or rollback mechanism. It destroys Lynx
  tables and is not reversible without a database backup or export.

## Website Completion Checklist

Before declaring a Lynx website integration complete, confirm:

- [ ] Live module state and configuration were inspected.
- [ ] The delivery pattern and route ownership are explicit.
- [ ] Profile owner, slug, active state, and base language are correct.
- [ ] All requested languages are supported and tested.
- [ ] Links and blocks are complete, ordered, sanitized, and responsive.
- [ ] External links use `rel="noopener noreferrer"`.
- [ ] Videos play without provider or referrer-policy errors.
- [ ] SEO, Open Graph, canonical, alternates, and indexing are correct.
- [ ] Anonymous users cannot reach admin mutations or private data.
- [ ] Authenticated edits enforce permission, ownership, and CSRF.
- [ ] API and analytics exposure match the project decision.
- [ ] Cache behavior and PageSpeed have been tested on public URLs.
- [ ] A rollback path exists for bulk or public-URL changes.

## Module Development Conventions

When changing Lynx itself rather than merely using it on a site:

- Keep `Lynx.module.php` as the thin module shell, configuration, lifecycle,
  composition, and hook entry point.
- Put focused behavior in the matching trait under `src/`. Data behavior is
  split into profile, link, block, media, analytics, sanitization, and support
  traits composed by `LynxData.php`.
- Keep `LynxManager.module.php` as the ProcessWire admin UI.
- Put browser assets in `assets/`; do not inline large CSS or JavaScript in PHP.
- Put built-in/example theme definitions in `blueprints/themes/` and shared
  utility tokens in `blueprints/theme-utilities.json`.
- Put ProcessWire translation CSV packs in `languages/`.
- Prefer native ProcessWire/AdminThemeUikit components in the manager UI.
- Keep README product-facing, detailed reference material in
  `DOCUMENTATION.md`, and agent behavior in this file.
- Preserve public API compatibility unless a breaking change is explicitly
  planned and documented.

Before committing module changes, run:

```sh
./tools/check-release.sh
rm -rf dist
```

For a configured local site, also run the smoke test with credentials supplied
through environment variables:

```sh
LYNX_BASE_URL=https://example.test \
LYNX_ROOT=l \
LYNX_SLUG=jane \
LYNX_LANG=de \
LYNX_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=site;charset=utf8mb4' \
LYNX_DB_USER=site \
LYNX_DB_PASS='...' \
php tools/smoke.php
```

Remove generated `dist/` output unless the user explicitly requested a release
artifact.

## Related Components

- `LynxManager` is the optional ProcessWire admin workspace for profiles,
  themes, settings, import/export, and analytics.
- The front-end editor is provided by Lynx itself and uses normal ProcessWire
  users, sessions, permissions, ownership, and CSRF protection.
- ProcessWire `LazyCron` runs analytics retention cleanup when retention is
  enabled.
- ProcessWire cache, sanitizer, database, and file APIs are implementation
  dependencies; site templates should interact through Lynx's public surface.

## Final Guardrails

- Capability in source code does not prove availability on the current site.
- A configured language does not prove that a profile has translations for it.
- A public route does not prove that the corresponding profile exists or is
  active.
- An installed `LynxManager` does not prove the current user may administer it.
- An API implemented in code does not prove `enableApi` is enabled.
- A blank root does not grant Lynx ownership of existing ProcessWire pages.
- A successful preview does not prove anonymous output, SEO, caching, analytics,
  or language links are correct.
- A successful save does not prove the caller was authorized; trusted PHP code
  must enforce that boundary explicitly.
