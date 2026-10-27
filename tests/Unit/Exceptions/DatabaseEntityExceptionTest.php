<?php
/**
 * A collection of reusable traits classes for Nextcloud apps.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\RotDrop\Toolkit\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\RotDrop\Toolkit\Exceptions;

/**
 * Test the Exceptions\DatabaseEntityException class and some of its child
 * classes.
 */
#[Attributes\CoversClass(Exceptions\DatabaseEntityException::class)]
#[Attributes\CoversClass(Exceptions\DatabaseEntityNotFoundException::class)]
#[Attributes\CoversClass(Exceptions\DatabaseMissingIdentifierException::class)]
class DatabaseEntityExceptionTest extends TestCase
{
  private const MESSAGE = 'MESSAGE';
  private const CODE = 666;
  private const PREVIOUS = null;
  private const ENTITY_NAME = 'ENTITY';
  private const IDENTIFIER = [ 'id' => -1 ];
  private const FIELD = 'FIELD';
  private const EXPECTED = 'EXPECTED';
  private const ACTUAL = 'ACTUAL';

  /** @return void */
  public function testDatabaseEntityException(): void
  {
    try {
      throw new Exceptions\DatabaseEntityException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
    }
  }

  /** @return void */
  public function testDatabaseEntityNotFoundException(): void
  {
    try {
      throw new Exceptions\DatabaseEntityNotFoundException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        identifier: self::IDENTIFIER,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::IDENTIFIER, $e->identifier);
    }
  }

  /** @return void */
  public function testDatabaseMissingIdentifierException(): void
  {
    try {
      throw new Exceptions\DatabaseMissingIdentifierException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        entityClassName: self::ENTITY_NAME,
        incompleteIdentifier: self::IDENTIFIER,
      );
    } catch (Exceptions\DatabaseEntityException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::ENTITY_NAME, $e->entityClassName);
      $this->assertEquals(self::IDENTIFIER, $e->incompleteIdentifier);
    }
  }
}
