<?php
namespace Omeka\Api\Adapter\Entity;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Event\Event;
use Omeka\Model\Entity\EntityInterface;
use Omeka\Model\Exception as ModelException;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\DateTime;
use Zend\Stdlib\Hydrator\HydratorInterface;

/**
 * Abstract entity API adapter.
 */
abstract class AbstractEntityAdapter extends AbstractAdapter implements
    EntityAdapterInterface
{
    /**
     * A unique token index for query builder aliases and placeholders.
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Entity fields on which to sort search results.
     *
     * The keys are the value of "sort_by" query. The values are the
     * corresponding entity fields on which to sort.
     *
     * @see self::sortQuery()
     * @var array
     */
    protected $sortFields = array();

    /**
     * Hydrate an entity with the provided array.
     *
     * Do not modify or perform operations on the data when setting properties.
     * Validation should be done in self::validate(). Filtering should be done
     * in the entity's mutator methods. Authorize state changes of individual
     * fields using self::authorize().
     *
     * @param array $data
     * @param EntityInterface $entity
     * @param ErrorStore $errorStore
     */
    abstract public function hydrate(array $data, EntityInterface $entity,
        ErrorStore $errorStore);

    /**
     * Validate an entity.
     *
     * Set validation errors to the passed $errorStore object. If an error is
     * present the entity will not be persisted or updated.
     *
     * @param EntityInterface $entity
     * @param ErrorStore $errorStore
     * @param bool $isPersistent
     */
    public function validate(EntityInterface $entity,
        ErrorStore $errorStore, $isPersistent
    ) {}

    /**
     * Build a conditional search query from an API request.
     *
     * Modify the passed $queryBuilder object according to the passed $query.
     * The sort_by, sort_order, page, limit, and offset parameters are included
     * separately.
     *
     * @link http://docs.doctrine-project.org/en/latest/reference/query-builder.html
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildQuery(QueryBuilder $qb, array $query)
    {}

    /**
     * Set sort_by and sort_order conditions to the query builder.
     *
     * @param array $query
     * @param QueryBuilder $qb
     */
    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (is_string($query['sort_by'])
            && array_key_exists($query['sort_by'], $this->sortFields)
        ) {
            $sortBy = $this->sortFields[$query['sort_by']];
            $qb->orderBy($this->getEntityClass() . ".$sortBy", $query['sort_order']);
        }
    }

    /**
     * Set page, limit (max results) and offset (first result) conditions to the
     * query builder.
     *
     * @param array $query
     * @param QueryBuilder $qb
     */
    public function limitQuery(QueryBuilder $qb, array $query)
    {
        if (is_numeric($query['page'])) {
            $paginator = $this->getServiceLocator()->get('Omeka\Paginator');
            $paginator->setCurrentPage($query['page']);
            if (is_numeric($query['per_page'])) {
                $paginator->setPerPage($query['per_page']);
            }
            $qb->setMaxResults($paginator->getPerPage());
            $qb->setFirstResult($paginator->getOffset());
            return;
        }
        if (is_numeric($query['limit'])) {
            $qb->setMaxResults($query['limit']);
        }
        if (is_numeric($query['offset'])) {
            $qb->setFirstResult($query['offset']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function search(Request $request)
    {
        $entityClass = $this->getEntityClass();
        $query = $request->getContent();

        // Set default query parameters
        if (!isset($query['page'])) {
            $query['page'] = null;
        }
        if (!isset($query['per_page'])) {
            $query['per_page'] = null;
        }
        if (!isset($query['limit'])) {
            $query['limit'] = null;
        }
        if (!isset($query['offset'])) {
            $query['offset'] = null;
        }
        if (!isset($query['sort_by'])) {
            $query['sort_by'] = null;
        }
        if (isset($query['sort_order'])
            && in_array(strtoupper($query['sort_order']), array('ASC', 'DESC'))
        ) {
            $query['sort_order'] = strtoupper($query['sort_order']);
        } else {
            $query['sort_order'] = 'ASC';
        }

        // Begin building the search query.
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select($entityClass)->from($entityClass, $entityClass);
        $this->buildQuery($qb, $query);

        // Trigger the search.query event.
        $event = new Event(Event::API_SEARCH_QUERY, $this, array(
            'services' => $this->getServiceLocator(),
            'query_builder' => $qb,
        ));
        $this->getEventManager()->trigger($event);

        // Finish building the search query and get the representations.
        $this->sortQuery($qb, $query);
        $this->limitQuery($qb, $query);
        $paginator = new Paginator($qb);
        $representations = array();
        foreach ($paginator as $entity) {
            $representations[] = $this->getRepresentation($entity->getId(), $entity);
        }

        $response = new Response($representations);
        $response->setTotalResults($paginator->count());
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function create(Request $request)
    {
        $t = $this->getTranslator();
        $response = new Response;

        $entityClass = $this->getEntityClass();
        $entity = new $entityClass;
        $this->hydrateEntity(
            Request::CREATE,
            $request->getContent(),
            $entity,
            new ErrorStore
        );
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        // Refresh the entity on the chance that it contains associations that
        // have not been loaded.
        $this->getEntityManager()->refresh($entity);
        $representation = $this->getRepresentation($entity->getId(), $entity);
        $response->setContent($representation);
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function batchCreate(Request $request)
    {
        $response = new Response;

        $errorStore = new ErrorStore;
        $representations = array();
        foreach ($request->getContent() as $datum) {
            $entityClass = $this->getEntityClass();
            $entity = new $entityClass;
            $this->hydrateEntity(Request::CREATE, $datum, $entity, $errorStore);
            $this->getEntityManager()->persist($entity);
            $representations[] = $this->getRepresentation($entity->getId(), $entity);
        }

        $this->getEntityManager()->flush();
        $response->setContent($representations);
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function read(Request $request)
    {
        $t = $this->getTranslator();
        $response = new Response;

        $entity = $this->findEntity(array('id' => $request->getId()));
        $this->authorize($entity, Request::READ);

        // Trigger the read.find.post event.
        $event = new Event(Event::API_READ_FIND_POST, $this, array(
            'services' => $this->getServiceLocator(),
            'entity' => $entity,
        ));
        $this->getEventManager()->trigger($event);

        $representation = $this->getRepresentation($entity->getId(), $entity);
        $response->setContent($representation);
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Request $request)
    {
        $t = $this->getTranslator();
        $response = new Response;

        $entity = $this->findEntity(array('id' => $request->getId()));
        $this->hydrateEntity(
            Request::UPDATE,
            $request->getContent(),
            $entity,
            new ErrorStore
        );
        $this->getEntityManager()->flush();
        $representation = $this->getRepresentation($entity->getId(), $entity);
        $response->setContent($representation);
        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Request $request)
    {
        $t = $this->getTranslator();
        $response = new Response;

        $entity = $this->findEntity(array('id' => $request->getId()));
        $this->authorize($entity, Request::DELETE);

        // Trigger the delete.find.post event.
        $event = new Event(Event::API_DELETE_FIND_POST, $this, array(
            'services' => $this->getServiceLocator(),
            'entity' => $entity,
        ));
        $this->getEventManager()->trigger($event);

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
        $representation = $this->getRepresentation($entity->getId(), $entity);
        $response->setContent($representation);
        return $response;
    }

    /**
     * Get the entity manager.
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Hydrate an entity.
     *
     * Encapsulates hydration, authorization, pre-validation API events, and
     * validation procedures into one method.
     *
     * @throws Exception\ValidationException
     * @param string $operation
     * @param array $data
     * @param EntityInterface $entity
     * @param ErrorStore $errorStore
     */
    protected function hydrateEntity($operation, array $data,
        EntityInterface $entity, ErrorStore $errorStore
    ) {
        if (Request::CREATE == $operation) {
            $eventName = Event::API_CREATE_VALIDATE_PRE;
        } elseif (Request::UPDATE == $operation) {
            $eventName = Event::API_UPDATE_VALIDATE_PRE;
        } else {
            throw new Exception\InvalidArgumentException(
                $this->getTranslator()->translate('Invalid operation for hydration.')
            );
        }

        // Prior to hydration, check whether the current user has access to this
        // entity in its original state.
        $this->authorize($entity, $operation);
        $this->hydrate($data, $entity, $errorStore);

        // Trigger the operation's validate.pre event.
        $event = new Event($eventName, $this, array(
            'services' => $this->getServiceLocator(),
            'entity' => $entity,
            'data' => $data,
        ));
        $this->getEventManager()->trigger($event);

        // Validate the entity.
        $this->validate($entity, $errorStore, $this->entityIsPersistent($entity));
        if ($errorStore->hasErrors()) {
            if (Request::UPDATE == $operation) {
                // Refresh the entity from the database, overriding any local
                // changes that have not yet been persisted
                $this->getEntityManager()->refresh($entity);
            }
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }
    }

    /**
     * Verify that the current user has access to the entity.
     *
     * @throws Exception\PermissionDeniedException
     * @param EntityInterface $entity
     * @param string $privilege
     */
    protected function authorize(EntityInterface $entity, $privilege)
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        if (!$acl->userIsAllowed($entity, $privilege)) {
            throw new Exception\PermissionDeniedException(sprintf(
                $t->translate('Permission denied for the current user to %s the %s resource.'),
                $operation, $entity->getResourceId()
            ));
        }
    }

    /**
     * Check whether an entity is persistent.
     *
     * @param EntityInterface $entity
     * @return bool
     */
    protected function entityIsPersistent(EntityInterface $entity)
    {
        $entityState = $this->getEntityManager()
            ->getUnitOfWork()
            ->getEntityState($entity);
        return UnitOfWork::STATE_MANAGED === $entityState;
    }

    /**
     * Find a single entity by identifier or a set of criteria.
     *
     * @throws Exception\NotFoundException
     * @param mixed $criteria An ID or an array of criteria
     * @return EntityInterface
     */
    protected function findEntity($id)
    {
        if (is_array($id)) {
            $entity = $this->getEntityManager()
                ->getRepository($this->getEntityClass())
                ->findOneBy($id);
        } else {
            $entity = $this->getEntityManager()
                ->find($this->getEntityClass(), $id);
        }
        if (null === $entity) {
            throw new Exception\NotFoundException(sprintf(
                $this->getTranslator()->translate('%s entity not found using criteria: %s.'),
                $this->getEntityClass(),
                is_array($id) ? json_encode($id) : $id
            ));
        }
        return $entity;
    }

    /**
     * Create a unique named parameter for the query builder and bind a value to
     * it.
     *
     * @param QueryBuilder $qb
     * @param mixed $value The value to bind
     * @param string $prefix The placeholder prefix
     * @return string The placeholder
     */
    public function createNamedParameter(QueryBuilder $qb, $value,
        $prefix = 'omeka_'
    ) {
        $placeholder = $prefix . $this->index;
        $this->index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
    }

    /**
     * Create a unique alias for the query builder.
     *
     * @param string $prefix The alias prefix
     * @return string The alias
     */
    public function createAlias($prefix = 'omeka_')
    {
        $alias = $prefix . $this->index;
        $this->index++;
        return $alias;
    }

    /**
     * Determine whether a string is a valid JSON-LD term.
     *
     * @param string $term
     * @return bool
     */
    public function isTerm($term)
    {
        return (bool) preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $term);
    }
}
