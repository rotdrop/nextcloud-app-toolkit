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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\RotDrop\Toolkit\Tests\Unit\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManagerInterface;
use IteratorAggregate;
use OCA\RotDrop\Toolkit\Doctrine\ORM\AbstractEntityManager;
use OCA\RotDrop\Toolkit\Doctrine\ORM\MutableTypeProvider;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use Traversable;

interface EntityManagerStub
{
  public function getConnection();
}

class EntityManagerDecoratorStub
{
  public function __construct(protected EntityManagerInterface $wrapped)
  {
  }
}

if (!interface_exists(EntityManagerInterface::class)) {
  class_alias(EntityManagerStub::class, EntityManagerInterface::class);
}
if (!class_exists(\Doctrine\ORM\Decorator\EntityManagerDecorator::class)) {
  class_alias(EntityManagerDecoratorStub::class, \Doctrine\ORM\Decorator\EntityManagerDecorator::class);
}

class RegisteredType extends Type
{
  public function getName(): string
  {
    return 'toolkit_registration_test';
  }

  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
  {
    return 'TEXT';
  }

  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
  {
    return 'registered:' . $value;
  }
}

class PreviousType extends RegisteredType
{
}

class DefaultMappingType extends RegisteredType
{
  public function getName(): string
  {
    return 'toolkit_default_mapping_test';
  }
}

class TestEntityManager extends AbstractEntityManager
{
  protected const DBAL_TYPES = [
    RegisteredType::class => 'toolkit_sql_test',
    DefaultMappingType::class => null,
  ];

  public function getEntityNamespace(): string
  {
    return '';
  }

  public function initializeTypes(): void
  {
    $this->registerTypes();
  }
}

#[Attributes\CoversClass(AbstractEntityManager::class)]
#[Attributes\CoversClass(MutableTypeProvider::class)]
#[Attributes\RunTestsInSeparateProcesses]
class AbstractEntityManagerTest extends TestCase
{
  private function initialize($connection): void
  {
    $wrapped = $this->createMock(EntityManagerInterface::class);
    $wrapped->method('getConnection')->willReturn($connection);
    $manager = new TestEntityManager($wrapped);

    Deprecation::enableWithTriggerError();
    Deprecation::withoutDeduplication();
    set_error_handler(static function (int $severity, string $message): void {
      if ($severity === E_USER_DEPRECATED || $severity === E_DEPRECATED) {
        throw new \ErrorException($message, 0, $severity);
      }
    });
    try {
      $manager->initializeTypes();
      $manager->initializeTypes();
    } finally {
      restore_error_handler();
      Deprecation::disable();
    }
  }

  public function testRegistrationReplacementAndMappings(): void
  {
    $configuration = new Configuration();
    $modern = method_exists($configuration, 'getTypeProvider');
    if ($modern) {
      $registry = new TypeRegistry();
      $registry->register('toolkit_registration_test', new PreviousType());
      $registry->register('toolkit_preserved_test', new PreviousType());
      $configuration->setTypeProvider($registry);
    } else {
      if (Type::hasType('toolkit_registration_test')) {
        Type::overrideType('toolkit_registration_test', PreviousType::class);
      } else {
        Type::addType('toolkit_registration_test', PreviousType::class);
      }
    }

    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $platform = $connection->getDatabasePlatform();
    $this->initialize($connection);

    $this->assertSame('registered:value', $connection->convertToPHPValue('value', 'toolkit_registration_test'));
    $this->assertSame('toolkit_registration_test', $platform->getDoctrineTypeMapping('toolkit_sql_test'));
    $this->assertSame('toolkit_default_mapping_test', $platform->getDoctrineTypeMapping('toolkit_default_mapping_test'));
    if ($modern) {
      $this->assertSame($registry, $configuration->getTypeProvider());
      $this->assertInstanceOf(RegisteredType::class, $registry->get('toolkit_registration_test'));
      $this->assertSame(RegisteredType::class, $registry->get('toolkit_registration_test')::class);
      $this->assertInstanceOf(PreviousType::class, $registry->get('toolkit_preserved_test'));
    } else {
      $this->assertSame(RegisteredType::class, Type::getType('toolkit_registration_test')::class);
    }
  }

  public function testReadOnlyProviderAndCachedPlatform(): void
  {
    $configuration = new Configuration();
    if (!method_exists($configuration, 'getTypeProvider')) {
      $this->markTestSkipped('DBAL does not support connection type providers.');
    }

    $registry = new TypeRegistry();
    $registry->register('toolkit_registration_test', new PreviousType());
    $preserved = new PreviousType();
    $registry->register('toolkit_preserved_test', $preserved);
    $custom = new class ($registry) implements TypeProvider, IteratorAggregate {
      public function __construct(private TypeRegistry $registry)
      {
      }
      public function has(string $name): bool
      {
        return $this->registry->has($name);
      }
      public function get(string $name): Type
      {
        return $this->registry->get($name);
      }
      public function getIterator(): Traversable
      {
        return $this->registry->getIterator();
      }
    };
    $configuration->setTypeProvider($custom);
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $platform = $connection->getDatabasePlatform();

    $this->initialize($connection);
    $provider = $configuration->getTypeProvider();
    $this->assertInstanceOf(MutableTypeProvider::class, $provider);
    $this->assertSame($preserved, $provider->get('toolkit_preserved_test'));
    $this->assertInstanceOf(PreviousType::class, $custom->get('toolkit_registration_test'));
    $this->assertSame(RegisteredType::class, $provider->get('toolkit_registration_test')::class);
    $this->assertSame('registered:value', $connection->convertToPHPValue('value', 'toolkit_registration_test'));
    $this->assertSame('toolkit_registration_test', $platform->getDoctrineTypeMapping('toolkit_sql_test'));
    $types = iterator_to_array($provider);
    $this->assertSame($preserved, $types['toolkit_preserved_test']);
    $this->assertSame(RegisteredType::class, $types['toolkit_registration_test']::class);
    $this->assertArrayHasKey('toolkit_default_mapping_test', $types);
  }
}
