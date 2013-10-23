<?php
namespace Omeka\Api\Adapter\Entity;

use Doctrine\ORM\QueryBuilder;
use Omeka\Model\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class PropertyAdapter extends AbstractEntityAdapter
{
    public function getEntityClass()
    {
        return 'Omeka\Model\Entity\Property';
    }

    public function hydrate(array $data, $entity)
    {
        if (isset($data['owner']['id'])) {
            $owner = $this->getEntityManager()
                ->getRepository('Omeka\Model\Entity\User')
                ->find($data['owner']['id']);
            $entity->setOwner($owner);
        }
        if (isset($data['vocabulary']['id'])) {
            $vocabulary = $this->getEntityManager()
                ->getRepository('Omeka\Model\Entity\Vocabulary')
                ->find($data['vocabulary']['id']);
            $entity->setVocabulary($vocabulary);
        }
        if (isset($data['local_name'])) {
            $entity->setLocalName($data['local_name']);
        }
        if (isset($data['label'])) {
            $entity->setLabel($data['label']);
        }
        if (isset($data['comment'])) {
            $entity->setComment($data['comment']);
        }

    }

    public function extract($entity)
    {
        return array(
            'id' => $entity->getId(),
            'owner' => $this->extractEntity($entity->getOwner(), new UserAdapter),
            'vocabulary' => $this->extractEntity(
                $entity->getVocabulary(),
                new VocabularyAdapter
            ),
            'local_name' => $entity->getLocalName(),
            'label' => $entity->getLabel(),
            'comment' => $entity->getComment(),
        );
    }

    public function buildQuery(array $query, QueryBuilder $qb)
    {
    }

    public function validate(EntityInterface $entity, ErrorStore $errorStore,
        $isPersistent
    ) {
        if (null === $entity->getLabel()) {
            $errorStore->addError('label', 'The label field cannot be null.');
        }
    }
}