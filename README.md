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

Update your database schema with :

```
bin/console doctrine:schema:update --force
```

or :

```
bin/console doctrine:migration:diff
bin/console doctrine:migration:migrate
```

