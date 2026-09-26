# Dynamic — خطة الإغلاق والتحقق قبل تسليم المشروع

تاريخ التثبيت: 26 سبتمبر 2026. المرجع التنفيذي لهذه المرحلة مع [نظام إغلاق الصفحات](PAGE_CLOSURE_SYSTEM_2026-09-26.md) و[تدقيق الأساس](GLOBAL_FOUNDATION_AUDIT_2026-09-26.md).

## 1. الهدف وحدود الدليل الحالي

الهدف منتج تجاري عالمي المستوى: سهل وواضح ومتماسك، آمن في البيانات والأموال والمخزون، سريع على أحجام واقعية، عربي/إنجليزي وRTL/LTR، صالح للفحص المستقل من مشتريه ومطوريه وأدوات التحليل. لا توجد أداة واحدة، أو عدد اختبارات، أو درجة شكلية يثبت غياب كل العيوب. ما نستطيع تقديمه هو دليل قابل لإعادة التنفيذ، وسجل صريح لما تحقق وما بقي.

| الطبقة | الحالة المثبتة عند بدء هذه الخطة |
| --- | --- |
| مصدر العمل | `sec03-framework-upgrade` عند `3ba6d5ab0a6196d32805ce9e7f64585c2d6682a3`، Laravel 13 / Livewire 4. هذا هو HEAD الذي روجعت عليه الخطة؛ حالة CI لهذا الـHEAD لم تثبت في هذا التحديث. |
| آخر تطبيق مثبت على QAS في السجل | `f1f20297a27e2236603788ac7cc343dbbf86d0b6`؛ اختبارات CI والتشغيل الآلي للـscheduler والـqueue موثقة له. تعديلات Page Closure والـsidebar الأحدث لم يثبت قبولها بصرياً على QAS. |
| Production | بلا تغيير في هذه المرحلة؛ لا استنتاج لحالته الحالية من نجاح QAS. |
| نطاق هذه المراجعة | قرأنا ملفات القواعد والحالة والخطة والتدقيق وقائمة QAS، وجردنا بنية المسارات والواجهات والاختبارات وCI. هذه مراجعة تنفيذية للخطة والمصدر، وليست قبولاً متصفحياً لكل صفحة أو تدقيقاً أمنياً سطرياً. |

التقييم الأساسي المفصل، بما فيه النتائج SEC/OPS/PAY/UI/UX، موجود في [تدقيق الإنتاج](PRODUCTION_FOUNDATION_AUDIT_2026-09-26_AR.md). لا ننقل البنود التاريخية منه كأنها ما زالت بنفس الحالة؛ أعلى `PROJECT_MASTER_STATUS.md` و`CURRENT_PHASE.md` هما سجل الحالة الأحدث.

## 2. الفجوات التي أضافتها هذه المراجعة للخطة

1. **تغطية قابلة للعد:** تعريف لكل صفحة مرئية، variant، modal، حالة خطأ، ومسار وظيفي من `route:list` والقوائم الفعلية. وجود 227 Blade view لا يعني 227 صفحة مستقلة؛ جردنا سيكون حسب تجربة المستخدم والدور، ويربطها بالمسار والقالب والمكون والاختبار.

بدأنا [جرد مصدر مبدئياً](PAGE_INVENTORY_2026-09-26.md) مع [CSV لمسارات GET](page_closure/route_seed_2026-09-26.csv)؛ يحتاج المصالحة مع `route:list` والمسارات المتولدة والـLivewire والواجهات الشرطية قبل وصفه بالشامل.
2. **دليل الإغلاق:** لكل صفحة صف واحد في سجل القبول يذكر SHA المصدر، SHA QAS، الأدوار واللغات والأحجام والسيناريوهات والنتيجة وروابط الاختبارات/الأدلة والعيوب المتبقية. لا ننقل `OPEN` إلى `CLOSED` بناءً على تعديل المصدر وحده.
3. **رحلات عابرة للصفحات:** نجاح كل صفحة منفردة لا يثبت صحة Checkout→Paymob→Stock→Order→Refund أو POS→Shift→Receipt. نختبر تلك الرحلات end-to-end مع إعادة الطلب، التأخير، الفشل والتزامن.
4. **استلام مستقل:** تشغيل نسخة نظيفة بإعدادات مثال بدون أسرار، migration على قاعدة جديدة وأخرى مرقاة من نسخة معقمة، اختبارات صلاحيات مباشرة، قياس أداء، browser evidence، runbooks وبيان صريح بحدود المنتج.
5. **حدود الادعاءات:** OAuth الحقيقي، تنفيذ CSV import، عزل tenants، commerce API وتطبيقات Android/iPhone، تعدد العملات والفاتورة الضريبية ليست ميزات مكتملة لمجرد وجود أساس أو واجهة. نعلن `implemented / source verified / QAS accepted / Production verified / planned` منفصلة.
6. **توافق المستندات:** بعض الأقسام القديمة تسجل `OPS-03` كغير مثبت أو تقدم Laravel 10 كالحالة الحالية. حالة QAS الأحدث تثبت scheduler/queue على rehearsal فقط؛ حالة Production وبوابات OPS-01 وPAY-01 وOPS-02 تظل مفتوحة. نصلح العناوين التنفيذية أولاً ونحتفظ بالتاريخ كأدلة مؤرخة.

