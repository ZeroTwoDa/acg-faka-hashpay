<?php
declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Base\API\User;
use App\Interceptor\Waf;
use App\Model\Order as OrderModel;
use App\Model\UserRecharge;
use App\Util\CallbackIpWhitelist;
use App\Util\PayConfig;
use App\Util\Str;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Context\Interface\Request;
use Kernel\Exception\JSONException;
use Kernel\Util\Arr;

#[Interceptor(Waf::class, Interceptor::TYPE_API)]
final class HashPayNotification extends User
{
    #[Inject]
    private \App\Service\Order $order;

    #[Inject]
    private \App\Service\Recharge $recharge;

    public function callback(Request $request): string
    {
        CallbackIpWhitelist::enforce();
        $handle = (string)($_GET['_PARAMETER'][0] ?? '');
        if (!Str::isValid($handle) || !PayConfig::isValid($handle)) {
            throw new JSONException('handle not found');
        }

        $data = [];
        foreach (['unsafePost', 'unsafeJson', 'unsafeGet'] as $method) {
            $candidate = $request->$method();
            if (is_array($candidate)) {
                unset($candidate['s'], $candidate['_PARAMETER']);
                if ($candidate !== []) {
                    $data = $candidate;
                    break;
                }
            }
        }
        if ($data === []) {
            $decoded = json_decode($request->raw(), true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if ($data === []) {
            $xml = Arr::xmlToArray($request->raw());
            $data = is_array($xml) ? $xml : [];
        }
        if ($data === []) {
            throw new JSONException('数据为空');
        }
        if ((isset($data['sign']) && Str::isInvalidSign((string)$data['sign']))
            || (isset($data['signature']) && Str::isInvalidSign((string)$data['signature']))) {
            throw new JSONException('非法签名');
        }

        $signatureClass = "\\App\\Pay\\{$handle}\\Impl\\Signature";
        $config = PayConfig::config($handle) ?? [];
        if (!class_exists($signatureClass) || !is_callable([$signatureClass, 'callbackTradeNo'])) {
            throw new JSONException('signature not implements interface');
        }
        try {
            $payload = $signatureClass::decryptAndValidate($data, $config);
            $tradeNo = $payload['merchantNo'] ?? null;
        } catch (\Throwable $e) {
            PayConfig::log($handle, 'CALLBACK', '统一回调认证或解密失败 error=' . $e->getMessage());
            throw new JSONException('HashPay callback verification failed');
        }
        if (!is_scalar($tradeNo) || trim((string)$tradeNo) === '') {
            PayConfig::log($handle, 'CALLBACK', '统一回调缺少有效 merchantNo');
            throw new JSONException('order number not found');
        }
        $tradeNo = trim((string)$tradeNo);

        $delegatedData = $data;
        $delegatedData['merchantNo'] = $tradeNo;

        $commodityOrder = OrderModel::with(['pay'])->where('trade_no', $tradeNo)->first();
        $rechargeOrder = UserRecharge::with(['pay'])->where('trade_no', $tradeNo)->first();
        if ($commodityOrder && $rechargeOrder) {
            PayConfig::log($handle, 'CALLBACK', '商品订单与充值订单号冲突，拒绝自动分发 trade_no=' . $tradeNo);
            throw new JSONException('ambiguous order number');
        }
        if ($commodityOrder) {
            if (!$commodityOrder->pay || $commodityOrder->pay->handle !== $handle) {
                throw new JSONException('pay handle not found');
            }
            if ((int)$commodityOrder->status !== 0) {
                PayConfig::log($handle, 'CALLBACK', '合法重复通知已确认 trade_no=' . $tradeNo);
                return 'success';
            }
            return $this->order->callback($handle, $delegatedData);
        }
        if ($rechargeOrder) {
            if (!$rechargeOrder->pay || $rechargeOrder->pay->handle !== $handle) {
                throw new JSONException('pay handle not found');
            }
            if ((int)$rechargeOrder->status !== 0) {
                PayConfig::log($handle, 'CALLBACK-RECHARGE', '合法重复通知已确认 trade_no=' . $tradeNo);
                return 'success';
            }
            return $this->recharge->callback($handle, $delegatedData);
        }

        PayConfig::log($handle, 'CALLBACK', '统一回调未找到订单 trade_no=' . $tradeNo);
        throw new JSONException('order not found');
    }
}
