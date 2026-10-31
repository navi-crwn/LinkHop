<p align="center">🌐 <a href="README.md">English</a> · <b>简体中文</b> · <a href="README.es.md">Español</a> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

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

<a id="family"></a>

## HOP 家族

LinkHop 是可自建的开源应用家族 HOP 的一部分。

| 应用 | 线上地址 | 代码 | 功能 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | 账户门户，HOP ID 登录 |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | 本仓库 | 短链接、二维码与点击统计 |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | 个人主页与链接展示 |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 图片与媒体存储托管 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | 客户端加密的私密便签 |

<a id="security"></a>

## 帮助与支持

发现问题或需要协助？请在以下地址提交议题： <a href="https://github.com/navi-crwn/LinkHop/issues">GitHub Issues</a>或提交拉取请求。

---

<p align="center"><sub>LinkHop · HOP 家族的一员 · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
