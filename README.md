# Voryx REST Generator Bundle

## About

A CRUD like REST Generator

## Features

* Generators RESTful action from entity
* Simplifies setting up a RESTful Controller


## Installation
Require the "voryx/restgeneratorbundle" package in your composer.json and update your dependencies.

```bash
$ php composer.phar require voryx/restgeneratorbundle dev-master
```

Add the VoryxRestGeneratorBundle to your application's kernel along with other dependencies:

```php
public function registerBundles()
{
    $bundles = array(
        //...
          new Voryx\RESTGeneratorBundle\VoryxRESTGeneratorBundle(),
          new FOS\RestBundle\FOSRestBundle(),
          new JMS\SerializerBundle\JMSSerializerBundle($this),
          new Nelmio\CorsBundle\NelmioCorsBundle(),
        //...
    );
    //...
}
```

## Configuration

This bundle depends on a number of other symfony bundles, so they need to be configured in order for the generator to work properly

```yaml
framework:
    csrf_protection: false #only use for public API

fos_rest:
    routing_loader:
        default_format: json
    param_fetcher_listener: true
    body_listener: true
    #disable_csrf_role: ROLE_USER
    body_converter:
        enabled: true
    view:
        view_response_listener: force

nelmio_cors:
    defaults:
        allow_credentials: false
        allow_origin: []
        allow_headers: []
        allow_methods: []
        expose_headers: []
        max_age: 0
    paths:
        '^/api/':
            allow_origin: ['*']
            allow_headers: ['*']
            allow_methods: ['POST', 'PUT', 'GET', 'DELETE']
            max_age: 3600

sensio_framework_extra:
    request: { converters: true }
    view:    { annotations: false }
    router:  { annotations: true }
```

## Generating the Controller

Right now, the generator uses forms created by the doctrine generator. You must create these to use the generated controller. (If you don't create them prior to running the voryx:generate:rest command, you will get an error, but it will still work if you just create them afterwards)

```bash
$ php app/console doctrine:generate:form AcmeDemoBundle:Post
```

Generate the REST controller

```bash
$ php app/console voryx:generate:rest
```
    
This will guide you through the generator which will generate a RESTful controller for an entity.

You will still need to Add a route for each generated entity:  (Hopefully this will be added to the generator soon)

```yaml
api_posts:
    type:     rest
    resource: "@AcmeDemoBundle/Controller/PostController.php"
    prefix: /api
```

## Related Entities

If you want the form to be able to convert related entities into the correct entity id on POST, PUT or PATCH, use the voryx_entity form type

```php
#Form/PostType()

    ->add(
        'user', 'voryx_entity', array(
            'class' => 'Acme\Bundle\Entity\User'
        )
    )
```
