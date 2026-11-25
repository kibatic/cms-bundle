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

They write the blocks: restrict the server's endpoint (`/mcp/<server>`) to the CMS's admins, e.g.
`{ path: ^/mcp, roles: ROLE_CMS }` in `access_control`. They never publish nor restore a version: the admins do it
in the CMS's administration. The HTTP transport needs a PSR-7 implementation (e.g. `nyholm/psr7`).

The assistant's page then has an "Install on Claude" button: the commands that add this server to Claude Code and
Claude Desktop, with the admin's account. They send it with HTTP Basic authentication (`http_basic` firewall).
