<?php


namespace Voryx\RESTGeneratorBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Voryx\RESTGeneratorBundle\Form\DataTransformer\ArrayToIdTransformer;

/**
 * Class VoryxEntityType
 * @package Voryx\RESTGeneratorBundle\Form\Type
 */
class VoryxEntityType extends EntityType
{

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @param EntityManager $em
     */

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $view_transformer = new ArrayToIdTransformer($this->em, null);
        $builder->addViewTransformer($view_transformer);
//        $model_transformer = new UserToUsernameTransformer()
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'invalid_message' => 'The selected entity does not exist',
            )
        );
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return EntityType::class;
    }
}
