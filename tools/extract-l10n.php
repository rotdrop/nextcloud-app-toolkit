#!/usr/bin/env php
<?php
/**
 * Extract the translatable string literals of the toolkit into a
 * "string catalogue" file which can be committed to a consuming app.
 *
 * The Nextcloud translation tool only scans the app's git checkout and
 * hence does not see strings of composer packages in vendor/. The generated
 * file is never executed, it just contains $l->t() and $l->n() calls which
 * can be picked up by xgettext.
 *
 * Usage: extract-l10n.php OUTPUT_FILE [SOURCE_DIR]
 *
 * @license AGPL-3.0-or-later
 */

if ($argc < 2) {
  fwrite(STDERR, 'Usage: ' . basename($argv[0]) . " OUTPUT_FILE [SOURCE_DIR]\n");
  exit(1);
}

$outputFile = $argv[1];
$sourceDir = realpath($argv[2] ?? dirname(__DIR__));
if ($sourceDir === false || !is_dir($sourceDir)) {
  fwrite(STDERR, "Source directory does not exist.\n");
  exit(1);
}

$skipDirs = [ 'tools', 'vendor', 'node_modules', 'l10n' ];

/** Decode a PHP string literal token without evaluating code, null if dynamic. */
function decodeLiteral(string $literal): ?string
{
  $quote = $literal[0];
  $body = substr($literal, 1, -1);
  if ($quote === "'") {
    return strtr($body, [ '\\\\' => '\\', "\\'" => "'" ]);
  }
  if (preg_match('/[$]|\{\$/', $body)) {
    return null;
  }
  return stripcslashes($body);
}

/**
 * Read a string literal, possibly concatenated with ".", starting at $i.
 * Returns the string and moves $i behind the literal, or null.
 */
function readString(array $tokens, int &$i): ?string
{
  $result = '';
  $count = count($tokens);
  while (true) {
    while ($i < $count && is_array($tokens[$i]) && in_array($tokens[$i][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ])) {
      ++$i;
    }
    if ($i >= $count || !is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING) {
      return null;
    }
    $part = decodeLiteral($tokens[$i][1]);
    if ($part === null) {
      return null;
    }
    $result .= $part;
    ++$i;
    $j = $i;
    while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ])) {
      ++$j;
    }
    if ($j < $count && $tokens[$j] === '.') {
      $i = $j + 1;
      continue;
    }
    return $result;
  }
}

function skipSpace(array $tokens, int $i): int
{
  $count = count($tokens);
  while ($i < $count && is_array($tokens[$i]) && in_array($tokens[$i][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ])) {
    ++$i;
  }
  return $i;
}

$singular = [];
$plural = [];
$skipped = [];

$iterator = new RecursiveIteratorIterator(
  new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
    function (SplFileInfo $file) use ($skipDirs): bool {
      return !($file->isDir() && in_array($file->getFilename(), $skipDirs, true));
    }
  )
);

foreach ($iterator as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'php') {
    continue;
  }
  $tokens = token_get_all(file_get_contents($file->getPathname()));
  $count = count($tokens);
  for ($i = 0; $i < $count; ++$i) {
    $token = $tokens[$i];
    if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], [ 't', 'n' ], true)) {
      continue;
    }
    // only method calls like $this->l->t( or $this->t(, not declarations
    $prev = $i - 1;
    while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
      --$prev;
    }
    if ($prev < 0 || !is_array($tokens[$prev])
        || !in_array($tokens[$prev][0], [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR ], true)) {
      continue;
    }
    $j = skipSpace($tokens, $i + 1);
    if (($tokens[$j] ?? null) !== '(') {
      continue;
    }
    $j = skipSpace($tokens, $j + 1);
    $line = $token[2];
    $first = readString($tokens, $j);
    if ($first === null) {
      $skipped[] = $file->getPathname() . ':' . $line;
      continue;
    }
    if ($token[1] === 't') {
      $singular[$first] = true;
      continue;
    }
    $j = skipSpace($tokens, $j);
    if (($tokens[$j] ?? null) !== ',') {
      $skipped[] = $file->getPathname() . ':' . $line;
      continue;
    }
    ++$j;
    $second = readString($tokens, $j);
    if ($second === null) {
      $skipped[] = $file->getPathname() . ':' . $line;
      continue;
    }
    $plural[$first . "\0" . $second] = [ $first, $second ];
  }
}

ksort($singular, SORT_STRING);
ksort($plural, SORT_STRING);

$content = <<<'EOF'
<?php

/*
 * GENERATED FILE, DO NOT EDIT. Re-generate by running the l10n target of
 * the nextcloud-app-toolkit, see tools/l10n.mk.
 *
 * This file is never executed. It only makes the translatable strings of
 * the toolkit visible to the Nextcloud translation tool, which does not
 * scan composer packages in vendor/.
 *
 * @var \OCP\IL10N $l
 */

EOF;
foreach (array_keys($singular) as $string) {
  $content .= '$l->t(' . var_export((string)$string, true) . ");\n";
}
foreach ($plural as [ $one, $many ]) {
  $content .= '$l->n(' . var_export($one, true) . ', ' . var_export($many, true) . ", 1);\n";
}

if (!is_dir(dirname($outputFile))) {
  mkdir(dirname($outputFile), 0777, true);
}
// avoid touching the file if nothing changed
if (!is_file($outputFile) || file_get_contents($outputFile) !== $content) {
  file_put_contents($outputFile, $content);
}

foreach ($skipped as $location) {
  fwrite(STDERR, 'Warning: non-literal translation string ignored at ' . $location . "\n");
}
fwrite(STDERR, count($singular) . ' strings and ' . count($plural) . " plural strings extracted.\n");
