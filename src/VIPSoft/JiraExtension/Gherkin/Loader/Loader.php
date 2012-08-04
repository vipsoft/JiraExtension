<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Gherkin\Loader;

use Behat\Gherkin\Loader\AbstractFileLoader;

use VIPSoft\JiraExtension\Service\FeatureLoaderService;

/**
 * Feature loader
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class Loader extends AbstractFileLoader
{
    private $featureLoaderService;

    /**
     * Constructor
     *
     * @param FeatureLoaderService $featureLoaderService
     */
    public function __construct($featureLoaderService)
    {
        $this->featureLoaderService = $featureLoaderService;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource)
    {
        return $this->featureLoaderService->supports($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource)
    {
        return $this->featureLoaderService->load($resource);
    }
}
