<?php 
namespace Omeka\Controller\Admin;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class VocabularyController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toRoute('admin/default', array(
            'controller' => 'vocabulary',
            'action' => 'browse',
        ));
    }

    public function browseAction()
    {
        $view = new ViewModel;
        $api = $this->getServiceLocator()->get('Omeka/ApiManager');
        $response = $api->search('vocabularies');
        if ($response->isError()) {
            print_r($response->getErrors());
            exit;
        }
        $view->setVariable('vocabularies', $response->getContent());
        return $view;
    }
}