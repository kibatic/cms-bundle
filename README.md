# KibaticCmsBundle

## Installation

```
composer require kibatic/cms-bundle
```

```
// config/routing.yaml

kibatic_cms:
    resource: "@KibaticCmsBundle/Resources/config/routing.yaml"
    prefix:   /
```

Update your database schema by generating and applying a new migration :

```
bin/console doctrine:migrations:diff
bin/console doctrine:migration:migrate
```

## Admin template layout & twig block

The bundle expect the layout template `::layout.html.twig` and block `content` to exist in order to load its admin pages.

If you wish to change the used layout, you can create a template `app/Resources/KibaticCmsBundle/layout.html.twig` and extend the layout you want to use.

You won't be able to use a different block name without overriding all the bundle's templates though.

## Pages of the blocks

The entries of a navigation block lead to the page of a block, `/page/{slug}`, shown in the site's layout.
These pages are public, for any block: import their route only if the site wants them.

```yaml
# config/routes/kibatic_cms.yaml
kibatic_cms_pages:
    resource: "@KibaticCmsBundle/Resources/config/routing_pages.yaml"
    prefix:   /
```

Without it, the entries of the navigations are plain labels, without link.

## MCP tools

With [symfony/mcp-bundle](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html) installed, the CMS's tools
(`cms_guide`, `cms_list_blocks`, `cms_get_block`, `cms_save_block`, `cms_save_metablock`, `cms_save_navigation`,
`cms_list_versions`) are MCP tools of the application. A server exposes them when its registry lists them, next to
the application's own tools:

```yaml
# config/packages/mcp.yaml
mcp:
    servers:
        app:
            registry: ['App\Mcp\', 'Kibatic\CmsBundle\']
```

Each tool answers only to the CMS's admins (`ROLE_CMS`), and refuses any other user of the server.
In the console (stdio transport), there is no user and the tools are allowed.
Restricting the server's endpoint (`/mcp/<server>`) to the CMS's admins is still advised when the server has no other users, e.g. `{ path: ^/mcp, roles: ROLE_CMS }` in `access_control`.
They never publish nor restore a version: the admins do it in the CMS's administration.
The HTTP transport needs a PSR-7 implementation (e.g. `nyholm/psr7`).

The assistant's page then has an "Install on Claude" button: the commands that add this server to Claude Code and
Claude Desktop, with the admin's account. They send it with HTTP Basic authentication (`http_basic` firewall).

## Upgrading

From a version before the reworked admin:

- Generate and apply a migration before deploying (`bin/console doctrine:migrations:diff`, then `bin/console doctrine:migrations:migrate`).
  The blocks get the columns `styles` and `meta_only`, and the versions their table `cms_version`.
  `cms_block()` reads the published version on every page, for the visitors too: without the migration, every page that shows a block fails.
- Text blocks are now plain text: their content is escaped, and its line breaks become `<br>`.
  A text block that holds HTML (`<br>`, `<strong>`, links...) shows its tags.
  The admin cannot change the type of a block, update it in the database: `UPDATE cms_block SET type = 'html' WHERE type = 'text' AND content LIKE '%<%'`.
- The pages of the blocks (`/page/{slug}`) are in a routing file of their own, to import if the site wants them (see "Pages of the blocks").
- Symfony 7.3 or later is required.
- The CMS's tools answer only to the CMS's admins (`ROLE_CMS`), on the assistant and on an MCP server (see "MCP tools").
