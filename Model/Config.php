<?php

namespace Freshworks\MagentoConnector\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'freshworks_connector/events/enabled';
    private const XML_PATH_API_TOKEN = 'freshworks_connector/events/api_token';
    private const XML_PATH_CALLBACK_URL = 'freshworks_connector/events/callback_url';
    private const XML_PATH_SHARED_SECRET = 'freshworks_connector/events/shared_secret';
    private const XML_PATH_SEND_CUSTOMER_CREATED = 'freshworks_connector/events/send_customer_created';
    private const XML_PATH_SEND_CUSTOMER_UPDATED = 'freshworks_connector/events/send_customer_updated';
    private const XML_PATH_SEND_ORDER_CREATED = 'freshworks_connector/events/send_order_created';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isFlagSet(self::XML_PATH_ENABLED, $storeId);
    }

    public function getCallbackUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue(self::XML_PATH_CALLBACK_URL, $storeId));
    }

    public function getApiToken(?int $storeId = null): string
    {
        return $this->getSecretValue(self::XML_PATH_API_TOKEN, $storeId);
    }

    public function getSharedSecret(?int $storeId = null): string
    {
        return $this->getSecretValue(self::XML_PATH_SHARED_SECRET, $storeId);
    }

    public function shouldSendCustomerCreated(?int $storeId = null): bool
    {
        return $this->isFlagSet(self::XML_PATH_SEND_CUSTOMER_CREATED, $storeId);
    }

    public function shouldSendCustomerUpdated(?int $storeId = null): bool
    {
        return $this->isFlagSet(self::XML_PATH_SEND_CUSTOMER_UPDATED, $storeId);
    }

    public function shouldSendOrderCreated(?int $storeId = null): bool
    {
        return $this->isFlagSet(self::XML_PATH_SEND_ORDER_CREATED, $storeId);
    }

    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getSecretValue(string $path, ?int $storeId = null): string
    {
        $value = trim((string) $this->getValue($path, $storeId));
        if ($value === '') {
            return '';
        }

        try {
            $decrypted = $this->encryptor->decrypt($value);
            return trim((string) $decrypted) ?: $value;
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    private function isFlagSet(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
