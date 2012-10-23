<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Service;

use Behat\Gherkin\Node\FeatureNode,
    Behat\Gherkin\Parser;

/**
 * Feature Loader service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 * @author Pascal Rehfeldt <Pascal@Pascal-Rehfeldt.com>
 */
class FeatureLoaderService
{
    private $jiraService;
    private $cacheService;
    private $gherkinParser;
    private $featureField;

    /**
     * Constructor
     *
     * @param JiraService  $jiraService   Jira service
     * @param CacheService $cacheService  Cache service
     * @param Parser       $gherkinParser Gherkin parser
     * @param string       $featureField  Field in Jira
     */
    public function __construct($jiraService, $cacheService, $gherkinParser, $featureField)
    {
        $this->jiraService = $jiraService;
        $this->cacheService = $cacheService;
        $this->gherkinParser = $gherkinParser;
        $this->featureField = $featureField;
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
        $body = $this->getFeature($issue);
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
     * Gets the feature from the defined field
     *
     * @param \stdClass $issue
     *
     * @return string
     */
    private function getFeature($issue)
    {
        $arrayIssue = (array) $issue;

        if (array_key_exists($this->featureField, $arrayIssue)) {
            return $this->extractFeatureFromString($arrayIssue[$this->featureField]);
        }

        $customFields = $arrayIssue['customFieldValues'];
        foreach ($customFields as $customField) {
            if ($this->featureField == $customField->customfieldId) {
                $value = current($customField->values);

                return $this->extractFeatureFromString($value);
            }
        }

        return '';
    }

    /**
     * Extracts a Feature between code block from a given string
     *
     * @param string $feature
     *
     * @return string
     */
    private function extractFeatureFromString($feature)
    {
        return preg_replace('/\{code.*?\}(.+?)\{code\}/s', '$1', $feature);
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
    private function createFeature($issue)
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
