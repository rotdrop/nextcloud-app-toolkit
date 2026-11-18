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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\RotDrop\Toolkit\Tests\Unit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OCP\Console\IInput;
use OCP\Console\IOutput;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use OCA\RotDrop\Toolkit\Traits\AuthenticatedCommandTrait;

#[Attributes\CoversTrait(AuthenticatedCommandTrait::class)]
class AuthenticatedCommandTraitTest extends TestCase
{
  public function testAuthenticateWithNextcloudInputWithoutGetHelper(): void
  {
    $localization = $this->createLocalization();
    $userManager = $this->createMock(IUserManager::class);
    $userSession = $this->createMock(IUserSession::class);
    $user = $this->createMock(IUser::class);

    $userManager->expects($this->once())->method('get')->with('alice')->willReturn($user);
    $userSession->expects($this->once())->method('setUser')->with($user);
    $userSession->expects($this->once())->method('login')->with('alice', 'secret')->willReturn(true);

    $input = $this->createMock(IInput::class);
    $input->expects($this->once())->method('ask')->with('User')->willReturn('alice');
    $input->expects($this->once())->method('askHidden')->with('Password')->willReturn('secret');
    $output = $this->createMock(IOutput::class);
    $output->expects($this->once())->method('writeln')->with('Login succeeded.');

    $command = new class($localization, $userManager, $userSession) {
      use AuthenticatedCommandTrait;

      public function __construct(IL10N $localization, IUserManager $userManager, IUserSession $userSession)
      {
        $this->l = $localization;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
      }

      public function authenticateForTest(IInput $input, IOutput $output): int
      {
        return $this->authenticate($input, $output);
      }
    };

    $this->assertFalse(method_exists($command, 'getHelper'));
    $this->assertSame(0, $command->authenticateForTest($input, $output));
  }

  public function testAuthenticateWithLegacySymfonyInput(): void
  {
    $localization = $this->createLocalization();
    $userManager = $this->createMock(IUserManager::class);
    $userSession = $this->createMock(IUserSession::class);
    $user = $this->createMock(IUser::class);

    $userManager->expects($this->once())->method('get')->with('alice')->willReturn($user);
    $userSession->expects($this->once())->method('setUser')->with($user);
    $userSession->expects($this->once())->method('login')->with('alice', 'secret')->willReturn(true);

    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);
    $output->expects($this->once())->method('writeln')->with('Login succeeded.');
    $questions = [];
    $helper = $this->createMock(QuestionHelper::class);
    $helper->expects($this->exactly(2))->method('ask')->willReturnCallback(
      function (InputInterface $input, OutputInterface $output, Question $question) use (&$questions): string {
        $questions[] = $question;
        return count($questions) === 1 ? 'alice' : 'secret';
      },
    );

    $command = new class($localization, $userManager, $userSession) extends Command {
      use AuthenticatedCommandTrait;

      public function __construct(IL10N $localization, IUserManager $userManager, IUserSession $userSession)
      {
        parent::__construct();
        $this->l = $localization;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
      }

      public function authenticateForTest(InputInterface $input, OutputInterface $output): int
      {
        return $this->authenticate($input, $output);
      }
    };
    $command->setHelperSet(new HelperSet([ 'question' => $helper ]));

    $this->assertSame(0, $command->authenticateForTest($input, $output));
    $this->assertSame('User: ', $questions[0]->getQuestion());
    $this->assertSame('Password: ', $questions[1]->getQuestion());
    $this->assertTrue($questions[1]->isHidden());
  }

  public function testMissingNextcloudInputAnswerDoesNotChangeUserSession(): void
  {
    $localization = $this->createLocalization();
    $userManager = $this->createMock(IUserManager::class);
    $userManager->expects($this->never())->method('get');
    $userSession = $this->createMock(IUserSession::class);
    $userSession->expects($this->never())->method('setUser');
    $userSession->expects($this->never())->method('login');

    $input = $this->createMock(IInput::class);
    $input->expects($this->once())->method('ask')->with('User')->willReturn(null);
    $input->expects($this->once())->method('askHidden')->with('Password')->willReturn('secret');
    $output = $this->createMock(IOutput::class);
    $output->expects($this->once())->method('writeln')->with('Login failed.');

    $command = new class($localization, $userManager, $userSession) {
      use AuthenticatedCommandTrait;

      public function __construct(IL10N $localization, IUserManager $userManager, IUserSession $userSession)
      {
        $this->l = $localization;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
      }

      public function authenticateForTest(IInput $input, IOutput $output): int
      {
        return $this->authenticate($input, $output);
      }
    };

    $this->assertSame(1, $command->authenticateForTest($input, $output));
  }

  private function createLocalization(): IL10N
  {
    $localization = $this->createMock(IL10N::class);
    $localization->method('t')->willReturnCallback(fn(string $text): string => $text);
    return $localization;
  }
}
