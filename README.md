# Template Maker module for Craft CMS 3

## Requirements

This module requires Craft 3

## Installation

To install the module, follow these instructions.

You will need to add the following content to your `config/app.php` file. This ensures that your module will get loaded for each request. You can remove components if you don't require the full set of features this module offers.
```
return [
  'modules' => [
    'template-maker' => [
      'class' => \modules\template-maker\Helpers::class,
      'components' => [
        'service' => [ 'class' => 'modules\template-maker\services\Services' ]
      ],
    ]
  ],
  'bootstrap' => ['template-maker'],
];
```
You'll also need to make sure that you incorporate the following to your project's `composer.json` file:

```
"require": {
  "oomphinc/composer-installers-extender": "^1.1",
  "marknotton/template-maker": "dev-master"
},
"autoload": {
  "psr-4": {
    "modules\\": "modules/",
    "modules\\template-maker\\": "modules/template-maker/module/"
  }
},
"extra": {
  "installer-types": ["craft-module"],
  "installer-paths": {
    "modules/{$name}": ["type:craft-module"]
  }
},
```

[Composer Installers Extender](https://github.com/oomphinc/composer-installers-extender) is what allows you to create your own [Package Type](https://github.com/composer/installers). The `craft-module` package type I'm using is not native to Craft, and there-for not supported by [Composer Installers](https://getcomposer.org/doc/faqs/how-do-i-install-a-package-to-a-custom-path-for-my-framework.md). Package types allow you to define `extra` settings that tell Composer to put this module into the `modules` directory. It's the only way you can reliably manage this module within your project.

After you have added this, you may need to run `composer dump-autoload` from the project’s root directory to rebuild the Composer autoload map. This will happen automatically any time you do a `composer install` or `composer update` as well.
