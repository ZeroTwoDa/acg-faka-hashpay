# ACG-Faka HashPay v1.0.1

本版本强化回调幂等行为，并补充重放防护、密钥轮换、失败路径和沙箱联调文档与测试。

## 变更

- 合法重复通知在订单已支付时直接返回 `success`，避免 HashPay 无意义重试。
- 仍以 ACG-Faka 原有订单状态、渠道归属和数据库事务作为最终幂等屏障，不重复发货或入账。
- 回调验证新增明确的远端 `orderId` 非空及 `status=paid` 检查。
- README 补充双层时间戳窗口、GCM 完整性、重复/乱序通知和最终幂等边界说明。
- README 补充 HashPay 商户密钥轮换流程与历史通知注意事项。
- 新增独立加密回归测试及公开测试密钥，覆盖正常、重复、篡改、过期、状态、币种、金额保真和错误私钥。
- 新增商品订单、余额充值、重复通知、错误私钥和失败日志的沙箱/小额联调清单。

## 兼容性

- ACG-Faka 3.5.6（commit `60b26c56`）
- PHP 8.0+
- 不修改 ACG-Faka 核心文件
- 配置与 v1.0.0 兼容

## 更新

覆盖以下路径前，请备份 `app/Pay/HashPay/Config/Config.php`：

```text
app/Pay/HashPay/
app/Controller/User/Api/HashPayNotification.php
```

覆盖后恢复配置和文件权限，并重启 PHP-FPM 或清除 OPcache。

## 回调地址

```text
https://你的商城域名/user/api/hashPayNotification/callback.HashPay
```

## 文件权限（PHP-FPM 用户为 www）

```bash
chown -R www:www app/Pay/HashPay
chown www:www app/Controller/User/Api/HashPayNotification.php
find app/Pay/HashPay -type d -exec chmod 750 {} \;
find app/Pay/HashPay -type f -exec chmod 640 {} \;
chmod 660 app/Pay/HashPay/Config/Config.php
touch app/Pay/HashPay/runtime.log
chown www:www app/Pay/HashPay/runtime.log
chmod 660 app/Pay/HashPay/runtime.log
chmod 640 app/Controller/User/Api/HashPayNotification.php
```

## SHA-256

安装包：

```text
acg-faka-hashpay-v1.0.1.zip
```

```text
31c77eb9545a67e50b253587ed2311439d62d1c4f3194b291ef97a4384a6d7a4
```

校验：

```bash
sha256sum acg-faka-hashpay-v1.0.1.zip
```

## 协议

GNU General Public License v3.0 only
