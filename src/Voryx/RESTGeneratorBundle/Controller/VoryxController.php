<?php
namespace Voryx\RESTGeneratorBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
class VoryxController extends Controller
{
    /**
     * Create a form without a name
     *
     * @param null $type
     * @param null $data
     * @param array $options
     *
     * @return Form|FormInterface
     */
    public function createForm($type = null, $data = null, array $options = array())
    {
        $form = $this->container->get('form.factory')->createNamed(
            null, //since we're not including the form name in the request, set this to null
            $type,
            $data,
            $options
        );
        return $form;
    }
    /**
     * Get rid on any fields that don't appear in the form
     *
     * @param Request $request
     * @param Form $form
     */
    protected function removeExtraFields(Request $request, Form $form)
    {
        $data     = $request->request->all();
        $children = $form->all();
        $data     = array_intersect_key($data, $children);
        $request->request->replace($data);
    }
}
