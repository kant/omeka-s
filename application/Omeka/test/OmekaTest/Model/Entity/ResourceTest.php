<?php
namespace OmekaTest\Model;

use DateTime;
use Omeka\Model\Entity\ResourceClass;
use Omeka\Model\Entity\User;
use Omeka\Model\Entity\Value;
use Omeka\Test\TestCase;

class ResourceTest extends TestCase
{
    protected $resource;

    public function setUp()
    {
        $this->resource = $this->getMockForAbstractClass('Omeka\Model\Entity\Resource');
    }

    public function testInitialState()
    {
        $this->assertNull($this->resource->getId());
        $this->assertNull($this->resource->getOwner());
        $this->assertNull($this->resource->getResourceClass());
        $this->assertNull($this->resource->getCreated());
        $this->assertNull($this->resource->getModified());
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->resource->getValues()
        );
    }

    public function testSetOwner()
    {
        $owner = new User;
        $this->resource->setOwner($owner);
        $this->assertSame($owner, $this->resource->getOwner());
    }

    public function testSetResourceClass()
    {
        $resourceClass = new ResourceClass;
        $this->resource->setResourceClass($resourceClass);
        $this->assertSame($resourceClass, $this->resource->getResourceClass());
    }

    public function testSetCreated()
    {
        $dateTime = new DateTime;
        $this->resource->setCreated($dateTime);
        $this->assertSame($dateTime, $this->resource->getCreated());
    }

    public function testSetModified()
    {
        $dateTime = new DateTime;
        $this->resource->setModified($dateTime);
        $this->assertSame($dateTime, $this->resource->getModified());
    }

    public function testAddValue()
    {
        $value = new Value;
        $this->resource->addValue($value);
        $this->assertSame($this->resource, $value->getResource());
        $this->assertTrue($this->resource->getValues()->contains($value));
    }

    public function testRemoveValue()
    {
        $value = new Value;
        $this->resource->addValue($value);
        $this->resource->removeValue($value);
        $this->assertFalse($this->resource->getValues()->contains($value));
        $this->assertNull($value->getValue());
    }

    public function testPrePersist()
    {
        $lifecycleEventArgs = $this->getMockBuilder('Doctrine\ORM\Event\LifecycleEventArgs')
            ->disableOriginalConstructor()
            ->getMock();
        $this->resource->prePersist($lifecycleEventArgs);
        $this->assertInstanceOf('DateTime', $this->resource->getCreated());
    }

    public function testPreUpdate()
    {
        $preUpdateEventArgs = $this->getMockBuilder('Doctrine\ORM\Event\PreUpdateEventArgs')
            ->disableOriginalConstructor()
            ->getMock();
        $this->resource->preUpdate($preUpdateEventArgs);
        $this->assertInstanceOf('DateTime', $this->resource->getModified());
    }
}
