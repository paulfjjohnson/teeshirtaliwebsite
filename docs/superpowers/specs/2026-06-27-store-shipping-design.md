# Store Shipping Repair Design

## Goal

Ensure a cart assigned to a configured TSA store always receives that store's pickup choices, while retaining WooCommerce zone-based shipping methods when shipping is enabled for the store.

## Architecture

WooCommerce remains the shipping engine and source of zone-based methods such as Flat Rate. The TSA store-delivery layer filters the package rates after WooCommerce calculates them and adds store-specific pickup rates independently of zone results.

## Behavior

- Resolve one store key from the cart and include it in the shipping package cache key.
- If the store has delivery configuration, add each configured pickup location as a free rate.
- When store shipping is enabled, retain non-pickup rates produced by WooCommerce.
- When store shipping is disabled, omit WooCommerce delivery rates.
- If a store configuration contains neither pickup locations nor enabled shipping, retain WooCommerce defaults to avoid making checkout unusable.
- General carts and stores without delivery configuration retain WooCommerce defaults unchanged.

## Diagnostics

Administrators receive a checkout diagnostic showing the resolved store key and available rate IDs. Customers do not see diagnostics. Diagnostic data must not include customer addresses or other personal information.

## Administration UI

Render recognized store slugs as valid code elements without displaying escaped HTML tags.

## Verification

- A configured store with pickup and no WooCommerce zone rates still receives pickup.
- A configured store with shipping enabled retains WooCommerce delivery rates.
- A pickup-only store excludes WooCommerce delivery rates.
- An empty store configuration falls back to WooCommerce defaults.
- General carts remain unchanged.
- PHP syntax checks pass for every modified PHP file.

