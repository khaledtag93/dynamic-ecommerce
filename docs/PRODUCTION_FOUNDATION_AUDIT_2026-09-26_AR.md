# Dynamic — المراجعة الشاملة وخطة الوصول للإنتاج

تاريخ المراجعة: 26 سبتمبر 2026، بتوقيت القاهرة.
نسخة المراجعة الأصلية: `e0420a31986ef11cb23c22b1b75dc56078a2389a` على `v42-clean-baseline`.
تحديث التنفيذ الأمني في 26 سبتمبر 2026: فرع rehearsal `sec03-framework-upgrade` وصل إلى `81ccfdc675a587431547ed0e4dde3a8c49968ac9`، CI أخضر، ونُشر على QAS بنجاح عند `81ccfdc6`.
هذا هو مرجع الأولويات الحالي. المستندات الأقدم أدلة تاريخية؛ لا تُقرأ عباراتها مثل «التالي» أو «لم يُنفذ» بمعزل عن هذا المرجع.

تحديث الخطة في 26 سبتمبر 2026: [خطة الإغلاق والتحقق قبل تسليم المشروع](BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md) تضيف جرد الصفحات والأدوار والرحلات ودليل قبول على SHA محدد وفحص مشتري مستقل. بعض النتائج أدناه بدأت كصورة مصدرية سابقة؛ اقرأ حالة `PROJECT_MASTER_STATUS.md` و`CURRENT_PHASE.md` الأحدث قبل استخدام جدول النتائج كحالة تنفيذ حالية.

## 1. الرأي التنفيذي

Dynamic تجاوز مرحلة متجر تجريبي بسيط من ناحية اتساع الوظائف. توجد خدمات فعلية للمبيعات والمدفوعات والمخزون والمشتريات والكاشير والموظفين والدعم والنمو. لكن لا توجد أدلة كافية لوصف النسخة الحالية بأنها منتج تجاري مكتمل أو جاهز للإنتاج.

المشكلة الأساسية: التنفيذ سبق القبول الفعلي، والتوثيق تراكم حتى أصبح يحمل حالات متعارضة. نجاح الاختبارات يحمي أجزاء مهمة لكنه لا يثبت جمال الواجهة، سهولة العمل، سلامة إعدادات السيرفر، عمل مزود الدفع الحقيقي أو تحمل حجم بيانات تجاري.

قرار العمل: **إغلاق الموجود قبل التوسع، مع تقديم إصلاحات الأمن والبيع والمخزون على التجميل.** الاحترافية تعني رحلة متماسكة وموثوقة، لا كثرة الشاشات أو الكروت أو عدد التعديلات.

لا حاجة لإعادة كتابة المشروع كله، أو تحويله إلى React لمجرد تقليل التحميل. نحتفظ بالخدمات وقواعد الأعمال الجيدة، ونصلح المشاكل المحددة، ونوسع المكونات المشتركة عند وجود احتياج مثبت.

## 2. ماذا تحقق فعلاً وحدود المراجعة

| الدليل | النتيجة |
| --- | --- |
| GitHub branch | آخر نسخة وقت التثبيت `e0420a3`؛ تم جلبها في worktree مستقل دون تعديل الشغل المحلي السابق |
| CI | [Hardening CI 36208521493](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36208521493) نجح على نفس SHA؛ 438 tests / 13,843 assertions |
| نطاق CI | PHP/Bash syntax، migration على MySQL نظيف، boot/routes، Blade/config compilation، PHPUnit، frontend build |
| QAS المسجل | آخر نشر أكده المشغل في السجل يستهدف `5e2a393a`؛ لم يُقرأ HEAD من السيرفر أثناء هذه المراجعة |
| فرق QAS | تعديلات Growth اللاحقة حتى `9526d275` غير مسجلة كنشر QAS؛ الفرق حتى `e0420a3` توثيق فقط |
| Production | لم يتم تغييره؛ آخر checkpoint تاريخي في master هو `95e9f50`، وليس تحققاً جديداً من السيرفر |
| فحص المتصفح | Home عربي، Product عربي/إنجليزي، Category إنجليزي، بحث حي بنتيجة فارغة، Refund Policy، شاشة Login |
| حدود الدخول | `/admin/growth` أعاد إلى Login؛ لم تتوفر جلسة إدارية. مراجعة Admin/POS/Workforce هنا مصدرية وليست قبولاً بصرياً مسجلاً |
| حدود الأجهزة | الفحص البصري الحالي Desktop؛ لم يُنفذ اختبار هاتف/طابعة/ماسح فعلي أو قياس أداء ميداني |
| حدود أدوات التشغيل | PHP/Composer غير متاحين محلياً؛ لم أعد تشغيل PHPUnit أو Composer audit محلياً. نتيجة الاختبارات من GitHub الفعلي |

