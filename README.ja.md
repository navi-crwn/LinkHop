<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <b>日本語</b> · <a href="README.id.md">Bahasa Indonesia</a></p>

# LinkHop — LinkHop · 短縮 URL、QR コード & クリック解析

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop · HOP 短縮 URL" width="100%"></picture></p>

<p align="center"><b>リンクを短縮し、すべてのクリックを記録。国別・端末別にアクセスを分析し、自動脅威検知で安全を確保します。</b></p>

<p align="center">
  <a href="https://link.hel.ink">link.hel.ink</a> ·
  <a href="#run">セルフホスト</a> ·
  <a href="#features">機能紹介</a> ·
  <a href="#faq">よくある質問</a> ·
  <a href="#family">HOP ファミリー</a>
</p>

---

## HOP 短縮 URL サービス

カスタムスラッグ、QR コード、詳細なアクセス解析、脅威スキャンを備えた高速でプライバシー重視の短縮 URL サービス。HOP ファミリーの一員。

<a id="features"></a>

## 主な機能

- **超高速な短縮リンク**: カスタムの分かりやすいスラッグやランダム ID を hel.ink/l/{slug} で即座にリダイレクト。
- **ダイナミック QR コード**: 印刷物やデジタル共有に適した高解像度 SVG/PNG QR コードを生成。
- **詳細なアクセス解析**: 訪問者の国（GeoIP）、デバイス、ブラウザ、OS、リファラーをリアルタイムで追跡。
- **自動脅威検知**: hop/safety と共有ドメインブラックリストによる能動的スキャンで安全性を維持。
- **きめ細かなリンク制御**: 有効期限、最大クリック数、パスワード保護を設定可能。
- **フォルダとタグ管理**: フォルダ分けとキャンペーンタグで多数のリンクをすっきり整理。
- **HOP ID シングルサインオン**: 中央の HOP ID でログイン。複数アカウントの管理は不要です。
- **開発者向け REST API**: プログラムからのリンク作成や監視を可能にするスコープ付き API トークン。

<a id="run"></a>

## 独自の LinkHop を構築する

LinkHop は標準的なモダン PHP と Laravel 環境で動作します。

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

## よくある質問

**以前のリンクインバイオ機能はどうなりましたか？**
バイオページ機能は独立した専用アプリ BioHop (bio.hel.ink) へ移行しました。従来のリンクも自動転送されます。

**好きな文字列で短縮 URL を作れますか？**
はい、ログインユーザーは空いている任意のカスタムスラッグを指定できます。

**アクセス解析のプライバシーは守られていますか？**
個人を特定する生のログは保持せず集計値のみを記録し、定期的に古いログを安全にパージしています。

**LinkHop はオープンソースですか？**
はい、LinkHop は MIT ライセンスのもとで自由に利用およびセルフホスト可能です。

<a id="family"></a>

## HOP ファミリー

LinkHop はセルフホスト可能なオープンソース Web ツール群 HOP の一部です。

| アプリ | 公開アドレス | ソースコード | 役割 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | アカウントポータル、HOP ID ログイン |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | このリポジトリ | 短縮 URL、QR コード & クリック解析 |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | リンクインバイオとテーマ |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 画像およびメディア配信 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | クライアント暗号化プライベートノート |

<a id="security"></a>

## ヘルプとサポート

バグの報告やサポートが必要ですか？ 次の場所で Issue を開いてください： <a href="https://github.com/navi-crwn/LinkHop/issues">GitHub Issues</a>またはプルリクエストを送信してください。

---

<p align="center"><sub>LinkHop · HOP ファミリーの一員 · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
