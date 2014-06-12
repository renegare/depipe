# DePipe README

[![Build Status](https://travis-ci.org/renegare/depipe.png?branch=master)](https://travis-ci.org/renegare/depipe)
[![Coverage Status](https://coveralls.io/repos/renegare/depipe/badge.png)](https://coveralls.io/r/renegare/depipe)

## What is DePipe?

A cli tool that allows you to build, test and deploy an application server (and an experimental project to familiarise my self with [aws-php-sdk][3] v2+).

Well thats the goal ...

## Basic Usage Examples:

### Build Executable (depipe.phar)

```
[prompt]$ ./build.sh
```

### Run Commands

```
# shows options
[prompt]$ ./depipe.phar

# run deployment pipeline
[prompt]$ ./depipe.phar pipeline --config depipe-config.yml --log depipe.log
```

### Example configuration (YAML)

```
# depipe-config.yml
parameters:
    credentials:
        class: App\Platform\Aws\Client
        key: {{env AWS_KEY}}
        secret: {{env AWS_SECRET}}
        region: us-east-1
        sleep.interval: 20 # how long to wait before retrying *something* e.g instances to be ready

    image: ami-XXXXX
    instance.config: # for options see http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.Ec2.Ec2Client.html#_runInstances
        InstanceType: m3.large
        UserData: [...] # prefixes "#cloud-config\n" and base64 encodes
        NetworkInterfaces: #if you launching into a VPC
            -
                DeviceIndex: 0
                Groups: [sg-XXXXX]
                SubnetId: subnet-XXXXX
                DeleteOnTermination: true
                AssociatePublicIpAddress: true
    instance.access: # will delete and recreate a key to be used
        class: App\Platform\Aws\InstanceAccess
        user: root
        key.name: web.app
        connect.attempts: 10
        connect.sleep: 20

pipeline: # runs each step (pipe) sequentially
    build base web app image:
        image.name: base.web.app.{{time Ym}}
        scripts:
            - yum clean all
            - yum update -y
            - yum install httpd php -y
            - echo '{{file resource/key.pub}}' > /root/.ssh/authorized_keys
            - echo 'Base Web App Instance Configured'

    build release candidate web.app image:
        image.name: web.app.{{time YmdH}}
        instance.access: # from here on we launch instances with our own private key
            class: App\Util\InstanceAccess\SSHAccess
            user: root
            key: '{{file resource/key.pem}}'
            connect.attempts: 10
            connect.sleep: 20
        scripts:
            - echo '{{file resource/app-vhost.conf}}' > /etc/httpd/conf.d/app.conf
            - git clone https://github.com/your/app /app
            - echo 'Candidate Web App Installed'

    launch web.app test instance:
        scripts:
            - yum install php-xdebug mysql-server php-mysqli php-imagick php-gd -y
            - service mysqld start
            - service memcached start
            - service elasticsearch start
            - echo 'CREATE DATABASE app_db;' | mysql -u root
            - cd /app && composer update --prefer-dist # should install dev deps
            - cd /app && php -d memory_limit=512M vendor/bin/phpunit --coverage-text
            - echo 'Finished Testing Application'

    launch production ready web.app instance(s):
        instance.count: 2
        scripts:
            - echo '{{file resource/app-production-config.json}}' > /app/config/production.json
            - service elasticsearch restart
            - service iptables restart
            - service httpd restart
            - cat /dev/null > /root/.ssh/authorized_keys # no more access to this instance! #youDecide
            - echo 'Configured Production Web App'

    connect web.app instances to loadbalancer:
        scripts: ~ # without this it will run the previous value for 'scripts' #bug
        load.balancer: web-elb
```

Requirements
------------

* PHP 5.4
* [composer][1] (preferably latest)
* [box][2] (for building a phar)

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
- [ ] Better arch idea around testing + deploying to a shared host (not everyone needs to launch an instance per app release! #possiblyOutsideOfScope)

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
- [x] tag v0.1.0

---

### Road Map to v0.2.0 (useful-release)
- [ ] Review goals below (make it more feasible)

#### Configuration (depipe.yml.dist)
- [ ] pipes need to clean up after themselves
- - [ ] Review current pipe configuration (but retain simplicity)
- [ ] Review configuration options (instance.access needs to be mutable and not crash)
- [ ] pipes that create/modify (not including search or destructive pipes) should be able to reverse/undo what they have done?
- [ ] file upload component ... remove string regex madness!
- [ ] pipelines: multi pipe line configuration (run one or all at the same damn time #Future)
> - Run pipelines in isolated processes (however they all start from the root config)
> - Allow special @wait param in pipe config (wait for an event in a parallel pipeline - #maybeToAmbitious)
- [ ] Documentation of usage (better examples)
- [ ] Replace references to internal classes within depipe to shorthand key names / enum
- [ ] get rid of "symfony/http-foundation" dep
- [ ] update all deps to latest (if beneficial)

#### Commands
- [ ] kill - terminate instances
- [ ] delete - delete an image
- [ ] find:instances - find instances
- [ ] find:image - find instances (half done ... but needs a rethink)

#### Release
- [ ] tag v0.2.0

### Road Map to v0.3.0 (security-release)
- [ ] Hmmm ...

### Road Map to v0.4.0 (vendor-independant-release)
- [ ] Review vendor specific classes, normalize and simplify
- [ ] Create another vender set of classes (rackspace? digital ocean?)
- [ ] Documentation of classes



[1]: https://getcomposer.org/download/
[2]: https://github.com/kherge/php-box
[3]: http://aws.amazon.com/sdkforphp/
