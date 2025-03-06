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
