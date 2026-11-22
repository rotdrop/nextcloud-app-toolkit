<?php
/**
 * @license AGPL-3.0-or-later
 */

namespace OCA\RotDrop\Toolkit\Tests\Unit\Service;

use RuntimeException;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUser;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\RotDrop\Toolkit\Service\GroupFoldersService;

require_once __DIR__ . '/../../Fixtures/GroupFolders.php';

#[Attributes\CoversClass(GroupFoldersService::class)]
class GroupFoldersServiceTest extends TestCase
{
  private FolderManager&MockObject $folderManager;
  private GroupFoldersService $service;

  protected function setUp():void
  {
    $this->folderManager = $this->createMock(FolderManager::class);
    $this->folderManager->expects($this->never())->method('getAllFoldersWithSize');
    $rootFolder = $this->createMock(IRootFolder::class);
    $rootFolder->expects($this->never())->method($this->anything());
    $this->service = new GroupFoldersService(
      $this->createStub(LoggerInterface::class),
      $rootFolder,
      $this->folderManager,
      new FolderStorageManager(),
      $this->createStub(IL10N::class),
    );
  }

  public function testReturnsAssignedFolderMetadataWithoutFilesystemAccess():void
  {
    $user = $this->createStub(IUser::class);
    $folder = new FolderDefinitionWithPermissions(42, 'Shared', Constants::PERMISSION_READ);
    $this->folderManager->expects($this->once())
      ->method('getFoldersForUser')
      ->with($this->identicalTo($user))
      ->willReturn([
        new FolderDefinitionWithPermissions(7, 'Other', Constants::PERMISSION_ALL),
        $folder,
      ]);

    $this->assertSame($folder, $this->service->getFolderForUser('Shared', $user));
    $this->assertSame(42, $folder->id);
    $this->assertSame(Constants::PERMISSION_READ, $folder->permissions);
  }

  public function testReturnsNullForMissingOrUnassignedFolder():void
  {
    $user = $this->createStub(IUser::class);
    $this->folderManager->expects($this->once())
      ->method('getFoldersForUser')
      ->with($this->identicalTo($user))
      ->willReturn([]);

    $this->assertNull($this->service->getFolderForUser('Shared', $user));
  }

  #[Attributes\DataProvider('nonMatchingMountPoints')]
  public function testMatchesOnlyTheExactConfiguredMountPoint(string $mountPoint):void
  {
    $user = $this->createStub(IUser::class);
    $this->folderManager->expects($this->once())
      ->method('getFoldersForUser')
      ->with($this->identicalTo($user))
      ->willReturn([new FolderDefinitionWithPermissions(42, 'Shared', Constants::PERMISSION_ALL)]);

    $this->assertNull($this->service->getFolderForUser($mountPoint, $user));
  }

  public static function nonMatchingMountPoints():array
  {
    return [
      'different folder' => ['Missing'],
      'case differs' => ['shared'],
      'user path' => ['/Shared'],
      'subfolder' => ['Shared/projects'],
    ];
  }

  public function testAssignmentsAreQueriedForEachUserAndNotCached():void
  {
    $assignedUser = $this->createStub(IUser::class);
    $otherUser = $this->createStub(IUser::class);
    $folder = new FolderDefinitionWithPermissions(42, 'Shared', Constants::PERMISSION_ALL);
    $this->folderManager->expects($this->exactly(3))
      ->method('getFoldersForUser')
      ->willReturnCallback(function(IUser $user) use ($assignedUser, $folder) {
        return $user === $assignedUser ? [$folder] : [];
      });

    $this->assertSame($folder, $this->service->getFolderForUser('Shared', $assignedUser));
    $this->assertNull($this->service->getFolderForUser('Shared', $otherUser));
    $this->assertSame($folder, $this->service->getFolderForUser('Shared', $assignedUser));
  }

  public function testMetadataErrorsAreNotReportedAsMissingFolders():void
  {
    $user = $this->createStub(IUser::class);
    $error = new RuntimeException('Metadata query failed');
    $this->folderManager->expects($this->once())
      ->method('getFoldersForUser')
      ->with($this->identicalTo($user))
      ->willThrowException($error);

    $this->expectExceptionObject($error);
    $this->service->getFolderForUser('Shared', $user);
  }
}
