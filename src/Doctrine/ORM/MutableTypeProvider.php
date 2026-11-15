<?php
/**
 * Some PHP utility functions for Nextcloud apps.
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

namespace OCA\RotDrop\Toolkit\Doctrine\ORM;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use IteratorAggregate;
use Traversable;

/**
 * Adds or replaces types without requiring the underlying provider to be mutable.
 *
 * @implements IteratorAggregate<string, Type>
 */
class MutableTypeProvider implements TypeProvider, IteratorAggregate
{
  /** @var array<string, Type> */
  private array $types = [];

  public function __construct(private TypeProvider $provider)
  {
  }

  public function set(string $name, Type $type): void
  {
    $this->types[$name] = $type;
  }

  public function has(string $name): bool
  {
    return isset($this->types[$name]) || $this->provider->has($name);
  }

  public function get(string $name): Type
  {
    return $this->types[$name] ?? $this->provider->get($name);
  }

  public function getIterator(): Traversable
  {
    foreach ($this->provider as $name => $type) {
      yield $name => $this->types[$name] ?? $type;
    }
    foreach ($this->types as $name => $type) {
      if (!$this->provider->has($name)) {
        yield $name => $type;
      }
    }
  }
}
