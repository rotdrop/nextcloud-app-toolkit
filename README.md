# nextcloud-app-toolkit

## Description

Reusable PHP classes for Nextcloud apps, distributed as a Composer package.

## Setup

Add the package to a Nextcloud app with:

```sh
composer require rotdrop/nextcloud-app-toolkit
```

Until a release is tagged and available through Packagist, configure
this repository in the app's `composer.json` and require its development
branch:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rotdrop/nextcloud-app-toolkit"
    }
  ],
  "require": {
    "rotdrop/nextcloud-app-toolkit": "dev-main"
  }
}
```

Composer registers the `OCA\RotDrop\Toolkit\` namespace automatically.
The package requires PHP 8.1 or later; Nextcloud and other runtime
dependencies are provided by the consuming app. Ensure the app loads
its `vendor/autoload.php` as part of its normal bootstrap. Once a
release is tagged, apps can use its version constraint instead of
`dev-main`.