حجم المصدر المحسوب: 58 Controller، 80 Service، 84 Model، 227 Blade view، 102 Migration، 88 ملف اختبار. هذه أرقام نطاق، وليست نسبة جودة أو تغطية.

هذه مراجعة واسعة مبنية على الجرد وفحص المسارات والخدمات الحرجة وعينات الواجهات؛ ليست اختبار اختراق أو مراجعة سطرية لكل ملف ولا قبولاً لكل شاشة. أي مجال لم يُنفذ عليه اختبار فعلي يبقى واضحاً كـ«غير متحقق».

## 2.1 تحديث hardening بعد المراجعة الأصلية — 26 سبتمبر 2026

- ترقية الإطار نُفذت في branch معزول `sec03-framework-upgrade` بدلاً من المخاطرة بـ`v42-clean-baseline` مباشرة.
- Composer lock تم توليده على platform PHP 8.3؛ الفحص الأمني للـdependencies أخضر.
- QAS ثبت فعلياً Laravel 13.33.0 وLivewire 4.4.6 وSanctum 4.3.3 وCarbon 3.14.0.
- Hardening CI 36214088800 نجح على `81ccfdc6` في كل المراحل، بما فيها PHPUnit وfrontend build.
- QAS deploy الأحدث عند `f1f20297` انتهى HTTP 200، وطبّق migrations الخاصة بالـdatabase queue والـoperations heartbeats.
- Security headers أضيفت وجرى التحقق منها على QAS: HSTS، nosniff، SAMEORIGIN، Referrer-Policy.
- Laravel 13 request-forgery middleware الحديث مستخدم عبر wrapper التطبيق.
- Session serialization أصبح explicit مع default `php` لحماية sessions الحالية أثناء الترقية؛ التحويل إلى JSON مؤجل إلى نافذة re-login مقصودة.
- QAS انتقل عمداً إلى `QUEUE_CONNECTION=database`. الاختبار اليدوي أثبت أن heartbeat job يدخل `jobs` ثم يستهلكه worker فعلياً حتى `Pending=0` و`Failed=0` ويمر `ops:health --strict`.
- تم تفعيل hPanel Cron فعلياً للـLaravel scheduler والـbounded database queue worker كل دقيقة باستخدام wrapper scripts تسجل stdout/stderr. التحقق على عدة دورات أثبت أن Scheduler وQueue worker يظلان `OK` تلقائياً، و`Failed jobs=0`، كما نُفذت أوامر `growth:run` و`notifications:scan-escalations` و`payments:expire-stock-reservations` من الـscheduler بنجاح. قد يظهر heartbeat واحد Pending مؤقتاً بسبب تزامن دورتَي cron في نفس الدقيقة ثم يستهلكه worker في الدورة التالية. بذلك يُعتبر إثبات OPS-03 على QAS مكتملًا؛ Production يحتاج نفس الإعداد والتحقق قبل النشر.
- هذا لا يعني Production-ready: الدمج إلى working line، authenticated QAS acceptance، Paymob E2E، secret rotation evidence، DB restore rehearsal، وإثبات التشغيل التلقائي النهائي للـscheduler/queue ما زالت release gates.

## 3. تقييم النضج بدون نسب مضللة

المقياس: 0 غائب، 1 مبدئي، 2 منفذ جزئياً/بحاجة لإغلاق، 3 مصدر منظم مع اختبارات، 4 مقبول عملياً ومراقب في الإنتاج. هذا تقدير هندسي، وليس قياس أداء أو شهادة أمنية.

| المحور | التقييم الحالي | سبب الحكم |
| --- | --- | --- |
| اتساع الوظائف الأساسية | 3/4 مصدر | توجد وحدات فعلية وخدمات واختبارات، لكن التشغيل الكامل غير مقبول بعد |
| قواعد الأموال والمخزون | 3/4 مصدر | معاملات وأقفال ومنع تكرار وسجلات؛ ينقص إثبات التكامل والتزامن الواقعي |
| UI وتجربة العميل | 2/4 للعينة المرئية | هرم بصري موجود، لكن زخرفة ومساحات وتكرار وصورة منتج مكسورة ونصوص غير مناسبة |
| Consistency وCoherence | 2/4 مصدر/عينة | مكونات مشتركة موجودة؛ المصطلحات والرسائل ومسارات الإجراء تحتاج إغلاقاً |
| البساطة Simplicity | 2/4 | بعض الشاشات قُسمت؛ المتجر ما زال يكرر وصف التجربة بدلاً من تسهيل المهمة |
| عربي/إنجليزي/RTL | 2/4 | دعم واسع، مع تسريبات ترجمة ورسائل صلبة؛ قبول الإدارة والهاتف معلق |
| Live/AJAX | 3/4 للقراءة، 2/4 للأفعال | helper قوي للقوائم؛ POS وبعض الأفعال ما زالت form navigation |
| Security readiness | محجوب | dependency advisory، إطار خارج الدعم، upload/redirect findings، تدوير أسرار غير مثبت |
| الأداء والتوسع | غير مقاس | توجد pagination؛ توجد أيضاً تحميلات غير محدودة ولم تُجرَ قياسات حجم |
| الاختبارات | 3/4 backend، 1/4 browser evidence | CI جيد؛ اختبارات HTML/string لا تعوض اختبار تفاعل المتصفح |
| التشغيل والإطلاق | محجوب | Paymob E2E، استعادة DB، scheduler/queue، قبول QAS لم تُغلق |

