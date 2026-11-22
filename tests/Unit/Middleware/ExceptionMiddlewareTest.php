<?php
/**
 * @license AGPL-3.0-or-later
 */

namespace OCA\RotDrop\Toolkit\Tests\Unit\Middleware;

use ReflectionProperty;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\IRequest;

use OCA\RotDrop\Toolkit\AppInfo\AbstractApplication;
use OCA\RotDrop\Toolkit\Middleware\ExceptionMiddleware;

#[Attributes\CoversClass(AbstractApplication::class)]
#[Attributes\CoversClass(ExceptionMiddleware::class)]
class ExceptionMiddlewareTest extends TestCase
{
  public function testMiddlewareOptionsUsePublicConsistentKeys(): void
  {
    $this->assertSame('middlewareOptions', AbstractApplication::MIDDLEWARE_OPTIONS);
    $this->assertSame('catchAll', ExceptionMiddleware::CATCH_ALL);
    $this->assertSame('httpStatusCodeMapping', ExceptionMiddleware::HTTP_STATUS_CODE_MAPPING);
    $this->assertSame(
      Http::STATUS_BAD_REQUEST,
      ExceptionMiddleware::DEFAULT_HTTP_STATUS_CODE_MAPPING[\InvalidArgumentException::class],
    );

    $middleware = new ExceptionMiddleware(
      $this->createStub(ContainerInterface::class),
      $this->createStub(IControllerMethodReflector::class),
      $this->createStub(IL10N::class),
      $this->createStub(IRequest::class),
      $this->createStub(LoggerInterface::class),
      [
        ExceptionMiddleware::class => [
          ExceptionMiddleware::CATCH_ALL => true,
          ExceptionMiddleware::HTTP_STATUS_CODE_MAPPING => [
            \InvalidArgumentException::class => Http::STATUS_CONFLICT,
          ],
        ],
      ],
    );

    $this->assertTrue(
      (new ReflectionProperty(ExceptionMiddleware::class, 'catchAll'))->getValue($middleware),
    );
    $httpStatusCodeMapping = (new ReflectionProperty(ExceptionMiddleware::class, 'httpStatusCodeMapping'))
      ->getValue($middleware);
    $this->assertSame(Http::STATUS_CONFLICT, $httpStatusCodeMapping[\InvalidArgumentException::class]);
    $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $httpStatusCodeMapping['default']);
  }
}
