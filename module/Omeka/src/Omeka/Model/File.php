<?php
namespace Omeka\Model;

/**
 * @Entity
 */
class File
{
    /** @Id @Column(type="integer") @GeneratedValue */
    protected $id;
    
    public function getId()
    {
        return $this->id;
    }
}