**الحكم التجاري: لا نعتمد Production-ready حالياً.** لا نعطي متوسطاً رقمياً يخفي بنداً أمنياً أو مالياً حرجاً.

## 4. ما الموجود وما الناقص حسب الوحدة

«موجود» أدناه يعني موجوداً في المصدر والاختبارات/السجل، ولا يعني كل سيناريو مقبولاً على QAS.

| الوحدة | الموجود | المتبقي للإغلاق أو التوسع |
| --- | --- | --- |
| الحساب والهوية | Password login، profile، password، address book، connected identity foundation | قبول recovery/ownership، labels ورسائل؛ OAuth Google/Apple/Facebook غير منفذ كتسجيل دخول فعلي |
| Catalog | منتجات ومتغيرات وتصنيفات وSKU/barcode وتحرير مخزون مدقق | صور فعلية، حدود الرفع، duplicate/stale edits، جودة بيانات EN/AR، عينة متغيرات كبيرة |
| Storefront | Home/Search/Category/Product، تقييمات مشتريات موثقة ومراجعة | تقليل التكرار، صور موثوقة، محتوى حقيقي، تحسين الهوية والتصفح والهاتف |
| Cart/Checkout | إضافة وكمية وكوبون live، shipping quote، order snapshot | EN/AR failure paths، شبكات بطيئة، أسعار متغيرة، double-submit، مخزون آخر قطعة |
| Payment | HMAC إلزامي، تحقق amount/currency/integration، terminal state وحماية replay | Paymob E2E حقيقي، retry/fail/late callback، حساب المزود وإعداداته، مصالحة مالية |
| Shipping/Delivery | طرق ومناطق ومدن وأسعار وsnapshot وحالات توصيل | قبول rates/thresholds/cancellation؛ drivers/carriers/pick-pack/POD لاحقاً حسب النشاط |
| Returns/Refunds | RMA وخدمات refund/restock وقيود | جزئي/كامل/متكرر/تالف، صلاحيات وسلامة السجلات وربط التنفيذ المالي بالمزود |
| Inventory/Purchases | receiving، count adjustments، audit، barcode labels/scan | إثبات scanner/printer، تزامن، reconciliation، أداء السجلات الكبيرة |
| POS | cart، بحث حي، عميل، خصومات، hold/resume، shifts، receipts | إكمال أفعال in-place، focus للماسح، cash variance، طباعة 58/80mm، عزل الكاشير |
| Workforce | موظفون، attendance/breaks، scheduling، corrections، leave، payroll V1 | قبول الأدوار والحسابات والتوقيت؛ proration/overtime/tax/leave monetization تحتاج سياسة واضحة |
| Helpdesk | cases، replies، SLA/templates، ربط سياق تجاري | قبول العزل والرسائل؛ attachments/omnichannel/business-hours توسع مؤجل |
| Account statement | حركات من السجلات الأصلية، filters/print/CSV، تجميع عملات منفصل | تحميل غير محدود داخل الفترة؛ pagination/streaming حقيقي؛ لا يُسمى رصيداً محاسبياً |
| Growth | Overview/Content/Operations/Insights، pagination، bounded reads، redirect للسياق | آخر slice يحتاج QAS؛ أفعال وحقول ومصطلحات وperformance ببيانات واقعية |
| Analytics | dashboards/KPIs/charts وتقسيمات | مطابقة الأرقام مع المصدر، timezone/range، تكلفة الاستعلامات، سهولة فهم المؤشرات |
| Branding/Settings | presets/core palette/preview وتبويبات | هوية أكثر هدوءاً ومقبولة بصرياً، اتساق storefront/admin، validation للروابط والرفع |
| Roles/Permissions | explicit roles، منع legacy super-admin fallback، permission workspaces | اختبار direct requests وLivewire/actions بكل دور؛ تحقق owner الفعلي على البيئة |
| Imports | شاشة drafts واسم ملف وmapping | لا يوجد تنفيذ CSV فعلي في controller؛ لا نقدمه للمستخدم كاستيراد جاهز |
| Notifications/WhatsApp | قنوات/سجلات/قواعد ومهام مجدولة | retry/delivery evidence، queue، opt-in، خصوصية logs؛ لا إرسال أثناء المراجعة |
| Deploy/Recovery | نسخ DB قبل migration وrollback للكود والملفات | استعادة DB إجراء منفصل يحتاج rehearsal؛ مراقبة وتشغيل وجدول دعم |
| Mobile/SaaS | خدمات قابلة لإعادة الاستخدام، Sanctum dependency | لا يوجد commerce API مكتمل أو tenant isolation مثبت؛ التطبيقات وmulti-tenant مؤجلان |

