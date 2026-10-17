# Flowra — الجرد التفصيلي وخطة إغلاق Phase 1

تاريخ: 2026-10-17 | baseline: `8b62fe81916c50a762df92b2c405a5320ead082d` | CI #38984262494 **completed/success** تم التحقق مباشرة.

**الهدف إغلاق Phase 1.** هذه الدفعة أغلقت جرد المصدر الصريح وربط الأدلة؛ لم تغلق القبول التشغيلي. لا تعديل كود أو QAS/Production، ولا تشغيل رحلات أو CI جديد محلياً.

## الحصيلة القابلة للمراجعة

- 317 تعريف مسار صريح في routes/web.php وroutes/api.php: 142 GET، 83 POST، 25 DELETE، 40 PATCH، 23 PUT، match GET/POST واحد، 3 Livewire. ليست runtime routes ولا عدد صفحات. Auth::routes/framework/package routes لا تدخل هذا الرقم.
- 229 Blade templates فُحصت لاستخراج الروابط والـincludes والـLivewire bindings؛ ليست 229 صفحة مستقلة.
- 116 public Livewire methods مرشحة تشمل lifecycle/computed/helpers؛ لا يعني أن كلها أزرار مستخدم أو reachable.
- 6 default roles و34 permission definitions من AuthorizationService؛ assignments الفعلية وcustom roles على QAS غير مفحوصة.
- 148 test files و981 test methods مفهرسة؛ 212 route declarations لها literal route-name references في tests. هذا ربط أدلة بحث، وليس قياس coverage أو نجاح كل سيناريو.
- لا توجد literal Blade route names غير محسومة بين التعريفات الصريحة وقائمة auth-generated/conditional المرشحة. يلزم runtime manifest لإثبات conditional Auth routes. Dynamic names/JS-generated actions خارج ضمان هذا الفحص.

## السجلات التفصيلية

| الملف | استخدامه |
|---|---|
| [Route/action inventory](PHASE1_ROUTE_ACTION_INVENTORY_2026-10-17.csv) | صف لكل تعريف، route/method/controller/view/permission/default-role candidates/source test references/QAS evidence/gap/journey |
| [Livewire inventory](PHASE1_LIVEWIRE_ACTION_INVENTORY_2026-10-17.csv) | methods وcomponent embeds وmatching binding candidates؛ لا تعتبر helpers إجراءات تجارية |
| [View surface inventory](PHASE1_VIEW_SURFACE_INVENTORY_2026-10-17.csv) | template/includes/tabs/modals/wire bindings؛ التفاعلات الشرطية تحتاج contextual acceptance |
| [View-route reconciliation](PHASE1_VIEW_ROUTE_LINKS_2026-10-17.csv) | كل literal route link في القوالب وحالة resolution |
| [Default roles](PHASE1_DEFAULT_ROLE_MATRIX_2026-10-17.csv) | exact default permission mapping؛ لا يعادل effective authorization |

## نتائج مؤثرة على الإغلاق

1. **F01 — Import capability:** confirmed source limitation، وليس ادعاء failure عام: `/admin/imports` ينشئ draft فقط؛ الاسم والوصف في permissions قد يوحيان بقدرة أوسع. اقتراح Phase2 لتنفيذ CSV؛ قرار المنتج وإثبات حدود العرض/access قبل إطلاق الوظيفة. لا تنفيذ الآن.
2. **F02 — GET ليس read-only تلقائياً:** PosController.index → PosService.cartFor → firstOrCreate يمكنه إنشاء PosCart. Paymob redirect يبدأ claimOnlineCheckoutInitiation ثم مسار الدفع. Locale يكتب session. لا نزور هذه الروابط ضمن read-only crawl. هذه حدود اختبار مؤكدة، وليست وحدها حكم security bug.
3. **F03 — صلاحية القائمة لا تكفي:** customers.manage يسمح بدخول سطح العملاء لأدوار Ops/Support، لكن updateRole يحميه controller بشرط Super Admin. لا نسجل false positive privilege bug من route middleware وحده. CSV يذكر candidates والـhandler guards منفصلة.
4. **F04 — حدود الدليل:** الأجزاء source-tested لا تتحول إلى release-closed تلقائياً. Slice04/05 وحدهما يمنحان PASS للـguest cases المحددة، وليس لكل سجل J01 أو Cart.
5. **F05 — التشغيل:** source API executor يتحقق من DeployRemoteRequest.isValid داخل controller؛ غياب permission middleware على API ليس دليلاً على endpoint مفتوح. validity semantics وإعداد التشغيل لم تُقبل في هذه الدفعة.

