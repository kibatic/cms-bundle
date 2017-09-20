# KibaticCmsBundle

## Installation

```
// composer.json
{
    ...
    "repositories": [
        {
            "type": "vcs",
            "url": "git@gitlab.kitpages.fr:kibatic/KibaticCmsBundle.git"
        }
    ]
}
```

```
composer require kibatic/cms-bundle dev-master
```

```
// app/AppKernel.php

public function registerBundles()
{
    $bundles = [
        // ...
        new Kibatic\CmsBundle\KibaticCmsBundle(),
        // ...
    ];
}
```

```
// app/config/routing.yml

kibatic_cms:
    resource: "@KibaticCmsBundle/Resources/config/routing.yml"
    prefix:   /
```


Finally update your database schema with :

```
bin/console doctrine:schema:update --force
```

or :

```
bin/console doctrine:migration:diff
bin/console doctrine:migration:migrate
```

## Override Layout

Create a template : `app/Resources/KibaticCmsBundle/layout.html.twig`

For example to use the layout of your app :

```
{% extends '::layout.html.twig' %}
```
