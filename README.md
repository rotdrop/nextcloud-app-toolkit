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

## Translations

The Nextcloud translation tool only scans the app's git checkout, not
`vendor/`. Strings of the toolkit which are translated by the app's
`IL10N` instance therefore have to be made visible to it by means of a
generated, never executed "string catalogue" which is committed to the app.

Include the Makefile fragment in the app's `Makefile`:

```makefile
ABSSRCDIR = $(CURDIR)
include $(ABSSRCDIR)/vendor/rotdrop/nextcloud-app-toolkit/tools/l10n.mk
```

and run `make toolkit-l10n` before creating the POT files. This writes
`l10n-sources/toolkit-strings.php` (override with
`APP_TOOLKIT_L10N_DEST`); commit it, so changes of the toolkit strings
show up as a diff. Without `make`, call
`vendor/bin/extract-l10n.php OUTPUT_FILE [SOURCE_DIR]`.

Only string literals are extracted, calls with dynamic arguments are
reported as warnings. Consider adding the output directory to the app's
`.gitattributes` with `export-ignore` to keep it out of release archives.
