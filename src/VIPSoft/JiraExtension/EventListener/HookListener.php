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
class HookListener implements EventSubscriberInterface
{
    private $commentOnPass;
    private $commentOnFail;
    private $reopenOnFail;
    private $jiraService;

    /**
     * Constructor
     *
     * @param boolean     $commentOnPass Post comment when scenario passes
     * @param boolean     $commentOnFail Post comment when scenario fails
     * @param boolean     $reopenOnFail  Reopen issue when scenario fails
     * @param JiraService $jiraService   Jira service
     */
    public function __construct($commentOnPass, $commentOnFail, $reopenOnFail, $jiraService)
    {
        $this->commentOnPass = $commentOnPass;
        $this->commentOnFail = $commentOnFail;
        $this->reopenOnFail = $reopenOnFail;
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
        $issue = $this->jiraService->getIssue($url);

        if ($issue) {
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
    private function postComment($issue, $result, $title)
    {
        if ($result === StepEvent::PASSED && $this->commentOnPass) {
            $this->jiraService->postComment($issue, sprintf('Scenario "%s" passed', $title));
        } elseif ($result === StepEvent::FAILED && $this->commentOnFail) {
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
        if ($result === StepEvent::FAILED && $this->reopenOnFail) {
            $this->jiraService->reopenIssue($issue);
        }
    }
}
