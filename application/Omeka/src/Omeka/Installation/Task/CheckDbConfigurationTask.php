<?php
namespace Omeka\Installation\Task;

/**
 * Check database configuration task.
 */
class CheckDbConfigurationTask extends AbstractTask
{
    /**
     * Check whether the database configuration is valid.
     */
    public function perform()
    {
        try {
            $this->getServiceLocator()->get('Omeka\Connection')->connect();
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return;
        }
        $this->addInfo(
            $this->getTranslator()->translate('Database configuration is valid.')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getTranslator()->translate('Check database configuration');
    }
}