## نطاق Phase 1

الأصل إبقاء الموجود ضمن Phase1 حتى اعتماد استثناء واضح. لا نعيد فتح حدود source المالية المغلقة بلا finding. الجديد هنا acceptance وربط الحالة بأدلة. Workforce V1 يبقى مطلوب القبول ما دام مفعلاً؛ لا نقفز إلى enterprise payroll أو documents أو biometrics. كل اقتراح Phase2 أدناه لا يساوي موافقة على تعطيل أو حذف.

## رحلات الإغلاق — صفوف الجرد تشير إلى IDs التالية

## J00

**Catalog Admin**

- الصفحات والأفعال: Categories CRUD؛ Products create/edit؛ Brands؛ Attributes/Values؛ Reviews moderation.
- personas/gates: catalog.manage / reviews.manage.
- أدلة source المرشحة: AdminCatalogLivewireRenderTest; CatalogPriceMoneyIntegrityTest; CatalogStockAuditTest; VerifiedProductReviewsTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: منتج simple ومتغيرات بأسعار/مخزون مختلف؛ حفظ وتعديل/حذف ورفع صور وتوليد variants وbulk/inline/export؛ ارفض duplicate/stale/invalid؛ افحص read-only وmanage denial.
- نطاق المرحلة: P1؛ لا تؤجل صحة السعر/المخزون أو الأفعال الموجودة.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J01

**Guest discovery/content/reviews**

- الصفحات والأفعال: Home/search/category/product؛ contact/privacy/terms/refund/shipping؛ customer review actions.
- personas/gates: Guest؛ auth للـreview.
- أدلة source المرشحة: StorefrontExperienceTest; StorefrontLiveCatalogTest; StorefrontNavigationTest; VerifiedProductReviewsTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: احتفظ بـSlice04/05 PASS المحدود؛ أكمل أسعار مختلفة/variants وstock semantics والمحتوى وسياسات المتجر وkeyboard؛ review يتطلب مشترياً مؤهلاً ومنع عميل آخر.
- نطاق المرحلة: P1؛ إغلاق المحتوى الحالي، لا تصميم جديد بلا finding.
- الحالة: جزء Guest GET PASS فقط؛ بقية النطاق OPEN.

## J02

**Customer account**

- الصفحات والأفعال: account/profile/password؛ addresses list/create/edit/delete.
- personas/gates: auth + customer ownership.
- أدلة source المرشحة: CustomerAccountTest; CustomerWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: حسابان منفصلان؛ بيانات صحيحة/خاطئة وتغيير كلمة المرور وعنوان افتراضي؛ منع قراءة/تعديل عنوان غير مملوك؛ EN/AR/mobile.
- نطاق المرحلة: P1؛ Social Login فقط P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J03

**Cart**

- الصفحات والأفعال: empty/populated cart؛ coupon apply/remove؛ add/bundle/update/remove.
- personas/gates: Guest أو customer cart ownership داخل handler.
- أدلة source المرشحة: CartCheckoutClosureTest; StorefrontAddToCartLiveTest; StorefrontCartLiveUpdateTest; StorefrontCartCouponLiveTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: empty render PASS فقط؛ اختبر variants/bundle وكميات وحدود وكوبون وإزالته؛ totals من السيرفر؛ duplicate submit/session isolation.
- نطاق المرحلة: P1؛ لا تأجيل acceptance للسلة.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J04

**Checkout**

- الصفحات والأفعال: checkout؛ shipping quote؛ submit.
- personas/gates: auth + ownership.
- أدلة source المرشحة: CheckoutIdempotencyTest; ShippingEngineTest; StorefrontCheckoutBilingualFeedbackTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: customer A يشتري منتجاً بسيطاً ومتغيراً مع shipping/coupon؛ تحقق totals وأثر آخر قطعة وإعادة الإرسال ورفض العميل B؛ COD ثم online.
- نطاق المرحلة: P1.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J05

