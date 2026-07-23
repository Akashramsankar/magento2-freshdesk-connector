<?php

namespace Freshworks\MagentoConnector\Api;

interface ConnectorInterface
{
    /**
     * @return mixed[]
     */
    public function ping(): array;

    /**
     * @param string|null $email
     * @param int $pageSize
     * @return mixed[]
     */
    public function getCustomers(?string $email = null, int $pageSize = 20): array;

    /**
     * @param string $customerId
     * @return mixed[]
     */
    public function getCustomer(string $customerId): array;

    /**
     * @param string $groupId
     * @return mixed[]
     */
    public function getCustomerGroup(string $groupId): array;

    /**
     * @param string|null $email
     * @param string|null $customerId
     * @param string|null $orderId
     * @param string|null $orderNumber
     * @param int $pageSize
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @return mixed[]
     */
    public function getOrders(
        ?string $email = null,
        ?string $customerId = null,
        ?string $orderId = null,
        ?string $orderNumber = null,
        int $pageSize = 20,
        ?string $sortField = null,
        ?string $sortDirection = null
    ): array;

    /**
     * @param string $orderId
     * @return mixed[]
     */
    public function getOrder(string $orderId): array;

    /**
     * @param string $orderId
     * @return mixed[]
     */
    public function getOrderComments(string $orderId): array;

    /**
     * @param string $orderId
     * @param mixed[] $statusHistory
     * @return bool
     */
    public function addOrderComment(string $orderId, array $statusHistory): bool;

    /**
     * @param string $orderId
     * @return mixed[]
     */
    public function getOrderShipments(string $orderId): array;

    /**
     * @param string $orderId
     * @return bool
     */
    public function cancelOrder(string $orderId): bool;

    /**
     * @param string $deliveryUrl
     * @param string|null $sharedSecret
     * @return bool
     */
    public function installWebhook(string $deliveryUrl, ?string $sharedSecret = null): bool;

    /**
     * @return bool
     */
    public function uninstallWebhook(): bool;
}
