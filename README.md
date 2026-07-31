# ACG-Faka HashPay

[![License](https://img.shields.io/badge/license-GPL--3.0--only-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4.svg)](https://www.php.net/)
[![ACG-Faka](https://img.shields.io/badge/ACG--Faka-3.5.6-green.svg)](https://github.com/lizhipay/acg-faka)

为 [ACG-Faka](https://github.com/lizhipay/acg-faka) 开发的 [HashPay](https://github.com/TGDash/HashPay) 加密货币支付模块，支持 RSA-SHA256 请求签名、RSA-OAEP-256 + AES-256-GCM 加密回调，以及商品订单和余额充值的单一回调自动分发。

- **作者：** ZeroTwoDa
- **当前版本：** 1.0.1
- **开源协议：** GPL-3.0-only
- **适配版本：** ACG-Faka 3.5.6（commit `60b26c56`）

## 功能特性

- `POST /api/merchant/new` 创建 HashPay 订单。
- `GET /api/order/:orderId` 查询 HashPay 订单。
- 使用商户 RSA 私钥生成 `RSASSA-PKCS1-v1_5 SHA-256` 请求签名。
- 自动设置 `X-Merchant-Id`、`X-Timestamp`、`X-Signature`。
- 解密并认证 `RSA-OAEP-256+A256GCM` 回调信封。
- 内置 phpseclib，自动使用可用的高性能大整数引擎，并在无 GMP/BCMath 时回退纯 PHP。
- 单一商户回调自动识别商品订单和余额充值订单。
- 校验商户、算法、外层及内层时间戳、GCM Tag、币种、状态、金额和订单号。
- 复用 ACG-Faka 原有事务、支付渠道归属检查、幂等入账和发货流程。
- 后台私钥保存后不回显，日志不记录私钥、签名或完整密文。
- 不修改 ACG-Faka 核心文件。

## 环境要求

- PHP 8.0 或更高版本
- PHP OpenSSL、JSON、cURL 扩展
- ACG-Faka 与 HashPay API 均使用 HTTPS
- 已在 HashPay 后台创建 REST API 商户，并保存 PKCS#8 PEM 私钥
- 服务器时间已通过 NTP 同步；默认允许的回调时间偏差为 300 秒

> 当前版本按 ACG-Faka 的人民币金额体系固定使用 `CNY`。若店铺经过二次开发使用其他法币，请先调整下单与回调币种校验逻辑，不要直接用于生产环境。

## 项目结构

```text
acg-faka-hashpay/
├── .github/
│   └── ISSUE_TEMPLATE/
│       └── bug_report.md
├── app/
│   ├── Controller/
│   │   └── User/
│   │       └── Api/
│   │           └── HashPayNotification.php
│   └── Pay/
│       └── HashPay/
│           ├── Config/
│           │   ├── Config.example.php
│           │   ├── Info.php
│           │   └── Submit.php
│           ├── Impl/
│           │   ├── HashPayClient.php
│           │   ├── Pay.php
│           │   └── Signature.php
│           ├── Vendor/
│           │   ├── autoload.php
│           │   ├── composer/
│           │   ├── paragonie/
│           │   └── phpseclib/
│           ├── icon.svg
│           └── README.md
├── .gitignore
├── tests/
│   ├── fixtures/
│   │   ├── test_private.pem
│   │   └── test_public.pem
│   ├── stubs/
│   ├── bootstrap.php
│   ├── callback_security.php
│   └── run.php
├── LICENSE
├── README.md
├── RELEASE_NOTES_v1.0.0.md
├── RELEASE_NOTES_v1.0.1.md
└── THIRD_PARTY_NOTICES.md
```

`Vendor/` 是 HashPay 回调解密所需的依赖，部署时必须完整保留；服务器无需强制安装 GMP/BCMath。源码仓库只跟踪空白模板 `Config.example.php`；真实 `Config.php` 和 `runtime.log` 已被 `.gitignore` 排除。

## 安装

### 方式一：使用 Release 安装包

下载 Release 中的 `acg-faka-hashpay-v1.0.1.zip`，解压后将其中的 `app` 目录合并到 ACG-Faka 根目录。Release 安装包已包含空白 `Config.php`，无需手动创建。

### 方式二：从源码安装

```bash
git clone https://github.com/ZeroTwoDa/acg-faka-hashpay.git
cd acg-faka-hashpay
cp app/Pay/HashPay/Config/Config.example.php app/Pay/HashPay/Config/Config.php
cp -R app /path/to/acg-faka/
```

安装后应存在：

```text
app/Pay/HashPay/Config/Config.php
app/Pay/HashPay/Vendor/autoload.php
app/Controller/User/Api/HashPayNotification.php
```

## 文件权限

若 PHP-FPM 使用 `www` 用户，在 ACG-Faka 根目录执行：

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

请勿长期使用 `777`。

## ACG-Faka 后台配置

进入“支付插件 → HashPay 加密货币支付”，填写：

- HashPay 站点地址，例如 `https://pay.example.com`
- 商户 ID
- 完整 PKCS#8 RSA 私钥
- 订单描述前缀
- 回调时间窗口，建议 `300` 秒

然后新增支付接口：

- 插件：`HashPay`
- 支付方式：`HashPay`
- 按需启用商品购买和余额充值

ACG-Faka 使用人民币计价，因此模块固定向 HashPay 提交 `CNY`，并强制校验回调币种为 `CNY`。

## HashPay 回调地址

HashPay 商户后台只需填写一个回调地址：

```text
https://你的商城域名/user/api/hashPayNotification/callback.HashPay
```

统一入口认证并解密 `merchantNo` 后，会自动查询商品订单表和余额充值表，再委派给 ACG-Faka 原有事务服务。如果两个表意外存在相同订单号，模块会拒绝处理，不会猜测订单类型。

## 回调安全模型

- 外层 `X-HashPay-Timestamp` 与加密信封内层 `timestamp` 都必须落在配置窗口内，默认 `300` 秒，可配置范围为 `60–1800` 秒。
- AES-256-GCM Tag 负责密文完整性和真实性；任何密文、IV 或 Tag 篡改都会被拒绝。
- 回调必须属于当前商户，算法必须为 `RSA-OAEP-256+A256GCM`，状态必须为 `paid`，币种必须为 `CNY`。
- 模块验证远端订单号非空，并将 `merchantNo`、金额和状态交给 ACG-Faka 原有支付服务再次校验。
- 重复与乱序通知以本地订单状态为准。合法通知在订单已经支付后直接返回 `success`，不会重复入账或发货，也不会触发 HashPay 无意义重试。
- HashPay 每次重试都会生成新的投递时间戳和加密信封，因此正常延迟重试不会复用已经过期的原始时间戳。
- 时间窗口用于限制截获信封的可接受时间；最终幂等边界由支付渠道归属、本地订单状态和 ACG-Faka 数据库事务共同保证。

## 密钥轮换

1. 在 HashPay 后台轮换商户密钥。
2. 立即将新 PKCS#8 私钥保存到 ACG-Faka HashPay 插件配置。
3. 重启 PHP-FPM 或清除 OPcache，并创建小额测试订单。
4. 使用旧公钥加密的历史通知无法被新私钥解密；如需补单，应在确认新密钥生效后由 HashPay 重新发送通知。
5. 不要同时长期保留新旧私钥，也不要把私钥粘贴到 Issue、日志或截图。

## 测试

仓库提供不连接生产数据库、不会创建真实订单的加密回归测试：

```bash
php tests/run.php
```

覆盖场景：

- 正常 OAEP-SHA256 + AES-256-GCM 回调
- 同一加密信封重复解析
- GCM 密文篡改
- 超出重放窗口
- 非 `paid` 状态
- 错误币种
- 错误私钥/密钥轮换
- 金额保真（最终金额匹配由 ACG-Faka 原服务完成）

测试目录中的 RSA 密钥仅用于公开回归测试，绝不能用于真实 HashPay 商户。

### 沙箱/小额联调清单

1. 使用独立 HashPay 测试商户和测试私钥，回调指向非生产或维护窗口内的站点。
2. 创建最低金额订单，确认 HashPay 与 ACG-Faka 的 `merchantNo`、金额和币种一致。
3. 完成支付，确认订单只发货或入账一次。
4. 在 HashPay 后台重发同一通知，确认返回 2xx/`success` 且不重复发货或入账。
5. 临时将回调窗口设为 `60` 秒，验证过期通知被拒绝，再恢复为 `300` 秒。
6. 在测试环境使用错误私钥，确认回调失败且日志不泄露密钥；恢复正确私钥后重发通知。
7. 对商品订单和余额充值分别执行一遍；检查两个表订单号冲突时会安全拒绝。
8. 查看 `runtime.log`，确认失败路径有原因且不包含私钥、签名或完整密文。

## 更新与缓存

更新前备份已配置的文件：

```bash
cp app/Pay/HashPay/Config/Config.php /tmp/hashpay-config.php
```

覆盖新版本后恢复配置并重新设置权限：

```bash
cp /tmp/hashpay-config.php app/Pay/HashPay/Config/Config.php
chown www:www app/Pay/HashPay/Config/Config.php
chmod 660 app/Pay/HashPay/Config/Config.php
```

随后重启 PHP-FPM 或清除 OPcache。不要用源码仓库中的 `Config.example.php` 覆盖生产环境的 `Config.php`。

## 故障排查

插件日志位于：

```text
app/Pay/HashPay/runtime.log
```

常见问题：

- **没有文件写入权限：** 将插件所有者调整为 PHP-FPM 用户，并确保 `Config.php` 可写。
- **HashPay 已支付但商城未支付：** 检查统一回调 URL、HashPay 重试记录、回调 IP 白名单和插件 `CALLBACK` 日志；修复后可在 HashPay 后台重发通知。
- **HashPay RSA dependency is missing：** `app/Pay/HashPay/Vendor` 未完整上传。
- **回调解密失败：** 确认 ACG-Faka 中配置的私钥属于当前 HashPay 商户；私钥轮换后必须同步更新配置。
- **回调时间戳过期：** 检查两端服务器时间，并启用 NTP 同步。
- **更新后仍运行旧代码：** 重启对应网站实际使用的 PHP-FPM，并清除 OPcache。

## 安全提示

- 不要将真实商户 ID、RSA 私钥、运行日志或订单数据提交到 Git。
- 首次保存后后台不会回显私钥；配置页面中的私钥输入框留空表示保留现有值。
- HashPay 私钥只在商户创建或轮换时显示一次，请离线备份。
- 如果私钥曾出现在截图、日志或公开仓库中，应立即在 HashPay 后台轮换。
- 不要在公开 Issue 中粘贴未经脱敏的 `Config.php`、回调密文或订单信息。
- 生产环境必须使用 HTTPS。

## 第三方组件

本项目随模块分发 phpseclib 及其依赖，相关许可证保留在各自 `Vendor` 目录中。详情见 [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)。

## 开源协议

本项目由 ZeroTwoDa 编写并以 [GNU General Public License v3.0 only](LICENSE) 发布。
