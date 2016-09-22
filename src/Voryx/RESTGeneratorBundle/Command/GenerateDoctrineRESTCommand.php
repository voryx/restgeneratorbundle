<?php

/*
 */

namespace Voryx\RESTGeneratorBundle\Command;


use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Voryx\RESTGeneratorBundle\Generator\DoctrineRESTGenerator;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Voryx\RESTGeneratorBundle\Manipulator\RoutingManipulator;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates a REST api for a Doctrine entity.
 */
class GenerateDoctrineRESTCommand extends GenerateDoctrineCrudCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setDefinition(
            array(
                new InputOption('data-bundle', '', InputOption::VALUE_REQUIRED, 'The bundle in which the doctrine entities exist'),
                new InputOption('api-bundle', '', InputOption::VALUE_REQUIRED, 'The bundle in which to put the generated controllers'),
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('parents', '', InputOption::VALUE_OPTIONAL, 'A comma-separated list of parents (used to create additional endpoints nested under each parent)'),
                new InputOption('config', '', InputOption::VALUE_OPTIONAL, 'Path to the config file to use for API generation'),
                new InputOption('exclude', '', InputOption::VALUE_OPTIONAL, 'A comma-separated list of actions to exclude from API generation (i.e. getAll, post, delete)')
            )
        )
            ->setDescription('Generates a REST api based on a Doctrine entity')
            ->setHelp($this->getHelpText())
            ->setName('voryx:generate:rest')
            ->setAliases(array('generate:voryx:rest'));
    }
    
    /**
     * This method is executed BEFORE execute().
     * Its purpose is to check if some of the options/arguments are missing and interactively ask the user for those values. 
     * This is the last place where you can ask for missing options/arguments. 
     * After this command, missing options/arguments will result in an error.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $input->getOption('config');

        if ($config) {
            $this->generateFromConfigFile($config);
        } else {
            $this->generateFromCommandLine($input);
        }

        $this->getQuestionHelper()->writeGeneratorSummary($output, []);
    }

    private function generateFromConfigFile($configFile) {
        $yaml = Yaml::parse(file_get_contents($configFile));

        $dataBundle = $yaml["dataBundle"];
        $apiBundle = $yaml["apiBundle"];
        $entities = $yaml["entities"];
        foreach ($entities as $entity => $options) {
            $parents = $this->getYamlOptionAsArray($options, "parents");
            $exclude = $this->getYamlOptionAsArray($options, "exclude");
            $this->generate($dataBundle, $apiBundle, $entity, $parents, $exclude);
        }
    }

    private function generateFromCommandLine(InputInterface $input) {
        $dataBundle = $input->getOption('data-bundle');
        $apiBundle = $input->getOption('api-bundle');
        $entity = $input->getOption('entity');
        $parents = $this->getCommandLineOptionAsArray($input, 'parents');
        $exclude = $this->getCommandLineOptionAsArray($input, 'exclude');

        $this->generate($dataBundle, $apiBundle, $entity, $parents, $exclude);
    }

    private function generate($dataBundle, $apiBundle, $entity, $parents, $exclude) {
        $entityClass  = $this->getContainer()->get('doctrine')->getAliasNamespace($dataBundle) . '\\' . $entity;
        $entityBundle = $this->getContainer()->get('kernel')->getBundle($dataBundle);
        $targetBundle = $this->getContainer()->get('kernel')->getBundle($apiBundle);
        $metadata     = $this->getEntityMetadata($entityClass);

        $generator = $this->getGenerator($entityBundle);
        $generator->generate($entityBundle, $targetBundle, $entity, $parents, $exclude, $metadata[0]);
        $this->generateForm($entityBundle, $entity, $metadata, true);
    }

    private function getCommandLineOptionAsArray(InputInterface $input, $option) {
        $rawOption = $input->getOption($option);
        return $rawOption ? explode(',', $rawOption) : [];
    }

    private function getYamlOptionAsArray($options, $key) {
        return isset($options[$key]) ? $options[$key] : [];
    }

    /**
     * @param null $bundle
     * @return DoctrineRESTGenerator
     */
    protected function createGenerator($bundle = null)
    {
        return new DoctrineRESTGenerator($this->getContainer()->get('filesystem'));
    }

    /**
     * @param BundleInterface $bundle
     * @return array
     */
    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {

        $reflClass = new \ReflectionClass(get_class($this));

        $skeletonDirs = parent::getSkeletonDirs($bundle);
        $skeletonDirs[] = dirname($reflClass->getFileName()) . '/../Resources/skeleton';
        $skeletonDirs[] = dirname($reflClass->getFileName()) . '/../Resources';

        return $skeletonDirs;
    }

    /**
     * Command description shown when running the command with the "--help" option
     * @return string
     */
    protected function getHelpText()
    {
    	return <<<EOT
The <info>voryx:generate:rest</info> command generates a REST api based on a Doctrine entity.

<info>php app/console voryx:generate:rest --entity=AcmeBlogBundle:Post --route-prefix=post_admin</info>

Every generated file is based on a template. There are default templates but they can be overriden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/SensioGeneratorBundle/skeleton/rest
APP_PATH/Resources/SensioGeneratorBundle/skeleton/rest</info>

And

<info>__bundle_path__/Resources/SensioGeneratorBundle/skeleton/form
__project_root__/app/Resources/SensioGeneratorBundle/skeleton/form</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT;
    }
}
