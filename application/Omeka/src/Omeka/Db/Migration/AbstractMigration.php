<?php
namespace Omeka\Db\Migration;

use Doctrine\DBAL\Connection;
use Omeka\Db\Helper as DbHelper;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Abstract migration class.
 *
 * Most migrations should extend from this class.
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var DbHelper
     */
    protected $dbHelper;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * Default downgrade.
     *
     * By default, downgrade is unsupported and simply throws an exception.
     */
    public function down()
    {
        throw new Exception\DowngradeUnsupportedException(
            $this->getTranslator()->translate('This migration cannot be downgraded.')
        );
    }

    /**
     * Get the db helper
     *
     * @return DbHelper
     */
    protected function getDbHelper()
    {
        if (null === $this->dbHelper) {
            $this->dbHelper = $this->getServiceLocator()
                ->get('Omeka\DbHelper');
        }
        return $this->dbHelper;
    }

    /**
     * Get the translator service
     *
     * return TranslatorInterface
     */
    public function getTranslator()
    {
        if (!$this->translator instanceof TranslatorInterface) {
            $this->translator = $this->getServiceLocator()->get('MvcTranslator');
        }
        return $this->translator;
    }

    /**
     * {@inheritDoc}
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    /**
     * {@inheritDoc}
     */
    public function getServiceLocator()
    {
        return $this->services;
    }
}