ملاحظة تصحيحية: تقييمات المنتجات، العناوين، الشحن، RMA والـpayroll V1 ليست أفكاراً لم تبدأ؛ موجودة بالفعل. Social identity foundation لا تعني أن Social Login يعمل. إيصال الطلب موجود؛ الفاتورة الضريبية ليست نفس الشيء.

## 5. سجل النتائج بالأولوية والدليل وشرط الإغلاق

P0: يمنع الإطلاق أو يحتاج أولوية أمنية عاجلة. P1: عطل/فجوة مهمة في رحلة أساسية. P2: جودة وصيانة وتوسع محدود. «مؤكد مصدر» يثبت سلوك الكود، ولا يثبت استغلالاً أو عطلاً في كل إعدادات السيرفر.

| ID | أولوية وحالة | الدليل/المشكلة | المطلوب وشرط الإغلاق |
| --- | --- | --- | --- |
| SEC-01 | مغلق في rehearsal؛ يحتاج merge/قبول نهائي | تمت الترقية إلى Livewire 4.4.6، و`composer audit --locked` أخضر على `81ccfdc6` | الحفاظ على نفس lock أثناء الدمج وتشغيل CI/QAS النهائي؛ لا إعادة فتحه إلا إذا ظهر advisory جديد |
| SEC-02 | منفذ في مصدر rehearsal؛ قبول QAS معلق | النتيجة الأصلية: مسارات الرفع كانت تبني الامتداد من `getClientOriginalExtension`. سجل المشروع الأحدث يثبت تنفيذ content/extension hardening وحظر تنفيذ uploads في المصدر | تحقق upload فعلي ورفض الامتدادات غير المسموحة على QAS دون payload هجومي، مع الحفاظ على regression coverage أثناء الدمج |
| SEC-03 | مغلق مصدر/CI/QAS rehearsal؛ الدمج النهائي معلق | QAS يشغل Laravel 13.33.0 + PHP 8.3.33 + Livewire 4.4.6 + Sanctum 4.3.3 على `81ccfdc6`؛ Hardening CI 36214088800 أخضر | دمج نفس التغييرات إلى `v42-clean-baseline`، إعادة CI، ثم authenticated QAS acceptance قبل Production |
| SEC-04 | منفذ ومغطى؛ قبول الدمج معلق | locale redirect hardening يقصر العودة على مسار محلي آمن مع fallback | الحفاظ على regression coverage أثناء الدمج وإعادة QAS smoke |
| OPS-01 | P0؛ دليل الإغلاق غائب | `KNOWN_ISSUES.md`: أسرار كانت في Git history؛ إزالة .env لا تثبت rotation | يسجل المالك أسماء الخدمات وتواريخ التدوير فقط؛ تحقق إبطال القديم دون كتابة قيم أسرار |
| PAY-01 | P0؛ تكامل غير مقبول | HMAC موجود؛ Paymob E2E بقي مفتوحاً في runbook | evidence لـpaid/failed/duplicate/late/retry ومطابقة order/payment/reservation، دون خصم/استرجاع مرتين |
| OPS-02 | P0؛ استعادة غير مثبتة | deploy يأخذ snapshot؛ rollback يعلن أنه يعيد الكود دون DB | restore rehearsal على DB معزولة، توافق schema/code، وقت الاستعادة وفقد البيانات المقبول وخطة reconciliation |
| OPS-03 | مقبول على QAS؛ Production معلق | الـscheduler والـdatabase queue والـheartbeat والأوامر المجدولة ثبتت تلقائياً على QAS عبر دورات متعددة؛ `Failed jobs=0` في سجل القبول | إعادة إعداد وتشغيل ومراقبة نفس runtime على Production عند الترقية؛ لا تعميم دليل QAS على Production |
| UI-01 | P1؛ مرئي مؤكد QAS | `/products/product-1`: الصورتان complete=true وnaturalWidth=0؛ الشكل ينهار إلى مساحة قصيرة | تشخيص record/file/path ثم fallback يحفظ مساحة المعرض؛ اختبار main/thumb/card. السبب الجذري غير مثبت |
| UX-01 | P1؛ مرئي ومصدر | trust-blocks تعرض شرحاً عن بناء متجر قوي وزيادة الثقة | استبدالها بسياسة/خدمة فعلية للعميل أو إخفاؤها؛ لا وعود توصيل/ضمان غير مؤكدة |
| I18N-01 | P1؛ مرئي/مصدر | Customer promise وproducts في العربي؛ RegisterController success وCheckoutService stock/empty errors صلبة بالإنجليزية | glossary واحد وpluralization ورسائل backend مترجمة؛ تشغيل invalid cases بالعربي والإنجليزي |
| A11Y-01 | P1؛ مصدر وDOM | login email/password labels بلا `for`؛ DOM يعرض textbox دون اسم | ربط labels وإضافة وصف الخطأ؛ keyboard/screen-reader check؛ تسمية زر quick view الأيقوني أيضاً |
| UX-02 | P2؛ عينة QAS | Home يكرر نفس المنتج في Featured/Best/Latest/Sale؛ تصنيف وحيد مع header كبير | تركيب الصفحة حسب حجم/تنوع الكتالوج؛ تقليل repetition والفراغ؛ بيانات اختبار غنية قبل حكم نهائي |
| CONTENT-01 | P1 إطلاق؛ QAS فقط | Refund Policy تعرض unpublished؛ Test categories/Product 1 وlogo غير متجانس | content checklist للمتجر الفعلي وسياسات معتمدة؛ لا نستنتج أن Production له نفس البيانات |
| LIVE-01 | P1 تشغيل POS؛ مصدر | POS autocomplete يجلب النتائج ثم submitChoice ينفذ form.submit | server-confirmed cart fragment، pending/error، إعادة focus للماسح ومنع double-submit؛ لا optimistic money/stock |
| LIVE-02 | P2؛ risk مصدر | POS autocomplete لا يبطل الطلب الجاري فور تقصير النص أقل من حرفين، ولا revision guard | abort فور input، response sequence check، stale result test مع شبكة بطيئة |
| PERF-01 | P1 للتوسع؛ مصدر | CustomerAccountStatementService يجمع 4 collections عبر get ثم sort؛ date range بلا سقف. CSV يبني statement قبل stream | pagination في DB، aggregate totals مستقلة، export chunk/job؛ قياس memory/query/time لعميل كثيف |
| VAL-01 | P1؛ مصدر | ImportController يقبل mapping string وjson_decode دون JSON validation ويقرأ key اختيارياً مباشرة | validation لـJSON/schema/type، معالجة غياب المفتاح؛ لا نجاح وهمي لاستيراد غير منفذ |
| QA-01 | تحسن؛ ما زال مفتوحاً جزئياً | Composer security audit gate أصبح جزءاً من Hardening CI ونجح على Laravel 13؛ ما زالت browser/perf/sanitized-snapshot upgrade evidence غير مكتملة | إضافة browser acceptance للرحلات الحرجة وupgrade rehearsal من snapshot معقمة قبل Production |
| ARCH-01 | P2؛ حجم مصدر | admin layout 3704 سطر وفيه CSS/JS/translation bridge؛ Growth 773 وNotificationCenter 988 سطر | استخراج seams مشتركة أثناء إصلاحات حقيقية، لا refactor شامل غير مرتبط بعيب |
| DOC-01 | P1؛ مؤكد | master/README/CURRENT_PHASE/handoff تحمل قديم QAS وnext وسجل fallback متناقض | مرجع حديث واحد واضح وروابط من الباقي، ونقل القراءة التاريخية تحت عنوان صريح |
| PROD-01 | P1 استراتيجية | Checkout currency=EGP؛ API routes ليست commerce API؛ لا tenant isolation مثبت | إعلان حدود النسخة: merchant/currency policy محددة. SaaS/mobile/multi-currency لا تُباع كميزات مكتملة |