**Orders/receipts**

- الصفحات والأفعال: customer list/detail/success/receipt/cancel؛ admin list/detail/status/quick-status/refund/delete.
- personas/gates: auth owner؛ orders.view/manage.
- أدلة source المرشحة: OrderWorkspaceClosureTest; OnlineOrderReceiptTest; FinancialHardeningTest; RefundComponentAllocationIntegrityTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: تتبع نفس الطلب من checkout إلى admin/receipt؛ reconcile المبلغ والعملة والخصم؛ cancellation/refund guards وduplicate/status boundaries.
- نطاق المرحلة: P1؛ receipt لا يعني tax invoice.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J06

**Payments/Paymob**

- الصفحات والأفعال: redirect/result/callback؛ admin records/status.
- personas/gates: owner؛ payments.view/manage؛ provider verification.
- أدلة source المرشحة: PaymobUnifiedCheckoutTest; OrderPaymentRetryEligibilityTest; PaymentWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: PAY-01: paid/failed/late/repeated callback/retry ومطابقة provider/order/payment/stock؛ refund لا يتكرر؛ اختبار المزود يحتاج إذن وبيئة محددة.
- نطاق المرحلة: P1 إن بقي الدفع مفعلاً؛ لا إسقاط البوابة ضمنياً.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J07

**Returns/RMA**

- الصفحات والأفعال: customer request/view/cancel؛ admin approve/reject/receive/complete.
- personas/gates: owner؛ orders.view/manage.
- أدلة source المرشحة: ReturnRequestWorkflowTest; ReturnManagementTest; ReturnWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: جزئي/كامل/مرفوض/تالف؛ reconcile refund/restock؛ عدم استرجاع نفس الكمية عبر POS وRMA؛ authorization وretry.
- نطاق المرحلة: P1.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J08

**Delivery**

- الصفحات والأفعال: list؛ update status/tracking/notes.
- personas/gates: delivery.view/manage.
- أدلة source المرشحة: DeliveryHardeningTest; DeliveryWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: نفس order عبر حالات التوصيل؛ Delivered/COD settlement؛ منع القفز والتكرار وتناقض order/payment.
- نطاق المرحلة: P1 foundation؛ carriers/POD توسع P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J09

**POS**

- الصفحات والأفعال: workspace/lookups؛ shifts؛ cart/customer/scan/discount/hold/resume/checkout؛ sale/return.
- personas/gates: pos.manage؛ discount/return/review permissions إضافية.
- أدلة source المرشحة: PosCashierTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: cashier A/B؛ افتح/أغلق وردية، بيع نقدي/بطاقة وhold/resume وreturn؛ reconcile stock/payment/cash variance؛ طباعة ممثلة وscanner focus.
- نطاق المرحلة: P1؛ GET workspace قد ينشئ cart فلا يُفتح تحت read-only افتراضاً.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J10

**Inventory/cost**

- الصفحات والأفعال: inventory/scan/labels/adjust؛ cost calculator/materials/save.
- personas/gates: inventory.manage.
- أدلة source المرشحة: InventoryAdjustmentTest; InventoryBarcodeScanTest; InventoryBarcodeLabelTest; CostCalculatorSemanticsTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: عد فعلي وتسوية وسبب مدقق؛ FEFO/expired/variant؛ before-after reconciliation؛ scanner/labels؛ cost save لا يغير semantics بلا سياسة.
- نطاق المرحلة: P1.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J11

**Procurement/suppliers**

- الصفحات والأفعال: supplier CRUD؛ purchases؛ scan/undo/partial/verified/full receive؛ reversal/cancel؛ settlement/void.
- personas/gates: inventory.manage OR purchasing.settlements.manage للقراءة؛ كل mutation له gate.
- أدلة source المرشحة: PurchaseReceivingHardeningTest; PurchaseBarcodeReceivingTest; PurchaseSettlementIntegrityTest; SupplierWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: purchase جزئي ثم استكمال/replay/reversal؛ reconcile aggregate/lots/received/settlement؛ finance يرى التسوية دون منحه receiving؛ supplier deletion dependencies.
- نطاق المرحلة: P1.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J12

**Customers/statement**

