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

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates a REST controller.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineRESTGenerator extends Generator
{
    /** @var Filesystem */
    protected $filesystem;
    protected $routePrefix;
    protected $routeNamePrefix;

    /** @var BundleInterface */
    protected $bundle;
    protected $entity;

    /** @var  ClassMetadataInfo */
    protected $metadata;
    protected $entityConstraints;
    protected $format;
    protected $actions;

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
     * @param BundleInterface $bundle A bundle object
     * @param string $entity The entity relative class name
     * @param ClassMetadataInfo $metadata The entity class metadata
     * @param array $entityConstraints array of fields with constraints array('field' => array(Constraint, Constraint2),'field2' => array(Constraint, Constraint2))
     * @param string $routePrefix The route name prefix
     * @param bool $forceOverwrite Whether or not to overwrite an existing controller
     * @param bool $resource
     * @param bool $document Whether or not to use Nelmio api documentation
     * @param string $format Format of routing
     * @param string $service_format Format of service generation
     * @param string $test Test-mode (none, oauth or no-authentication)
     */
    public function generate(BundleInterface $bundle,$entity,ClassMetadataInfo $metadata, $entityConstraints,$routePrefix,$forceOverwrite,$resource,$document,$format, $service_format, $test)
    {
        $this->routePrefix = $routePrefix;
        $this->routeNamePrefix = str_replace('/', '_', $routePrefix);
        $this->actions         = array('getById', 'getAll', 'post', 'put', 'delete');

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException(
                'The REST api generator does not support entity classes with multiple primary keys.'
            );
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException(
                'The REST api generator expects the entity object has a primary key field named "id" with a getId() method.'
            );
        }

        $this->entity = $entity;
        $this->bundle = $bundle;
        $this->metadata = $metadata;
        $this->entityConstraints = $entityConstraints;
        $this->setFormat($format);

        $this->generateControllerClass($forceOverwrite, $document, $resource);
        $this->generateHandler($forceOverwrite, $document);
        $this->generateExceptionClass();
        $this->declareService($service_format);
        $this->generateTestClass($forceOverwrite, $test);
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
     *
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
     * Generates the controller class only.
     * @param bool $forceOverwrite
     * @param bool $document
     * @param bool $resource
     */
    protected function generateControllerClass($forceOverwrite, $document, $resource)
    {
        $dir = $this->bundle->getPath();

        $parts           = explode('\\', $this->entity);
        $entityClass     = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = sprintf(
            '%s/Controller/%s/%sRESTController.php',
            $dir,
            str_replace('\\', '/', $entityNamespace),
            $entityClass
        );

        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile(
            'rest/controller.php.twig',
            $target,
            array(
                'actions' => $this->actions,
                'route_prefix' => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle' => $this->bundle->getName(),
                'entity' => $this->entity,
                'entity_class' => $entityClass,
                'namespace' => $this->bundle->getNamespace(),
                'entity_namespace' => $entityNamespace,
                'format' => $this->format,
                'resource' => $resource,
                'document' => $document,
            )
        );
    }

    /**
     * Generates the Handle only.
     * @param bool $forceOverwrite
     * @param bool $document
     */
    protected function generateHandler($forceOverwrite, $document)
    {
        $dir = $this->bundle->getPath();

        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = sprintf(
            '%s/Handler/%s/%sRESTHandler.php',
            $dir,
            str_replace('\\', '/', $entityNamespace),
            $entityClass
        );

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile(
            'rest/handler.php.twig',
            $target,
            array(
                'route_prefix' => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'bundle' => $this->bundle->getName(),
                'entity' => $this->entity,
                'entity_class' => $entityClass,
                'namespace' => $this->bundle->getNamespace(),
                'entity_namespace' => $entityNamespace,
                'format' => $this->format,
                'document' => $document,
            )
        );
    }

    /**
     *
     */
    public function generateExceptionClass()
    {
        $dir = $this->bundle->getPath();

        $target = sprintf('%s/Exception/InvalidFormException.php', $dir);

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        $this->renderFile(
            'rest/form_exception.php.twig',
            $target,
            array('namespace' => $this->bundle->getNamespace())
        );
    }

    /**
     * Declares the handler as a service
     * @param $service_format
     */
    public function declareService($service_format)
    {
        $dir = $this->bundle->getPath();

        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);
        if (strlen($entityNamespace) > 0)
        {
            $entityNamespace .= '\\';
        }
        $namespace = $this->bundle->getNamespace();

        $bundleName = strtolower($this->bundle->getName());
        $entityName = strtolower($this->entity);
        $entityName = str_replace('\\','.',$entityName);

        $services = sprintf(
            "%s/Resources/config/servicesREST.".$service_format,
            $dir
        );

        $handlerClass = sprintf(
            "%s\\Handler\\%s%sRESTHandler",
            $namespace,
            $entityNamespace,
            $entityClass
        );

        $newId = sprintf(
            "%s.%s.handler",
            str_replace("bundle", "", $bundleName),
            $entityName
        );

        $fileName = sprintf(
            "%s/DependencyInjection/%s.php",
            $dir,
            str_replace("Bundle", "Extension", $this->bundle->getName())
        );

        if (!is_file($services)) {
            $this->renderFile("rest/service/services.".$service_format.".twig", $services, array());
        }

        switch($service_format)
        {
            case 'xml':
                $this->handleServiceDeclarationAsXML($services,$newId, $handlerClass,$namespace,$entityNamespace,$entityClass,$fileName);
                break;
            case 'yml':
            default:
            $this->handleServiceDeclarationAsYML($services,$newId, $handlerClass,$namespace,$entityNamespace,$entityClass,$fileName);
                break;

        }
        $this->updateDIFile($fileName,$service_format);
    }

    private function handleServiceDeclarationAsXML($services, $newId, $handlerClass,$namespace,$entityNamespace,$entityClass,$fileName)
    {
        //this could be saved more readable by using dom_import_simplexml (http://stackoverflow.com/questions/1191167/format-output-of-simplexml-asxml)
        $newXML = simplexml_load_file($services);

        if (!($servicesTag = $newXML->services)) {
            $servicesTag = $newXML->addChild("services");
        }

        $search = $newXML->xpath("//*[@id='$newId']");
        if (!$search) {
            $newServiceTag = $servicesTag->addChild("service");
            $newServiceTag->addAttribute("id", $newId);
            $newServiceTag->addAttribute("class", $handlerClass);

            $entityManagerTag = $newServiceTag->addChild("argument");
            $entityManagerTag->addAttribute("type", "service");
            $entityManagerTag->addAttribute("id", "doctrine.orm.entity_manager");

            $newServiceTag->addChild(
                "argument",
                sprintf(
                    "%s\\Entity\\%s%s",
                    $namespace,
                    $entityNamespace,
                    $entityClass
                )
            );

            $formFactoryTag = $newServiceTag->addChild("argument");
            $formFactoryTag->addAttribute("type", "service");
            $formFactoryTag->addAttribute("id", "form.factory");
        }

        $newXML->saveXML($services);
    }

    private function handleServiceDeclarationAsYML($services, $newId, $handlerClass,$namespace,$entityNamespace,$entityClass,$fileName)
    {
        $yml_file = Yaml::parse(file_get_contents($services));
        $params = $yml_file['parameters'];
        $yml_file['parameters'] =
            array_merge(
                $params,
                array(
                    $newId.'.handler_class' => $handlerClass,
                    $newId.'.entity_class' => sprintf(
                        "%s\\Entity\\%s%s",
                        $namespace,
                        $entityNamespace,
                        $entityClass
                    ),
                )
            );
        $yml_services = $yml_file['services'];
        $yml_file['services'] =
            array_merge(
                $yml_services,
                array(
                    $newId => array(
                        'class' => '%'.$newId.'.handler_class%', 'arguments' => array(
                            '@doctrine.orm.entity_manager',
                            '%'.$newId.'.entity_class%',
                            '@form.factory',
                        ),
                    ),
                )
            );
        $yml_content = Yaml::dump($yml_file, 3);
        file_put_contents($services, $yml_content);
    }

    /**
     * @param $fileName
     * @param $serviceFormat
     */
    private function updateDIFile($fileName, $serviceFormat)
    {
        $toInput = '';
        switch($serviceFormat)
        {
            case 'xml':
                $toInput = PHP_EOL . "\t\t\$loader2 = new Loader\\XmlFileLoader(\$container, new FileLocator(__DIR__ . '/../Resources/config'));" . PHP_EOL .
                    "\t\t\$loader2->load('servicesREST.".$serviceFormat."');" . PHP_EOL . "\t";
                break;
            case 'yml':
            default:
            $toInput = PHP_EOL . "\t\t\$loader2 = new Loader\\YamlFileLoader(\$container, new FileLocator(__DIR__ . '/../Resources/config'));" . PHP_EOL .
                "\t\t\$loader2->load('servicesREST.".$serviceFormat."');" . PHP_EOL . "\t";
            break;

        }

        if (!file_exists(dirname($fileName)))
        {
            mkdir(dirname($fileName), 0777, true);
        }
        if (!file_exists($fileName))
        {
            $this->handleExtensionFileCreation($fileName);
        }
        $text = file_get_contents($fileName);

        if (strpos($text, "servicesREST.".$serviceFormat) == false) {
            $position = strpos($text, "}", strpos($text, "function load("));

            $newContent = substr_replace($text, $toInput, $position, 0);
            file_put_contents($fileName, $newContent);
        }
    }

    /**
     * @param $fileName
     */
    private function handleExtensionFileCreation($fileName)
    {
        $parts           = explode('\\', $this->entity);
        $entityNamespace = implode('\\', $parts);

        $this->renderFile(
            'rest/extension.php.twig',
            $fileName,
            array(
                'class_name'        => str_replace("Bundle", "Extension", $this->bundle->getName()),
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
            )
        );
    }

    private function makeFormatUserFriendly($format)
    {
        $returnFormat = '';
        $parts = explode('-',$format);
        foreach($parts as $part)
        {
            $returnFormat .= ucfirst($part);
        }

        return $returnFormat;
    }

    /**
     * Generates the functional test class only.
     * @param boolean $forceOverwrite whether or not to force overwriting or not
     * @param string $format either none, no-authentication or oauth
     */
    protected function generateTestClass($forceOverwrite, $format)
    {
        if ($format === 'none')
        {
            return;
        }

        $friendlyFormat = $this->makeFormatUserFriendly($format);

        $base_dir = $this->bundle->getPath() . '/Tests/Base';
        $dir = $this->bundle->getPath() . '/Tests/Controller';

        $parts           = explode('\\', $this->entity);
        $entityClass     = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = $dir . '/' . str_replace('\\', '/', $entityNamespace) . '/' . $entityClass . 'RESTControllerTest.php';
        $base_target = $base_dir . '/' . $friendlyFormat . 'BaseCase.php';

        if ($forceOverwrite === false && file_exists($target))
        {
            throw new \RuntimeException('Unable to generate the test as it already exists.');
        }

        $this->generateBaseTestCaseIfNotExists($forceOverwrite, $format, $friendlyFormat, $base_target);

        $this->renderFile(
            'rest/test.php.twig',
            $target,
            array(
                'format'             => $format,
                'friendly_format'    => $friendlyFormat,
                'fields'             => $this->metadata->fieldMappings,
                'assoc_mapping'      => $this->metadata->associationMappings,
                'entity_constraints' => $this->entityConstraints,
                'base_file'          => $base_target,
                'route_prefix'       => $this->routePrefix,
                'route_name_prefix'  => $this->routeNamePrefix,
                'entity'             => $this->entity,
                'bundle'             => $this->bundle->getName(),
                'entity_class'       => $entityClass,
                'namespace'          => $this->bundle->getNamespace(),
                'entity_namespace'   => $entityNamespace,
                'actions'            => $this->actions,
                'form_type_name'     => strtolower(str_replace('\\', '_', $this->bundle->getNamespace()) . ($parts ? '_' : '') . implode('_', $parts) . '_' . $entityClass . 'Type'),
            )
        );
    }

    /**
     * @param $overwrite
     * @param $format
     * @param $friendlyFormat
     * @param $target
     */
    protected function generateBaseTestCaseIfNotExists($overwrite, $format, $friendlyFormat, $target)
    {
        $parts           = explode('\\', $this->entity);
        $entityClass     = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        if (file_exists($target))
        {
            return;
        }

        $this->renderFile(
            'rest/tests/base/'.$format.'.php.twig',
            $target,
            array(
                'format'            => $format,
                'friendly_format'   => $friendlyFormat,
                'fields'            => $this->metadata->fieldMappings,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'entity'            => $this->entity,
                'bundle'            => $this->bundle->getName(),
                'entity_class'      => $entityClass,
                'namespace'         => $this->bundle->getNamespace(),
                'entity_namespace'  => $entityNamespace,
                'actions'           => $this->actions,
            )
        );
    }

}
