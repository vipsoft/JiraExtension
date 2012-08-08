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

    # behat.yml
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
          cache_directory: /tmp/behat-jira

Settings
--------
Most of the settings are self-evident.  **jql** is a query (written in JQL) to locate Jira issues that contain Behat features.  Example:

.. code-block:: yaml

    # behat.yml
    default:
      # ...
      extensions:
        VIPSoft\JiraExtension\Extension:
          ...
          jql: "summary ~ 'Feature'"
          ...

You can be more specific in your search if you create a custom issue type, and use that in the query.

By default caching is disabled.  If a relative path is specified, the cache directory is resolved relative to the current working directory.

Limitations
-----------
The number of issues (and hence, features) returned by the SOAP API is constrained by jira.search.views.max.limit and jira.search.views.max.unlimited.group JIRA properties.

If the Jira user has only read-access to issues, the extension will not be able to comment on the pass/fail of scenarios.

The ability to reopen issues is subject to workflow progression rules.

You should periodically clear the cache as it does not detect issues that may have been deleted or moved, and/or issues that no longer meet the **jql** criteria.

Usage
=====
1. Create Issue

2. Enter "Summary", e.g., "Feature: Jira integration"

.. note::

   JiraExtension will auto-tag the assignee and fixVersions.

3. Enter "Description" containing the feature, e.g.,

.. code-block:: gherkin

    {code:none}
    Feature: Jira integration
        In order to facilitate the authoring of Behat features by non-developers
        As a developer
        I want to write an extension to load features from Jira issues.

        Scenario: Load Me!
            Given I am a Jira issue
            And I contain a Behat feature
            When I am loaded by JiraExtension
            Then I should parsed by Gherkin
    {code}

4. Run a specific test, specifying either a URL or a jira: issue "number"

.. code-block:: bash

    bin/behat jira:BDD-1

    bin/behat http://jira.example.com:8080/browse/BDD-1

5. Or run your entire Jira-based feature suite:

    bin/behat http://jira.example.com:8080/

.. note::

   The trailing slash is mandatory.

Source
======
`Github <https://github.com/vipsoft/JiraExtension>`_

Copyright
=========
Copyright (c) 2012 Anthon Pang.  See **LICENSE** for details.

Contributors
============
* Anthon Pang `(robocoder) <http://github.com/robocoder>`_
* Jakub Zalas `(jakzal) <https://github.com/jakzal>`_
* `Others <https://github.com/vipsoft/JiraExtension/graphs/contributors>`_
