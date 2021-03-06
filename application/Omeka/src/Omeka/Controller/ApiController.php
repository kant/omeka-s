<?php
namespace Omeka\Controller;

use Omeka\Api\Response;
use Omeka\View\Model\ApiJsonModel;
use Zend\Json\Exception\RuntimeException as JsonRuntimeException;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Mvc\MvcEvent;

class ApiController extends AbstractRestfulController
{
    /**
     * @var array
     */
    protected $viewOptions = array();

    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        $resource = $this->params()->fromRoute('resource');
        $response = $this->api()->read($resource, $id);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    /**
     * {@inheritDoc}
     */
    public function getList()
    {
        $resource = $this->params()->fromRoute('resource');
        $data = $this->params()->fromQuery();
        $response = $this->api()->search($resource, $data);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    /**
     * {@inheritDoc}
     */
    public function create($data)
    {
        $resource = $this->params()->fromRoute('resource');
        $response = $this->api()->create($resource, $data);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    /**
     * {@inheritDoc}
     */
    public function update($id, $data)
    {
        $resource = $this->params()->fromRoute('resource');
        $response = $this->api()->update($resource, $id, $data);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    /**
     * {@inheritDoc}
     */
    public function delete($id)
    {
        $resource = $this->params()->fromRoute('resource');
        $response = $this->api()->delete($resource, $id);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    /**
     * Validate the API request and set global options.
     *
     * @param MvcEvent $event
     */
    public function onDispatch(MvcEvent $event)
    {
        $request = $this->getRequest();

        // Require application/json Content-Type for certain methods.
        $method = strtolower($request->getMethod());
        if (in_array($method, array('post', 'put', 'patch'))
            && !$this->requestHasContentType($request, self::CONTENT_TYPE_JSON)
        ) {
            $contentType = $request->getHeader('Content-Type');
            $errorMessage = sprintf(
                'Invalid Content-Type header. Expecting "application/json", got "%s".',
                $contentType ? $contentType->getMediaType() : 'none'
            );
            return $this->getErrorResult($event, Response::ERROR_BAD_REQUEST, $errorMessage);
        }

        // Set pretty print.
        $prettyPrint = $request->getQuery('pretty_print');
        if (null !== $prettyPrint) {
            $this->setViewOption('pretty_print', true);
        }

        // Set the JSONP callback.
        $callback = $request->getQuery('callback');
        if (null !== $callback) {
            $this->setViewOption('callback', $callback);
        }

        try {
            // Finish dispatching the request.
            parent::onDispatch($event);
        } catch (JsonRuntimeException $e) {
            $this->getServiceLocator()->get('Omeka\Logger')->err((string) $e);
            return $this->getErrorResult($event, Response::ERROR_BAD_REQUEST, $e->getMessage());
        } catch (\Exception $e) {
            $this->getServiceLocator()->get('Omeka\Logger')->err((string) $e);
            return $this->getErrorResult($event, Response::ERROR_INTERNAL, $e->getMessage());
        }
    }

    /**
     * Set a view model option.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setViewOption($key, $value)
    {
        $this->viewOptions[$key] = $value;
    }

    /**
     * Get all view options.
     *
     * return array
     */
    public function getViewOptions()
    {
        return $this->viewOptions;
    }

    /**
     * Set an error result to the MvcEvent and return the result.
     *
     * @param MvcEvent $event
     * @param string $errorStatus
     * @param string $errorMessage
     */
    protected function getErrorResult(MvcEvent $event, $errorStatus, $errorMessage)
    {
        $response = new Response;
        $response->setStatus($errorStatus);
        $response->addError($errorStatus, $errorMessage);
        $result = new ApiJsonModel($response);
        $event->setResult($result);
        return $result;
    }
}
