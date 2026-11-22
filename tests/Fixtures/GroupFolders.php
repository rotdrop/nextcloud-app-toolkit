<?php
/**
 * Minimal metadata API doubles for the optional Groupfolders app.
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use LogicException;
use OCP\IUser;

class FolderDefinitionWithPermissions
{
  public function __construct(
    public readonly int $id,
    public readonly string $mountPoint,
    public readonly int $permissions,
  ) {
  }
}

class FolderManager
{
  /** @return list<FolderDefinitionWithPermissions> */
  public function getFoldersForUser(IUser $user, ?int $folderId = null, ?array $paths = null):array
  {
    throw new LogicException('This method must be mocked.');
  }

  /** @return array */
  public function getAllFoldersWithSize():array
  {
    throw new LogicException('This method must not be used for per-user discovery.');
  }
}

namespace OCA\GroupFolders\Mount;

class FolderStorageManager
{
}
