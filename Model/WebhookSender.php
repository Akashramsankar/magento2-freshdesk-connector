<?php

namespace Freshworks\MagentoConnector\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WebhookSender
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        Curl $curl,
        Json $json,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function send(string $topic, array $data, ?int $storeId = null): void
    {
        if (!$this->config->isEnabled($storeId)) {
            return;
        }

        $callbackUrl = $this->config->getCallbackUrl($storeId);
        if (!$callbackUrl) {
            $this->logger->warning('[Freshworks Magento Connector] Event sync is enabled but callback URL is empty.');
            return;
        }

        try {
            $body = $this->buildBody($topic, $data, $storeId);
            $encodedBody = $this->json->serialize($body);
            $headers = $this->buildHeaders($topic, $encodedBody, $storeId);

            $this->curl->setTimeout(5);
            $this->curl->setHeaders($headers);
            $this->curl->post($callbackUrl, $encodedBody);

            $status = (int) $this->curl->getStatus();
            if ($status < 200 || $status >= 300) {
                throw new LocalizedException(__('Freshworks callback returned HTTP status %1.', $status));
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('[Freshworks Magento Connector] Failed to send %s event: %s', $topic, $exception->getMessage())
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildBody(string $topic, array $data, ?int $storeId): array
    {
        return [
            'topic' => $topic,
            'source' => $this->getSourceUrl($storeId),
            'data' => $data,
            'meta' => [
                'platform' => 'magento2',
                'magento_version' => $this->productMetadata->getVersion(),
                'sent_at' => gmdate('c'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $topic, string $body, ?int $storeId): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Magento-Topic' => $topic,
            'X-Magento-Source' => $this->getSourceUrl($storeId),
            'X-Freshworks-Magento-Version' => '1.0.0',
        ];

        $secret = $this->config->getSharedSecret($storeId);
        if ($secret !== '') {
            $headers['X-Freshworks-Magento-Signature'] = hash_hmac('sha256', $body, $secret);
        }

        return $headers;
    }

    private function getSourceUrl(?int $storeId): string
    {
        try {
            return rtrim((string) $this->storeManager->getStore($storeId)->getBaseUrl(), '/');
        } catch (\Throwable $exception) {
            return '';
        }
    }
}
