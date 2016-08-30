# Voryx REST Generator Bundle
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ac1842d9-4e36-45cc-8db1-b97e2e62540e/big.png)](https://insight.sensiolabs.com/projects/ac1842d9-4e36-45cc-8db1-b97e2e62540e)

## About

A CRUD like REST Generator

## Features

* Generators RESTful action from entity
* Simplifies setting up a RESTful Controller


## Installation
In composer.json:

```php
"require" : {
  ...
  "noinc/restgeneratorbundle": "dev-master"
  ...
},
"repositories" : [ {
  ...
}, {
  "type" : "git",
  "url" : "git@github.com:noinc/restgeneratorbundle",
  "name" : "restgeneratorbundle"
} ],
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

This bundle depends on a number of other Symfony bundles, so they need to be configured in order for the generator to work properly

```yaml
framework:
    csrf_protection: ~

fos_user:
    db_driver: orm
    firewall_name: main
    user_class: NoInc\Qris\DataBundle\Entity\User

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


Generate the REST controller

```bash
$ php app/console voryx:generate:rest
```
    
This will guide you through the generator which will generate a RESTful controller for an entity.


## Example

Create a new entity called 'Post':

```bash
$ php app/console doctrine:generate:entity --entity=Post --format=annotation --fields="name:string(255) description:string(255)" --no-interaction
```

Update the database schema:

```bash
$ php app/console doctrine:schema:update --force
```

Generate the API controller:

```bash
$ php app/console voryx:generate:rest --entity="Post"
```

### Using the API
If you selected the default options you'll be able to start using the API like this:

Creating a new post (`POST`)

```bash
$ curl -i -H "Content-Type: application/json" -X POST -d '{"name" : "Test Post", "description" : "This is a test post"}' http://localhost/app_dev.php/api/posts
```

Updating (`PUT`)

```bash
$ curl -i -H "Content-Type: application/json" -X PUT -d '{"name" : "Test Post 1", "description" : "This is an updated test post"}' http://localhost/app_dev.php/api/posts/1
```

Get all posts (`GET`)

```bash
$ curl http://localhost/app_dev.php/api/posts
```

Get one post (`GET`)

```bash
$ curl http://localhost/app_dev.php/api/posts/1
```


Delete (`DELETE`)

```bash
$ curl -X DELETE  http://localhost/app_dev.php/api/posts/1
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
