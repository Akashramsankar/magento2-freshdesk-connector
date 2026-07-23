<?php

namespace Freshworks\MagentoConnector\Model;

use Freshworks\MagentoConnector\Api\ConnectorInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentRepository;

class Connector implements ConnectorInterface
{
    private const XML_PATH_ENABLED = 'freshworks_connector/events/enabled';
    private const XML_PATH_CALLBACK_URL = 'freshworks_connector/events/callback_url';
    private const XML_PATH_SHARED_SECRET = 'freshworks_connector/events/shared_secret';

    /**
     * @var ApiTokenValidator
     */
    private $tokenValidator;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    public function __construct(
        ApiTokenValidator $tokenValidator,
        CustomerRepositoryInterface $customerRepository,
        GroupRepositoryInterface $groupRepository,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagement,
        ShipmentRepository $shipmentRepository,
        WriterInterface $configWriter,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->tokenValidator = $tokenValidator;
        $this->customerRepository = $customerRepository;
        $this->groupRepository = $groupRepository;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->shipmentRepository = $shipmentRepository;
        $this->configWriter = $configWriter;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    public function ping(): array
    {
        $this->tokenValidator->validate();
        return [
            'success' => true,
            'module' => 'Freshworks_MagentoConnector',
            'version' => '1.0.0',
        ];
    }

    public function getCustomers(?string $email = null, int $pageSize = 20): array
    {
        $this->tokenValidator->validate();
        $builder = $this->freshSearchCriteriaBuilder($pageSize);
        if ($this->normalizeText($email) !== '') {
            $builder->addFilter('email', $this->normalizeText($email));
        }

        $result = $this->customerRepository->getList($builder->create());
        $items = [];
        foreach ($result->getItems() as $customer) {
            $items[] = $this->customerToArray($customer);
        }

        return [
            'items' => $items,
            'total_count' => $result->getTotalCount(),
        ];
    }

    public function getCustomer(string $customerId): array
    {
        $this->tokenValidator->validate();
        return $this->customerToArray($this->customerRepository->getById((int) $customerId));
    }

    public function getCustomerGroup(string $groupId): array
    {
        $this->tokenValidator->validate();
        $group = $this->groupRepository->getById((int) $groupId);
        return [
            'id' => $group->getId(),
            'code' => $group->getCode(),
            'customer_group_code' => $group->getCode(),
        ];
    }

    public function getOrders(
        ?string $email = null,
        ?string $customerId = null,
        ?string $orderId = null,
        ?string $orderNumber = null,
        int $pageSize = 20,
        ?string $sortField = null,
        ?string $sortDirection = null
    ): array {
        $this->tokenValidator->validate();
        $builder = $this->freshSearchCriteriaBuilder($pageSize);
        $filters = [
            'customer_email' => $email,
            'customer_id' => $customerId,
            'entity_id' => $orderId,
            'increment_id' => $orderNumber,
        ];

        foreach ($filters as $field => $value) {
            if ($this->normalizeText($value) !== '') {
                $builder->addFilter($field, $this->normalizeText($value));
            }
        }

        if ($this->normalizeText($sortField) !== '') {
            $direction = strtoupper($this->normalizeText($sortDirection)) === SortOrder::SORT_ASC
                ? SortOrder::SORT_ASC
                : SortOrder::SORT_DESC;
            $builder->setSortOrders([
                $this->sortOrderBuilder
                    ->setField($this->normalizeText($sortField))
                    ->setDirection($direction)
                    ->create(),
            ]);
        }

        $result = $this->orderRepository->getList($builder->create());
        $items = [];
        foreach ($result->getItems() as $order) {
            $items[] = $this->orderToArray($order);
        }

        return [
            'items' => $items,
            'total_count' => $result->getTotalCount(),
        ];
    }

    public function getOrder(string $orderId): array
    {
        $this->tokenValidator->validate();
        return $this->orderToArray($this->loadOrder($orderId));
    }

    public function getOrderComments(string $orderId): array
    {
        $this->tokenValidator->validate();
        $comments = [];
        foreach ($this->loadOrder($orderId)->getStatusHistories() as $history) {
            $comments[] = [
                'entity_id' => $history->getEntityId(),
                'comment' => $history->getComment(),
                'is_visible_on_front' => (bool) $history->getIsVisibleOnFront(),
                'created_at' => $history->getCreatedAt(),
            ];
        }

        return [
            'items' => $comments,
        ];
    }

    public function addOrderComment(string $orderId, array $statusHistory): bool
    {
        $this->tokenValidator->validate();
        $order = $this->loadOrder($orderId);
        $comment = $this->normalizeText($statusHistory['comment'] ?? '');
        if ($comment === '') {
            return false;
        }

        $order->addCommentToStatusHistory(
            $comment,
            $order->getStatus(),
            (bool) ($statusHistory['is_visible_on_front'] ?? false)
        );
        $this->orderRepository->save($order);
        return true;
    }

    public function getOrderShipments(string $orderId): array
    {
        $this->tokenValidator->validate();
        $builder = $this->freshSearchCriteriaBuilder(20);
        $builder->addFilter('order_id', $this->loadOrder($orderId)->getEntityId());

        $result = $this->shipmentRepository->getList($builder->create());
        $items = [];
        foreach ($result->getItems() as $shipment) {
            $tracks = [];
            foreach ($shipment->getTracks() as $track) {
                $tracks[] = [
                    'entity_id' => $track->getEntityId(),
                    'track_number' => $track->getTrackNumber(),
                    'title' => $track->getTitle(),
                    'carrier_code' => $track->getCarrierCode(),
                    'created_at' => $track->getCreatedAt(),
                ];
            }
            $items[] = [
                'entity_id' => $shipment->getEntityId(),
                'created_at' => $shipment->getCreatedAt(),
                'tracks' => $tracks,
            ];
        }

        return [
            'items' => $items,
            'total_count' => $result->getTotalCount(),
        ];
    }

    public function cancelOrder(string $orderId): bool
    {
        $this->tokenValidator->validate();
        $order = $this->loadOrder($orderId);
        return (bool) $this->orderManagement->cancel((int) $order->getEntityId());
    }

    public function installWebhook(string $deliveryUrl, ?string $sharedSecret = null): bool
    {
        $this->tokenValidator->validate();
        $normalizedUrl = $this->normalizeText($deliveryUrl);
        if ($normalizedUrl === '' || !preg_match('/^https:\/\//i', $normalizedUrl)) {
            return false;
        }

        $this->configWriter->save(self::XML_PATH_ENABLED, '1');
        $this->configWriter->save(self::XML_PATH_CALLBACK_URL, $normalizedUrl);
        if ($this->normalizeText($sharedSecret) !== '') {
            $this->configWriter->save(self::XML_PATH_SHARED_SECRET, $this->normalizeText($sharedSecret));
        }
        return true;
    }

    public function uninstallWebhook(): bool
    {
        $this->tokenValidator->validate();
        $this->configWriter->save(self::XML_PATH_CALLBACK_URL, '');
        return true;
    }

    private function freshSearchCriteriaBuilder(int $pageSize): SearchCriteriaBuilder
    {
        $this->searchCriteriaBuilder->setPageSize(max(1, min(100, (int) $pageSize)));
        return $this->searchCriteriaBuilder;
    }

    /**
     * @return Order
     */
    private function loadOrder(string $orderId)
    {
        $normalizedOrderId = $this->normalizeText($orderId);
        if ($normalizedOrderId === '') {
            throw new NoSuchEntityException(__('Order ID is required.'));
        }

        return $this->orderRepository->get((int) $normalizedOrderId);
    }

    private function normalizeText($value): string
    {
        return trim((string) $value);
    }

    /**
     * @return mixed[]
     */
    private function customerToArray($customer): array
    {
        $addresses = [];
        foreach ((array) $customer->getAddresses() as $address) {
            $addresses[] = [
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
     * @return mixed[]
     */
    private function orderToArray($order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
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
            'billing_address' => $this->orderAddressToArray($order->getBillingAddress()),
            'extension_attributes' => [
                'shipping_assignments' => [
                    [
                        'shipping' => [
                            'address' => $this->orderAddressToArray($order->getShippingAddress()),
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
     * @return mixed[]
     */
    private function orderAddressToArray($address): array
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
