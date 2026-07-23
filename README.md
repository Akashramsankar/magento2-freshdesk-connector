# Magento 2 Freshdesk Connector Extension

Companion Magento 2 module for the Freshdesk Magento connector.

This extension prepares Magento 2 customer/order data for the Freshdesk connector app, provides a Magento-generated API token, and sends customer/order events to the Freshdesk app's external-event callback URL. The Freshdesk app can display Magento data on tickets, create or update contacts, and create tickets for new customers or orders.

## Install From GitHub

From the Magento 2 project root, run:

```bash
mkdir -p app/code/Freshworks
git clone https://github.com/Akashramsankar/magento2-freshdesk-connector.git app/code/Freshworks/MagentoConnector

bin/magento module:enable Freshworks_MagentoConnector
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Install In DDEV

From the DDEV Magento 2 project root, run:

```bash
mkdir -p app/code/Freshworks
git clone https://github.com/Akashramsankar/magento2-freshdesk-connector.git app/code/Freshworks/MagentoConnector

ddev magento module:enable Freshworks_MagentoConnector
ddev magento setup:upgrade
ddev magento setup:di:compile
ddev magento cache:flush
```

## Manual Install

Copy this repository into `app/code/Freshworks/MagentoConnector`, then run:

```bash
bin/magento module:enable Freshworks_MagentoConnector
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Composer Install

If this module is published to a Composer repository as `freshworks/magento2-connector`, install it with:

```bash
composer require freshworks/magento2-connector
bin/magento module:enable Freshworks_MagentoConnector
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configure

In Magento Admin, open:

`Stores > Configuration > Services > Freshworks Connector`

Set:

- `Enable Event Sync`: `Yes`
- `Magento API Token`: click `Generate new token`, then save config
- `Freshworks Callback URL`: the URL shown by the Freshdesk app's `Reconnect Sync` action
- `Shared Secret`: optional, but recommended when the Freshdesk app is configured to verify signatures
- `Send Customer Created Events`: `Yes`
- `Send Customer Updated Events`: `Yes`, if contact updates should sync
- `Send Order Created Events`: `Yes`

Save config and flush cache:

```bash
bin/magento cache:flush
```

For DDEV:

```bash
ddev magento cache:flush
```

## Freshdesk App Setup

In the Freshdesk app settings:

1. Connect and verify the Magento store.
2. Paste the token generated in Magento into the `Magento API Token` field.
3. Enable the sync options you need:
   - Create/update contact when customer is created or updated
   - Create ticket when customer is created
   - Create ticket when order is created
4. Click `Reconnect Sync`.

The Freshdesk app will try to install the callback URL into this Magento extension automatically. If automatic installation fails, copy the callback URL from the success message and paste it into Magento Admin under `Freshworks Callback URL`.

The Freshdesk app also supports native Magento admin/integration access tokens. When verifying a store, it first checks this extension's `/freshworks/ping` endpoint. If that endpoint is unavailable, it falls back to native Magento REST verification.

## Extension API Endpoints

The generated Magento API token protects these endpoints:

- `GET /rest/<store-code>/V1/freshworks/ping`
- `GET /rest/<store-code>/V1/freshworks/customers`
- `GET /rest/<store-code>/V1/freshworks/customers/:customerId`
- `GET /rest/<store-code>/V1/freshworks/customer-groups/:groupId`
- `GET /rest/<store-code>/V1/freshworks/orders`
- `GET /rest/<store-code>/V1/freshworks/orders/:orderId`
- `GET /rest/<store-code>/V1/freshworks/orders/:orderId/comments`
- `POST /rest/<store-code>/V1/freshworks/orders/:orderId/comments`
- `GET /rest/<store-code>/V1/freshworks/orders/:orderId/shipments`
- `POST /rest/<store-code>/V1/freshworks/orders/:orderId/cancel`
- `POST /rest/<store-code>/V1/freshworks/webhook/install`
- `POST /rest/<store-code>/V1/freshworks/webhook/uninstall`

Send the token as:

```text
Authorization: Bearer <Magento API Token>
```

## Events Sent

When enabled, this extension sends:

- `customer.created`
- `customer.updated`
- `order.created`

Each event is sent as a JSON `POST` request with these headers:

- `X-Magento-Topic`
- `X-Magento-Source`
- `X-Freshworks-Magento-Version`
- `X-Freshworks-Magento-Signature`, when a shared secret is configured

## Test The Setup

After configuration, create a new Magento customer or place a new order. Then check Freshdesk for the expected contact update or ticket.

For local DDEV testing, make sure your Magento store is reachable from Freshdesk through a public HTTPS tunnel before testing hosted app webhooks.
