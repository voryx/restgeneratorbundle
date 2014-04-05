<?php

namespace Voryx\RESTGeneratorBundle\Form\DataTransformer;


use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Validator\Tests\Fixtures\EntityInterface;

class ArrayToIdTransformer implements DataTransformerInterface
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string Name spaced entity
     */
    private $class;

    /**
     * @param EntityManager $em
     * @param $class
     */
    public function __construct(EntityManager $em, $class)
    {
        $this->em = $em;
        $this->class = $class;
    }


    /**
     * @param mixed $data
     * @return mixed
     */
    public function transform($data)
    {
        return $data;
    }

    /**
     * Transforms a string or array into an id.
     */
    public function reverseTransform($data)
    {

        if (!$data) {
            return null;
        }

        if (is_scalar($data)) {
            return $data;
        }

        //@todo lookup entity's identifier.  Assuming that "id" is the identifier
        if (is_array($data) && isset($data['id'])) {
            return $data['id'];
        }

        return null;

    }
}