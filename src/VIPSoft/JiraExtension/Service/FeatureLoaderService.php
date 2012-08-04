<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Service;

use Behat\Gherkin\Node\FeatureNode,
    Behat\Gherkin\Parser;

use VIPSoft\JiraExtension\Service\FeatureLoaderService;

/**
 * Feature Loader service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class FeatureLoaderService
{
    private $jiraService;
    private $gherkinParser;

    /**
     * Constructor
     *
     * @param JiraService $jiraService   Jira service
     * @param Parser      $gherkinParser Gherkin parser
     */
    public function __construct($jiraService, $gherkinParser)
    {
        $this->jiraService = $jiraService;
        $this->gherkinParser = $gherkinParser;
    }

    /**
     * Get issue ID from resource
     *
     * @param mixed $resource
     *
     * @return string
     */
    private function getIssue($resource)
    {
        if (strncmp($resource, 'jira:', 5) === 0) {
            return substr($resource, 5);
        }

        return $this->jiraService->getIssue($resource);
    }

    /**
     * Create feature from issue
     *
     * @param object $issue
     *
     * @return FeatureNode
     */
    private function createFeature($issue)
    {
        $body = str_replace(array('{code:none}', '{code}'), '', $issue->body);
        $url = $this->jiraService->getUrl($issue->key);

        return $this->gherkinParser->parse($body, $url);
    }

    /**
     * Create features from issues
     *
     * @param array $issues
     *
     * @return array
     */
    private function createFeatures($issues)
    {
        $features = array();

        foreach ($issues as $issue) {
            $feature = $this->createFeature($issue);

            if ($feature instanceof FeatureNode) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Is this resource supported?
     *
     * @param mixed $resource
     *
     * @return boolean
     */
    public function supports($resource)
    {
        return $resource === '' || $this->getIssue($resource);
    }

    /**
     * Load features
     *
     * @param mixed $resource
     *
     * @return array
     */
    public function load($resource)
    {
        $issue = $this->getIssue($resource);
        $issues = $issue ? array($this->jiraService->fetchIssue($issue)) : $this->jiraService->fetchIssues();
        return $this->createFeatures($issues);
    }
}
