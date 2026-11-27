<?php

namespace OCA\RotDrop\Toolkit\Tests\Unit\Doctrine\ORM\EntitySerializer;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\Persistence\Proxy;
use JsonSerializable;
use OCA\RotDrop\Toolkit\Doctrine\ORM\AbstractEntityManager;
use OCA\RotDrop\Toolkit\Doctrine\ORM\EntitySerializer\EntityArrayAdapter;
use OCA\RotDrop\Toolkit\Doctrine\ORM\EntitySerializer\EntityReference;
use OCA\RotDrop\Toolkit\Doctrine\ORM\EntitySerializer\EntityReferenceCollection;
use OCA\RotDrop\Toolkit\Doctrine\ORM\EntitySerializer\EntitySerializer;
use OCP\IL10N;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class SerializerMember
{
  public int $id = 1;
  public string $name = 'Member';
  public ?SerializerFile $file = null;
  public array $files = [];
}

class SerializerFile implements JsonSerializable
{
  public function __construct(public int $id = 2)
  {
  }

  public string $name = 'File';
  public ?SerializerFileData $data = null;
  public array $dataCollection = [];

  public function jsonSerialize(): array
  {
    return ['downloadLink' => '/download/' . $this->id];
  }
}

class SerializerEncryptedFile extends SerializerFile
{
}

class SerializerFileProxy extends SerializerEncryptedFile implements Proxy
{
  public function __load(): void
  {
    throw new RuntimeException('Excluded proxy must not be loaded.');
  }

  public function __isInitialized(): bool
  {
    return false;
  }
}

class SerializerFileData
{
  public int $id = 4;
  public string $name = 'Blob';
}

#[Attributes\CoversClass(EntitySerializer::class)]
#[Attributes\CoversClass(EntityArrayAdapter::class)]
class EntitySerializerTest extends TestCase
{
  private function metadata(string $className): ClassMetadata
  {
    $metadata = new ClassMetadata($className);
    $reflection = new RuntimeReflectionService();
    $metadata->initializeReflection($reflection);
    $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
    $metadata->mapField(['fieldName' => 'name', 'type' => 'string']);
    if ($className === SerializerMember::class) {
      $metadata->mapManyToOne(['fieldName' => 'file', 'targetEntity' => SerializerFile::class]);
      $metadata->mapOneToMany([
        'fieldName' => 'files',
        'targetEntity' => SerializerFile::class,
        'mappedBy' => 'member',
      ]);
    } elseif (is_a($className, SerializerFile::class, true)) {
      $metadata->mapManyToOne(['fieldName' => 'data', 'targetEntity' => SerializerFileData::class]);
      $metadata->mapOneToMany([
        'fieldName' => 'dataCollection',
        'targetEntity' => SerializerFileData::class,
        'mappedBy' => 'file',
      ]);
    }
    $metadata->wakeupReflection($reflection);
    return $metadata;
  }

  private function serializer(array $initializedEntities): EntitySerializer
  {
    $manager = $this->createMock(AbstractEntityManager::class);
    $manager->method('getUnitOfWork')->willReturn($this->createStub(UnitOfWork::class));
    $manager->method('getMetadataFactory')->willReturn($this->createStub(ClassMetadataFactory::class));
    $manager->method('getClassMetadata')->willReturnCallback(function (string $className): ClassMetadata {
      return $this->metadata($className === SerializerFileProxy::class ? SerializerEncryptedFile::class : $className);
    });
    $manager->expects($this->exactly(count($initializedEntities)))
      ->method('initializeObject')
      ->willReturnCallback(function (object $entity) use ($initializedEntities): void {
        $this->assertContains($entity, $initializedEntities);
      });

    return (new EntitySerializer($manager, $this->createStub(IL10N::class), new NullLogger()))
      ->setCommonPrefix(__NAMESPACE__);
  }

  private function member(): SerializerMember
  {
    $member = new SerializerMember();
    $member->file = new SerializerFile();
    $member->files = [
      'base' => $member->file,
      'derived' => new SerializerEncryptedFile(3),
    ];
    $member->file->data = new SerializerFileData();
    $member->file->dataCollection = [$member->file->data];
    return $member;
  }

  public function testExcludedBaseClassStopsBothAssociationKindsAndSubclasses(): void
  {
    $member = $this->member();
    $serializer = $this->serializer([$member])->setExcludedClasses([SerializerFile::class]);
    $serializer->addEntity($member, depth: 10);
    $response = $serializer->export();

    $this->assertSame(['SerializerMember' => ['1']], $response->entities);
    $this->assertSame(['SerializerMember'], array_keys($response->repositories));
    $flatMember = $response->repositories['SerializerMember'][1];
    $this->assertEquals(new EntityReference('2', 'SerializerFile'), $flatMember['file']);
    $this->assertInstanceOf(EntityReferenceCollection::class, $flatMember['files']);
    $this->assertSame('SerializerFile', $flatMember['files']->entityClassName);
    $this->assertSame(['base', 'derived'], array_keys($flatMember['files']->entities));
    $this->assertSame('2', $flatMember['files']->entities['base']->flatIdentifier);
    $this->assertNull($flatMember['files']->entities['base']->entityClassName);
    $this->assertSame('3', $flatMember['files']->entities['derived']->flatIdentifier);
    $this->assertSame('SerializerEncryptedFile', $flatMember['files']->entities['derived']->entityClassName);
  }

