<?php
namespace Omeka\Installation\Task;

/**
 * Create the first user.
 */
class CreateFirstUserTask extends AbstractTask
{
    /**
     * Create the first user.
     */
    public function perform()
    {
        $apiManager = $this->getServiceLocator()->get('Omeka\ApiManager');
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');

        $response = $apiManager->create('users', array(
            'o:role'     => 'global_admin',
            'o:username' => $this->getVar('username'),
            'o:name'     => $this->getVar('name'),
            'o:email'    => $this->getVar('email'),
        ));
        if ($response->isError()) {
            $this->addErrorStore($response->getErrorStore());
            return;
        }

        // Set the password.
        $user = $response->getContent()->jsonSerialize();
        $userEntity = $entityManager->find('Omeka\Model\Entity\User', $user['o:id']);
        $userEntity->setPassword($this->getVar('password'));
        $entityManager->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getTranslator()->translate('Create the first user');
    }
}
