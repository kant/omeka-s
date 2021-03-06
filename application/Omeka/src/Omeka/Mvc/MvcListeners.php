<?php
namespace Omeka\Mvc;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Mvc\MvcEvent;

class MvcListeners extends AbstractListenerAggregate
{
    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'redirectToInstallation')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'redirectToLogin')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'authenticateApiKey')
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'authorizeUserAgainstRoute'),
            -1000
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH,
            array($this, 'setLayoutForRoute')
        );
    }

    /**
     * Redirect all requests to install route if Omeka is not installed.
     *
     * @param MvcEvent $event
     * @return Zend\Http\PhpEnvironment\Response
     */
    public function redirectToInstallation(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        if ($serviceLocator->get('Omeka\Status')->isInstalled()) {
            // Omeka is installed
            return;
        }
        $matchedRouteName = $event->getRouteMatch()->getMatchedRouteName();
        if ('install' == $matchedRouteName) {
            // On the install route
            return;
        }
        $url = $event->getRouter()->assemble(array(), array('name' => 'install'));
        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }

    /**
     * Redirect all admin requests to install route if user not logged in.
     *
     * @param MvcEvent $event
     * @return Zend\Http\PhpEnvironment\Response
     */
    public function redirectToLogin(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            // User is logged in.
            return;
        }

        $routeParams = $event->getRouteMatch()->getParams();
        if (isset($routeParams['__NAMESPACE__'])
            && 'Omeka\Controller\Admin' == $routeParams['__NAMESPACE__']
        ) {
            // Admin request.
            $url = $event->getRouter()->assemble(array(), array('name' => 'login'));
            $response = $event->getResponse();
            $response->getHeaders()->addHeaderLine('Location', $url);
            $response->setStatusCode(302);
            $response->sendHeaders();
            return $response;
        }
    }

    /**
     * Authorize the current user against an API key.
     *
     * @param MvcEvent $event
     */
    public function authenticateApiKey(MvcEvent $event)
    {
        $status = $event->getApplication()->getServiceManager()
            ->get('Omeka\Status');

        if (!$status->isApiRequest()) {
            // This is not an API request.
            return;
        }

        $identity = $event->getRequest()->getQuery('key_identity');
        $credential = $event->getRequest()->getQuery('key_credential');

        if (is_null($identity) || is_null($credential)) {
            // No identity/credential key to authenticate against.
            return;
        }

        $auth = $event->getApplication()->getServiceManager()
            ->get('Omeka\AuthenticationService');
        $auth->getAdapter()->setIdentity($identity);
        $auth->getAdapter()->setCredential($credential);
        $auth->authenticate();
    }

    /**
     * Authorize the current user against the requested route.
     *
     * @param MvcEvent $event
     */
    public function authorizeUserAgainstRoute(MvcEvent $event)
    {
        $application = $event->getApplication();
        $routeMatch = $event->getRouteMatch();
        $controller = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');

        $acl = $application->getServiceManager()->get('Omeka\Acl');
        if ($acl->userIsAllowed($controller, $action)) {
            return;
        }

        $event->setError(Application::ERROR_CONTROLLER_PERMISSION_DENIED);
        $errorMessage = sprintf('Permission denied to access %s:%s', $controller, $action);
        $event->setParam('exception', new Exception\PermissionDeniedException($errorMessage));

        $application->getEventManager()->trigger(MvcEvent::EVENT_DISPATCH_ERROR, $event);
    }

    /**
     * Set the layout template according to route.
     *
     * @param MvcEvent $event
     */
    public function setLayoutForRoute(MvcEvent $event)
    {
        $serviceLocator = $event->getApplication()->getServiceManager();
        $config = $serviceLocator->get('Config');
        $matchedRouteName = $event->getRouteMatch()->getMatchedRouteName();
        if (!array_key_exists($matchedRouteName, $config['view_route_layouts'])) {
            return;
        }
        $viewModel = $event->getViewModel();
        $viewModel->setTemplate($config['view_route_layouts'][$matchedRouteName]);
    }
}
