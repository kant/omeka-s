<?php
namespace Omeka\Api\Representation\Entity;

class ItemSetRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * {@inheritDoc}
     */
    public function getResourceJsonLd()
    {
        return array();
    }

    public function getItemCount()
    {
        return count($this->getData()->getItems());
    }
}
