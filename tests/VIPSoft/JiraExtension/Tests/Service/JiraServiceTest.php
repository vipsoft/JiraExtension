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
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class JiraServiceTest extends \PHPUnit_Framework_TestCase
{
    private $soapClient = null;

    private $jiraService = null;

    public function setUp()
    {
        $this->soapClient = $this->getMockFromWsdl(__DIR__.'/Fixtures/jirasoapservice_v2.wsdl');

        $this->jiraService = new JiraService(
            $this->soapClient,
            'https://acme.jira.com',
            'ted',
            '$ecret',
            'summary ~ \'Feature\''
        );
    }

    /**
     * Fetch issues
     */
    public function testFetchIssues()
    {
        $expectedIssues = array(
            (object) array('id' => 'JIRA-12'),
            (object) array('id' => 'JIRA-13')
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

    public function testFetchIssuesWithTimestamp()
    {
        $timestamp = 1344329723;
        $expectedIssues = (object) array(
            (object) array('id' => 'JIRA-12'),
            (object) array('id' => 'JIRA-13')
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
        $expectedIssue = (object) array('id' => 'JIRA-12');

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
        $id = $this->jiraService->getIssue('https://acme.jira.com/browse/JIRA-12');

        $this->assertEquals('JIRA-12', $id);
    }

    /**
     * Get issue
     */
    public function testGetIssueForUnknownResource()
    {
        $id = $this->jiraService->getIssue('https://badger.jira.com/browse/JIRA-12');

        $this->assertNull($id);
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
}
