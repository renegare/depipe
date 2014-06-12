DePipe README
===========

[![Build Status](https://travis-ci.org/renegare/depipe.png?branch=master)](https://travis-ci.org/renegare/depipe)
[![Coverage Status](https://coveralls.io/repos/renegare/depipe/badge.png)](https://coveralls.io/r/renegare/depipe)

What is DePipe?
-------------

A cli tool that allows you to build, test and deploy an application server (and an experimental project to familiarise my self with aws-php-sdk v2+).

Well thats the goal ... currently it does not do anything! DO NOT TRY TO USE!

Requirements
------------

* PHP 5.4
* composer (preferably latest)

CLI Structure
-------------

So ... still playing about with this idea ... but ... the project can be broken down to the following class types:

* Command (Task Master): Symfony based Command class, that relies on classes of type Task to do the work
* Task: The actual classes that do the work ... work here must be super concise and minimal. Working with AWS can be a pain and testing it all works is a pain!

*Note*: Everything is reasonably Psr\Log aware, makes it easier to know when something has gone wrong!

Building
--------

Currently uses box-project to convert source into a single file executable (PHAR).

Given you have box and composer installed on your machine, you should be able to run ```./build.sh``` which will
compile (really zip up!) a phar ready for use :)

Test
----

Check out the repo and from the top level directory run the following command:
```
$ composer update && vendor/bin/phpunit
```

*NOTE:* You need composer installed on your machine

Help Needed
-----------

- [ ] Shell script to build the cli tool into a phar and optionally install it somewhere in a PATH
- [ ] Better arch idea around managing various cloud providers e.g rackspace (currently targeting aws)
- [ ] Better arch idea around testing + deploying to a shared host (not everyone needs to launch an instance per app!)

----------------------
### Road Map to v0.1.0 (prototype-release)

#### Cloud Platform Interfaces
- [x] App\Platform\ClientInterface
- [x] App\Platform\ImageInterface
- [x] App\Platform\InstanceInterface
- [x] App\Platform\LoadBalancerInterface

#### Aws Platform Concrete Classes
- [x] App\Platform\Aws\Client
- [x] App\Platform\Aws\Image
- [x] App\Platform\Aws\Instance
- [x] App\Platform\Aws\LoadBalancer

#### Release
- [ ] tag v0.1.0

---

### Road Map to v0.2.0 (useful-release)

#### Configuration (depipe.yml.dist)
- [ ] pipes need to clean up after themselves
- [ ] pipes that create/modify (not including search or destructive pipes) should be able to reverse/undo what they have done?
- [ ] introduce concept of private cloud?
- [ ] file upload component ... remove string regex madness!
- [ ] pipelines: multi pipe line config
> - Run pipelines in isolated processes (however they all start from the root config)
> - Allow special @wait param in pipe config (wait for an event in a parallel pipeline)

#### Commands
- [ ] find:instances - find instances
- [ ] kill - terminate instances
- [ ] find:image - find instances (half done ... but needs a rethink)
- [ ] delete - delete an image

#### Release
- [ ] tag v0.2.0

### Road Map to v0.3.0 (vendor-independant-release)
- [ ] Review vendor specific classes, normalize and simplify
- [ ] Create another vender set of classes
- [ ] Documentation!
