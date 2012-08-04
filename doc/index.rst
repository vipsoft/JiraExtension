=============
JiraExtension
=============

The Jira extension allows features to be loaded from Jira issues.

Installation
============
This extension requires:

* Behat 2.4+
* Mink 1.4+
* Atlassian Jira (SOAP API)

Through Composer
----------------
1. Set dependencies in your **composer.json**:

.. code-block:: js

    {
        "require": {
            ...
            "vipsoft/jira-extension": "*"
        }
    }

2. Install/update your vendors:

.. code-block:: bash

    $ curl http://getcomposer.org/installer | php
    $ php composer.phar install

Through PHAR
------------
Download the .phar archive:

* `jira_extension.phar <http://behat.org/downloads/jira_extension.phar>`_

Configuration
=============
Activate extension in your **behat.yml** and define your Jira connection settings:

.. code-block:: yaml

    # behat-client.yml
    default:
      # ...
      extensions:
        VIPSoft\JiraExtension\Extension:
          host: http://jira.example.com:8080/
          user: jirauser
          password: secret
          jql: "summary ~ 'Feature'"
          comment_on_pass: false
          comment_on_fail: true
          reopen_on_fail: false

Copyright
=========
Copyright (c) 2012 Anthon Pang.  See **LICENSE** for details.

Contributors
============
* Anthon Pang `(robocoder) <http://github.com/robocoder>`_
