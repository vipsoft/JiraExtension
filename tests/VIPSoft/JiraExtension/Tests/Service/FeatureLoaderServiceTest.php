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
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class FeatureLoaderServiceTest extends \PHPUnit_Framework_TestCase
{
    private $jiraService = null;

    private $cacheService = null;

    private $gherkinParser = null;

    private $featureLoader = null;

    public function setUp()
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

        $this->featureLoader = new FeatureLoaderService($this->jiraService, $this->cacheService, $this->gherkinParser);
    }

    public function testThatResourceIsOptional()
    {
        $this->assertTrue($this->featureLoader->supports(''));
    }

    public function testThatIssueKeyIsSupported()
    {
        $this->assertTrue($this->featureLoader->supports('jira:BDD-13'));
    }

    public function testThatIssueUrlIsSupported()
    {
        $url = 'https://acme.jira.com/browser/BDD-13';

        $this->jiraService->expects($this->once())
            ->method('getIssue')
            ->with($url)
            ->will($this->returnValue('BDD-13'));

        $this->assertTrue($this->featureLoader->supports($url));
    }

    public function testThatJiraUrlIsSupported()
    {
        $url = 'https://acme.jira.com';

        $this->jiraService->expects($this->once())
            ->method('urlMatches')
            ->with($url)
            ->will($this->returnValue(true));

        $this->assertTrue($this->featureLoader->supports($url));
    }

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

    public function testLoadingMultipleFeatures()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }
}
