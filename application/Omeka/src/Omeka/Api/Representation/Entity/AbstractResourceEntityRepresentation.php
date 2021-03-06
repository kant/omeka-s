<?php
namespace Omeka\Api\Representation\Entity;

use Omeka\Api\Exception;
use Omeka\Api\Representation\Entity\ResourceClassRepresentation;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Model\Entity\Resource;
use Omeka\Model\Entity\Value;
use Omeka\Model\Entity\Vocabulary;

/**
 * Abstract resource entity representation.
 *
 * Provides functionality for entities that extend Omeka\Model\Entity\Resource.
 */
abstract class AbstractResourceEntityRepresentation extends AbstractEntityRepresentation
{
    /**
     * All value representations of this resource.
     *
     * <code>
     * array(
     *     {vocabularyPrefix} => array(
     *         'vocabulary' => {vocabularyRepresentation},
     *         'properties' => array(
     *             {propertyLocalName} => array(
     *                 'property' => {propertyRepresentation},
     *                 'values' => array(
     *                     {valueRepresentation},
     *                     {valueRepresentation},
     *                 ),
     *             ),
     *         ),
     *     ),
     * )
     * </code>
     *
     * @var array
     */
    protected $values = array();

    /**
     * Get the internal members of this resource entity.
     *
     * @return array
     */
    abstract function getResourceJsonLd();

