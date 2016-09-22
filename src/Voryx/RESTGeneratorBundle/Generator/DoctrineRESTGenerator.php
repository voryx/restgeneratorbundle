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
    protected $parents;
    protected $parentActions;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $roles;
//     protected $tests;
//     protected $parentTests;

    const ACTIONS = ['get', 'getAll', 'post', 'put', 'patch', 'delete'];
    const PARENT_ACTIONS = ['getAllByParent', 'postByParent'];
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
     * @param array $parents The parents array
     * @param ClassMetadataInfo $metadata The entity class metadata
     * @param boolean $forceOverwrite Whether or not to overwrite an existing controller
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, BundleInterface $targetBundle, $entity, $parents, $exclude, ClassMetadataInfo $metadata)
    {
        $this->routePrefix     = Inflector::pluralize(strtolower($entity));
        $this->routeNamePrefix = 'noinc_' . $this->routePrefix . '_';
        //$this->tests		   = array('testGetAll', 'testGetById', 'testPost', 'testPut', 'testPatch', 'testDelete');
        //$this->parentTests   = array('testGetAllByParent', 'testPostByParent');
        $this->actions         = array_diff(self::ACTIONS, $exclude);
        $this->parentActions   = self::PARENT_ACTIONS;
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

        $this->entity       = $entity;
        $this->bundle       = $bundle;
        $this->targetBundle = $targetBundle;
        $this->exclude      = $exclude;
        $this->metadata     = $metadata;

        // change parents array from array of entity names to associative array of entity name => entity route
        // i.e. ["Car", "Truck"] becomes ["Car" => "cars", "Truck" => "trucks"]
        $this->parents = array_reduce($parents, function($result, $parent) {
            $result[$parent] = Inflector::pluralize(strtolower($parent));
            return $result;
        }, []);

        $this->setFormat('yml');

        $this->generateControllerClass();
		$this->generateBaseRESTControllerClass();
		$this->generateBaseTestControllerClass();
		$this->generateTestControllerClass();
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
    		'%s/Controller/Generated/NoIncBaseRESTController.php',
    		$dir
    	);
    
    	$this->renderFile(
    		'rest/base_controller.php.twig',
    		$target,
    		array('target_namespace' => $this->targetBundle->getNamespace())
    	);
    }

    /**
     * Generates the test for rest controller class.
     *
     */
    protected function generateTestControllerClass()
    {
    	$dir = $this->targetBundle->getPath();
    
    	$parts           = explode('\\', $this->entity);
    	$entityClass     = array_pop($parts);
    	$entityNamespace = implode('\\', $parts);
    
    	$target = sprintf(
    		'%s/Tests/Controller/Generated/%s/%sRESTControllerTest.php',
    		$dir,
    		str_replace('\\', '/', $entityNamespace),
    		$entityClass
    	);
    
    	$this->renderFile(
    		'rest/test_controller.php.twig',
    		$target,
    		array(
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
                'entity_class'      => $entityClass,
                'parents'           => $this->parents,
                'parent_actions'    => $this->parentActions,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'target_namespace'  => $this->targetBundle->getNamespace(),
                'format'            => $this->format,
                'roles'             => $this->roles
    		)
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
            '%s/Controller/Generated/%s/%sRESTController.php',
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
                'parents'           => $this->parents,
                'parent_actions'    => $this->parentActions,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'target_namespace'  => $this->targetBundle->getNamespace(),
                'format'            => $this->format,
                'roles'             => $this->roles
            )
        );
    }

    /**
     * Generates the base test controller class only (all generated controllers will inherit from this base controller).
     */
    protected function generateBaseTestControllerClass()
    {
    	$dir = $this->targetBundle->getPath();
    	 
    	$target = sprintf(
    		'%s/Tests/Controller/Generated/NoIncBaseTestController.php',
    		$dir
    	);
    
    	$this->renderFile(
    		'rest/base_test_controller.php.twig',
    		$target,
    		array('target_namespace' => $this->targetBundle->getNamespace())
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

        $dir    = $this->bundle->getPath() . '/Tests/Controller/Generated';
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
