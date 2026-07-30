<?php
declare(strict_types=1);

return [
    'name' => 'HashPay 加密货币支付',
    'version' => '1.0.0',
    'author' => 'ZeroTwoDa',
    'description' => 'HashPay RSA 签名 API 与 RSA-OAEP-256+A256GCM 加密回调',
    'options' => [
        'hashpay' => 'HashPay',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'paid',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'merchantNo',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'amount',
        \App\Consts\Pay::FIELD_RESPONSE => 'success',
    ],
];
