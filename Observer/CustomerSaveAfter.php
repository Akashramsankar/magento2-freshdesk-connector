<?php

namespace Freshworks\MagentoConnector\Observer;

use Freshworks\MagentoConnector\Model\Config;
use Freshworks\MagentoConnector\Model\WebhookSender;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerSaveAfter implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var WebhookSender
     */
    private $webhookSender;

    public function __construct(Config $config, WebhookSender $webhookSender)
    {
        $this->config = $config;
        $this->webhookSender = $webhookSender;
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomerDataObject();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $storeId = $customer->getStoreId() ? (int) $customer->getStoreId() : null;
        $origCustomer = $observer->getEvent()->getOrigCustomerDataObject();
        $isCreated = !$origCustomer || !$origCustomer->getId();
        $topic = $isCreated ? 'customer.created' : 'customer.updated';

        if ($isCreated && !$this->config->shouldSendCustomerCreated($storeId)) {
            return;
        }
        if (!$isCreated && !$this->config->shouldSendCustomerUpdated($storeId)) {
            return;
        }

        $this->webhookSender->send($topic, [
            'customer' => $this->buildCustomerPayload($customer),
        ], $storeId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerPayload(CustomerInterface $customer): array
    {
        $addresses = [];
        foreach ((array) $customer->getAddresses() as $address) {
            if ($address instanceof AddressInterface) {
                $addresses[] = $this->buildAddressPayload($address);
            }
        }

        return [
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'group_id' => $customer->getGroupId(),
            'store_id' => $customer->getStoreId(),
            'website_id' => $customer->getWebsiteId(),
            'created_at' => $customer->getCreatedAt(),
            'updated_at' => $customer->getUpdatedAt(),
            'default_billing' => $customer->getDefaultBilling(),
            'default_shipping' => $customer->getDefaultShipping(),
            'addresses' => $addresses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressPayload(AddressInterface $address): array
    {
        return [
            'id' => $address->getId(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'region' => $address->getRegion() ? $address->getRegion()->getRegion() : '',
            'region_code' => $address->getRegion() ? $address->getRegion()->getRegionCode() : '',
            'region_id' => $address->getRegion() ? $address->getRegion()->getRegionId() : null,
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            'telephone' => $address->getTelephone(),
        ];
    }
}
