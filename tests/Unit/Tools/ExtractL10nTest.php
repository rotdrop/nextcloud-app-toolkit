<?php

namespace OCA\RotDrop\Toolkit\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

#[Attributes\CoversNothing]
class ExtractL10nTest extends TestCase
{
  private string $directory;

  protected function setUp(): void
  {
    $this->directory = sys_get_temp_dir() . '/toolkit-l10n-' . bin2hex(random_bytes(8));
    mkdir($this->directory . '/src/Nested', 0777, true);
    file_put_contents($this->directory . '/src/Keep.php', <<<'PHP'
<?php
$l->t('Keep');
$l->t('Shared');
PHP);
    file_put_contents($this->directory . '/src/Skip.php', <<<'PHP'
<?php
$l->t('Skip');
$l->t('Shared');
$l->n('Skipped singular', 'Skipped plural', 2);
$l->t($dynamic);
PHP);
    file_put_contents($this->directory . '/src/Nested/Skip.php', <<<'PHP'
<?php
$l->t('Nested skip');
PHP);
  }

  protected function tearDown(): void
  {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
      if ($file->isDir()) {
        rmdir($file->getPathname());
      } else {
        unlink($file->getPathname());
      }
    }
    rmdir($this->directory);
  }

  private function extract(?string $ignoreFile = null): array
  {
    $command = [
      PHP_BINARY,
      dirname(__DIR__, 3) . '/tools/extract-l10n.php',
      $this->directory . '/catalogue.php',
      $this->directory . '/src',
    ];
    if ($ignoreFile !== null) {
      $command[] = $ignoreFile;
    }
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    self::assertIsResource($process);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($process), $errors];
  }

  public function testWithoutIgnoreFilePreservesAllStrings(): void
  {
    [$status, $errors] = $this->extract();
    self::assertSame(0, $status, $errors);
    $content = file_get_contents($this->directory . '/catalogue.php');
    self::assertStringContainsString("\$l->t('Skip');", $content);
    self::assertStringContainsString("\$l->t('Nested skip');", $content);
    self::assertStringContainsString("\$l->n('Skipped singular', 'Skipped plural', 1);", $content);
    self::assertStringContainsString('Warning: non-literal translation string ignored', $errors);
  }

  public function testExactPathsAndPrefixedPathsExcludeFiles(): void
  {
    $ignoreFile = $this->directory . '/.l10nignore';
    file_put_contents($ignoreFile, " # comment /rotdrop/nextcloud-app-toolkit/src/Keep.php\r\n\r\n"
      . " /app/vendor/rotdrop/nextcloud-app-toolkit/src/Skip.php \r\n"
      . "/app/vendor/rotdrop/nextcloud-app-toolkit/src/Nested/Skip.php\r\n"
      . "unrelated/Keep.php\r\nsrc/*.php\r\nsrc/Nested/\r\n");
    [$status, $errors] = $this->extract($ignoreFile);
    self::assertSame(0, $status, $errors);
    $content = file_get_contents($this->directory . '/catalogue.php');
    self::assertStringContainsString("\$l->t('Keep');", $content);
    self::assertStringContainsString("\$l->t('Shared');", $content);
    self::assertStringNotContainsString('Skip', $content);
    self::assertStringNotContainsString('Warning:', $errors);
    self::assertStringContainsString('2 strings and 0 plural strings extracted.', $errors);
    self::assertStringContainsString('/src/Keep.php:', $content);
  }

  public function testEmptyIgnoreFileDoesNotChangeCatalogue(): void
  {
    $this->extract();
    $original = file_get_contents($this->directory . '/catalogue.php');
    $ignoreFile = $this->directory . '/.l10nignore';
    file_put_contents($ignoreFile, "\n# No excluded toolkit files\n");
    [$status, $errors] = $this->extract($ignoreFile);
    self::assertSame(0, $status, $errors);
    self::assertSame($original, file_get_contents($this->directory . '/catalogue.php'));
  }

  public function testExclusionMatchesOnlyTheExactRelativePath(): void
  {
    $ignoreFile = $this->directory . '/.l10nignore';
    file_put_contents($ignoreFile, 'vendor/rotdrop/nextcloud-app-toolkit/src/Skip.php');
    [$status, $errors] = $this->extract($ignoreFile);
    self::assertSame(0, $status, $errors);
    $content = file_get_contents($this->directory . '/catalogue.php');
    self::assertStringNotContainsString("\$l->t('Skip');", $content);
    self::assertStringContainsString("\$l->t('Nested skip');", $content);
    self::assertStringContainsString('/src/Nested/Skip.php:', $content);
  }

  public function testOtherPackagePrefixesAndBareSourcePathsAreIgnored(): void
  {
    $ignoreFile = $this->directory . '/.l10nignore';
    file_put_contents($ignoreFile, "src/Skip.php\n"
      . "vendor/rotdrop/nextcloud-vue-components/src/Skip.php\n"
      . "vendor/rotdrop/other-package/src/Nested/Skip.php\n");
    [$status, $errors] = $this->extract($ignoreFile);
    self::assertSame(0, $status, $errors);
    $content = file_get_contents($this->directory . '/catalogue.php');
    self::assertStringContainsString("\$l->t('Skip');", $content);
    self::assertStringContainsString("\$l->t('Nested skip');", $content);
  }

  public function testMissingIgnoreFileFailsWithoutWritingCatalogue(): void
  {
    [$status, $errors] = $this->extract($this->directory . '/missing');
    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot read ignore file:', $errors);
    self::assertFileDoesNotExist($this->directory . '/catalogue.php');
  }
}
