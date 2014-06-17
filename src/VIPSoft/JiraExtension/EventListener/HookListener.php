<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\StepEvent,
    Behat\Behat\Event\SuiteEvent;

use VIPSoft\JiraExtension\Service\JiraService;

/**
 * Hook event listener
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class HookListener implements EventSubscriberInterface
{
    private $commentOnPass;
    private $commentOnFail;
    private $reopenOnFail;
    private $jiraService;
    private $pushIssue;
    private $tagPattern;

    /**
     * Constructor
     *
     * @param boolean     $commentOnPass Post comment when scenario passes
     * @param boolean     $commentOnFail Post comment when scenario fails
     * @param boolean     $reopenOnFail  Reopen issue when scenario fails
     * @param boolean     $pushIssue     Pushes issue to Jira
     * @param string      $tagPattern    The Regex for parsing the tag
     * @param JiraService $jiraService   Jira service
     */
    public function __construct($commentOnPass, $commentOnFail, $reopenOnFail, $pushIssue, $tagPattern, $jiraService)
    {
        $this->commentOnPass = $commentOnPass;
        $this->commentOnFail = $commentOnFail;
        $this->reopenOnFail = $reopenOnFail;
        $this->pushIssue = $pushIssue;
        $this->tagPattern = $tagPattern;
        $this->jiraService = $jiraService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterScenario' => 'afterScenario',
            'afterSuite' => 'afterSuite'
        );
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
        $issue = null;

        if ($this->pushIssue) {
            $jiraTags = $this->parseJiraTags($scenario->getTags());
            $text = implode("\n", $this->getStepText($scenario));
            $issue = $this->jiraService->pushScenario($jiraTags, $text);
        } else {
            $url = $feature->getFile();
            $issue = $this->jiraService->getIssue($url);
        }
        if ($issue) {
            $this->postComment($issue, $event->getResult(), $scenario->getTitle());
            $this->updateIssue($issue, $event->getResult());
        }
    }

    /**
     * After Suite hook
     *
     * @param ScenarioEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        if ($this->pushIssue) {
            file_put_contents('php://stdout', "Pushing issues to Jira...");
            $this->jiraService->postIssue();
            file_put_contents('php://stdout', " done!" . PHP_EOL);
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

    /**
     * Parse an array of tags, and find the corresponding Jira Ticket
     * via the tag pattern regex (default /jira:(.*)/) configurable in 
     * the behat.yml
     * 
     * @param Array $tags
     * 
     * @return Array $jiraTags
     */
    public function parseJiraTags($tags)
    {
        $jiraTags = array();
        foreach ($tags as $value) {
            if (preg_match($this->tagPattern, $value, $results)) {
                array_push($jiraTags, $results[1]);
            }
        }

        return $jiraTags;
    }


    /**
     * Get the Steps from a scenario as an array of strings
     * 
     * @param ScenarioNode $scenario 
     * 
     * @return Array $jiraTags
     */
    public function getStepText($scenario)
    {

        $stepArray = array("{code}");
        $feature = $scenario -> getFeature();

        //Parse Title
        $title = basename($feature -> getFile());
        $stepArray[] = "#Title: " . $title;

        //Parse Background
        if ($feature -> hasBackground()) {
            $background = $feature -> getBackground();

            $backgroundSteps = $background -> getSteps();
            $stepArray[] = "Background: " . $background -> getTitle();
            foreach ($backgroundSteps as $step) {
                $stepArray[] = "  " . $step->getType()." ".$step->getText();
            }
        }

        $scenarioSteps = $scenario->getSteps();
        $stepArray[] = "Scenario: " . $scenario->getTitle();

        foreach ($scenarioSteps as $step) {
            $stepArray[] = "  " . $step->getType()." ".$step->getText();
        }
        $stepArray[] = "{code}";

        return $stepArray;
    }
}
