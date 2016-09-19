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

/**
 * Generates a REST api for a Doctrine entity.
 */
class GenerateDoctrineRESTCommand extends GenerateDoctrineCrudCommand
{
	
	const DATA_BUNDLE = 'NoIncQrisDataBundle';
	const API_BUNDLE = 'NoIncQrisApiBundle';
    /**
     * @var
     */
    private $formGenerator;

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setDefinition(
            array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('parent', '', InputOption::VALUE_OPTIONAL, 'The parent of the entity (used to create additional endpoints nested under the parent)'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('overwrite', '', InputOption::VALUE_NONE, 'Do not stop the generation if rest api controller already exist, thus overwriting all generated files'),
                new InputOption('resource', '', InputOption::VALUE_NONE, 'The object will return with the resource name'),
                new InputOption('document', '', InputOption::VALUE_NONE, 'Use NelmioApiDocBundle to document the controller'),
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
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Doctrine2 REST api generator');

        // namespace
        $output->writeln(
            array(
                '',
                'This command helps you generate a REST api controller.',
                '',
                'First, you need to give the entity for which you want to generate a REST api.',
                'You can give an entity that does not exist yet and the wizard will help',
                'you defining it.',
                '',
                'Ex: <comment>Post</comment>.',
                '',
            )
        );

        // entity name
        $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('entity')), $input->getOption('entity'));
        $entity = $questionHelper->ask($input, $output, $question);
        $input->setOption('entity', $entity);
        
        // entity's parent
        $question = new Question($questionHelper->getQuestion('Entity\'s parent', $input->getOption('parent')), $input->getOption('parent'));
        $parent = $questionHelper->ask($input, $output, $question);
        $input->setOption('parent', $parent);
        
        // route prefix
        $prefix = 'api/v1';
        $prefix = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes prefix', '/' . $prefix), '/' . $prefix));
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(
            array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                '',
                sprintf("You are going to generate a REST api controller for \"<info>%s:%s</info>\"", self::DATA_BUNDLE, $entity),
                '',
            )
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = $input->getOption('entity');
        $forceOverwrite = $input->getOption('overwrite');
        $parent = $input->getOption('parent');
        $resource    = $input->getOption('resource');
        $document    = $input->getOption('document');

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'REST api generation for "' . $entity . '"');

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace(self::DATA_BUNDLE) . '\\' . $entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle(self::DATA_BUNDLE);
        $targetBundle = $this->getContainer()->get('kernel')->getBundle(self::API_BUNDLE);

        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $targetBundle, $entity, $parent, $metadata[0], $forceOverwrite, $resource, $document);

        $output->writeln('Generating the REST api code: <info>OK</info>');

        $errors = array();

        // form, override every time.
        
        $this->generateForm($bundle, $entity, $metadata, true);
        $output->writeln('Generating the Form code: <info>OK</info>');

        $questionHelper->writeGeneratorSummary($output, $errors);
    }



    /**
     * @param QuestionHelper $questionHelper
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param BundleInterface $bundle
     * @param $entity
     * @param $prefix
     * @return array
     */
    protected function updateRouting(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, BundleInterface $bundle, $format, $entity, $prefix)
    {
        $auto = true;

        $output->write('Importing the REST api routes: ');
        $this->getContainer()->get('filesystem')->mkdir($bundle->getPath() . '/Resources/config/');
        $routing = new RoutingManipulator($this->getContainer()->getParameter('kernel.root_dir') . '/config/routing.yml');
        try {
            $ret = $auto ? $routing->addResource($bundle->getName(), '/' . $prefix, $entity) : false;
        } catch (\RuntimeException $exc) {
            $ret = false;
        }

        if (!$ret) {
            $help = sprintf(
                "        <comment>resource: \"@%s/Controller/%sRESTController.php\"</comment>\n",
                $bundle->getName(),
                $entity
            );
            $help .= sprintf("        <comment>type:   %s</comment>\n", 'rest');
            $help .= sprintf("        <comment>prefix:   /%s</comment>\n", $prefix);

            return array(
                '- Import this resource into the Apps routing file',
                sprintf('  (%s).', $this->getContainer()->getParameter('kernel.root_dir') . '/config/routing.yml'),
                '',
                sprintf(
                    '    <comment>%s:</comment>',
                    substr($bundle->getName(), 0, -6) . '_' . $entity . ('' !== $prefix ? '_' . str_replace('/', '_', $prefix) : '')
                ),
                $help,
                '',
            );
        }
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