- الصفحات والأفعال: list/detail؛ statement/print/export.
- personas/gates: customers.manage؛ role change منفصل J18.
- أدلة source المرشحة: CustomerAccountStatementTest; CustomerStatementWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: reconcile captured/refunded values حسب العملة؛ فلاتر وتصدير/طباعة ببيانات كبيرة؛ عدم تقديم running ledger balance غير موجود.
- نطاق المرحلة: P1؛ multi-currency accounting توسع منفصل.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J13

**Offers**

- الصفحات والأفعال: coupon/promotion CRUD.
- personas/gates: promotions.manage.
- أدلة source المرشحة: CouponMoneyIntegrityTest; PromotionMoneyIntegrityTest; AdminPromotionHardeningTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: percent/fixed/conditions/expiry/stacking حسب الموجود؛ totals checkout/POS/reports متوافقة؛ validation وأذونات.
- نطاق المرحلة: P1.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J14

**Store configuration**

- الصفحات والأفعال: branding/content؛ shipping methods/zones/cities/rates؛ payment settings.
- personas/gates: settings.manage أو payments.settings.
- أدلة source المرشحة: BrandingWorkspaceV2Test; CommerceSettingsWorkspaceV2Test; ShippingWorkspaceClosureTest; ProviderSecretHardeningTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: حفظ مصرح به وعكسه على storefront/quote؛ secret masking؛ روابط وصور وlocale validation؛ تبويبات وتحذير أخطاء واضح.
- نطاق المرحلة: P1؛ لا تغيير إعدادات حقيقية ضمن audit.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J15

**Dashboard/analytics/growth**

- الصفحات والأفعال: dashboard/reports؛ growth overview/content/operations/insights؛ campaign/rule/template/segment/experiment CRUD/toggles/run/retry/demo.
- personas/gates: dashboard.view؛ growth.view/manage.
- أدلة source المرشحة: AdminDashboardExperienceTest; AnalyticsRealizedPurchaseReconciliationTest; GrowthControlIntegrityTest; GrowthDemoEnvironmentGuardTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: reconcile KPIs إلى معاملات معلومة؛ filters/date/currency؛ runtime datasets؛ لا run/send/demo mutation دون إذن؛ review UI/EN/AR.
- نطاق المرحلة: P1 للقدرات الحالية؛ advanced AI/analytics P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J16

**Support**

- الصفحات والأفعال: customer list/create/detail/reply؛ admin manage/cases/settings/SLA/templates.
- personas/gates: customer owner؛ support.view/manage.
- أدلة source المرشحة: SupportCaseFoundationTest; SupportSlaAndTemplatesTest; CustomerSupportLiveReplyTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: عميل A/B + agent؛ internal note لا تظهر للعميل؛ linked order ownership؛ reply/status/SLA بعد التحويل؛ no unauthorized sends.
- نطاق المرحلة: P1 foundation؛ attachments/omnichannel P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J17

**Notifications/WhatsApp**

- الصفحات والأفعال: customer/admin inbox read؛ notification center logs/templates/automation/diagnostics؛ retries/test-send/scanner.
- personas/gates: customer owner؛ notifications.view؛ settings.manage.
- أدلة source المرشحة: CustomerNotificationLiveActionsTest; NotificationEscalationScannerCommandTest; WhatsAppWorkspaceClosureTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: isolation وread state؛ queue/retry/idempotency؛ delivery proof عبر recipient test مصرح به؛ لا تعتبر API success دليلاً على delivery.
- نطاق المرحلة: P1 للقنوات المفعلة؛ التوسعات فقط P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J18

**Roles/security**

- الصفحات والأفعال: permission matrix/custom role/custom permission/user role؛ customer role update.
- personas/gates: permissions.manage؛ controller Super Admin guards.
- أدلة source المرشحة: ExplicitAdminRoleHardeningTest; PermissionEscalationHardeningTest; PermissionRoleDeletionTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: لكل default role + custom/negative persona: direct route/action/Livewire؛ self/system/super-admin restrictions؛ لا اعتماد على menu hiding.
- نطاق المرحلة: P1؛ لا تغيير permission policy.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J19

**Workforce identity/attendance**

