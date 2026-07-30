<?php
declare(strict_types=1);

namespace App\Pay\HashPay\Impl;

use App\Entity\PayEntity;
use Kernel\Exception\JSONException;

final class Pay extends \App\Pay\Base implements \App\Pay\Pay
{
    public function trade(): PayEntity
    {
        try {
            $client = new HashPayClient($this->config);
            $result = $client->createOrder(
                $this->tradeNo,
                $this->amount,
                'CNY',
                (string)($this->config['description'] ?? 'Order') . ' ' . $this->tradeNo,
                $this->returnUrl
            );
            $checkoutUrl = trim((string)($result['checkoutUrl'] ?? ''));
            if ($checkoutUrl === '' || !preg_match('~^https://~i', $checkoutUrl)) {
                throw new \RuntimeException('HashPay 未返回有效收银台地址');
            }
            $remoteId = (string)($result['order']['id'] ?? '');
            $this->log('创建订单成功 trade_no=' . $this->tradeNo . ' hashpay_order=' . $remoteId);
            $entity = new PayEntity();
            $entity->setType(\App\Pay\Pay::TYPE_REDIRECT);
            $entity->setUrl($checkoutUrl);
            $entity->setOption(['hashpay_order_id' => $remoteId]);
            return $entity;
        } catch (\Throwable $e) {
            $this->log('创建订单失败 trade_no=' . $this->tradeNo . ' error=' . $e->getMessage());
            throw new JSONException('HashPay 下单失败：' . $e->getMessage());
        }
    }
}
