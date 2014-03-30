# Voryx REST Generator Bundle

## About

A CRUD like REST Generator

## Features

* Generators RESTful action from entity
* Simplifies setting up a RESTful Controller


## Installation
Require the "voryx/restgeneratorbundle" package in your composer.json and update your dependencies.

    $ php composer.phar require voryx/restgeneratorbundle dev-master

Add the VoryxRestGeneratorBundle to your application's kernel along with other dependencies:

    public function registerBundles()
    {
        $bundles = array(
            ...
              new Voryx\RESTGeneratorBundle\VoryxRESTGeneratorBundle(),
              new FOS\RestBundle\FOSRestBundle(),
              new JMS\SerializerBundle\JMSSerializerBundle($this),
              new Nelmio\CorsBundle\NelmioCorsBundle(),
            ...
        );
        ...
    }

## Configuration

This bundle depends on a number of other symfony bundles, so they need to be configured in order for the generator to work properly

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

## Generating the Controller

Generate the REST controller

    php app/console voryx:generate:rest
    
This will guide you through the generator which will generate a RESTful controller for an entity.

You will still need to Add a route for each generated entity:  (Hopefully this will be added to the generator soon)

    api_posts:
        type:     rest
        resource: "@AcmeDemoBundle/Controller/PostController.php"
        prefix: /api
