<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Voryx\RESTGeneratorBundle\Generator;

use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a REST controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineRESTGenerator extends Generator
{
	
    protected $filesystem;
    protected $routePrefix;
    protected $routeNamePrefix;
    protected $bundle;
    protected $targetBundle;
    protected $entity;
    protected $parent;
    protected $parentActions;
    protected $parentRoute;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $roles;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem A Filesystem instance
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Generate the REST controller.
     *
     * @param BundleInterface $bundle A bundle where entities live
     * @param BundleInterface $targetBundle A bundle where API will live
     * @param string $entity The entity relative class name
     * @param string $parent The parent entity class name
     * @param ClassMetadataInfo $metadata The entity class metadata
     * @param boolean $forceOverwrite Whether or not to overwrite an existing controller
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, BundleInterface $targetBundle, $entity, $parent, ClassMetadataInfo $metadata, $forceOverwrite)
    {
        $this->routePrefix     = Inflector::pluralize(strtolower($entity));
        $this->routeNamePrefix = 'noinc_' . $this->routePrefix . '_';
        $this->actions         = array('getById', 'getAll', 'post', 'put', 'patch', 'delete');
        $this->parentActions   = array('getAllByParent', 'postByParent');
        $this->roles           = [
            'all'    =>  'ROLE_' . strtoupper($entity) . '_ALL',
            'create' =>  'ROLE_' . strtoupper($entity) . '_CREATE',
            'read'   =>  'ROLE_' . strtoupper($entity) . '_READ',
            'update' =>  'ROLE_' . strtoupper($entity) . '_UPDATE',
            'delete' =>  'ROLE_' . strtoupper($entity) . '_DELETE'
        ];

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The REST api generator does not support entity classes with multiple primary keys.');
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException('The REST api generator expects the entity object has a primary key field named "id" with a getId() method.');
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->targetBundle   = $targetBundle;
        $this->parent   = $parent;
        if ($parent) {
            $this->parentRoute = Inflector::pluralize(strtolower($parent));
        }
        $this->metadata = $metadata;
        $this->setFormat('yml');

        $this->generateControllerClass();
		$this->generateBaseRESTControllerClass();
    }

    /**
     * Sets the configuration format.
     *
     * @param string $format The configuration format
     */
    private function setFormat($format)
    {
        switch ($format) {
            case 'yml':
            case 'xml':
            case 'php':
            case 'annotation':
                $this->format = $format;
                break;
            default:
                $this->format = 'yml';
                break;
        }
    }

    /**
     * Generates the routing configuration.
     */
    protected function generateConfiguration()
    {
        if (!in_array($this->format, array('yml', 'xml', 'php'))) {
            return;
        }

        $target = sprintf(
            '%s/Resources/config/routing/%s.%s',
            $this->bundle->getPath(),
            strtolower(str_replace('\\', '_', $this->entity)),
            $this->format
        );

        $this->renderFile(
            'rest/config/routing.' . $this->format . '.twig',
            $target,
            array(
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
            )
        );
    }
    
    /**
     * Generates the base controller class only (all generated controllers will inherit from this base controller).
     */
    protected function generateBaseRESTControllerClass()
    {
    	$dir = $this->targetBundle->getPath();
    	
    	$target = sprintf(
    		'%s/Controller/NoIncBaseRESTController.php',
    		$dir
    	);
    
    	$this->renderFile(
    		'rest/base_controller.php.twig',
    		$target,
    		array()
    	);
    }

    /**
     * Generates the entity rest controller class.
     *
     * @param boolean $forceOverwrite whether to overwrite controller class if it exists
     */
    protected function generateControllerClass()
    {
        $dir = $this->targetBundle->getPath();

        $parts           = explode('\\', $this->entity);
        $entityClass     = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = sprintf(
            '%s/Controller/%s/%sRESTController.php',
            $dir,
            str_replace('\\', '/', $entityNamespace),
            $entityClass
        );

        $this->renderFile(
            'rest/controller.php.twig',
            $target,
            array(
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
                'entity_class'      => $entityClass,
                'parent'            => $this->parent,
                'parent_route'      => $this->parentRoute,
                'parent_actions'    => $this->parentActions,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'format'            => $this->format,
                'roles'             => $this->roles
            )
        );
    }

    /**
     * Generates the functional test class only.
     *
     */
    protected function generateTestClass()
    {
        $parts           = explode('\\', $this->entity);
        $entityClass     = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $dir    = $this->bundle->getPath() . '/Tests/Controller';
        $target = $dir . '/' . str_replace('\\', '/', $entityNamespace) . '/' . $entityClass . 'RESTControllerTest.php';

        $this->renderFile(
            'rest/tests/test.php.twig',
            $target,
            array(
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'entity'            => $this->entity,
                'bundle'            => $this->bundle->getName(),
                'entity_class'      => $entityClass,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'actions'           => $this->actions,
                'form_type_name'    => strtolower(str_replace('\\', '_', $this->bundle->getNamespace()) . ($parts ? '_' : '') . implode('_', $parts) . '_' . $entityClass . 'Type'),
            )
        );
     }

}
