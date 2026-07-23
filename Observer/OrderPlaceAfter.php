<?php

namespace Freshworks\MagentoConnector\Observer;

use Freshworks\MagentoConnector\Model\Config;
use Freshworks\MagentoConnector\Model\WebhookSender;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class OrderPlaceAfter implements ObserverInterface
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
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $storeId = $order->getStoreId() ? (int) $order->getStoreId() : null;
        if (!$this->config->shouldSendOrderCreated($storeId)) {
            return;
        }

        $this->webhookSender->send('order.created', [
            'order' => $this->buildOrderPayload($order),
        ], $storeId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderPayload(OrderInterface $order): array
    {
        $items = [];
        foreach ((array) $order->getAllVisibleItems() as $item) {
            if ($item instanceof OrderItemInterface) {
                $items[] = $this->buildOrderItemPayload($item);
            }
        }

        return [
            'entity_id' => $order->getEntityId(),
            'increment_id' => $order->getIncrementId(),
            'state' => $order->getState(),
            'status' => $order->getStatus(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
            'customer_id' => $order->getCustomerId(),
            'customer_email' => $order->getCustomerEmail(),
            'customer_firstname' => $order->getCustomerFirstname(),
            'customer_lastname' => $order->getCustomerLastname(),
            'store_id' => $order->getStoreId(),
            'base_currency_code' => $order->getBaseCurrencyCode(),
            'order_currency_code' => $order->getOrderCurrencyCode(),
            'subtotal' => $order->getSubtotal(),
            'tax_amount' => $order->getTaxAmount(),
            'shipping_amount' => $order->getShippingAmount(),
            'discount_amount' => $order->getDiscountAmount(),
            'grand_total' => $order->getGrandTotal(),
            'total_refunded' => $order->getTotalRefunded(),
            'shipping_description' => $order->getShippingDescription(),
            'billing_address' => $this->buildAddressPayload($order->getBillingAddress()),
            'extension_attributes' => [
                'shipping_assignments' => [
                    [
                        'shipping' => [
                            'address' => $this->buildAddressPayload($order->getShippingAddress()),
                            'method' => $order->getShippingMethod(),
                        ],
                        'items' => $items,
                    ],
                ],
            ],
            'payment' => [
                'method' => $order->getPayment() ? $order->getPayment()->getMethod() : '',
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderItemPayload(OrderItemInterface $item): array
    {
        return [
            'item_id' => $item->getItemId(),
            'product_id' => $item->getProductId(),
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'product_type' => $item->getProductType(),
            'qty_ordered' => $item->getQtyOrdered(),
            'price' => $item->getPrice(),
            'row_total' => $item->getRowTotal(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressPayload(?OrderAddressInterface $address): array
    {
        if (!$address) {
            return [];
        }

        return [
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'region' => $address->getRegion(),
            'region_code' => $address->getRegionCode(),
            'region_id' => $address->getRegionId(),
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            'telephone' => $address->getTelephone(),
            'email' => $address->getEmail(),
        ];
    }
}
