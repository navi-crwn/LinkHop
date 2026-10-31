<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <b>العربية</b> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# LinkHop — LinkHop · تقصير الروابط، رموز QR وإحصاءات النقرات

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/LinkHop/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="LinkHop · مختصر روابط HOP" width="100%"></picture></p>

<p align="center"><b>اختصر الروابط، وتتبع كل نقرة، وحلل بيانات الزوار حسب الدولة والجهاز، واحفظ سلامتهم بفحص أمني متقدم.</b></p>

<p align="center">
  <a href="https://link.hel.ink">link.hel.ink</a> ·
  <a href="#run">تشغيل نسختك</a> ·
  <a href="#features">الميزات</a> ·
  <a href="#faq">الأسئلة الشائعة</a> ·
  <a href="#family">عائلة HOP</a>
</p>

---

## أداة تقصير روابط HOP

أداة سريعة ومراعية للخصوصية لتقصير الروابط مع عناوين مخصصة ورموز QR وتحليلات دقيقة. جزء من عائلة HOP.

<a id="features"></a>

## الميزات الرئيسية

- **تقصير فائق السرعة**: عناوين مخصصة سهلة التذكر أو معرفات عشوائية تُقدّم في hel.ink/l/{slug} بتوجيه سريع.
- **رموز QR ديناميكية**: توليد رموز QR عالية الدقة بصيغتي SVG/PNG للطباعة أو المشاركة الإلكترونية.
- **تحليلات نقرات معمقة**: تتبع الدول (GeoIP)، والأجهزة، والمتصفحات، وأنظمة التشغيل، والمصادر فورياً.
- **أمان وحماية تلقائية**: فحص استباقي عبر hop/safety وقوائم الحظر المشتركة لحماية زوارك من التهديدات.
- **تحكم دقيق في الروابط**: حدد تاريخ انتهاء الصلاحية، والحد الأقصى للنقرات، والحماية بكلمة مرور.
- **المجلدات والوسوم**: نظّم الروابط داخل مجلدات وأضف وسوماً للحملات لإدارة سهلة وتقارير شاملة.
- **دخول موحد بـ HOP ID**: سجّل الدخول بحساب HOP ID المركزي دون الحاجة لحسابات متعددة.
- **واجهة برمجة REST للمطورين**: واجهة متكاملة مع رموز مصادقة ذات صلاحيات محددة لإنشاء الروابط ومتابعتها آلياً.

<a id="run"></a>

## شغّل LinkHop بنفسك

يعمل LinkHop على بنية تحتية حديثة قياسية من PHP وLaravel.

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

## الأسئلة المتكررة

**أين ذهبت صفحات الروابط الشخصية (Bio)؟**
تم فصل صفحات البايو لتصبح تطبيقاً مستقلاً غنياً بالميزات باسم BioHop (bio.hel.ink). الروابط القديمة تحول تلقائياً.

**هل يمكنني استخدام اسم مخصص للرابط؟**
نعم، يمكن للمستخدمين المسجلين اختيار عنوان قصير مخصص لأي رابط إذا كان متاحاً.

**كيف تحمي تحليلات النقرات خصوصية المستخدمين؟**
تُجمع البيانات بشكل إحصائي مجهول دون حفظ معلومات شخصية خام، وتُنظف السجلات بانتظام.

**هل LinkHop مفتوح المصدر؟**
نعم، LinkHop مرخص بموجب رخصة MIT الحرة للجميع.

<a id="family"></a>

## عائلة HOP

LinkHop جزء من عائلة تطبيقات ويب مفتوحة المصدر تحترم الخصوصية.

| التطبيق | العنوان المباشر | الكود | الوظيفة |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | بوابة الحسابات ودخول HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | هذا المستودع | تقصير الروابط ورموز QR والتحليلات |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | صفحات الروابط الشخصية والقوالب |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | استضافة الصور والوسائط |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | ملاحظات مشفرة على جهاز العميل |

<a id="security"></a>

## المساعدة والدعم

هل وجدت خطأ أو تحتاج مساعدة؟ افتح تذكرة عبر <a href="https://github.com/navi-crwn/LinkHop/issues">قضايا GitHub</a>أو أرسل طلب سحب.

---

<p align="center"><sub>LinkHop · جزء من عائلة HOP · <a href="https://link.hel.ink">link.hel.ink</a></sub></p>
