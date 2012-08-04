<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\StepEvent;

use VIPSoft\JiraExtension\Service\JiraService;

/**
 * After Scenario event listener
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class AfterScenarioListener implements EventSubscriberInterface
{
    private $comment_on_pass;
    private $comment_on_fail;
    private $reopen_on_fail;
    private $jiraService;

    /**
     * Constructor
     *
     * @param boolean     $comment_on_pass Post comment when scenario passes
     * @param boolean     $comment_on_fail Post comment when scenario fails
     * @param boolean     $reopen_on_fail  Reopen issue when scenario fails
     * @param JiraService $jiraService Jira service
     */
    public function __construct($comment_on_pass, $comment_on_fail, $reopen_on_fail, $jiraService)
    {
        $this->comment_on_pass = $comment_on_pass;
        $this->comment_on_fail = $comment_on_fail;
        $this->reopen_on_fail = $reopen_on_fail;
        $this->jiraService = $jiraService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array('afterScenario' => 'afterScenario');
    }

    /**
     * After Scenario hook
     *
     * @param ScenarioEvent $event
     */
    public function afterScenario(ScenarioEvent $event)
    {
        $scenario = $event->getScenario();
        $feature = $scenario->getFeature();
        $url = $feature->getFile();

        if ($this->jiraService->getIssue($url)) {
            $issue = $this->jiraService->getIssue($url);

            $this->postComment($issue, $event->getResult(), $scenario->getTitle());
            $this->updateIssue($issue, $event->getResult());
        }
    }

    /** 
     * Post comment in corresponding Jira issue
     *
     * @param string  $issue  Issue key
     * @param integer $result Result
     * @param string  $title  Scenario title
     */
    private function postComment($issue, $result)
    {
        if ($result === StepEvent::PASSED && $this->comment_on_pass) {
            $this->jiraService->postComment($issue, sprintf('Scenario "%s" passed', $title));
        } elseif ($result === StepEvent::FAILED && $this->comment_on_fail) {
            $this->jiraService->postComment($issue, sprintf('Scenario "%s" failed', $title));
        }
    }

    /** 
     * Update Jira issue status
     *
     * @param string  $issue  Issue key
     * @param integer $result Result
     */
    private function updateIssue($issue, $result)
    {
        if ($result === StepEvent::FAILED && $this->reopen_on_fail) {
            $this->jiraService->reopenIssue($issue);
        }
    }
}
