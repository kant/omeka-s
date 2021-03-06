<?php
namespace OmekaTest\Model;

use Omeka\Model\Entity\SitePage;
use Omeka\Model\Entity\SitePageBlock;
use Omeka\Test\TestCase;

class SitePageBlockTest extends TestCase
{
    protected $block;

    public function setUp()
    {
        $this->block = new SitePageBlock;
    }

    public function testInitialState()
    {
        $this->assertNull($this->block->getId());
        $this->assertNull($this->block->getPage());
        $this->assertInstanceOf(
            'Doctrine\Common\Collections\ArrayCollection',
            $this->block->getAttachments()
        );
    }

    public function testSetPage()
    {
        $page = new SitePage;
        $this->block->setPage($page);
        $this->assertSame($page, $this->block->getPage());
        $this->assertTrue($page->getBlocks()->contains($this->block));
    }
}