- الصفحات والأفعال: employees CRUD؛ time clock/breaks؛ corrections approve/reject؛ attendance void.
- personas/gates: clock/view/manage + reporting scope/anti-self.
- أدلة source المرشحة: WorkforceScopedAccessTest; WorkforceReportingHierarchyFoundationTest; WorkforceAttendanceRulesTest; WorkforceTimezoneAttendanceUxTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: W-ORG-01b: own/team/other team/org/inactive؛ وقت/استراحة وتصحيح وvoid مع audit؛ لا salary leakage؛ runtime concurrency.
- نطاق المرحلة: P1 existing default؛ module exclusion يحتاج قرار وعزل مثبت.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J20

**Scheduling**

- الصفحات والأفعال: my schedule؛ admin schedule CRUD/cancel.
- personas/gates: clock؛ view/manage + employee scope.
- أدلة source المرشحة: WorkforceSchedulingTest; WorkforceLeaveLocalDayBoundariesTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: overnight/timezone/DST؛ leave overlap؛ concurrent edits؛ scoped visibility وcancel؛ policy draft/publish لا يُفترض.
- نطاق المرحلة: P1 foundation؛ policies الجديدة P2 بعد اعتماد.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J21

**Leave**

- الصفحات والأفعال: my leave/preview/request/cancel؛ review approve/reject؛ adjustment؛ leave type CRUD.
- personas/gates: clock؛ view/manage؛ org and reviewer guards.
- أدلة source المرشحة: WorkforceLeaveManagementTest; WorkforceLeavePolicyEngineTest; WorkforceLeavePolicyPreviewTest; WorkforceLeaveApprovalRoutingTest; WorkforceLeaveApprovalReadinessTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: legacy/fixed/flexible + snapshot + balance؛ منع self/cross-team؛ missing manager؛ approve/reject/cancel وتعارض schedule/concurrency؛ سياسة دون اختراع.
- نطاق المرحلة: P1 foundation؛ delegation/escalation/advanced entitlement P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J22

**Payroll**

- الصفحات والأفعال: my payslips؛ periods/runs/entries؛ compensation؛ adjustments؛ approve/paid.
- personas/gates: payroll.self/view/manage + ownership.
- أدلة source المرشحة: WorkforcePayrollTest; WorkforceDemoPersonaMatrixTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: employee لا يرى غيره؛ supervisor لا salary؛ finance approved arithmetic/reconciliation؛ منع تعديلات بعد lock/pay؛ period/timezone policy واضحة.
- نطاق المرحلة: P1 V1 إن بقي مفعلاً؛ enterprise tax/overtime/advanced compensation P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J23

**Import drafts**

- الصفحات والأفعال: imports list؛ create draft.
- personas/gates: imports.manage.
- أدلة source المرشحة: LiveListClosureTest (candidate؛ لا يثبت CSV execution). أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: المصدر ImportController.store ينشئ draft/meta.future_ready فقط؛ حسم عرضها كdraft داخلي أو إخراجها بأمان؛ لا ادعاء استيراد فعلي.
- نطاق المرحلة: اقتراح: CSV execution إلى Phase2؛ لا تنفيذ/إخفاء أو scope approval ضمن هذه الدفعة.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J24

**Operations/supporting endpoints**

- الصفحات والأفعال: ping/locale/media/home؛ deploy center؛ signed executor API؛ sanctum user.
- personas/gates: mixed؛ signed executor validation داخل controller.
- أدلة source المرشحة: HealthProbeExposureTest; OperationsHealthTest; DatabaseSnapshotCommandTest; LocaleRedirectSecurityTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: OPS-01/02/Production runtime؛ secure deployment/restore/rollback؛ main release path؛ API signatures؛ no blind GET crawl.
- نطاق المرحلة: P1 gates؛ assistant deploy automation P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## J25

**Generated authentication**

- الصفحات والأفعال: Auth::routes() + Auth controllers/traits؛ login/register/reset/confirm؛ verification views conditional.
- personas/gates: Guest/auth حسب runtime manifest.
- أدلة source المرشحة: CustomerAccountTest; SharedShellAccessibilityTest; ConnectedIdentityFoundationTest. أسماء الاختبارات موجودة؛ references الدقيقة للمسارات في CSV، وفحص assertions/coverage ضمن القبول.
- شرط الإغلاق: runtime route manifest + register/login/logout/recovery/confirm/session expiry؛ تحقق email verification wiring قبل ادعائها؛ mail test recipients only.
- نطاق المرحلة: P1 الأساسي؛ OAuth P2.
- الحالة: OPEN؛ لا دليل QAS كامل مربوط لهذه الرحلة في هذه الدفعة.

