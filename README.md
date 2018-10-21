<p align="center">
    <a href="https://sylius.com" target="_blank">
        <img src="https://demo.sylius.com/assets/shop/img/logo.png" />
    </a>
</p>

<h1 align="center">Sylius Standard Edition</h1>

<p align="center">This is Sylius Standard Edition repository for starting new projects.</p>

About
-----

Sylius is the first decoupled eCommerce framework based on [**Symfony**](http://symfony.com) and [**Doctrine**](http://doctrine-project.org). 
The highest quality of code, strong testing culture, built-in Agile (BDD) workflow and exceptional flexibility make it the best solution for application tailored to your business requirements. 
Enjoy being an eCommerce Developer again!

Powerful REST API allows for easy integrations and creating unique customer experience on any device.

We're using full-stack Behavior-Driven-Development, with [phpspec](http://phpspec.net) and [Behat](http://behat.org)

Documentation
-------------

Documentation is available at [docs.sylius.org](http://docs.sylius.org).

Installation
------------

Requirements: PHP7.2 and yarn.

```bash
$ git clone https://github.com/rimas-kudelis/zetshop.git
$ cd zetshop
$ cp .env.dist .env
$ nano .env # Use your favourite text editor to update DATABASE_URL value in .env
$ wget http://getcomposer.org/composer.phar
$ php composer.phar install
$ yarn install
$ yarn build
$ php bin/console sylius:install
$ php bin/console server:start
$ open http://localhost:8000/
```

JSON import
-----------

Product import is implemented as a Symfony console command. To see its help, run:
```bash
$ php bin/console app:import:products --help
```

The most basic mode of operation of the command looks like this:
```bash
$ php bin/console app:import:products /path/to/json-file default
```

Above, `default` refers to the code of the channel to which all imported products will be published. More than one channel can be specified, separated by spaces (or none, in which case product prices will not be imported from JSON).

Optionally, the importer can also create taxons for categories and producers (manufacturers) and add these taxons to the products created. Taxons will be called `cat_xxx` and `prod_xxx`, where `xxx` will be the original value of the `category_id` and `producer_id` field, respectively.

Update of existing products is also supported. However it may result in unintended consequences if multiple products share same value of the `ean` field for some reason.

Finally, the number of records being imported may be limited by using `--skip-records` and/or `--max-records` options.


Troubleshooting
---------------

If something goes wrong, errors & exceptions are logged at the application level:

```bash
$ tail -f var/log/prod.log
$ tail -f var/log/dev.log
```

If you are using the supplied Vagrant development environment, please see the related [Troubleshooting guide](etc/vagrant/README.md#Troubleshooting) for more information.

Contributing
------------

Would like to help us and build the most developer-friendly eCommerce platform? Start from reading our [Contributing Guide](http://docs.sylius.org/en/latest/contributing/index.html)!

Stay Updated
------------

If you want to keep up with the updates, [follow the official Sylius account on Twitter](http://twitter.com/Sylius) and [like us on Facebook](https://www.facebook.com/SyliusEcommerce/).

Bug Tracking
------------

If you want to report a bug or suggest an idea, please use [GitHub issues](https://github.com/Sylius/Sylius/issues).

Community Support
-----------------

Have a question? Join our [Slack](https://slackinvite.me/to/sylius-devs) or post it on [StackOverflow](http://stackoverflow.com) tagged with "sylius". You can also join our [group on Facebook](https://www.facebook.com/groups/sylius/)!

MIT License
-----------

Sylius is completely free and released under the [MIT License](https://github.com/Sylius/Sylius/blob/master/LICENSE).

Authors
-------

Sylius was originally created by [Paweł Jędrzejewski](http://pjedrzejewski.com).
See the list of [contributors from our awesome community](https://github.com/Sylius/Sylius/contributors).
