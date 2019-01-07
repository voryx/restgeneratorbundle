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
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Voryx\RESTGeneratorBundle\Generator\DoctrineRESTGenerator;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Voryx\RESTGeneratorBundle\Manipulator\RoutingManipulator;

/**
 * Generates a REST api for a Doctrine entity.
 *
 */
class GenerateDoctrineRESTCommand extends GenerateDoctrineCrudCommand
{
    /**
     * @var
     */
    private $formGenerator;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setDefinition(
            array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('route-format', '', InputOption::VALUE_REQUIRED, 'The format used for generation of routing (yml or annotation)', 'yml'),
                new InputOption('service-format', '', InputOption::VALUE_REQUIRED, 'The format used for generation of services (yml or xml)', 'yml'),
                new InputOption('test', '', InputOption::VALUE_REQUIRED, 'Generate a test for the given authentication mode (oauth2, no-authentication, none)', 'none'),
                new InputOption('overwrite', '', InputOption::VALUE_NONE, 'Do not stop the generation if rest api controller already exist, thus overwriting all generated files'),
                new InputOption('resource', '', InputOption::VALUE_NONE, 'The object will return with the resource name'),
                new InputOption('document', '', InputOption::VALUE_NONE, 'Use NelmioApiDocBundle to document the controller')
            )
        )
            ->setDescription('Generates a REST api based on a Doctrine entity')
            ->setHelp(
                <<<EOT
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
EOT
            )
            ->setName('voryx:generate:rest')
            ->setAliases(array('generate:voryx:rest'));
    }

    /**
     * @see Command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $format         = $input->getOption('route-format');
        $service_format = $input->getOption('service-format');
        $prefix         = $this->getRoutePrefix($input, $entity);
        /** @var bool $forceOverwrite */
        $forceOverwrite = $input->getOption('overwrite');
        $test           = $input->getOption('test');

        $questionHelper->writeSection($output, 'REST api generation');

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle) . '\\' . $entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);
        $resource    = $input->getOption('resource');
        $document    = $input->getOption('document');
        $constraints = array();

        $constraintMetadata = null;
        try
        {
            /** @var \Symfony\Component\Validator\Validator\RecursiveValidator $validator */
            $validator = $this->getContainer()->get('validator');

            /** @var ClassMetadata $constraintMetadata */
            $constraintMetadata = $validator->getMetadataFor(new $entityClass);
            foreach($constraintMetadata->getConstrainedProperties() as $property)
            {
                //var_dump($constraint_metadata->getPropertyMetadata($property));
                $constraints[$property] = $constraintMetadata->getPropertyMetadata($property)[0]->constraints;
            }
        }
        catch(ServiceNotFoundException $snfex)
        {
            //no constraints are checked
            $output->writeln($snfex->getMessage());
        }
        catch(\Exception $ex)
        {
            $output->writeln($ex->getMessage());
        }

        if ($constraintMetadata === null)
        {
            if ($test !== 'none')
            {
                $output->writeln('<error>No class constraint metadata found for entity ' . $entityClass . '</error>');
            }
        }

        /** @var DoctrineRESTGenerator $generator */
        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $entity, $metadata[0], $constraints, $prefix, $forceOverwrite, $resource, $document, $format, $service_format, $test);

        $output->writeln('Generating the REST api code: <info>OK</info>');
        if ($test === 'oauth2')
        {
            $output->writeln('Please make sure you check the Tests/oauthBase.php and fill in a correct username/password on line 17/18 before running the test.');
        }

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // form
        $this->generateForm($bundle, $entity, $metadata, $forceOverwrite);
        $output->writeln('Generating the Form code: <info>OK</info>');

        // create route
        $runner($this->updateRouting($questionHelper, $input, $output, $bundle, $format, $entity, $prefix));


        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    /**
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
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
                '',
            )
        );

        $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('entity')), $input->getOption('entity'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
        $entity = $questionHelper->ask($input, $output, $question);

        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        //routing format
        $format = $input->getOption('route-format');
        $output->writeln(
            array(
                '',
                'Determine the routing format (yml or annotation).',
                ''
            )
        );
        $question = new Question($questionHelper->getQuestion('Routing format', $format), $format);
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'));
        $format = $questionHelper->ask($input, $output, $question);

        $input->setOption('route-format',$format);

        // route prefix
        $prefix = 'api';
        $output->writeln(
            array(
                '',
                'Determine the routes prefix (all the API routes will be "mounted" under this',
                'prefix: /prefix/, /prefix/posts, ...).',
                '',
            )
        );

        $prefix = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes prefix', '/' . $prefix), '/' . $prefix));
        $input->setOption('route-prefix', $prefix);

        //service format
        $serviceFormat = $input->getOption('service-format');
        $output->writeln(
            array(
                '',
                'Determine the service format (yml or xml).',
                ''
            )
        );
        $question = new Question($questionHelper->getQuestion('Service format', $serviceFormat), $serviceFormat);
        $question->setValidator(array('Voryx\RESTGeneratorBundle\Command\Validators', 'validateServiceFormat'));
        $serviceFormat = $questionHelper->ask($input, $output, $question);

        $input->setOption('service-format',$serviceFormat);

        //testing mode
        $output->writeln(
            array(
                '',
                'Determine what kind of test you want to have generated (if any)',
                'Possible values are none (no tests), no-authentication and oauth2',
                ''
            )
        );
        $question = new Question($questionHelper->getQuestion('What type of tests do you want to generate?', $input->getOption('test')),$input->getOption('test'));
        $question->setValidator(array('Voryx\RESTGeneratorBundle\Command\Validators', 'validateTestFormat'));
        $test = $questionHelper->ask($input, $output, $question);

        $input->setOption('test',$test);

        // summary
        $output->writeln(
            array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                '',
                sprintf("You are going to generate a REST api controller for \"<info>%s:%s</info>\"", $bundle, $entity),
                '',
            )
        );
    }

    /**
     * @param QuestionHelper $questionHelper
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param BundleInterface $bundle
     * @param $format
     * @param $entity
     * @param $prefix
     * @return array
     */
    protected function updateRouting(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, BundleInterface $bundle, $format, $entity, $prefix)
    {
        $auto = true;
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Confirm automatic update of the Routing', 'yes', '?'), true);
            $auto     = $questionHelper->ask($input, $output, $question);
        }

        $output->write('Importing the REST api routes: ');
        $this->getContainer()->get('filesystem')->mkdir($bundle->getPath() . '/Resources/config/');

        if ($format === 'annotation')
        {
            $bundle_name = str_replace("Bundle", "", $bundle->getName());
            $route_name = strtolower($bundle_name);
            $yml_file_location = $this->getContainer()->getParameter('kernel.root_dir') . '/config/routing.yml';
            try
            {
                $yml_file = Yaml::parse(file_get_contents($yml_file_location));
            }
            catch(ParseException $pex)
            {
                return array(
                    '<error>Could not read yaml file '.$yml_file_location.'</error>',
                    'On line',
                    'parsed line: '.$pex->getParsedLine() . ' and current line '. $pex->getLine(),
                    'With snippet '.$pex->getSnippet(),
                    'Exception message:',
                    '<error>'.$pex->getMessage().'</error>'
                    );
            }
            $resource_location = sprintf('@%s/Controller/', $bundle->getName());

            $bundle_routing = null;
            if (array_key_exists($route_name, $yml_file))
            {
                $bundle_routing = $yml_file[$route_name];
            }

            if (is_array($bundle_routing))
            {
                if (array_key_exists('type',$bundle_routing) && array_key_exists('resource',$bundle_routing) && $bundle_routing['type'] === $format && $bundle_routing['resource'] === $resource_location)
                {
                    //all is good
                    return array();
                }
            }

            $bundle_routing = array(
                'resource' => $resource_location,
                'type' => $format
            );

            $yml_file[$route_name] = $bundle_routing;

            $yml_content = Yaml::dump($yml_file, 2);
            file_put_contents($yml_file_location, $yml_content);

            return array();
        }

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
        /** @var Filesystem $fileSystem */
        $fileSystem = $this->getContainer()->get('filesystem');

        return new DoctrineRESTGenerator($fileSystem);
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

}
