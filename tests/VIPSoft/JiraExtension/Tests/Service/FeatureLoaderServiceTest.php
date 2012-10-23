<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Tests\Service;

use VIPSoft\JiraExtension\Service\FeatureLoaderService;

/**
 * Feature Loader service test
 *
 * @group Service
 *
 * @author Jakub Zalas <jakub@zalas.pl>
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 * @author Pascal Rehfeldt <Pascal@Pascal-Rehfeldt.com>
 */
class FeatureLoaderServiceTest extends \PHPUnit_Framework_TestCase
{
    private $jiraService = null;

    private $cacheService = null;

    private $gherkinParser = null;

    private $featureLoader = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->jiraService = $this->getMockBuilder('VIPSoft\JiraExtension\Service\JiraService')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cacheService = $this->getMockBuilder('VIPSoft\JiraExtension\Service\FileCacheService')
            ->disableOriginalConstructor()
            ->getMock();

        $this->gherkinParser = $this->getMockBuilder('Behat\Gherkin\Parser')
            ->disableOriginalConstructor()
            ->setMethods(array('parse'))
            ->getMock();

        $this->featureLoader = new FeatureLoaderService($this->jiraService, $this->cacheService, $this->gherkinParser, 'description');
    }

    /**
     * Test supports() doesn't require a resource
     */
    public function testThatResourceIsOptional()
    {
        $this->assertTrue($this->featureLoader->supports(''));
    }

    /**
     * Test support for issue key
     */
    public function testThatIssueKeyIsSupported()
    {
        $this->assertTrue($this->featureLoader->supports('jira:BDD-13'));
    }

    /**
     * Test support for URL + Issue
     */
    public function testThatIssueUrlIsSupported()
    {
        $url = 'https://acme.jira.com/browser/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getIssue')
            ->with($url)
            ->will($this->returnValue('BDD-13'));

        $this->assertTrue($this->featureLoader->supports($url));
    }

    /**
     * Test support for JIRA URL
     */
    public function testThatJiraUrlIsSupported()
    {
        $url = 'https://acme.jira.com';

        $this->jiraService->expects($this->once())
            ->method('urlMatches')
            ->with($url)
            ->will($this->returnValue(true));

        $this->assertTrue($this->featureLoader->supports($url));
    }

    /**
     * Fetch a single issue given an issue key
     */
    public function testThatLoadFetchesSingleIssueByIssueKey()
    {
        $url = 'https://acme.jira.com/browse/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getUrl')
            ->with('BDD-13')
            ->will($this->returnValue($url));

        $this->jiraService->expects($this->once())
            ->method('fetchIssue')
            ->with('BDD-13')
            ->will($this->returnValue((object) array(
                'id' => 2034,
                'description' => '{code}As a Developer I want to load features from Jira{code}',
                'key' => 'BDD-13'
            )));

        $this->gherkinParser->expects($this->once())
            ->method('parse')
            ->with('As a Developer I want to load features from Jira', $url.'#')
            ->will($this->returnValue($this->getMock('Behat\Gherkin\Node\FeatureNode')));

        $issues = $this->featureLoader->load('jira:BDD-13');

        $this->assertInternalType('array', $issues);
        $this->assertCount(1, $issues);
        $this->assertInstanceOf('Behat\Gherkin\Node\FeatureNode', $issues[0]);
    }

    /**
     * Fetch a single issue given a URL
     */
    public function testThatLoadFetchesSingleIssueByUrl()
    {
        $url = 'https://acme.jira.com/browse/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getIssue')
            ->with($url)
            ->will($this->returnValue('BDD-13'));

        $this->jiraService->expects($this->once())
            ->method('getUrl')
            ->with('BDD-13')
            ->will($this->returnValue($url));

        $this->jiraService->expects($this->once())
            ->method('fetchIssue')
            ->with('BDD-13')
            ->will($this->returnValue((object) array(
                'id' => 2034,
                'description' => '{code}As a Developer I want to load features from Jira{code}',
                'key' => 'BDD-13'
            )));

        $this->gherkinParser->expects($this->once())
            ->method('parse')
            ->with('As a Developer I want to load features from Jira', $url.'#')
            ->will($this->returnValue($this->getMock('Behat\Gherkin\Node\FeatureNode')));

        $issues = $this->featureLoader->load($url);

        $this->assertInternalType('array', $issues);
        $this->assertCount(1, $issues);
        $this->assertInstanceOf('Behat\Gherkin\Node\FeatureNode', $issues[0]);
    }

    /**
     * Auto-tag with assignee
     */
    public function testThatFeatureIsTaggedWithAssignee()
    {
        $url = 'https://acme.jira.com/browse/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getUrl')
            ->with('BDD-13')
            ->will($this->returnValue($url));

        $this->jiraService->expects($this->once())
            ->method('fetchIssue')
            ->with('BDD-13')
            ->will($this->returnValue((object) array(
                'id' => 2034,
                'description' => '{code}As a Developer I want to load features from Jira{code}',
                'key' => 'BDD-13',
                'assignee' => '@Jakub Zalas'
            )));

        $feature = $this->getMockBuilder('Behat\Gherkin\Node\FeatureNode')
            ->disableOriginalConstructor()
            ->setMethods(array('addTag'))
            ->getMock();

        $feature->expects($this->once())
            ->method('addTag')
            ->with('assignee:_Jakub_Zalas');

        $this->gherkinParser->expects($this->once())
            ->method('parse')
            ->will($this->returnValue($feature));

        $issues = $this->featureLoader->load('jira:BDD-13');

        $this->assertInternalType('array', $issues);
    }

    /**
     * Auto-tag fix versions
     */
    public function testThatFeatureIsTaggedWithFixVersions()
    {
        $url = 'https://acme.jira.com/browse/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getUrl')
            ->with('BDD-13')
            ->will($this->returnValue($url));

        $this->jiraService->expects($this->once())
            ->method('fetchIssue')
            ->with('BDD-13')
            ->will($this->returnValue((object) array(
                'id' => 2034,
                'description' => '{code}As a Developer I want to load features from Jira{code}',
                'key' => 'BDD-13',
                'fixVersions' => array((object) array('name' => 'Sprint 1@a'))
            )));

        $feature = $this->getMockBuilder('Behat\Gherkin\Node\FeatureNode')
            ->disableOriginalConstructor()
            ->setMethods(array('addTag'))
            ->getMock();

        $feature->expects($this->once())
            ->method('addTag')
            ->with('fixVersion:Sprint_1_a');

        $this->gherkinParser->expects($this->once())
            ->method('parse')
            ->will($this->returnValue($feature));

        $issues = $this->featureLoader->load('jira:BDD-13');

        $this->assertInternalType('array', $issues);
    }

    /**
     * Load multiple features
     */
    public function testLoadingMultipleFeatures()
    {
        $url = 'https://acme.jira.com/browse/';

        $this->jiraService->expects($this->exactly(2))
            ->method('getUrl')
            ->will($this->onConsecutiveCalls($url . '13', $url . '14'));

        $this->jiraService->expects($this->once())
            ->method('fetchIssues')
            ->will($this->returnValue(array(
                (object) array(
                    'id' => 2034,
                    'description' => '{code}As a Developer I want to load features from Jira{code}',
                    'key' => 'BDD-13',
                    'updated' => 0,
                ),
                (object) array(
                    'id' => 2035,
                    'description' => '{code}As a Developer I want to load features from Jira{code}',
                    'key' => 'BDD-14',
                    'updated' => 0,
                ),
            )));

        $this->gherkinParser->expects($this->exactly(2))
            ->method('parse')
            ->will($this->returnValue($this->getMock('Behat\Gherkin\Node\FeatureNode')));

        $issues = $this->featureLoader->load('');

        $this->assertInternalType('array', $issues);
        $this->assertCount(2, $issues);
        $this->assertInstanceOf('Behat\Gherkin\Node\FeatureNode', $issues['BDD-13']);
        $this->assertInstanceOf('Behat\Gherkin\Node\FeatureNode', $issues['BDD-14']);
    }

    /**
     * Make private and protected function callable
     *
     * @param string $function
     *
     * @return \ReflectionMethod
     */
    public function makeTestable($function)
    {
        $method = new \ReflectionMethod($this->featureLoader, $function);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * Sets the given property to given value on Object in Test
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    public function setPropertyOnObject($name, $value)
    {
        $property = new \ReflectionProperty($this->featureLoader, $name);
        $property->setAccessible(true);
        $property->setValue($this->featureLoader, $value);
    }

    /**
     * Get feature from default field
     */
    public function testGetFeatureWithDefaultField()
    {
        $issue = (object) array(
            'description' => '{code}foobar{code}'
        );

        $this->setPropertyOnObject('featureField', 'description');

        $method = $this->makeTestable('getFeature');
        $result = $method->invoke($this->featureLoader, $issue);

        $this->assertEquals('foobar', $result);
    }

    /**
     * Get feature from custom field
     */
    public function testGetFeatureWithCustomField()
    {
        $issue = (object) array(
            'customFieldValues' => array(
                (object) array(
                    'customfieldId' => 'foo',
                    'values' => array('{code}foobar{code}')
                )
            )
        );

        $this->setPropertyOnObject('featureField', 'foo');

        $method = $this->makeTestable('getFeature');
        $result = $method->invoke($this->featureLoader, $issue);

        $this->assertEquals('foobar', $result);
    }
}
