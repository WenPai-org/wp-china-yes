# TEST ONLY Ed25519 keys

**TEST ONLY — never use in production.**

一对测试密钥，供驻留 ruleset 与（后续 M2-04）apps manifest 合同测试验签。生产私钥不在本仓。

| 文件 | 内容 |
|------|------|
| `wpcy-test-ed25519.key` | Ed25519 私钥（Base64，`sodium_crypto_sign_secretkey`） |
| `wpcy-test-ed25519.pub` | 对应公钥（Base64） |

每个密钥文件首行均为 `# TEST ONLY — never use in production`。

## 生成（仅测试）

```bash
php -r '
$kp = sodium_crypto_sign_keypair();
$sk = sodium_crypto_sign_secretkey($kp);
$pk = sodium_crypto_sign_publickey($kp);
file_put_contents("tests/fixtures/keys/wpcy-test-ed25519.key",
  "# TEST ONLY — never use in production\n" . sodium_bin2base64($sk, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n");
file_put_contents("tests/fixtures/keys/wpcy-test-ed25519.pub",
  "# TEST ONLY — never use in production\n" . sodium_bin2base64($pk, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n");
'
```

重新生成后必须：

1. 把公钥写入 `WenPai\ChinaYes\Privacy\DataResidency\Ruleset::TEST_PUBLIC_KEY`；
2. 用 `php scripts/sign-ruleset.php src/Privacy/rulesets/baseline.json tests/fixtures/keys/wpcy-test-ed25519.key` 重签基线。

## 生产密钥

**禁止用于生产。禁止把生产私钥写入本仓。**

生产流程见 linuxjoy 定稿 §7.5b-3：一套密钥两个 `kid`（`wpcy-apps-2026`、`wpcy-ruleset-2026`），由 devops 在 feicode-prod 生成，作为 license-server 的签名服务保管，不落人手。公钥随插件发布。仓内只有 TEST ONLY 密钥。
