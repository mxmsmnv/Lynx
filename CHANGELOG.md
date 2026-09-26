# Changelog

All notable changes to Lynx are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Public releases use
semantic versioning; ProcessWire module metadata uses the equivalent integer
version.

## [1.0.1] - 2026-09-26

### Fixed
- Use ProcessWire's portable schema introspection API during upgrades instead
  of querying MySQL's `information_schema` directly.
- Keep legacy profile deletion compatible with SQLite and PostgreSQL by using
  a portable subquery rather than MySQL's `DELETE ... JOIN` syntax.
- Skip unsupported post-install foreign-key additions on SQLite while retaining
  the constraints on fresh installs and explicit cascade cleanup on upgrades.

## [1.0.0] - 2026-07-21

First public release.

### Added
- Multi-profile, self-hosted link-in-bio pages with custom database tables,
  public routes, tracked redirects and template rendering through `render()`.
- Companion Lynx Manager module with a dashboard, unified settings, profile
  statistics, theme gallery, JSON import/export and optional demo installer.
- Responsive front-end editor for profile owners, including avatar uploads,
  drag-and-drop ordering, live preview and editable profile slugs.
- Links, social presets and scheduled visibility windows.
- Gallery, project, video and testimonial/quote content blocks.
- Multiple simultaneous profile languages with translated profile fields, link
  labels and nested block content.
- Localized public routes, REST responses, alternate-language navigation,
  `hreflang` metadata and base-language fallback.
- Built-in and custom themes, accent colors, background overrides, curated
  open-source fonts and permission-gated custom CSS.
- SEO titles and descriptions, canonical and Open Graph metadata, social cards,
  OG images and optional `noindex`.
- Optional public-page caching, click/view analytics and configurable detailed
  click-history retention.
- Server-rendered, Alpine-powered and REST/headless integration options.
- Ten-profile multilingual ProcessWire community demo containing every content
  block type and verified public source links.
- Agent guidance, technical documentation, smoke tests, release validation and
  reproducible ZIP packaging.

- Public profiles may be served below a configured root segment or directly
  from the site root.
- Lynx and Lynx Manager are composed from focused traits for routing, data,
  rendering, translations, editor actions/views, manager workspaces and
  import/export concerns.
- Theme previews render isolated live public-profile demos in a three-column
  desktop gallery.
- Anonymous standalone profiles are cacheable and do not create an unnecessary
  ProcessWire session cookie.
- Demo profiles are crawlable by default; editors can enable `noindex` per
  profile when needed.

- Inline the small public stylesheet and use lightweight built-in link glyphs
  instead of a render-blocking icon-font dependency.
- Defer optional web fonts and YouTube playback until after the initial render.
- Request appropriately sized avatars, declare image dimensions and use lazy
  loading and asynchronous decoding where appropriate.
- Negotiate gzip for standalone responses and include an inline favicon.

- Enforce ProcessWire permissions, ownership and CSRF validation on all
  mutating manager, editor and API routes.
- Sanitize public URLs, custom CSS, translated block payloads and supported
  video embeds.
- Validate decoded uploads, scope them to owned profiles, enforce upload limits
  and clean up unreferenced managed media.
- Use transactions for editor saves, imports and profile deletion.
- Preserve ownership unless a change is explicitly supplied.
- Send restrictive CSP, content-type, referrer and permissions-policy headers.
- Protect outbound links with `rel="noopener noreferrer"`.

- Prevent editor live previews from increasing public view counts.
- Preserve base media and URLs when translated nested block text is merged.
- Translate links and blocks correctly when a profile base language differs
  from the global default.
- Invalidate cached pages after slug, content and global appearance changes.
- Reject reserved slugs, duplicates and unexpected trailing route segments.
- Generate stable canonical URLs and correct paths for subdirectory installs.
- Supply the referrer information required by YouTube embeds, preventing player
  configuration error 153.
- Add accessible iframe titles, non-redundant image alternatives and WCAG AA
  contrast for public navigation and footer elements.
