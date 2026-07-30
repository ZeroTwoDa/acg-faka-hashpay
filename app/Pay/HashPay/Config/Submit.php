<?php
declare(strict_types=1);

return [
    ['title' => 'HashPay 站点地址', 'name' => 'api_url', 'type' => 'input', 'required' => true, 'placeholder' => 'https://pay.example.com'],
    ['title' => '商户 ID', 'name' => 'merchant_id', 'type' => 'input', 'required' => true],
    ['title' => '商户 RSA 私钥（PKCS#8 PEM）', 'name' => 'private_key', 'type' => 'textarea', 'required' => true, 'placeholder' => "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"],
    ['title' => '订单描述前缀', 'name' => 'description', 'type' => 'input', 'required' => true],
    ['title' => '回调时间窗口（秒）', 'name' => 'callback_window', 'type' => 'number', 'required' => true, 'placeholder' => '300'],
];
