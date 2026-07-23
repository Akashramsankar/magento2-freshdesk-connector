# Freshworks Magento Connector Extension

Companion Magento 2 module for the Freshdesk Magento connector.

## Install in a Magento 2 store

Copy `Freshworks/MagentoConnector` into `app/code/Freshworks/MagentoConnector`, then run:

```bash
bin/magento module:enable Freshworks_MagentoConnector
bin/magento setup:upgrade
bin/magento cache:flush
```

For Composer packaging, publish this module folder as `freshworks/magento2-connector` and install it with:

```bash
composer require freshworks/magento2-connector
bin/magento module:enable Freshworks_MagentoConnector
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configure

In Magento Admin, open `Stores > Configuration > Services > Freshworks Connector`.

Set:

- `Enable Event Sync`: Yes
- `Freshworks Callback URL`: the URL shown by the Freshdesk app's `Reconnect Sync` action
- Enable the customer/order event toggles you need

When enabled, the module sends these events to Freshworks:

- `customer.created`
- `customer.updated`
- `order.created`
