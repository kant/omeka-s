<?php
namespace OmekaTest\Model;

use DateTime;
use Omeka\Model\Entity\User;
use Omeka\Test\TestCase;

class UserTest extends TestCase
{
    protected $user;

    public function setUp()
    {
        $this->user = new User;
    }

    public function testInitialState()
    {
        $this->assertNull($this->user->getId());
        $this->assertNull($this->user->getUsername());
        $this->assertNull($this->user->getName());
        $this->assertNull($this->user->getCreated());
        $this->assertNull($this->user->getEmail());
        $this->assertNull($this->user->getRole());
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getKeys()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getSites()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getVocabularies()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getResourceClasses()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getProperties()
        );
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->user->getPropertyAssignmentSets()
        );
    }

    public function testSetUsername()
    {
        $username = 'test-username';
        $this->user->setUsername($username);
        $this->assertEquals($username, $this->user->getUsername());
    }

    public function testSetName()
    {
        $name = 'test-name';
        $this->user->setName($name);
        $this->assertEquals($name, $this->user->getName());
    }

    public function testSetEmail()
    {
        $email = 'test-email';
        $this->user->setEmail($email);
        $this->assertEquals($email, $this->user->getEmail());
    }

    public function testSetRole()
    {
        $role = 'test-role';
        $this->user->setRole($role);
        $this->assertEquals($role, $this->user->getRole());
    }

    public function testSetCreated()
    {
        $dateTime = new DateTime;
        $this->user->setCreated($dateTime);
        $this->assertSame($dateTime, $this->user->getCreated());
    }

    public function testSetsCreatedOnPersist()
    {
        $this->user->prePersist();
        $this->assertInstanceOf('DateTime', $this->user->getCreated());
    }
}
