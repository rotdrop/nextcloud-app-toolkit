For PHP changes, follow the repository rules in `.phpcs.xml` and `.phpmd.xml`. Before considering a change ready for a pull request, run:

```sh
vendor-bin/phpcs/vendor/bin/phpcs --standard=.phpcs.xml src/ tests/
vendor-bin/phpmd/vendor/bin/phpmd src/ text .phpmd.xml --suffixes php --threads 1 --no-cache
vendor-bin/phpmd/vendor/bin/phpmd tests/ text .phpmd.xml --suffixes php --threads 1 --no-cache
```

Address reported violations introduced or exposed by your changes. The PHPCS and PHPMD GitHub Actions checks must pass.
