<?php
/**
 * A collection of reusable traits classes for Nextcloud apps.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\RotDrop\Toolkit\Tests\Unit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OCP\Files\IFilenameValidator;
use OCP\IL10N;

use Psr\Container\ContainerInterface;

use OCA\RotDrop\Toolkit\Traits\SanitizeFilenameTrait;

#[Attributes\CoversTrait(SanitizeFilenameTrait::class)]
class SanitizeFilenameTraitTest extends TestCase
{
  public function testReplacesStrippedExtensionWhenBasenameContainsDots(): void
  {
    $filenameValidator = $this->createMock(IFilenameValidator::class);
    $filenameValidator->expects($this->once())
      ->method('sanitizeFilename')
      ->with('photo.2026.part')
      ->willReturn('photo.2026');
    $filenameValidator->expects($this->once())
      ->method('isFilenameValid')
      ->with('photo.2026.jpg')
      ->willReturn(true);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
      ->method('get')
      ->with(IFilenameValidator::class)
      ->willReturn($filenameValidator);

    $sanitizer = new class($container, $this->createStub(IL10N::class)) {
      use SanitizeFilenameTrait;

      public function __construct(ContainerInterface $container, IL10N $localization)
      {
        $this->appContainer = $container;
        $this->l = $localization;
      }

      public function sanitizeForTest(string $name, ?string $mimeType): string
      {
        return $this->sanitizeFilename($name, $mimeType);
      }
    };

    $this->assertSame('photo.2026.jpg', $sanitizer->sanitizeForTest('photo.2026.part', 'image/jpeg'));
  }
}
