<?php

namespace Freshworks\MagentoConnector\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\AuthorizationException;

class ApiTokenValidator
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(Config $config, RequestInterface $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @throws AuthorizationException
     */
    public function validate(?int $storeId = null): void
    {
        $configuredToken = $this->config->getApiToken($storeId);
        $requestToken = $this->getRequestToken();

        if ($configuredToken === '' || $requestToken === '' || !hash_equals($configuredToken, $requestToken)) {
            throw new AuthorizationException(__('The Freshworks Magento API token is invalid.'));
        }
    }

    private function getRequestToken(): string
    {
        $authorization = trim((string) $this->request->getHeader('Authorization'));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return trim((string) $this->request->getHeader('X-Freshworks-Magento-Token'));
    }
}