  public function testExcludedProxyIsNotInitialized(): void
  {
    $member = new SerializerMember();
    $member->file = new SerializerFileProxy();
    $member->files = [$member->file];
    $serializer = $this->serializer([$member])->setExcludedClasses([SerializerFile::class]);
    $serializer->addEntity($member, depth: 10);

    $response = $serializer->export();
    $this->assertSame(['SerializerMember'], array_keys($response->repositories));
    $this->assertEquals(
      new EntityReference('2', 'SerializerEncryptedFile'),
      $response->repositories['SerializerMember'][1]['file'],
    );
  }

  public function testExclusionsSurviveResetAndCanBeCleared(): void
  {
    $member = $this->member();
    $serializer = $this->serializer([$member, $member, $member, $member->file, $member->files['derived']]);
    $serializer->setExcludedClasses([SerializerFile::class]);
    $serializer->addEntity($member, depth: 1);
    $serializer->reset();
    $this->assertSame([], $serializer->export()->repositories);
    $serializer->setCommonPrefix(__NAMESPACE__);
    $serializer->addEntity($member, depth: 1);
    $this->assertSame(['SerializerMember'], array_keys($serializer->export()->repositories));

    $serializer->reset();
    $serializer->setCommonPrefix(__NAMESPACE__)->setExcludedClasses([]);
    $serializer->addEntity($member, depth: 1);
    $repositories = $serializer->export()->repositories;
    $this->assertSame('/download/2', $repositories['SerializerFile'][2]['downloadLink']);
    $this->assertSame('/download/3', $repositories['SerializerEncryptedFile'][3]['downloadLink']);
    $this->assertArrayNotHasKey('SerializerFileData', $repositories);
  }

  public function testExplicitRootRemainsSerializableWhenExcluded(): void
  {
    $file = new SerializerFile();
    $serializer = $this->serializer([$file])->setExcludedClasses([SerializerFile::class]);
    $serializer->addEntity($file, depth: 0);
    $response = $serializer->export();

    $this->assertSame(['SerializerFile' => ['2']], $response->entities);
    $this->assertSame('/download/2', $response->repositories['SerializerFile'][2]['downloadLink']);
  }

  public function testAdapterRetainsExcludedReferencesAtPositiveDepth(): void
  {
    $member = $this->member();
    $serializer = $this->serializer([$member])->setExcludedClasses([SerializerFile::class]);
    $adapter = EntityArrayAdapter::create($member, depth: 10, entitySerializer: $serializer);

    $this->assertEquals(new EntityReference('2', 'SerializerFile'), $adapter['file']);
    $this->assertInstanceOf(EntityReferenceCollection::class, $adapter['files']);
    $json = json_decode(json_encode($adapter, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame('2', $json['file']['flatIdentifier']);
    $this->assertSame('SerializerEncryptedFile', $json['files']['entities']['derived']['entityClassName']);
    $this->assertArrayNotHasKey('data', $json['file']);
  }

  public function testAdapterKeepsMixedCollectionReferencesAndExpandsFetchedToOne(): void
  {
    $member = $this->member();
    $serializer = $this->serializer([$member, $member->file])
      ->setExcludedClasses([SerializerEncryptedFile::class]);
    $adapter = EntityArrayAdapter::create($member, depth: 1, entitySerializer: $serializer);

    $this->assertInstanceOf(EntityArrayAdapter::class, $adapter['file']);
    $this->assertSame('/download/2', $adapter['file']['downloadLink']);
    $this->assertInstanceOf(EntityReferenceCollection::class, $adapter['files']);
    $json = json_decode(json_encode($adapter, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame('3', $json['files']['entities']['derived']['flatIdentifier']);
  }

  public function testAdapterWithoutExclusionsPreservesDepthBehavior(): void
  {
    $member = $this->member();
    $serializer = $this->serializer([$member, $member, $member->file, $member->files['derived']]);
    $adapter = EntityArrayAdapter::create($member, depth: 0, entitySerializer: $serializer);

    $this->assertInstanceOf(EntityReference::class, $adapter['file']);
    $this->assertInstanceOf(EntityReferenceCollection::class, $adapter['files']);
    $adapter->setDepth(1);
    $this->assertInstanceOf(EntityArrayAdapter::class, $adapter['file']);
    $this->assertIsArray($adapter['files']);
    $this->assertSame('/download/2', $adapter['files']['base']['downloadLink']);
    $this->assertSame('/download/3', $adapter['files']['derived']['downloadLink']);
    $this->assertInstanceOf(EntityReference::class, $adapter['file']['data']);
    $this->assertInstanceOf(EntityReferenceCollection::class, $adapter['file']['dataCollection']);
  }
}
