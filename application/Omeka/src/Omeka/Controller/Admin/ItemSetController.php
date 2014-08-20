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
        return $view;
    }
}
?>