<?php
namespace Omeka\Api\Adapter;

use Omeka\Api\Representation\RepresentationInterface;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\Permissions\Acl\Resource\ResourceInterface;

/**
 * API adapter interface.
 */
interface AdapterInterface extends
    ServiceLocatorAwareInterface,
    EventManagerAwareInterface,
    ResourceInterface
{
    /**
     * Get the name of the corresponding API resource.
     *
     * @return string
     */
    public function getResourceName();

    /**
     * Get the fully qualified name of the corresponding representation class.
     *
     * @return string
     */
    public function getRepresentationClass();

    /**
     * Search a set of resources.
     *
     * @param Request $request
     * @return Response
     */
    public function search(Request $request);

    /**
     * Create a resource.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request);

    /**
     * Batch create resources.
     *
     * Adapters implementing this operation should return the resultant
     * resources as the response content so the create.pre and create.post
     * events can be triggered for every resource.
     *
     * @param Request $request
     * @return Response
     */
    public function batchCreate(Request $request);

    /**
     * Read a resource.
     *
     * @param Request $request
     * @return Response
     */
    public function read(Request $request);

    /**
     * Update a resource.
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request);

    /**
     * Delete a resource.
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request);
}