المسؤول التنفيذي افتراضياً: المطور لإصلاح الكود وإثباته؛ مالك المشروع لإعدادات المزود والسياسات والأسرار وقبول المظهر؛ القبول يجري مشتركاً على QAS. لا تُغلق أي نتيجة بمجرد تعديل النص في هذا الجدول.

### مصادر التنبيهات الخارجية

- [Laravel support policy](https://laravel.com/framework/docs/10.x/releases): تاريخ انتهاء الدعم الأمني لـLaravel 10.
- [Livewire official advisory GHSA-f3cx-396f-7jqp](https://github.com/livewire/livewire/security/advisories/GHSA-f3cx-396f-7jqp): النطاق المتأثر والإصلاح وشروط الاستغلال.
- لم يُنفذ exploit أو رفع ملف هجومي. غياب Composer منع audit شامل، لذلك لا ندعي أن القائمة تحصر جميع advisories أو تثبت اختراقاً.

## 6. UI / UX / Consistency / Coherence / Simplicity: الأساس الملزم

- **UI:** خط واضح وأحجام ومسافات منظمة، ألوان قليلة، primary action واحدة، حالات success/warning/error ثابتة. نقلل gradients والظلال والكروت المتداخلة عندما لا تضيف معنى. الهوية التجارية تأتي من محتوى وصور حقيقية وقابلية استخدام، وليس زخرفة كل عنصر.
- **UX:** كل شاشة تجيب: أين أنا؟ ماذا أفعل الآن؟ ماذا حدث؟ ماذا أفعل عند الخطأ؟ نحتفظ بالفلاتر والتمرير والقيم، ونمنع فقد العمل، ونوجه إلى أول خطأ.
- **Consistency:** نفس الجدول والفلاتر والزر والتأكيد والرسالة يعمل بنفس الشكل في Admin/Customer/POS، مع كثافة مناسبة لكل جمهور. نجاح الحفظ لا يُعرض بأربعة أنماط مختلفة.
- **Coherence:** كلمات وحالات الطلب والدفع والتوصيل والاسترجاع مترابطة. Paid لا يعني Delivered؛ attendance shift لا يعني cash shift؛ customer statement لا يعني wallet balance؛ receipt لا يعني tax invoice.
- **Simplicity:** Overview لقرار سريع، التفاصيل عند الطلب. لا تقسيم اعتباطي يخفي السياق، ولا tabs تعيد تحميل كل شيء بلا داع. إظهار الأساس وإخفاء الإعدادات المتقدمة مع حفظ النموذج والأخطاء.
- **المحتوى:** إزالة demo explanations وعبارات «تجربة احترافية تزيد الثقة» من واجهة العميل. عروض ومبيعات وتقييمات مشتقة من بيانات حقيقية فقط. بيانات الاختبار تبقى في QAS.
- **إمكانية الوصول:** labels مرتبطة، focus ظاهر، keyboard navigation، live status قابل للقراءة، icons لها أسماء، لا تعتمد الحالة على اللون وحده؛ اختبار contrast وzoom والـreduced-motion.
- **RTL:** اتجاه النص والحقول والأيقونات والأرقام/العملة والعناوين يفحص منفصلاً؛ البريد والباركود لا يُقلبان لمجرد الصفحة العربية.

قالب كل شاشة: عنوان + غرض قصير عند الحاجة + إجراء رئيسي؛ فلاتر واضحة؛ نتائج/empty state؛ إجراءات صفوف بصلاحيات؛ loading/error/retry؛ مساعدة سياقية لا تحجب العمل.

## 7. سياسة Live actions / AJAX

الهدف إلغاء التحميل غير الضروري، وليس إخفاء حقيقة العملية.

| النوع | السلوك المطلوب |
| --- | --- |
| search/filter/sort/pagination | debounce، إلغاء طلب قديم، حفظ URL وBack/Forward، loading وempty/error واضح |
| cart/coupon/POS quantity | نتيجة معتمدة من السيرفر، pending، totals موحدة، rollback بصري عند الفشل، منع التكرار |
| تحديث بيانات نموذج | field errors 422 وقيم محفوظة؛ dirty state؛ save feedback دون قفز الصفحة |
| money/stock/permissions/delete | authorization/validation/transaction/idempotency في backend؛ confirmation حسب أثر الفعل؛ async مسموح لكنه لا يتجاوز القواعد |
| الانتقال إلى Checkout أو مزود الدفع | navigation طبيعي مقصود، لا نحوله قسراً إلى inline |
| session expired/403/419/network failure | لا نجاح وهمي؛ رسالة واضحة ومسار إعادة المحاولة أو تسجيل الدخول مع حماية البيانات |

الموجود الذي نعيد استخدامه: `public/admin/js/live-list.js` يحتوي debounce 300ms، AbortController، revision check، history وfallback وaria-busy. البحث داخل تصنيف QAS حدث بالفعل إلى 0 نتائج مع رسالة Results updated. هذا لا يثبت كل القوائم أو كل أفعال POS.

## 8. معايير الوظائف والـValidation والأمان

- المتصفح يساعد المستخدم، والسيرفر يقرر. التحقق يشمل النوع والحدود والعلاقات والصلاحيات والحالة الحالية، وليس required فقط.
- Catalog: SKU/barcode متعارضان، variant لا يتبع product، حذف متغير مرتبط بطلب، stale editor، upload آمن.
- Commerce: أسعار وخصومات وشحن ومخزون يعاد التحقق منها داخل المعاملة؛ rounding موحد؛ order snapshot لا يتغير مع تعديل المنتج.
- Payments: provider amount/currency/integration/reference، duplicate/out-of-order callback، terminal states، refund reconciliation، redacted logs.
- Permissions: اختبار endpoint وLivewire method وليس إخفاء الزر فقط؛ ownership للعميل والكاشير والموظف.
- Workforce: overlap، timezone، overnight shifts، breaks، pending corrections، leave conflict؛ أرقام payroll لا تُوصف قانونياً مكتملة دون سياسة معتمدة.
- Privacy: أقل بيانات لازمة في نتائج البحث والسجلات وexports؛ retention/delete/anonymization بحسب السياسة المطلوبة للمنتج.
- Secrets: خارج Git والـHTML/logs، masked settings، تدوير تاريخي موثق. منع تنفيذ ملفات uploads وإعادة فحص إعدادات الـwebserver.
- Abuse controls: مراجعة throttling لتسجيل الحساب/الدخول/البحث/الدعم والـcallbacks حسب الاستخدام؛ عدم افتراض أنها غائبة كلها من فحص route فقط.

## 9. الأداء والتوسع والتشغيل

لا توجد أرقام سرعة موثقة الآن. نستخدم مجموعة QAS اصطناعية بمقاسات معلنة ونقيس قبل التحسين وبعده.

خطة القياس: قوائم تحتوي 100 ثم 10,000 ثم حجم ممثل للنشاط؛ منتجات ومتغيرات وصور، وعميل عالي الحركات، وGrowth logs كثيرة. نسجل p50/p95، عدد queries، memory، حجم response، وقت export، وأثر concurrent checkouts.

أهداف أولية قابلة للتعديل بعد baseline: p95 للقوائم البسيطة أقل من ثانية على البيئة المتفق عليها؛ feedback محلي فور الإجراء؛ لا تحميل غير محدود؛ لا نمو خطي للاستعلامات مع عدد الصفوف المعروضة. الأهداف ليست نتائج تحققت.

النقاط العملية: indexes بعد query plans، eager loading، pagination حقيقية، aggregates منفصلة، queue للأعمال الطويلة، cache مع invalidation معروف، صور responsive/lazy loading، تحميل assets حسب الصفحة. لا نحذف vendor assets لمجرد اسم demo دون تحليل استخدامها.

تشغيل Production يحتاج: health check، scheduler/worker heartbeat، تنبيه exceptions/failed jobs، backup retention ومكان آمن واختبار restore، سجل إصدار ومهاجرات، payment reconciliation، صلاحيات تشغيل وأسرار منفصلة بين QAS وProduction.

المحاسبة متعددة العملات، المخازن المتعددة، tenancy، offline POS وتطبيقات الهاتف ليست نتائج يمكن استنتاجها من أسماء الجداول. كل منها يحتاج عقداً واضحاً وعزلاً واختبارات وسياسة عمل.

## 10. ترتيب التنفيذ من الآن

### الدفعة A — إغلاق المخاطر الأمنية المثبتة

SEC-01/02/04 أولاً، مع dependency inventory وaudit وإضافة gate. توثيق SEC-03 كترقية ضرورية ضمن إطلاق الإنتاج، مع compatibility spike منفصل. لا ننتقل إلى مكتبة أحدث عشوائياً ولا نغير lock يدوياً. جمع أدلة OPS-01 بالتوازي من المالك دون تعطيل إصلاح الكود المتاح.

الخروج: تحديثات سليمة + focused regressions + CI أخضر + مراجعة رفع الصور والروابط والصلاحيات. تعالج الثغرات المكتشفة قبل تحسينات شكلية جديدة.

### الدفعة B — رحلة العميل القائمة

UI-01/I18N-01/A11Y-01/UX-01/CONTENT-01، ثم Home/Product/Category/Cart/Checkout/My Orders/Returns/Support. اختيار direction بصري هادئ وتطبيق المكونات تدريجياً؛ لا إعادة تصميم كل الصفحات في دفعة واحدة.

الخروج: صور سليمة/fallback، نصوص مناسبة، عربي/إنجليزي، desktop/mobile، حالة فارغة/خطأ/نجاح، الرحلة الأساسية مقبولة.

### الدفعة C — الإدارة وPOS وتماسك الأفعال

Growth على النسخة الحديثة، Analytics/Branding/Roles، POS live mutation والفوكس، ثم Inventory/Purchases/Workforce. نصلح findings الفعلية، ولا نعيد broad consistency sweep لمجرد إعادة تسميتها V3.

الخروج: المهام اليومية قابلة للإكمال بأقل خطوات مع المحافظة على money/stock/permissions/audit؛ printers/scanners ذات دليل فعلي.

### الدفعة D — الأداء والتكامل والإطلاق

PERF-01، اختبارات بيانات كبيرة، PAY-01 وOPS-02/03، supported framework migration، upgrade migrations من نسخة DB قديمة، QAS acceptance النهائي على SHA محدد ثم release review.

الخروج: كل P0 مغلق، وكل P1 في المسارات المفعلة مغلق أو مؤجل بقرار واضح لا يعرض المبيعات للخطر؛ backup/restore مثبت؛ موافقة نشر Production على نسخة محددة.

### بعد الإغلاق — التوسع التجاري

CSV import حقيقي (preview/mapping/row errors/chunk/retry/audit)، Social Login، migration/onboarding center، deeper delivery، staff policies، modules enable/disable، SaaS isolation، API/mobile Android/iPhone. تحفظ الأفكار ولا تقاطع الإغلاق إلا dependency مباشرة أو إصلاح أمني.

ترتيب Social Login الافتراضي في آخر سجل: Google ثم Apple استعداداً للهاتف، وFacebook حسب الحاجة. الأساس الحالي يمنع silent merge؛ نبقي هذا القيد.

## 11. طريقة الشغل التي تمنع تضييع الوقت

1. نبدأ كل دفعة من آخر SHA وفرق source/CI/QAS/Production، لا من ذاكرة الشات وحدها.
2. نختار نتيجة واحدة قابلة للمراجعة وIDs من السجل، ونحدد حدودها وشرط نجاحها.
3. نفحص التدفق الحالي ونحافظ على المنطق السليم؛ إصلاح component مشترك أفضل من نسخ عشر إصلاحات إذا كان السلوك واحداً.
4. نكتب اختبارات للسلوك المعرض للخطر: الأموال والعزل والتكرار والتزامن؛ لا نزيد tests شكلية فقط لرفع العدد.
5. تشغيل targeted checks ثم CI على نفس commit؛ لا يُنسب نجاح run سابق لكود لاحق مختلف.
6. نراجع في QAS باللغة/الدور/الجهاز المناسب ونحفظ expected/actual/evidence. لا يشترط وقف التطوير كله انتظاراً لكل الاختبارات اليدوية المؤجلة، لكن الإنتاج يظل محجوباً.
7. نحدث هذا السجل وحالة الدفعة؛ لا نعلن Done للمنتج إذا كان Source-complete فقط.
8. تسليم كل دفعة: ما تغير ولماذا، ما اختُبر، ما لم يُختبر، commit/CI/QAS، والخطوة التالية.

الأفكار الجديدة تسجل: المشكلة التي تحلها، القيمة للمستخدم، التبعية، الكلفة التقريبية بعد الفحص، وسبب الأولوية. لا وقت تقديري جزافي قبل معرفة السلوك والاعتمادات.

## 12. تعريف «مكتمل» وبوابة الإنتاج

لكل ميزة: business rules + authorization/ownership + validation + invalid states + idempotency عند اللزوم + EN/AR/RTL + mobile web + accessible focus/labels + bounded data + regression + documentation + QAS evidence.

بوابة الإنتاج منفصلة:

- [ ] dependency/security findings مغلقة، إصدار framework مدعوم وخطة تحديث مستمرة.
- [ ] secrets rotation موثق دون أسرار في التقارير.
- [ ] CI على exact release SHA، upgrade migration على بيانات ممثلة.
- [ ] QAS authenticated journeys مقبولة للأدوار واللغتين والهاتف.
- [ ] Paymob E2E والمخزون والاسترجاع والمصالحة مثبتة.
- [ ] scheduler/queue/alerts مثبتة والوظائف المفعلة تعمل.
- [ ] سياسات ومحتوى وصور وهوية ووسائل دعم فعلية؛ إزالة بيانات التجربة من بيئة الإطلاق بإجراء مدروس.
- [ ] backup/restore/rollback rehearsal مع RPO/RTO متفق عليهما.
- [ ] لا critical permission/money/stock/UX blocker مفتوح.
- [ ] موافقة نشر منفصلة ثم smoke ومراقبة وسجل deployed SHA.

لا نشر Production، لا تعديل حسابات/أسرار، لا إرسال رسائل، ولا معاملات مالية أثناء هذه المراجعة.

## 13. نموذج تسجيل نتيجة/تسليم

`ID | severity | module/route | role | locale/viewport | source SHA | deployed SHA | steps | expected | actual | evidence | owner | dependency | status | closing test`

الحالات: Open → In progress → Source fixed → CI verified → QAS verified → Production verified. بالإضافة إلى Blocked/Deferred مع سبب. كل حالة لها دليل وتاريخ، ولا نستخدم «اتقفل» وحدها.

أول شغل تنفيذي بعد اعتماد هذا الأساس هو دفعة A، ثم رحلة العميل. هذا المستند لا يدعي إصلاح النتائج؛ هو مرجع التشخيص والتنفيذ والقبول.
