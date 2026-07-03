<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['tsa_test_options'] = [];

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function get_option( $key, $default = false ) { return $GLOBALS['tsa_test_options'][ $key ] ?? $default; }
function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }

class WC_Shipping_Rate {
    private string $id;
    private string $label;
    private float $cost;
    private string $method_id;

    public function __construct( $id = '', $label = '', $cost = 0, $taxes = [], $method_id = '', $instance_id = 0 ) {
        $this->id = (string) $id;
        $this->label = (string) $label;
        $this->cost = (float) $cost;
        $this->method_id = (string) $method_id;
    }

    public function get_id(): string { return $this->id; }
    public function get_method_id(): string { return $this->method_id; }
}

require dirname( __DIR__ ) . '/inc/store-cart.php';

function tsa_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

tsa_assert(
    function_exists( 'tsa_build_store_shipping_rates' ),
    'A testable store shipping-rate policy function must exist.'
);

$flat = new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', 8, [], 'flat_rate' );
$core_pickup = new WC_Shipping_Rate( 'local_pickup:2', 'Local pickup', 0, [], 'local_pickup' );

$rates = tsa_build_store_shipping_rates(
    [],
    'dutchtown',
    [ 'pickups' => [ '12192 Deventer Drive' ], 'shipping' => true ]
);
tsa_assert( isset( $rates['tsa_pickup_dutchtown_0'] ), 'Pickup must exist when WooCommerce returns no zone rates.' );

$rates = tsa_build_store_shipping_rates(
    [ 'flat_rate:1' => $flat, 'local_pickup:2' => $core_pickup ],
    'dutchtown',
    [ 'pickups' => [ '12192 Deventer Drive' ], 'shipping' => true ]
);
tsa_assert( isset( $rates['flat_rate:1'] ), 'Shipping-enabled stores must retain WooCommerce delivery rates.' );
tsa_assert( ! isset( $rates['local_pickup:2'] ), 'Core pickup must be replaced by configured store pickup.' );

$rates = tsa_build_store_shipping_rates(
    [ 'flat_rate:1' => $flat ],
    'dutchtown',
    [ 'pickups' => [ '12192 Deventer Drive' ], 'shipping' => false ]
);
tsa_assert( ! isset( $rates['flat_rate:1'] ), 'Pickup-only stores must exclude delivery rates.' );

$rates = tsa_build_store_shipping_rates(
    [ 'flat_rate:1' => $flat ],
    'football',
    [ 'pickups' => [], 'shipping' => false ]
);
tsa_assert( isset( $rates['flat_rate:1'] ), 'Empty store configuration must retain WooCommerce defaults.' );

echo "PASS: store shipping policy\n";