## 3. ترتيب التنفيذ

| مرحلة | العمل والشرط للانتقال |
| --- | --- |
| 0 — ثبّت الحقيقة والجرد | راجع HEAD وفرق source/CI/QAS/Production؛ استخرج `route:list` على بيئة PHP سليمة، واربط صفحات Admin/Customer/POS/Workforce والأدوار والرحلات الأساسية بسجل OPEN. صنّف P0/P1/الاعتماديات، وحدد بيانات اختبار معقمة. لا تفترض قبول صفحة من وثيقة قديمة. |
| 1 — Global Foundation | أصلح Admin shell ثم Storefront shell، tokens/components، action hierarchy، forms/validation/feedback، i18n/RTL، mobile/accessibility، Livewire/state، motion، ومواقع استخراج CSS/JS التي تمنع التكرار. كل عقد مشترك يُطبق ويُقبل على عينات حقيقية من Admin وCustomer؛ لا ننتظر refactor شاملاً كي نبدأ الصفحات. GF-01 sidebar ينتظر CI وقبول QAS. |
| 2 — صفحات كاملة بترتيب الاعتماد | Customer: Home/search/category/product → cart/checkout/payment status → account/auth/orders/returns/support. Admin: navigation → catalog/settings/branding → orders/payment/shipping/inventory → POS → workforce → Growth/Analytics → support/permissions/notifications وسائر الصفحات المكتشفة بالجرد. نعدل الترتيب عند وجود خلل P0 أو اعتماد مباشر، ونراجع كل صفحة بجميع أبعاد [Definition of Done](PAGE_CLOSURE_SYSTEM_2026-09-26.md). |
| 3 — رحلات وسلوك على بيانات واقعية | اختبر البيع والدفع والمخزون والاسترجاع؛ صلاحيات العملاء والموظفين والكاشير؛ التزامن والتكرار؛ الإشعارات والـqueue والمهام المجدولة؛ السجلات الكبيرة وعمليات التصدير. أعد فحص الصفحات التي تأثرت بتغيير shared layer، من غير إعادة اللفة كاملة دون سبب. |
| 4 — مراجعة مشتري مستقلة | شغّل CI وتحليل dependencies/security، تدقيق صلاحيات/أسرار/رفع ملفات، browser checks باللغتين وعلى هاتف وdesktop، keyboard/zoom/reduced motion، قياسات أداء معلنة، clean install/upgrade/restore، وفحص وثائق التشغيل والترخيص وحدود الميزات. سجّل كل finding برقم وشدة ودليل وإصلاح/قرار. |
| 5 — بوابة إصدار | لا Production قبل إغلاق P0: OPS-01 تدوير الأسرار التاريخية، PAY-01 Paymob E2E، OPS-02 restore rehearsal؛ ونقل OPS-03 وضبطه والتحقق منه على Production عند الترقية. ادمج فرع Laravel 13 إلى working line، شغّل CI على SHA الدمج، انشر نفس SHA إلى QAS، وأعد قبول الرحلات المتأثرة. النشر قرار منفصل على SHA معلوم وخطة rollback للملفات والبيانات. |

بوابات P0 تسير بالتوازي مع العمل على الواجهات عندما لا تعتمد عليه، لكنها تمنع وصف المنتج بأنه جاهز للتسليم الإنتاجي. لا يتحول عمل مستقبلي كبير إلى شرط مبهم لإغلاق صفحة: نحدد في الجرد إن كان نقصه يمنع وظيفة الصفحة الحالية أو يخص منتجاً مستقبلياً، ونصدق على حدود النسخة المباعة.

## 4. مصفوفة الفحص التي يتوقعها مطور أو أداة AI

| المجال | أدلة قابلة لإعادة الفحص |
| --- | --- |
| المنطق والبيانات | business invariants للأموال والمخزون والحالات، معاملات وقفل/idempotency عند الحاجة، snapshots، اختبارات edge/duplicate/concurrent؛ لا تعارض بين UI وقاعدة البيانات. |
| الأمن والخصوصية | route/action/Livewire authorization + ownership، CSRF/XSS/validation/upload/redirect، كشف dependencies، عدم وجود secrets، masking للسجلات والـexports، صلاحيات أدوار ممثلة؛ أي finding مصنف ومغلق بدليل. |
| UX/visual/i18n | screenshots/تسجيلات قصيرة من QAS EN/AR وRTL/LTR على هاتف/تابلت/desktop، حالات loading/empty/error/success، اختيار actions والتبويبات، scroll/focus/back، محتوى حقيقي بلا نصوص demo. |
| Accessibility | keyboard-only، focus/labels/aria، contrast وzoom، قارئ شاشة لعينة تفاعلية، reduced motion؛ التحقق على المكونات المشتركة ثم الصفحات. |
| الأداء | dataset وحجم وبيئة محددة، p50/p95، query count، memory، حجم response/export، صور وأصول؛ سجل baseline ثم التحسن. لا نختار أرقاماً من غير قياس. |
| CI وإعادة الإنتاج | Composer validation/audit، MySQL migration وupgrade rehearsal، PHPUnit، build، browser tests للرحلات الأعلى خطراً، إعدادات مثال وتعليمات تثبيت وتشغيل دقيقة. قارن أدلة CI/QAS بنفس SHA. |
| التشغيل والتسليم | restore، scheduler/queue، health/alerting، أخطاء الدفع والتسوية، سجل إصدار وخطة rollback، تراخيص الحزم/الأصول والمحتوى، بيان حدود النطاق وتبعيات المزود والسياسات. |

