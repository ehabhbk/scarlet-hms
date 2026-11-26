# تشغيل مستمعات الأجهزة كخدمات دائمة

يوفّر Scarlet HMS مستمعين TCP:

- `devices:listen`: HL7/ASTM للمختبر (2575) والأشعة/الموجات (2576).
- `devices:listen-dicom`: DICOM C-STORE (2577)، يحفظ الملفات ويستخرج نص التقرير.

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
sudo systemctl daemon-reload
sudo systemctl enable --now scarlet-devices-hl7 scarlet-devices-dicom
sudo systemctl status scarlet-devices-hl7 scarlet-devices-dicom
journalctl -u scarlet-devices-dicom -f
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
```

استخدم HTTPS دائماً عند إرسال النتائج عبر `X-Device-Token`، ولا تترك الرمز الافتراضي في الإنتاج.

## ملاحظات الأجهزة

- سجّل كل جهاز من «الإعدادات ← أجهزة المختبر / أجهزة الأشعة والموجات» برمز `code` أو `AE Title` مطابق لما يرسله الجهاز.
- نتائج المختبر المستوردة تُحفظ بحالة `entered` دون توقيع، ونتائج الأشعة بحالة `result_received` حتى يعتمدها مستخدم بشري.
- أي رسالة غير مطابقة تُحفظ بحالة `unmatched` ويمكن إعادة معالجتها من صفحة «رسائل الأجهزة» بعد إصلاح بيانات الطلب/المريض.
