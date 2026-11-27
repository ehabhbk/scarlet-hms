# تشغيل مستمعات الأجهزة كخدمات دائمة

يوفّر Scarlet HMS بوابة أجهزة عامة تعمل بالوضعين: استقبال (Push) واستجواب/سحب (Pull):

- `devices:listen`: HL7/ASTM/JSON/FHIR/XML عبر TCP+MLLP للمختبر (2575) والأشعة/الموجات (2576).
- `devices:listen-dicom`: DICOM C-STORE (2577)، يحفظ الملفات ويستخرج نص التقرير.
- `devices:listen-udp`: UDP/Syslog للأجهزة السريرية (2578) مثل مونيتورات العناية والطوارئ.
- `devices:poll --daemon`: يسحب النتائج من الأجهزة التي لا ترسل تلقائياً، حسب إعداد `poll_enabled` و`poll_query` لكل جهاز.
- `POST /api/v1/device-ingest/clinical`: استقبال عام عبر HTTP مع `X-Device-Token`.

يكتشف المحوّل `AUTO` نوع الرسالة تلقائياً: HL7 v2، ASTM، FHIR، JSON، XML/CDA، CSV، KEY_VALUE، ونصوص الأجهزة.
كل الرسائل تُحفظ خاماً، تُطابق على رقم العينة أو رقم الملف، وتنتظر التوقيع البشري؛ لا يوجد اعتماد تلقائي.

## Supervisor (موصى به)

```bash
sudo cp deploy/supervisor/scarlet-devices.conf /etc/supervisor/conf.d/scarlet-devices.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status 'scarlet-devices:*'
sudo supervisorctl restart 'scarlet-devices:*'
```

## systemd (بديل)

```bash
sudo cp deploy/systemd/scarlet-devices-hl7.service /etc/systemd/system/
sudo cp deploy/systemd/scarlet-devices-dicom.service /etc/systemd/system/
sudo cp deploy/systemd/scarlet-devices-udp.service /etc/systemd/system/
sudo cp deploy/systemd/scarlet-devices-poller.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now scarlet-devices-hl7 scarlet-devices-dicom scarlet-devices-udp scarlet-devices-poller
sudo systemctl status 'scarlet-devices-*'
journalctl -u scarlet-devices-poller -f
```

عدّل مسارات PHP والمشروع ومستخدم التشغيل داخل الملفات لتناسب الخادم.

## الإعدادات البيئية للإنتاج

```dotenv
APP_ENV=production
APP_URL=https://hms.example.com
FRONTEND_URL=https://hms.example.com
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8
DEVICE_INGEST_TOKEN=change-me-to-a-long-random-value
DEVICE_LISTENER_HOST=0.0.0.0
DEVICE_DICOM_AE_TITLE=SCARLET
DEVICE_UDP_PORT=2578
DEVICE_UDP_KIND=clinical
DEVICE_POLL_INTERVAL=30
```

استخدم HTTPS دائماً عند إرسال النتائج عبر `X-Device-Token`، ولا تترك الرمز الافتراضي في الإنتاج.

## ملاحظات الأجهزة

- سجّل كل جهاز من «الإعدادات ← أجهزة المختبر / أجهزة الأشعة والموجات / بوابة الأجهزة العامة» برمز `code` أو `AE Title` مطابق لما يرسله الجهاز.
- للأجهزة التي لا ترسل تلقائياً: فعّل «السحب التلقائي»، اضبط `host` و`port` وأمر الاستجواب (مثال `\x05` أو استعلام HL7)؛ ثم اضغط «جلب الآن» للاختبار.
- نتائج المختبر المستوردة تُحفظ بحالة `entered` دون توقيع، ونتائج الأشعة بحالة `result_received` حتى يعتمدها مستخدم بشري.
- أي رسالة غير مطابقة تُحفظ بحالة `unmatched` ويمكن إعادة معالجتها من صفحة «رسائل الأجهزة» بعد إصلاح بيانات الطلب/المريض.