    /**
     * {@inheritDoc}
     */
    public function getJsonLd()
    {
        // Set the JSON-LD node type.
        $nodeType = array();
        if ($this->getData()->getResourceClass()) {
            $resourceClass = $this->getData()->getResourceClass();
            $vocabulary = $resourceClass->getVocabulary();
            $prefix = $vocabulary->getPrefix();
            $suffix = $resourceClass->getLocalName();
            $this->addVocabularyToContext($vocabulary);
            $nodeType['@type'] = "$prefix:$suffix";
        }

        // Set the date time value objects.
        $dateTime = array(
            'o:created' => array(
                '@value' => $this->getDateTime($this->getData()->getCreated()),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ),
            'o:modified' => null,
        );
        if ($this->getData()->getModified()) {
            $dateTime['o:modified'] = array(
               '@value' => $this->getDateTime($this->getData()->getModified()),
               '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            );
        }

        // Set the values as JSON-LD value objects.
        $values = array();
        foreach ($this->values() as $prefix => $vocabulary) {
            foreach ($vocabulary['properties'] as $localName => $property) {
                foreach ($property['values'] as $value) {
                    $values["$prefix:$localName"][] = $value;
                }
            }
        }

        return array_merge(
            $nodeType,
            array(
                'o:owner' => $this->getReference(
                    null,
                    $this->getData()->getOwner(),
                    $this->getAdapter('users')
                ),
                'o:resource_class' => $this->getReference(
                    null,
                    $this->getData()->getResourceClass(),
                    $this->getAdapter('resource_classes')
                ),
            ),
            $dateTime,
            $this->getResourceJsonLd(),
            $values
        );
    }

    /**
     * Get the resource class representation of this resource.
     *
     * @return ResourceClassRepresentation
     */
    public function resourceClass()
    {
        return $this->getAdapter('resource_classes')
            ->getRepresentation(null, $this->getData()->getResourceClass());
    }

    /**
     * Get the date-time when this resource was created.
     *
     * @return DateTime
     */
    public function created()
    {
        return $this->getData()->getCreated();
    }

    /**
     * Get the date-time when this resource was last modified.
     *
     * @return DateTime
     */
    public function modified()
    {
        return $this->getData()->getModified();
    }

    /**
     * Get all value representations of this resource.
     *
     * @return array
     */
    public function values()
    {
        if (empty($this->values)) {
            $this->setValues();
        }
        return $this->values;
    }

    /**
     * Get value representations.
     *
     * @param string $term The prefix:local_part
     * @param array $options
     * - type: (null) Get values of this type only. Valid types are "literal",
     *   "uri", and "resource". Returns all types by default.
     * - all: (false) If true, returns all values that match criteria. If false,
     *   returns the first matching value.
     * - default: (null) Default value if no values match criteria. Returns null
     *   by default.
     * - lang: (null) Get values of this language only. Returns values of all
     *   languages by default.
     * @return RepresentationInterface|mixed
     */
    public function value($term, array $options = array())
    {
        // Set defaults.
        if (!isset($options['type'])) {
            $options['type'] = null;
        }
        if (!isset($options['all'])) {
            $options['all'] = false;
        }
        if (!isset($options['default'])) {
            $options['default'] = null;
        }
        if (!isset($options['lang'])) {
            $options['lang'] = null;
        }

        if (!$this->getAdapter()->isTerm($term)) {
            return $options['default'];
        }

        $values = $this->values();
        list($prefix, $localName) = explode(':', $term);
        if (!isset($values[$prefix]['properties'][$localName])) {
            return $options['default'];
        }

        // Match only the representations that fit all the criteria.
        $matchingValues = array();
        foreach ($values[$prefix]['properties'][$localName]['values'] as $value) {
            if (!is_null($options['type'])
                && $value->type() !== $options['type']
            ) {
                continue;
            }
            if (!is_null($options['lang'])
                && $value->lang() !== $options['lang']
            ) {
                continue;
            }
            $matchingValues[] = $value;
        }

        if (!count($matchingValues)) {
            return $options['default'];
        }

        return $options['all'] ? $matchingValues : $matchingValues[0];
    }

    /**
     * Get the display title for this resource.
     *
     * @param string|null $default
     * @return RepresentationInterface|null
     */
    public function displayTitle($default = null)
    {
        return $this->value('dcterms:title', array(
            'type' => 'literal',
            'default' => $default,
        ));
    }

    /**
     * Get the display description for this resource.
     *
     * @param string|null $default
     * @return RepresentationInterface|null
     */
    public function displayDescription($default = null)
    {
        return $this->value('dcterms:description', array(
            'type' => 'literal',
            'default' => $default,
        ));
    }

    /**
     * Get the display resource class label for this resource.
     *
     * @param string|null $default
     * @return string|null
     */
    public function displayResourceClassLabel($default = null)
    {
        $resourceClass = $this->resourceClass();
        return $resourceClass ? $resourceClass->label() : $default;
    }

    /**
     * Set all value representations of this resource.
     *
     * Organizes the values by JSON-LD term (prefix:local_part) and builds the
     * JSON-LD context.
     */
    protected function setValues()
    {
        foreach ($this->getData()->getValues() as $value) {
            $property = $value->getProperty();
            $vocabulary = $property->getVocabulary();
            $localName = $property->getLocalName();
            $prefix = $vocabulary->getPrefix();

            if (!isset($this->values[$prefix])) {
                $this->values[$prefix] = array(
                    'vocabulary' => $this->getAdapter('vocabularies')
                        ->getRepresentation(null, $vocabulary),
                    'properties' => array(),
                );
            }

            if (!isset($this->values[$prefix]['properties'][$localName])) {
                $this->values[$prefix]['properties'][$localName] = array(
                    'property' => $this->getAdapter('properties')
                        ->getRepresentation(null, $property),
                    'values' => array(),
                );
            }

            $this->values[$prefix]['properties'][$localName]['values'][]
                = new ValueRepresentation($value, $this->getServiceLocator());
        }
    }

    /**
     * Add a vocabulary term definition to the JSON-LD context.
     *
     * @param Vocabulary $vocabulary
     */
    protected function addVocabularyToContext(Vocabulary $vocabulary)
    {
        $this->addTermDefinitionToContext($vocabulary->getPrefix(), array(
            '@id' => $vocabulary->getNamespaceUri(),
            'vocabulary_id' => $vocabulary->getId(),
            'vocabulary_label' => $vocabulary->getLabel(),
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function validateData($data)
    {
        if (!$data instanceof Resource) {
            throw new Exception\InvalidArgumentException(
                $this->getTranslator()->translate(sprintf(
                    'Invalid data sent to %s.', get_called_class()
                ))
            );
        }
    }
}
