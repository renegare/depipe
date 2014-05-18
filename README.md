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
### What Next (this list is never complete!)

- [x] connect:command - connect an instance to a load balancer
- [ ] 'user land' config to represent connected piping
- - [ ] parameters: root config values that all pipes inherit their initial configuration from
- -  - [x] need to allow config values to be retrieved from the environment e.g credential secrets
- - [ ] pipelines: connected pipes
- - - [ ] Pipe names: [command] > [free text description]:
- - - - [ ] Name-spaced commands: [name-spaced]\([command]) > [free text description]:
- - - [ ] Special keywords @*:
- - - - [ ] from: pipes can choose to inherit parameters from any other pipe in their ancestory (default is the immediate parent|root paremeters)
- - - - [ ] wait: pipes can halt their process until another pipe from another pipeline has completed (need some kind of event system!)
- [ ] implement commands
- - [ ] find:command - find instances
- - [ ] kill:command - terminate instances
- - [ ] delete:command - delete an image
- [ ] implement: tasks
- [ ] actually use the damn thing!
- [ ] tag v0.1.0