## ترتيب التنفيذ المحدد لإغلاق الإصدار

| دفعة | الناتج الذي يسمح بإغلاقها |
|---|---|
| C1 — Core commerce | J00–J08 + J13 + J25: catalog/auth/cart/checkout/payment/order/delivery/return؛ معاملات متطابقة وموانع صلاحيات واختبارات failure/replay |
| C2 — Store operations | J09–J12: POS/stock/purchases/settlements/customer statement؛ مصالحة كل حركة وأدوار وطباعة |
| C3 — Remaining enabled surfaces | J14–J18 وJ23: settings/content/growth/support/notifications/roles؛ قرار Import drafts وتطبيق حدوده بأمان |
| C4 — Workforce V1 | J19–J22: W-ORG-01b وconcurrency وسياسات V1 وحساباتها؛ أو قرار استبعاد صريح مثبت العزل |
| C5 — Release gate | J24 + cross-cutting: rotation/restore/upgrade/CI/exact SHA/performance/EN-AR/mobile/a11y/production runbooks ثم owner Go/No-Go |

مشترك لكل دفعة: happy/invalid/empty/loading/error/retry/session-expiry؛ EN/AR/RTL + desktop/mobile؛ ownership/direct-route/action denial؛ screenshots/results وsource/QAS SHA؛ إصلاح العيب المثبت فقط ثم regression وCI وQAS. كل release-blocker مفتوح يمنع Go حتى لو نجحت بقية الصفحات.

## الخطوة التالية وليست جرداً جديداً

**C1 acceptance readiness:** استخدم الصفوف الموجودة، وافحص Assertions الاختبارات والخدمات ذات الصلة لتجهيز expected-before/after للطلب والمبلغ والمخزون، وحدد fixtures ومعاملات اختبار قليلة مع cleanup/reconciliation وآثار GET المعروفة. طابق route manifest من CI حين يتاح مع هذا الجرد، دون تشغيل QAS أو تعديل policy. ثم ابدأ تنفيذ QAS فقط بعد إذن واضح لحدود Login/Cart/Checkout والـfixtures المطلوبة. لا نعيد كتابة خطة عامة ولا نكرر guest PASS.

القيود الحالية تمنع mutations وLogin Submit؛ لذلك C1 stateful QAS **BLOCKED BY SCOPE** وليس FAIL. إنشاء الخطة لا يمنح إذن الكتابة. لا نطلب صلاحيات مفتوحة؛ نحضر matrix محددة قابلة للمراجعة أولاً.

## ما لم تثبته هذه الدفعة

- لم نستخرج runtime route:list؛ لا نزعم أن 317 هو كامل registered routes. Auth generated/package routes تحتاج reconciliation.
- استخراج controller views/guards آلي ومحدود literal patterns؛ middleware المسجل في CSV معلن في route file وليس كل global/controller/trait/service gates.
- test references exact literals، لا تعني assertion كامل أو mapping لكل branch. Livewire candidate calls وbinding names قد تتكرر بين components؛ لا تُعتبر test-proof دون سياق.
- لا runtime performance/security audit كامل ولا QAS role assignments ولا new bug reproduction. Framework helpers/conditional tabs/dynamic JS والحالات غير المرئية تحتاج قبول.
- CI baseline ناجح؛ CI الوثيقة الجديدة مستقل ويُفحص بعد commit.

## قاعدة الاستمرار

هذا سجل تنفيذ تحت [Phase1 closure register](PHASE1_PRODUCTION_CLOSURE_REGISTER_2026-10-17_AR.md)، وليس roadmap منافسة. حافظ على G01–G10 وقرارات Phase2 فيه. اقرأ CURRENT_PHASE ثم هذا التقرير، ونفذ C1، ثم تقدم بالترتيب. لا تصف Phase1 CLOSED قبل جميع release gates.
