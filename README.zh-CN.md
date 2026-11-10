<p align="center">🌐 <a href="README.md">English</a> · <b>简体中文</b> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

# LinkHop — LinkHop · 短链接、二维码与点击统计

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop · HOP 短链接" width="100%"></picture></p>

<p align="center"><b>缩短链接，统计每一次点击，按国家和设备分析访客，并通过自动威胁检测保护受众。</b></p>

<p align="center">
  <a href="https://link.hel.ink">link.hel.ink</a> ·
  <a href="#run">自建部署</a> ·
  <a href="#features">功能特性</a> ·
  <a href="#faq">常见问题</a> ·
  <a href="#family">HOP 家族</a>
</p>

---

一条命令安装（它会先检查你的电脑，只问几个问题，还能为你装好 HOP ID 中心）：

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

已克隆仓库时：`./install.sh` · 更新：`./update.sh` · 卸载：`./uninstall.sh`。加上 `--yes` 即接受所有默认值；`--help` 列出全部选项。

## HOP 短链接工具

快速且保护隐私的短链接工具，支持自定义别名、二维码、深度点击分析和威胁扫描。属于 HOP 家族。

<a id="features"></a>

## 功能特性

- **极速缩短链接**: 自定义易记别名或随机标识，通过 hel.ink/l/{slug} 提供低延迟即时跳转。
- **动态二维码**: 生成高质量 SVG/PNG 二维码，适用于印刷、包装与线上分享。
- **深度点击分析**: 实时追踪访客国家（GeoIP）、访问设备、浏览器、操作系统及来源网站。
- **自动威胁防护**: 通过 hop/safety 与共享黑名单进行主动扫描，守护访客安全。
- **细粒度链接控制**: 为敏感链接设置有效期、最大点击次数以及访问密码保护。
- **文件夹与标签整理**: 将链接归类到文件夹并设置营销标签，实现团队高效管理与汇总报表。
- **HOP ID 单点登录**: 使用统一的 HOP ID 登录，无需重复注册或维护多套会话。
- **开发者 REST API**: 提供带有权限范围的完整 API，便于自动化创建与监控链接。

<a id="run"></a>

## 自建您的 LinkHop

LinkHop 运行在标准现代 PHP 与 Laravel 环境上。

```bash
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

<a id="faq"></a>

## 常见问题

**Link-in-bio 页面去哪儿了？**
个人主页功能已独立升级为 BioHop (bio.hel.ink)。旧版 bio 链接仍会自动跳转。

**我可以使用自定义短网址吗？**
可以，登录用户可以为可用范围内的任意链接指定自定义后缀。

**点击统计如何保护用户隐私？**
系统只对统计数据进行匿名聚合，绝不记录原始敏感数据或出售访客画像，日志也会定期清理。

**LinkHop 是开源的吗？**
是的，LinkHop 遵循宽松的 MIT 许可证，任何人均可自由使用和自建托管。

**可以缩短加密 NoteHop 笔记的链接吗？**
不可以。加密笔记的密钥就是链接中 `#` 之后的部分，短链接会把它存到服务器上，所以 LinkHop 会拒绝这类链接：请直接分享 NoteHop 链接（它的分享按钮已经提供短地址）。

<a id="family"></a>

## 自行托管

<!-- self-hosting:start -->
LinkHop 所需的一切都在公开仓库中：不需要私有仓库、令牌或 hel.ink 账户。详细说明见家族指南：[docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md)（英文）。

| 组件 | 对 LinkHop 而言 | 作用 |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (中心，HOP ID) | **必需**：账户保存在那里，使用同一个数据库 | 所有应用的账户与登录 |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | 由 `composer install` 从公开的 GitHub 安装 | 共享 PHP 代码：登录、界面、链接安全 |
| [Imperium](https://github.com/navi-crwn/Imperium) | 可选，但 LinkHop 的管理页面在那里 | 整个家族的管理面板；需要中心 |
| 家族字体与浮动图块 | 无需安装：从 `hel.ink/hop-assets` 加载 | 无法访问 hel.ink 时：使用系统字体，没有浮动图块 |

- **安装顺序**：先装[中心](https://github.com/navi-crwn/HEL.ink)，再装 LinkHop。`./install.sh --hub new` 会同时安装两者。
- **最少设置**：`APP_URL`, `PUBLIC_URL`, `HOP_APP=linkhop`, `HOP_HUB_URL`, `HOP_SSO_SECRET` (与中心相同), `DB_*` (中心的数据库), `HOP_TRUSTED_HOSTS` (你自己的主机名); 使用 Imperium 时: `HOP_CONTROL_URL`。
- **单独运行或加入家族**：LinkHop 不能脱离中心运行。在家族中，它与中心共用数据库和 `HOP_SSO_SECRET`。
- **没有 Imperium 时**：`/admin` 只显示一条说明。账户可在中心自己的管理页面中管理，另可使用 签名的 `/api/hop/*` API 和 artisan 命令。
<!-- self-hosting:end -->

## HOP 家族

LinkHop 是可自建的开源应用家族 HOP 的一部分。

| 应用 | 线上地址 | 代码 | 功能 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | 账户门户，HOP ID 登录 |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | 本仓库 | 短链接、二维码与点击统计 |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | 个人主页与链接展示 |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 图片与媒体存储托管 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | 客户端加密的私密便签 |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | 在浏览器中转换文件，不上传任何内容 |
| **PayHop** | `pay.hel.ink` | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | 生成收款二维码并分摊账单，数据不会离开浏览器 |

<a id="security"></a>

## 帮助与支持

发现问题或需要协助？请在以下地址提交议题： <a href="https://github.com/navi-crwn/LinkHop/issues">GitHub Issues</a>或提交拉取请求。

---

<p align="center"><sub>LinkHop · HOP 家族的一员 · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
