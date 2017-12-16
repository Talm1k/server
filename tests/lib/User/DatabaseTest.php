<?php
/**
* ownCloud
*
* @author Robin Appelman
* @copyright 2012 Robin Appelman icewind@owncloud.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

namespace Test\User;
use OC\HintException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use OC\User\User;

/**
 * Class DatabaseTest
 *
 * @group DB
 */
class DatabaseTest extends Backend {
	/** @var array */
	private $users;
	/** @var  EventDispatcher | \PHPUnit_Framework_MockObject_MockObject */
	private $eventDispatcher;

	public function getUser() {
		$user = parent::getUser();
		$this->users[]=$user;
		return $user;
	}

	protected function setUp() {
		parent::setUp();

		$this->eventDispatcher = $this->createMock(EventDispatcher::class);

		$this->backend=new \OC\User\Database($this->eventDispatcher);
	}

	protected function tearDown() {
		if(!isset($this->users)) {
			return;
		}
		foreach($this->users as $user) {
			$this->backend->deleteUser($user);
		}
		parent::tearDown();
	}

	public function testVerifyPasswordEvent() {
		$user = $this->getUser();
		$this->backend->createUser($user, 'pass1');

		$this->eventDispatcher->expects($this->once())->method('dispatch')
			->willReturnCallback(
				function ($eventName, GenericEvent $event) {
					$this->assertSame('OCP\PasswordPolicy::validate',  $eventName);
					$this->assertSame('newpass', $event->getSubject());
				}
			);

		$this->backend->setPassword($user, 'newpass');
		$this->assertSame($user, $this->backend->checkPassword($user, 'newpass'));
	}

	/**
	 * @expectedException \OC\HintException
	 * @expectedExceptionMessage password change failed
	 */
	public function testVerifyPasswordEventFail() {
		$user = $this->getUser();
		$this->backend->createUser($user, 'pass1');

		$this->eventDispatcher->expects($this->once())->method('dispatch')
			->willReturnCallback(
				function ($eventName, GenericEvent $event) {
					$this->assertSame('OCP\PasswordPolicy::validate', $eventName);
					$this->assertSame('newpass', $event->getSubject());
					throw new HintException('password change failed', 'password change failed');
				}
			);

		$this->backend->setPassword($user, 'newpass');
		$this->assertSame($user, $this->backend->checkPassword($user, 'newpass'));
	}

	public function testCreateUserInvalidatesCache() {
		$user1 = $this->getUniqueID('test_');
		$this->assertFalse($this->backend->userExists($user1));
		$this->backend->createUser($user1, 'pw');
		$this->assertTrue($this->backend->userExists($user1));
	}

	public function testDeleteUserInvalidatesCache() {
		$user1 = $this->getUniqueID('test_');
		$this->backend->createUser($user1, 'pw');
		$this->assertTrue($this->backend->userExists($user1));
		$this->backend->deleteUser($user1);
		$this->assertFalse($this->backend->userExists($user1));
		$this->backend->createUser($user1, 'pw2');
		$this->assertTrue($this->backend->userExists($user1));
	}

	public function testSearch() {
		parent::testSearch();

		$user1 = $this->getUser();
		$this->backend->createUser($user1, 'pass1');

		$user2 = $this->getUser();
		$this->backend->createUser($user2, 'pass1');

		$user1Obj = new User($user1, $this->backend);
		$user2Obj = new User($user2, $this->backend);
		$emailAddr1 = "$user1@nextcloud.com";
		$emailAddr2 = "$user2@nextcloud.com";

		$user1Obj->setEMailAddress($emailAddr1);
		$user2Obj->setEMailAddress($emailAddr2);

		$result = $this->backend->getUsers('@nextcloud.com');
		$this->assertSame(2, count($result));

		$result = $this->backend->getDisplayNames('@nextcloud.com');
		$this->assertSame(2, count($result));
	}
}
