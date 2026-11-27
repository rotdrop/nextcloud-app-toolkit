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
`vendor/bin/extract-l10n.php OUTPUT_FILE [SOURCE_DIR] [IGNORE_FILE]`.

To omit toolkit files not used by the app, pass its `.l10nignore` as
`IGNORE_FILE`, or set `APP_TOOLKIT_L10N_IGNORE = $(ABSSRCDIR)/.l10nignore`
before including the Makefile fragment. The ignore file contains one exact
file path per line; blank lines and lines starting with `#` are ignored.
Entries are considered only when they contain
`rotdrop/nextcloud-app-toolkit/src/...`; any preceding path is ignored
when matching the file below `src/`. Entries for other package names, bare
`src/...` paths, globs, directory entries, and negation are not supported.
Without an ignore file, all toolkit files are included as before.

Only string literals are extracted, calls with dynamic arguments are
reported as warnings. Consider adding the output directory to the app's
`.gitattributes` with `export-ignore` to keep it out of release archives.

## Entity serialization

`EntitySerializer::setExcludedClasses()` limits association traversal in addition
to the depth limit. Pass fully qualified class names; subclasses (including
Doctrine proxies) match too:

```php
$serializer->setExcludedClasses([File::class]);
$serializer->addEntity($musician, depth: 1);
$response = $serializer->export();
```

Excluded associations retain their class-and-identifier references but are not
recursively serialized or explicitly initialized by the serializer.
`EntityArrayAdapter::create(..., entitySerializer: $serializer)` also respects
this configuration and retains references to unfetched entities.
If a collection is only partly fetched, the adapter retains the entire reference
collection, preserving its base class and identifiers.
Exclusions survive `reset()`; pass an empty list to clear them. Configure them
before collecting entities, since previously collected data is not removed.
Explicitly added root entities are still serialized: this is a traversal limit,
not an access-control mechanism. Collections still have to be iterated to obtain
their identifiers, so this does not prevent Doctrine from loading collection
members.

## Development

PHPUnit is installed in its own `vendor-bin/phpunit` namespace by means of
[`bamarni/composer-bin-plugin`](https://github.com/bamarni/composer-bin-plugin):

```sh
make test    # install everything and run the test-suite
make clean   # remove vendor dirs, caches and composer.lock files
```

Alternatively run `composer install && composer test`.

PHPCS and PHPMD are also installed in separate `vendor-bin` namespaces.
Check PHP changes with:

```sh
vendor-bin/phpcs/vendor/bin/phpcs --standard=.phpcs.xml src/ tests/
vendor-bin/phpmd/vendor/bin/phpmd src/ text .phpmd.xml --suffixes php --threads 1 --no-cache
vendor-bin/phpmd/vendor/bin/phpmd tests/ text .phpmd.xml --suffixes php --threads 1 --no-cache
```

PHPCS reports warnings without failing, as in the upstream project. Its
documentation and class-layout checks are relaxed for tests, where fixtures
and test doubles commonly share files. PHPCS and PHPMD run in GitHub Actions
on pushes to `main` and pull requests, and can also be started manually.

The PHPUnit GitHub Actions workflow runs the same commands with PHP 8.3, 8.4, and 8.5
on pushes to `main` and when pull requests are opened, updated with new
commits, reopened, or edited. It can also be started manually. Composer
selects PHPUnit 12.5 on PHP 8.3 and PHPUnit 13 on PHP 8.4.1 or later.

The unit tests live in `tests/Unit` and mirror the source layout. Tests
which need a running Nextcloud server or a database are not part of this
package.

`tests/lib` contains the shared PHPUnit helper classes (mock provider,
database provider, ...) which formerly lived in
[`rotdrop/nextcloud-app-phpunit`](https://github.com/rotdrop/nextcloud-app-phpunit).
They keep their `OCA\RotDrop\Tests` namespace and are registered in
`autoload-dev`. Most of them need a Nextcloud server environment
(`OC\` classes) and are not used by the standalone unit tests.
