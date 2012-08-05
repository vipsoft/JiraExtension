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
    private $cacheService;
    private $gherkinParser;

    /**
     * Constructor
     *
     * @param JiraService  $jiraService   Jira service
     * @param CacheService $cacheService  Cache service
     * @param Parser       $gherkinParser Gherkin parser
     */
    public function __construct($jiraService, $cacheService, $gherkinParser)
    {
        $this->jiraService = $jiraService;
        $this->cacheService = $cacheService;
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
     * Parse feature from issue
     *
     * @param \StdClass $issue
     *
     * @return FeatureNode
     */
    private function parseFeature($issue)
    {
        $body = str_replace(array('{code:none}', '{code}'), '', $issue->description);
        $url = $this->jiraService->getUrl($issue->key) . '#';
        $feature = $this->gherkinParser->parse($body, $url);

        if (isset($issue->assignee)) {
            $feature->addTag('assignee:' . str_replace(array(' ', '@'), '_', $issue->assignee));
        }

        if (isset($issue->fixVersions)) {
            foreach ($issue->fixVersions as $fixVersion) {
               $feature->addTag('fixVersion:' . str_replace(array(' ', '@'), '_', $fixVersion->name));
            }
        }

        return $feature;
    }

    /**
     * Parse features from issues
     *
     * @param array $issues
     *
     * @return array
     */
    private function parseFeatures($issues)
    {
        $features = array();

        foreach ($issues as $issue) {
            $feature = $this->parseFeature($issue);

            if ($feature instanceof FeatureNode) {
                $features[$issue->key] = $feature;
                $timestamp = $this->cacheService->convertToUnixTimestamp($issue->updated);
                $this->cacheService->write($issue->key, $feature, $timestamp);
            }
        }

        return $features;
    }

    /**
     * Create "single feature" feature suite
     *
     * @param string $issue
     *
     * @return array
     */
    public function createFeature($issue)
    {
        $issue = $this->jiraService->fetchIssue($issue);
        $feature = $this->parseFeature($issue);

        if ($feature instanceof FeatureNode) {
            return array($feature);
        }

        return array();
    }

    /**
     * Create features from issues
     *
     * @param array $issues
     * @param array $keys
     *
     * @return array
     */
    private function createFeatures()
    {
        $timestamp = $this->cacheService->getLatestTimestamp();
        $issues = $this->jiraService->fetchIssues($timestamp);
        $features = $this->parseFeatures($issues);

        $keys = $this->cacheService->getKeys() ?: array();
        foreach ($keys as $key) {
            if (!array_key_exists($key, $features)) {
                $features[$key] = $this->cacheService->read($key);
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
        return $resource === ''
            || $this->getIssue($resource)
            || $this->jiraService->urlMatches($resource);
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
        if ($issue = $this->getIssue($resource)) {
            return $this->createFeature($issue);
        }

        return $this->createFeatures();
    }
}
