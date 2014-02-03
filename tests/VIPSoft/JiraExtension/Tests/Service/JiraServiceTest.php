<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Tests\Service;

use VIPSoft\JiraExtension\Service\JiraService;

/**
 * Jira service test
 *
 * @group Service
 *
 * @author Jakub Zalas <jakub@zalas.pl>
 * @author Pascal Rehfeldt <Pascal@Pascal-Rehfeldt.com>
 */
class JiraServiceTest extends \PHPUnit_Framework_TestCase
{
    private $soapClient = null;

    private $jiraService = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->soapClient = $this->getMockFromWsdl(__DIR__.'/Fixtures/jirasoapservice_v2.wsdl');

        $this->jiraService = new JiraService(
            $this->soapClient,
            'https://acme.jira.com',
            'ted',
            '$ecret',
            'summary ~ \'Feature\'',
            'description'
        );
    }

    /**
     * Fetch issues
     */
    public function testFetchIssues()
    {
        $expectedIssues = array(
            (object) array('id' => '12'),
            (object) array('id' => '13')
        );

        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getIssuesFromJqlSearch')
            ->with('AUTH_TOKEN', 'summary ~ \'Feature\'', $this->anything())
            ->will($this->returnValue($expectedIssues));

        $issues = $this->jiraService->fetchIssues();

        $this->assertSame($expectedIssues, $issues);
    }

    /**
     * Fetch issues with timestamp
     */
    public function testFetchIssuesWithTimestamp()
    {
        $timestamp = 1344329723;
        $expectedIssues = (object) array(
            (object) array('id' => '12'),
            (object) array('id' => '13')
        );

        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getIssuesFromJqlSearch')
            ->with('AUTH_TOKEN', 'summary ~ \'Feature\' AND updated > \''.date('Y-m-d H:i', $timestamp).'\'', $this->anything())
            ->will($this->returnValue($expectedIssues));

        $issues = $this->jiraService->fetchIssues($timestamp);

        $this->assertSame($expectedIssues, $issues);
    }

    /**
     * Test login is only called once
     */
    public function testThatLoginIsOnlyCalledOnce()
    {
        $this->soapClient->expects($this->once())
            ->method('login')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->exactly(2))
            ->method('getIssuesFromJqlSearch');

        $this->jiraService->fetchIssues();
        $this->jiraService->fetchIssues();
    }

    /**
     * Fetch issue
     */
    public function testFetchIssue()
    {
        $expectedIssue = (object) array('id' => '12');

        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getIssue')
            ->with('AUTH_TOKEN', 'JIRA-12')
            ->will($this->returnValue($expectedIssue));

        $issue = $this->jiraService->fetchIssue('JIRA-12');

        $this->assertSame($expectedIssue, $issue);
    }

    /**
     * Post comment
     */
    public function testPostComment()
    {
        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('addComment')
            ->with('AUTH_TOKEN', 'JIRA-12', array('body' => 'Message.'));

        $this->jiraService->postComment('JIRA-12', 'Message.');
    }

    /**
     * Reopen issue
     *
     * @param integer $expectedActionId Expected action ID
     * @param array   $issues           Issues
     *
     * @dataProvider provideReopenIssues
     */
    public function testReopenIssue($expectedActionId, $issues)
    {
        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getAvailableActions')
            ->will($this->returnValue($issues));

        $this->soapClient->expects($this->once())
            ->method('progressWorkflowAction')
            ->with('AUTH_TOKEN', 'JIRA-12', $expectedActionId, array());

        $this->jiraService->reopenIssue('JIRA-12');
    }

    /**
     * @return array
     */
    public function provideReopenIssues()
    {
        return array(
            array(3, array((object) array('id' => 2, 'name' => 'Delete Issue'), (object) array('id' => 3, 'name' => 'Reopen Issue'))),
            array(3, array((object) array('id' => 3, 'name' => 'Reopen issue'))),
            array(4, array((object) array('id' => 4, 'name' => 'Reopen'))),
            array(5, array((object) array('id' => 5, 'name' => 'reopen')))
        );
    }

    /**
     * Test reopen issue is not called if workflow transition is not available
     */
    public function testThatReopenIssueIsNotCalledIfTransitionIsNotAvailable()
    {
        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getAvailableActions')
            ->will($this->returnValue(array((object) array('id' => 3, 'name' => 'Delete Issue'))));

        $this->soapClient->expects($this->never())
            ->method('progressWorkflowAction');

        $this->jiraService->reopenIssue('JIRA-12');
    }

    /**
     * Get issue
     */
    public function testGetIssue()
    {
        $key = $this->jiraService->getIssue('https://acme.jira.com/browse/JIRA-12');

        $this->assertEquals('JIRA-12', $key);

        $key = $this->jiraService->getIssue('https://acme.jira.com/browse/JIRA-12#anchor');

        $this->assertEquals('JIRA-12', $key);
    }

    /**
     * Get issue
     */
    public function testGetIssueForUnknownResource()
    {
        $key = $this->jiraService->getIssue('https://badger.jira.com/browse/JIRA-12');

        $this->assertNull($key);
    }

    /**
     * Get URL
     */
    public function testGetUrl()
    {
        $url = $this->jiraService->getUrl('JIRA-12');

        $this->assertEquals('https://acme.jira.com/browse/JIRA-12', $url);
    }

    /**
     * Url matches
     */
    public function testUrlMatches()
    {
        $this->assertTrue($this->jiraService->urlMatches('https://acme.jira.com/browser/JIRA-12'));
        $this->assertFalse($this->jiraService->urlMatches('https://badger.jira.com/browser/JIRA-12'));
    }

    /**
     * Test a specfic Jira Tag gets put into Array
     */
    public function testPushScenarios()
    {
        $jiraTags = array("BDD-13", "BDD-12");
        $text = "foo";
        $this->jiraService->pushScenario($jiraTags, $text);

        $expectedStore = array("BDD-13" => array("foo"), "BDD-12" => array("foo"));

        $store = $this->jiraService->getStore();
        $this->assertEquals($expectedStore, $store);
    }

    /**
     * Sets the given property to given value on Object in Test
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    public function setPropertyOnObject($name, $value)
    {
        $property = new \ReflectionProperty($this->jiraService, $name);
        $property->setAccessible(true);
        $property->setValue($this->jiraService, $value);
    }

    /**
     * Test if sync issue works if the issues have different values for fields
     */
    public function testSyncIssueIfDifferent()
    {
        $exampleStore = array(
            "BDD-13" => array("foo")
            );

        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getIssue')
            ->with('AUTH_TOKEN', 'BDD-13')
            ->will($this->returnValue(array("description" => "bar")));

        $this->soapClient->expects($this->once())
            ->method('updateIssue');

        $this->setPropertyOnObject("store", $exampleStore);

        $this->jiraService->syncIssue();
    }

    /**
     * Test if sync issues don't work if the issues have same field value
     */
    public function testSyncIssueIfSame()
    {
        $exampleStore = array(
            "BDD-13" => array("Same Content")
            );

        $this->soapClient->expects($this->once())
            ->method('login')
            ->with('ted', '$ecret')
            ->will($this->returnValue('AUTH_TOKEN'));

        $this->soapClient->expects($this->once())
            ->method('getIssue')
            ->with('AUTH_TOKEN', 'BDD-13')
            ->will($this->returnValue(array("description" => "Same Content")));

        $this->soapClient->expects($this->never())
            ->method('updateIssue');

        $this->setPropertyOnObject("store", $exampleStore);

        $this->jiraService->syncIssue();
    }
}
