# ACG-Faka HashPay v1.0.0

由 **ZeroTwoDa** 开发的 ACG-Faka HashPay 加密货币支付模块首个正式版本。

适配 [ACG-Faka](https://github.com/lizhipay/acg-faka) 3.5.6，为商城提供 HashPay 加密货币支付能力，支持商品购买和余额充值，并通过一个统一回调地址自动识别订单类型。

## 主要功能

- 支持 `POST /api/merchant/new` 创建 HashPay 订单
- 支持 `GET /api/order/:orderId` 查询 HashPay 订单
- 使用商户 RSA 私钥生成 `RSASSA-PKCS1-v1_5 SHA-256` 请求签名
- 自动设置 `X-Merchant-Id`、`X-Timestamp`、`X-Signature`
- 支持 `RSA-OAEP-256+A256GCM` 加密回调
- 校验 RSA-OAEP-SHA256、AES-256-GCM Tag 和回调时间戳
- 校验商户 ID、订单号、状态、金额和币种
- 商品订单与余额充值使用同一个 HashPay 商户回调地址
- 自动识别商品订单和余额充值订单
- 复用 ACG-Faka 原有事务、幂等入账和自动发货流程
- 内置 phpseclib 纯 PHP RSA 引擎，不依赖 GMP 或 BCMath
- 不修改 ACG-Faka 核心文件
- 后台保存的 RSA 私钥不会回显
- 日志不会记录 RSA 私钥、请求签名或完整密文

## 适配环境

- ACG-Faka 3.5.6
- ACG-Faka commit：`60b26c56f23d02da94715b1fc92899f02c86f35f`
- PHP 8.0+
- PHP OpenSSL、JSON、cURL 扩展
- HTTPS 商城域名
- HashPay REST API 商户

## 下载与完整性校验

安装包：

```text
acg-faka-hashpay-v1.0.0.zip
```

SHA-256：

```text
a76dc837c096ba8ddcf970b67d734b68b13b4d219c4786c16c46816bfd1cf2d2
```

Linux 校验命令：

```bash
sha256sum acg-faka-hashpay-v1.0.0.zip
```

macOS 校验命令：

```bash
shasum -a 256 acg-faka-hashpay-v1.0.0.zip
```

正确结果应为：

```text
a76dc837c096ba8ddcf970b67d734b68b13b4d219c4786c16c46816bfd1cf2d2  acg-faka-hashpay-v1.0.0.zip
```

## 安装方法

下载并解压安装包，将其中的 `app` 目录合并到 ACG-Faka 网站根目录。

安装后应存在：

```text
app/Pay/HashPay/
app/Controller/User/Api/HashPayNotification.php
```

请确认以下依赖目录已完整上传：

```text
app/Pay/HashPay/Vendor/
```

如果该目录缺失或上传不完整，回调会提示：

```text
HashPay RSA dependency is missing
```

## 设置文件权限

以下命令假设当前目录为 ACG-Faka 网站根目录，PHP-FPM 运行用户及用户组均为 `www`：

```bash
chown -R www:www app/Pay/HashPay
chown www:www app/Controller/User/Api/HashPayNotification.php

find app/Pay/HashPay -type d -exec chmod 750 {} \;
find app/Pay/HashPay -type f -exec chmod 640 {} \;

chmod 750 app/Pay/HashPay/Config
chmod 660 app/Pay/HashPay/Config/Config.php
chmod 640 app/Controller/User/Api/HashPayNotification.php

touch app/Pay/HashPay/runtime.log
chown www:www app/Pay/HashPay/runtime.log
chmod 660 app/Pay/HashPay/runtime.log
```

一条命令执行：

```bash
cd /www/wwwroot/你的站点目录 && chown -R www:www app/Pay/HashPay app/Controller/User/Api/HashPayNotification.php && find app/Pay/HashPay -type d -exec chmod 750 {} \; && find app/Pay/HashPay -type f -exec chmod 640 {} \; && chmod 660 app/Pay/HashPay/Config/Config.php && touch app/Pay/HashPay/runtime.log && chown www:www app/Pay/HashPay/runtime.log && chmod 660 app/Pay/HashPay/runtime.log && chmod 640 app/Controller/User/Api/HashPayNotification.php
```

请将 `/www/wwwroot/你的站点目录` 替换为实际网站目录，请勿长期使用 `777`。

## 后台配置

进入：

```text
ACG-Faka 后台 → 支付插件 → HashPay 加密货币支付
```

填写：

- HashPay 站点地址
- 商户 ID
- 完整 PKCS#8 PEM RSA 私钥
- 订单描述前缀
- 回调时间窗口，建议 `300` 秒

然后新增支付接口：

- 插件选择：`HashPay`
- 支付方式选择：`HashPay`
- 按需启用商品购买和余额充值

ACG-Faka 使用人民币计价，因此模块固定向 HashPay 提交 `CNY`，并强制校验回调币种为 `CNY`。

## HashPay 回调地址

HashPay 商户后台只需填写一个统一回调地址：

```text
https://你的商城域名/user/api/hashPayNotification/callback.HashPay
```

注意事项：

- 必须使用 HTTPS
- `hashPayNotification` 的大小写需保持一致
- 后缀必须为 `.HashPay`
- 不需要为商品订单和余额充值分别创建 HashPay 商户

统一入口会认证并解密 `merchantNo`，自动查询商品订单表和余额充值表，再交给 ACG-Faka 原有服务处理。如果两个订单表意外存在相同订单号，模块会拒绝处理，不会猜测订单类型或错误入账。

## 更新与缓存

覆盖模块文件前，请备份：

```text
app/Pay/HashPay/Config/Config.php
```

```bash
cp app/Pay/HashPay/Config/Config.php /tmp/hashpay-config.php
```

覆盖后恢复配置：

```bash
cp /tmp/hashpay-config.php app/Pay/HashPay/Config/Config.php
chown www:www app/Pay/HashPay/Config/Config.php
chmod 660 app/Pay/HashPay/Config/Config.php
```

更新后请重启 PHP-FPM 或清除 OPcache。宝塔用户可在对应 PHP 版本页面点击“重启”。

## 故障排查

插件日志位于：

```text
app/Pay/HashPay/runtime.log
```

如果 HashPay 已支付但 ACG-Faka 未更新：

1. 核对统一回调地址
2. 确认 `Vendor` 目录完整上传
3. 查看插件 `CALLBACK` 日志
4. 检查服务器时间同步
5. 在 HashPay 后台重新发送通知

## 安全提示

- 不要将真实商户 ID、RSA 私钥、`Config.php` 或运行日志提交到 GitHub
- 不要在截图中暴露 RSA 私钥
- 如果私钥曾公开，应立即在 HashPay 后台轮换
- 生产环境必须使用 HTTPS
- 建议启用服务器时间自动同步

## 开源协议

本项目由 **ZeroTwoDa** 开发，使用 **GNU General Public License v3.0 only**。完整协议请参阅仓库中的 `LICENSE` 文件。
