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
    private $host;
    private $user;
    private $password;
    private $jql;
    private $soapClient;
    private $token;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $jql
     */
    public function __construct($host, $user, $password, $jql)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->jql = $jql;
    }

    /**
     * Login to Jira
     */
    private function connect()
    {
        if ($this->soapClient) {
            return;
        }

        $wsdl = $this->host . '/rpc/soap/jirasoapservice-v2?wsdl';
        $this->soapClient = new \SoapClient($wsdl, array('trace'=>true));
        $this->token = $this->soapClient->login($this->user, $this->password);
    }

    /**
     * Fetch issues matching jql and resource
     *
     * @return array
     *
     * {@internal the number of results is constrained by jira.search.views.max.limit
     *            and jira.search.views.max.unlimited.group JIRA properties }}
     */
    public function fetchIssues()
    {
        $this->connect();

        $issues = $this->soapClient->getIssuesFromJqlSearch($this->token, $this->jql, 2147483647);

        return $issues;
    }

    /**
     * Fetch issue
     *
     * @param string $id Issue key
     *
     * @return string
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
        $url = $this->host . '/browse/';

        if (strncmp($resource, $url, strlen($url)) === 0) {
            return substr($resource, strlen($url));
        }
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
}
