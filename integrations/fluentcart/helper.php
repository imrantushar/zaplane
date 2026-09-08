<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

    protected static function field_products(): array {
        return [
            [
                'key'      => 'products',
                'label'    => 'Products',
                'type'     => 'repeater',
                'required' => true,
                'fields'   => [
                    [
                        'key'      => 'product_id',
                        'label'    => 'Product',
                        'type'     => 'select',
                        'dynamic'  => [
                            'integration' => 'fluentcart',
                            'query'       => 'products',
                            'select'      => [ 'name', 'label' ],
                        ],
                        'required' => true,
                    ],
                    [
                        'key'      => 'quantity',
                        'label'    => 'Quantity',
                        'type'     => 'number',
                        'default'  => 1,
                        'min'      => 1,
                        'required' => true,
                    ],
                    [
                        'key'      => 'unit_price',
                        'label'    => 'Unit Price',
                        'type'     => 'number',
                        'step'     => '0.01',
                        'required' => true,
                    ],
                ],
            ],
        ];
    }

    protected static function field_customer_id(): array {
        return [
            [
                'key'      => 'customer_id',
                'label'    => 'Customer',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'customers',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_order_status(): array {
        return [
            [
                'key'      => 'order_status',
                'label'    => 'Order Status',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'order_statuses',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_subscription_status(): array {
        return [
            [
                'key'      => 'subscription_status',
                'label'    => 'Subscription Status',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'subscription_statuses',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_subscription_id(): array {
        return [
            [
                'key'      => 'subscription_id',
                'label'    => 'Subscription',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'subscriptions',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_shipping_status(): array {
        return [
            [
                'key'     => 'shipping_status',
                'label'   => 'Shipping Status',
                'type'    => 'select',
                'options' => [
                    [
                        'label' => 'Select a shipping status',
                        'value' => '',
                    ],
                    [
                        'label' => 'Pending',
                        'value' => 'pending',
                    ],
                    [
                        'label' => 'Processing',
                        'value' => 'processing',
                    ],
                    [
                        'label' => 'Shipped',
                        'value' => 'shipped',
                    ],
                    [
                        'label' => 'Delivered',
                        'value' => 'delivered',
                    ],
                    [
                        'label' => 'Cancelled',
                        'value' => 'cancelled',
                    ],
                ],
            ],
        ];
    }

    protected static function field_fulfillment_type(): array {
        return [
            [
                'key'     => 'fulfillment_type',
                'label'   => 'Fulfillment Type',
                'type'    => 'select',
                'options' => [
                    [
                        'label' => 'Select a fulfillment type',
                        'value' => '',
                    ],
                    [
                        'label' => 'Physical',
                        'value' => 'physical',
                    ],
                    [
                        'label' => 'Digital',
                        'value' => 'digital',
                    ],
                    [
                        'label' => 'Service',
                        'value' => 'service',
                    ],
                ],
            ],
        ];
    }

    protected static function field_order_type(): array {
        return [
            [
                'key'     => 'order_type',
                'label'   => 'Order Type',
                'type'    => 'select',
                'options' => [
                    [
                        'label' => 'Select an order type',
                        'value' => '',
                    ],
                    [
                        'label' => 'Normal',
                        'value' => 'normal',
                    ],
                    [
                        'label' => 'Subscription',
                        'value' => 'subscription',
                    ],
                    [
                        'label' => 'Renewal',
                        'value' => 'renewal',
                    ],
                ],
            ],
        ];
    }

    protected static function field_order_mode(): array {
        return [
            [
                'key'     => 'order_mode',
                'label'   => 'Order Mode',
                'type'    => 'select',
                'options' => [
                    [
                        'label' => 'Select order mode',
                        'value' => '',
                    ],
                    [
                        'label' => 'Live',
                        'value' => 'live',
                    ],
                    [
                        'label' => 'Test',
                        'value' => 'test',
                    ],
                ],
            ],
        ];
    }

    protected static function field_payment_method(): array {
        return [
            [
                'key'   => 'payment_method',
                'label' => 'Payment Method',
                'type'  => 'text',
            ],
        ];
    }

    protected static function field_payment_method_title(): array {
        return [
            [
                'key'   => 'payment_method_title',
                'label' => 'Payment Method Title',
                'type'  => 'text',
            ],
        ];
    }

    protected static function field_payment_status(): array {
        return [
            [
                'key'      => 'payment_status',
                'label'    => 'Payment Status',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'payment_statuses',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_currency_code(): array {
        return [
            [
                'key'         => 'currency_code',
                'label'       => 'Currency Code',
                'type'        => 'text',
                'placeholder' => 'USD',
            ],
        ];
    }

    protected static function field_subtotal(): array {
        return [
            [
                'key'   => 'subtotal',
                'label' => 'Subtotal',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_discount_tax(): array {
        return [
            [
                'key'   => 'discount_tax',
                'label' => 'Discount Tax',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_manual_discount_total(): array {
        return [
            [
                'key'   => 'manual_discount_total',
                'label' => 'Manual Discount Total',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_coupon_discount_total(): array {
        return [
            [
                'key'   => 'coupon_discount_total',
                'label' => 'Coupon Discount Total',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_shipping_tax(): array {
        return [
            [
                'key'   => 'shipping_tax',
                'label' => 'Shipping Tax',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_shipping_total(): array {
        return [
            [
                'key'   => 'shipping_total',
                'label' => 'Shipping Total',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_tax_total(): array {
        return [
            [
                'key'   => 'tax_total',
                'label' => 'Tax Total',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_total_amount(): array {
        return [
            [
                'key'   => 'total_amount',
                'label' => 'Total Amount',
                'type'  => 'number',
                'step'  => '0.01',
            ],
        ];
    }

    protected static function field_exchange_rate(): array {
        return [
            [
                'key'   => 'exchange_rate',
                'label' => 'Exchange Rate',
                'type'  => 'number',
                'step'  => '0.000001',
            ],
        ];
    }

    protected static function field_tax_behavior(): array {
        return [
            [
                'key'     => 'tax_behavior',
                'label'   => 'Tax Behavior',
                'type'    => 'select',
                'options' => [
                    [
                        'label' => 'Select tax behavior',
                        'value' => '',
                    ],
                    [
                        'label' => 'Exclusive',
                        'value' => 'exclusive',
                    ],
                    [
                        'label' => 'Inclusive',
                        'value' => 'inclusive',
                    ],
                ],
            ],
        ];
    }

    protected static function field_order_note(): array {
        return [
            [
                'key'   => 'order_note',
                'label' => 'Order Note',
                'type'  => 'textarea',
            ],
        ];
    }

    protected static function field_order_id(): array {
        return [
            [
                'key'      => 'order_id',
                'label'    => 'Order',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'orders',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_product_id(): array {
        return [
            [
                'key'      => 'product_id',
                'label'    => 'Product',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'products',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_metadata_key(): array {
        return [
            [
                'key'   => 'metadata_key',
                'label' => 'Metadata Key',
                'type'  => 'text',
                'required' => true,
            ],
        ];
    }

    protected static function field_metadata_value(): array {
        return [
            [
                'key'   => 'metadata_value',
                'label' => 'Metadata Value',
                'type'  => 'textarea',
                'required' => true,
            ],
        ];
    }

    protected static function field_customer_status(): array {
        return [
            [
                'key'      => 'customer_status',
                'label'    => 'Customer Status',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'customer_statuses',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_post_status(): array {
        return [
            [
                'key'      => 'post_status',
                'label'    => 'Post Status',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'post_statuses',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => false,
            ],
        ];
    }

    protected static function field_limit_page(): array {
        return [
            [
                'key'     => 'limit',
                'label'   => 'Limit',
                'type'    => 'number',
                'required' => true,
            ],
            [
                'key'     => 'page',
                'label'   => 'Page',
                'type'    => 'number',
                'required' => true,
            ],
        ];
    }

    protected static function field_customer_fields_mapping(): array {
        return [
            [
                'key'      => 'customer_fields_mapping',
                'label'    => 'Customer Fields Mapping',
                'type'     => 'repeater',
                'required' => false,
                'fields'   => [
                    [
                        'key'      => 'customer_field',
                        'label'    => 'Customer Field',
                        'type'     => 'select',
                        'options'  => [
                            [
                                'label' => 'First Name',
                                'value' => 'first_name',
                            ],
                            [
                                'label' => 'Last Name',
                                'value' => 'last_name',
                            ],
                            [
                                'label' => 'Email',
                                'value' => 'email',
                            ],
                            [
                                'label' => 'Status',
                                'value' => 'status',
                            ],
                            [
                                'label' => 'Country',
                                'value' => 'country',
                            ],
                            [
                                'label' => 'City',
                                'value' => 'city',
                            ],
                            [
                                'label' => 'State',
                                'value' => 'state',
                            ],
                            [
                                'label' => 'Postal Code',
                                'value' => 'postal_code',
                            ],
                            [
                                'label' => 'Notes',
                                'value' => 'notes',
                            ],
                            [
                                'label' => 'Address',
                                'value' => 'address',
                            ],
                            [
                                'label' => 'Address 2',
                                'value' => 'address_2',
                            ],
                        ],
                        'required' => true,
                    ],
                    [
                        'key'         => 'value',
                        'label'       => 'Value',
                        'type'        => 'text',
                        'placeholder' => 'Enter value',
                        'required'    => true,
                    ],
                ],
            ],
        ];
    }

        protected static function field_search(): array {
        return [
            [
                'key'   => 'search',
                'label' => 'Search',
                'type'  => 'text',
            ],
        ];
    }

    protected static function field_transaction_id(): array {
        return [
            [
                'key'      => 'transaction_id',
                'label'    => 'Transaction ID',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    protected static function field_coupon_id(): array {
        return [
            [
                'key'      => 'coupon_id',
                'label'    => 'Coupon',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'fluentcart',
                    'query'       => 'coupons',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
        ];
    }

    protected static function field_license_id(): array {
        return [
            [
                'key'      => 'license_id',
                'label'    => 'License ID',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    protected static function field_coupon_fields(): array {
        return [
            [
                'key'      => 'code',
                'label'    => 'Coupon Code',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'amount',
                'label'    => 'Amount',
                'type'     => 'number',
                'step'     => '0.01',
            ],
            [
                'key'     => 'type',
                'label'   => 'Discount Type',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Select a type', 'value' => '' ],
                    [ 'label' => 'Percentage', 'value' => 'percent' ],
                    [ 'label' => 'Fixed Amount', 'value' => 'fixed' ],
                ],
            ],
            [
                'key'     => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Select a status', 'value' => '' ],
                    [ 'label' => 'Active', 'value' => 'active' ],
                    [ 'label' => 'Inactive', 'value' => 'inactive' ],
                ],
            ],
            [
                'key'   => 'expiry_date',
                'label' => 'Expiry Date',
                'type'  => 'date',
            ],
        ];
    }
}