نسمح للمراجع المستقل بإعادة تشغيل الفحوص، ولا نستخدم تقرير AI كبديل عن اختبار متصفح أو دليل تشغيل فعلي. لا ندّعي «صفر عيوب»؛ نغلق كل عيب معروف مؤثر على النطاق ونوثق المخاطر والحدود المتبقية بوضوح.

## 5. بطاقة إغلاق صفحة أو رحلة

تُسجل بطاقة واحدة لكل عنصر في الجرد:

```text
ID / name / route(s) / roles / linked journey / owner
Status: OPEN | IN REVIEW | CLOSED
Source SHA / CI run + conclusion / deployed QAS SHA
EN + AR / RTL + LTR / phone + tablet + desktop / keyboard + zoom
Happy path / invalid input / empty / loading / error / retry / expired session
Direct authorization + ownership / state + scroll + focus + back-forward
Money/stock/concurrency or N/A with reason / performance dataset + result
Help/content/product completeness / screenshot or reproducible QAS evidence
Known findings: IDs + severity + resolution; none open that block the active scope
Reviewer + date / affected shared components / impacted regression checks
```

إذا كان المصدر جاهزاً والقبول العملي لم يحدث، تبقى الحالة `IN REVIEW` ويظهر `source verified, QAS pending` كحقل دليل مستقل. عند إصلاح مشترك بعد إغلاق صفحة نعيد اختبار سطح التأثير المحدد؛ أي regression يفتحها من جديد. نراجع التغييرات التجارية في المنتج بعين المستخدم، لا بمجرد matching strings في الاختبارات.

## 6. طريقة العمل بسرعة وبلا تهنيج

- دفعة متماسكة بحد واضح: مشكلة مشتركة أو مساحة صفحات مرتبطة، تشمل الفحص والتنفيذ والاختبار والتوثيق. نتجنب تغييرات مفردة تافهة وعمليات ضخمة تفتح المشروع كله دفعة واحدة.
- قبل كل دفعة: تحقق HEAD ونظافة worktree وفرق QAS، واكتب معيار قبول قصيراً. أثناءها: اقرأ الملفات ذات الصلة ببحث محدود ومخرجات مختصرة، قسم التشغيل الطويل إلى مراحل، واستمر في عمل مستقل بينما CI يعمل.
- شغّل focused checks بعد تعديل سلوك خطر، وCI الكامل على آخر HEAD للدفعة. لا تكرر الاختبارات بلا خطر جديد، ولا تسمّ نجاح اختبار PHP قبولاً بصرياً. توقف لإصلاح فشل حقيقي قبل بناء مزيد من العمل عليه.
- سجّل عند كل checkpoint مفيد: ما اتغير، SHA، CI/QAS/Production كل على حدة، ما هو OPEN، وأول إجراء تالي في `CURRENT_PHASE.md` وروابط التفاصيل. ادفع النوتس مع الكود بدل الاعتماد على طول الشات.
- أثناء عمل طويل أرسل تحديثاً موجزاً عن النتيجة والعائق والخطوة التالية؛ تجنب لصق logs طويلة. عند اقتراب الشات من التعليق، أنهِ العملية الجارية بأمان، احفظ checkpoint، وقدم رسالة جاهزة للشات الجديد تشير إلى [handoff](NEW_CHAT_HANDOFF_2026-09-26.md) وSHA الحالي. استمرار العمل بين الشاتات يحتاج الرجوع إلى repo، وليس وعداً بذاكرة ذاتية مستقلة.
- عند غياب بيئة محلية أو تعذر قبول QAS، سجّل القيد والعمل المستقل المنجز. لا تنقل الحالة إلى CLOSED أو Production ready تخميناً.

## 7. أول دفعة بعد تثبيت الخطة

1. تحقق من CI الخاص بـ`3ba6d5a` ثم اقبل GF-01 على QAS عند نشر نفس كود التطبيق؛ لا نغلقه قبل ذلك.
2. أنشئ جرد الصفحات/الأدوار/الرحلات من المسارات والقوائم وقارن بالـQAS checklist، ثم طبق عقد Admin shell على عينات فعلية قبل الانتقال إلى Storefront shell.
3. تابع OPS-01 وPAY-01 وOPS-02 كمسارات إطلاق منفصلة بالتوازي، من دون إدخال أسرار في الشات أو Git أو تعطيل تحسينات المصدر المستقلة.

هذه نقطة بدء تنفيذية، لا إعلان بأن المراجعة التفصيلية لكل ملفات المشروع قد اكتملت.
