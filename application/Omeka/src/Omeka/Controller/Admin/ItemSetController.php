<?php  
namespace Omeka\Controller\Admin;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ItemSetController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toRoute('admin/default', array(
            'controller' => 'item-set',
            'action' => 'browse'
        ));
    }

    public function browseAction()
    {
        $view = new ViewModel;
        $page = $this->params()->fromQuery('page', 1);
        $response = $this->api()->search('item_sets', array('page' => $page));
        $item_sets = $response->getContent();

        $this->paginator($response->getTotalResults(), $page);

        $view->setVariable('item_sets', $item_sets);
        return $view;
    }
}
?>