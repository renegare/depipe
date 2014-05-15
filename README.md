DePipe README
===========

[![Build Status](https://travis-ci.org/renegare/depipe.png?branch=master)](https://travis-ci.org/renegare/depipe)


What is DePipe?
-------------

A cli tool that allows you to build, test and deploy an application server.

Well thats the goal ... currently it does not do anything! DO NOT TRY TO USE!


Requirements
------------

* PHP 5.4
* composer (preferably latest)


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
