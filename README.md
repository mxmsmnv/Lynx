# Lynx

Lynx adds open-source, self-hosted link-in-bio profiles to ProcessWire. Publish multiple branded and multilingual profiles with links, social accounts, portfolio blocks, themes, scheduling, analytics and a REST API.

![Lynx](assets/Lynx.png)

It is made for creators, teams, products and communities that want fast profile pages they own, manage and localize inside their existing site.

**Author:** Maxim Semenov<br>
**Website:** [smnv.org](https://smnv.org)<br>
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Lynx Does

- Personal link pages for creators, founders, teams and projects.
- Multilingual profiles with localized links and content blocks.
- Lightweight portfolio pages with images, projects, video and quotes.
- Branded campaign pages with scheduled links.
- Self-hosted profile hubs managed from ProcessWire.
- A single-profile homepage rendered directly from a ProcessWire template.

## Admin Area

Lynx Manager adds a focused admin section where site editors can:

- create and manage profiles;
- rename profile slugs after creation;
- add links, social presets and portfolio sections;
- translate profile text, link labels and portfolio content;
- choose themes, colors, backgrounds and open-source fonts;
- schedule links for launches or campaigns;
- review views and clicks;
- import, export or install sample content.

Profile owners can also use the responsive front-end editor without entering the ProcessWire admin.

## Public Profiles

Each profile can include:

- avatar, title and short bio;
- regular links and social links;
- gallery, project, video and quote blocks;
- translated versions with alternate-language links;
- custom visual styling per person or project.
- SEO title, description, canonical/Open Graph metadata and optional `noindex`.

Standalone profile pages are optimized for fast first paint, deferred video playback, accessible markup and cacheable anonymous responses.

## Developer Integration

Render a profile from any ProcessWire template:

```php
$lynx = $modules->get('Lynx');
echo $lynx->render('profile-slug');
```

Lynx also provides an embeddable Alpine renderer and optional REST endpoints for headless or JavaScript-driven integrations.

## Requirements

- ProcessWire 3.0.244 or newer.
- PHP 8.3 or newer.

## Installation

1. Copy the `Lynx` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `Lynx`.
4. Install `Lynx Manager`.
5. Open Lynx Manager and create a profile or install the optional multilingual demo.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for setup, configuration and template integration.

See [CHANGELOG.md](CHANGELOG.md) for the release notes.

## Author

Maxim Semenov  
[smnv.org](https://smnv.org)  
[maxim@smnv.org](mailto:maxim@smnv.org)

## Support

If this project helps your work, consider supporting future development through [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## License

MIT
