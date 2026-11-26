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

namespace OCA\RotDrop\Toolkit\Traits;

use Throwable;

use OCP\IL10N;
use OCP\Files\IFilenameValidator;
use Psr\Container\ContainerInterface;

use OCA\RotDrop\Toolkit\Exceptions;

/**
 * Cloned from OCP\Files\Command\SanitzeFilenames and changed a bit.
 */
trait SanitizeFilenameTrait
{
  protected ContainerInterface $appContainer;

  protected IL10N $l;

  /**
   * Remove "forbidden" characters as configured in order to achieve a
   * filename which is also valid on certain strange operating systems.
   *
   * @param string $name
   *
   * @param null|string $mimeType
   *
   * @return string
   *
   * @throws Exceptions\EnduserNotificationException
   */
  protected function sanitizeFilename(string $name, ?string $mimeType = null): string
  {
    if ($name === '') {
      return $name; // root folder
    }
    $oldName = $name;
    try {
      /** @var IFilenameValidator $filenameValidator */
      $filenameValidator = $this->appContainer->get(IFilenameValidator::class);
      $name = $filenameValidator->sanitizeFilename($name);
      // A forbidden extension (e.g. ".part") has been stripped, try to
      // replace it by one derived from the MIME-type.
      if (pathinfo($oldName, PATHINFO_EXTENSION) !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
        $extension = $this->fileExtensionFromMimeType($mimeType);
        if ($extension && $filenameValidator->isFilenameValid($name . '.' . $extension)) {
          $name .= '.' . $extension;
        }
      }
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to sanitize filename "%s".', $oldName),
        previous: $t,
      );
    }
    return $name;
  }

  protected const FILE_EXTENSIONS_BY_MIME_TYPE = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/vnd.microsoft.icon' => 'ico',
    'image/svg+xml' => 'svg',
    'application/pdf' => 'pdf',
  ];

  /**
   * @param null|string $mimeType
   *
   * @return null|string
   */
  protected function fileExtensionFromMimeType(?string $mimeType):?string
  {
    if (!$mimeType) {
      return null;
    }
    if (!empty(self::FILE_EXTENSIONS_BY_MIME_TYPE[$mimeType])) {
      return self::FILE_EXTENSIONS_BY_MIME_TYPE[$mimeType];
    }
    // as a wild guess we return anything after the slash if it is at
    // most 4 characters.
    list(/* $first*/, $second) = explode('/', $mimeType);
    if (strlen($second) <= 4) {
      return $second;
    }
    return null;
  }
}
