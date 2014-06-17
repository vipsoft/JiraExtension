<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Service;

/**
 * Jira service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class JiraService
{
    const MAX_ISSUES = 2147483647;

    /**
     * @var \SoapClient $soapClient
     */
    private $soapClient;

    /**
     * @var string $host
     */
    private $host;

    /**
     * @var string $user
     */
    private $user;

    /**
     * @var string $password
     */
    private $password;

    /**
     * @var string $jql
     */
    private $jql;

    /**
     * @var string $token
     */
    private $token;

    /**
     * @var string $featureField
     */
    private $featureField;

    /**
     * @var array $store
     */
    private $store;

    /**
     * @var array $pushedIssues
     */
    private $pushedIssue;

    /**
     * @var array $ignoredStatus
     */
    private $ignoredStatus;

    /**
     * @var array $ignoredIssues
     */
    private $ignoredIssues;


    /**
     * Constructor
     *
     * @param \SoapClient $soapClient    SOAP client class name
     * @param string      $host          Jira server base URL
     * @param string      $user          Jira user ID
     * @param string      $password      Jira user password
     * @param string      $jql           JQL query
     * @param string      $featureField  Jira field to upload feature file
     * @param string      $ignoredStatus Tickets with the statuses in this array are ignored
     */
    public function __construct(\SoapClient $soapClient, $host, $user, $password, $jql, $featureField, $ignoredStatus)
    {
        $this->soapClient = $soapClient;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->jql = $jql;
        $this->featureField = $featureField;
        $this->store = array();
        $this->pushedIssue = array();
        $this->ignoredStatus = $ignoredStatus;
        $this->ignoredIssues = array();
    }

    /**
     * Login to Jira
     */
    private function connect()
    {
        if (!empty($this->token)) {
            return;
        }

        $this->token = $this->soapClient->login($this->user, $this->password);
    }

    /**
     * Get JQL query
     *
     * @param integer $timestamp
     *
     * @return string
     */
    protected function getJql($timestamp)
    {
        if (!isset($timestamp)) {
            return $this->jql;
        }

        return $this->jql . " AND updated > '" . date('Y-m-d H:i', $timestamp) . "'";
    }

    /**
     * Fetch issues matching jql and resource
     *
     * @param integer $timestamp Optional timestamp for issues updated since the timestamp
     *
     * @return array
     *
     * {@internal the number of results is constrained by jira.search.views.max.limit
     *            and jira.search.views.max.unlimited.group JIRA properties }}
     */
    public function fetchIssues($timestamp = null)
    {
        $this->connect();

        $issues = $this->soapClient->getIssuesFromJqlSearch($this->token, $this->getJql($timestamp), self::MAX_ISSUES);

        return $issues;
    }

    /**
     * Fetch issue
     *
     * @param string $id Issue key
     *
     * @return \stdClass
     */
    public function fetchIssue($id)
    {
        $this->connect();

        return $this->soapClient->getIssue($this->token, $id);
    }

    /**
     * Add comment
     *
     * @param string $id   Issue key
     * @param string $body Comment body
     */
    public function postComment($id, $body)
    {
        $this->connect();

        $comment = array(
            'body' => $body,
        );

        $this->soapClient->addComment($this->token, $id, $comment);
    }

    /**
     * Get reopen action ID
     *
     * @param string $id Issue key
     *
     * @return string
     */
    private function getReopenActionId($id)
    {
        $actions = $this->soapClient->getAvailableActions($this->token, $id);

        foreach ($actions as $action) {
            if (strpos(strtolower($action->name), 'reopen') !== false) {
                return $action->id;
            }
        }
    }

    /**
     * Re-open issue
     *
     * @param string $id Issue key
     *
     * {@internal subject to workflow progression rules }}}
     */
    public function reopenIssue($id)
    {
        $this->connect();

        $action = $this->getReopenActionId($id);

        if (isset($action)) {
            $this->soapClient->progressWorkflowAction($this->token, $id, $action, array());
        }
    }

    /**
     * Get Jira issue from URL
     *
     * @param string $resource
     *
     * @return string|null
     */
    public function getIssue($resource)
    {
        $hashPosition = strpos($resource, '#');
        if ($hashPosition !== false) {
            $resource = substr($resource, 0, $hashPosition);
        }

        $url = $this->host . '/browse/';

        if (strncmp($resource, $url, strlen($url)) === 0) {
            return substr($resource, strlen($url));
        }
    }

    /**
     * Add Scenario to internal store
     *
     * @param array  $jiraTags
     * @param string $scenarioText
     */
    public function pushScenario($jiraTags, $scenarioText)
    {
        foreach ($jiraTags as $value) {
            $this->store[$value][] = $scenarioText;
        }
    }

    /**
     * Sync Scenario to Jira
     */
    public function postIssue()
    {
        $this->connect();

        foreach ($this->store as $jiraTicket => $value) {
            if (!$this->isIgnored($jiraTicket) && $this->compareIssueField($jiraTicket, $value)) {
                $data = array(
                    'fields'=>array(
                    'id'=>$this->featureField,
                    'values'=>array(implode($value) . "\n\n")
                ));
                $this->pushedIssue[$jiraTicket] = $value;
                $this->soapClient->updateIssue($this->token, $jiraTicket, $data);
            }
        }
    }

    /**
     * Compare the issue fields if they are the same or not
     *
     * @param string $jiraTicket
     * @param string $value
     *
     * @return boolean
     */
    public function compareIssueField($jiraTicket, $value)
    {
        $issue = $this->fetchIssue($jiraTicket);

        $arrayIssue = (array) $issue;
        $fieldValue = null;

        if (array_key_exists($this->featureField, $arrayIssue)) {
            $fieldValue = $arrayIssue[$this->featureField];
        } else {
            $customFields = $arrayIssue['customFieldValues'];
            foreach ($customFields as $customField) {
                if ($this->featureField == $customField->customfieldId) {
                    $fieldValue = current($customField->values);
                }
            }
        }

        $strip = preg_replace("/[\n\r]/", "", $fieldValue);
        if ($strip===implode($value)) {
           return false; //Issues are the same
        }

        return true; //Issues are not the same
    }

    /**
     * This method checks the status of the Jira Ticket to ensure that
     * it is not in the array of ignored statuses.
     *
     * @param string $ticket Jira ticket id to check the status of
     *
     * @return boolean true/false if the ticket is supposed to ignored
     */
    public function isIgnored($ticket)
    {
        $allStatus = $this->soapClient->getStatuses($this->token);
        $ignoredStatusArray = explode(",", $this->ignoredStatus);
        $issue = $this->fetchIssue($ticket);
        $arrayTicket = (array) $issue;
        foreach ($allStatus as $status) {
            if (in_array($status->name, $ignoredStatusArray)) {
                if ($status->id === $arrayTicket["status"]) {
                    $this->ignoredIssues[] = $ticket;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get Jira URL for issue
     *
     * @param string $id Issue key
     *
     * @return string
     */
    public function getUrl($id)
    {
        return $this->host . '/browse/' . $id;
    }

    /**
     * Does URL match Jira URL?
     *
     * @param string $url
     *
     * @return boolean
     */
    public function urlMatches($url)
    {
        return strncmp($url, $this->host, strlen($this->host)) === 0;
    }

    /**
     * Return the internal store of JiraTickets and Features
     *
     * @return array $store
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * Return the internal list of JiraTickets that are ignored due to their status
     *
     * @return array $ignoredIssues
     */
    public function getIgnoredIssues()
    {
        return $this->ignoredIssues;
    }
}
