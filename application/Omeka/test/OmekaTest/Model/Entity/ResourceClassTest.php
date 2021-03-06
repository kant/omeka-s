<?php
namespace OmekaTest\Model;

use Omeka\Model\Entity\PropertyAssignmentSet;
use Omeka\Model\Entity\ResourceClass;
use Omeka\Model\Entity\User;
use Omeka\Model\Entity\Vocabulary;
use Omeka\Test\TestCase;

class ResourceClassTest extends TestCase
{
    protected $resourceClass;

    public function setUp()
    {
        $this->resourceClass = new ResourceClass;
    }

    public function testInitialState()
    {
        $this->assertNull($this->resourceClass->getId());
        $this->assertNull($this->resourceClass->getOwner());
        $this->assertNull($this->resourceClass->getVocabulary());
        $this->assertNull($this->resourceClass->getLocalName());
        $this->assertNull($this->resourceClass->getLabel());
        $this->assertNull($this->resourceClass->getComment());
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->resourceClass->getPropertyAssignmentSets()
        );
    }

    public function testSetOwner()
    {
        $owner = new User;
        $this->resourceClass->setOwner($owner);
        $this->assertSame($owner, $this->resourceClass->getOwner());
        $this->assertTrue($owner->getResourceClasses()->contains($this->resourceClass));
    }

    public function testSetVocabulary()
    {
        $vocabulary = new Vocabulary;
        $this->resourceClass->setVocabulary($vocabulary);
        $this->assertSame($vocabulary, $this->resourceClass->getVocabulary());
        $this->assertTrue($vocabulary->getResourceClasses()->contains($this->resourceClass));
    }

    public function testSetLocalName()
    {
        $localName = 'test-localName';
        $this->resourceClass->setLocalName($localName);
        $this->assertEquals($localName, $this->resourceClass->getLocalName());
    }

    public function testSetLabel()
    {
        $label = 'test-label';
        $this->resourceClass->setLabel($label);
        $this->assertEquals($label, $this->resourceClass->getLabel());
    }

    public function testSetComment()
    {
        $comment = 'test-comment';
        $this->resourceClass->setComment($comment);
        $this->assertEquals($comment, $this->resourceClass->getComment());
    }

    public function testAddPropertyAssignmentSet()
    {
        $propertyAssignmentSet = new PropertyAssignmentSet;
        $this->resourceClass->addPropertyAssignmentSet($propertyAssignmentSet);
        $this->assertSame($this->resourceClass, $propertyAssignmentSet->getResourceClass());
        $this->assertTrue($this->resourceClass->getPropertyAssignmentSets()->contains($propertyAssignmentSet));
    }

    public function testRemovePropertyAssignmentSet()
    {
        $propertyAssignmentSet = new PropertyAssignmentSet;
        $this->resourceClass->addPropertyAssignmentSet($propertyAssignmentSet);
        $this->resourceClass->removePropertyAssignmentSet($propertyAssignmentSet);
        $this->assertFalse($this->resourceClass->getPropertyAssignmentSets()->contains($propertyAssignmentSet));
        $this->assertNull($propertyAssignmentSet->getResourceClass());
    }
}
