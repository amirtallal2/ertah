# 📱 تطبيق ارتاح للصيانة المنزلية - Flutter App

## 📋 نظرة عامة

تطبيق **ارتاح** هو تطبيق للخدمات المنزلية والصيانة يربط بين العملاء ومقدمي الخدمة. هذا هو تطبيق Flutter للعملاء.

## ✨ المميزات

### الشاشات المتوفرة:
- 🎬 **Splash Screen** - شاشة البداية مع الأنيميشن
- 📖 **Onboarding** - شاشة التعريف بالتطبيق (3 صفحات)
- 📱 **تسجيل الدخول** - بالهاتف مع رمز OTP
- 📍 **اختيار الموقع** - GPS أو اختيار المدينة
- 🏠 **الرئيسية** - البانرات والخدمات والمتاجر
- 📋 **الطلبات** - عرض الطلبات الحالية والمكتملة والملغاة
- 🎁 **العروض** - عرض العروض والخصومات المتاحة
- 🏪 **المتجر** - المتاجر والمنتجات
- ⚙️ **الإعدادات** - الملف الشخصي والإعدادات

### الميزات التقنية:
- 🎨 **تصميم عصري** - ألوان جذابة وأنيميشن سلس
- 🌍 **دعم RTL** - التطبيق يعمل بالعربية
- 🔐 **مصادقة JWT** - تسجيل دخول آمن
- 📡 **API Ready** - جاهز للتواصل مع الـ Backend
- 💾 **تخزين محلي** - حفظ بيانات المستخدم

## 🛠 التقنيات المستخدمة

```yaml
Flutter: ^3.7.0
Dart: ^3.0.0

# State Management
provider: ^6.1.2

# UI/UX
flutter_animate: ^4.5.2
cached_network_image: ^3.4.1
smooth_page_indicator: ^1.2.1
google_fonts: ^6.3.3

# Authentication
intl_phone_field: ^3.2.0
pin_code_fields: ^8.0.1

# Storage
shared_preferences: ^2.3.4
flutter_secure_storage: ^9.2.4

# Network
http: ^1.3.1
```

## 📁 هيكل المشروع

```
lib/
├── config/
│   ├── app_config.dart      # إعدادات التطبيق
│   └── app_theme.dart       # الثيم والألوان
├── models/
│   ├── user_model.dart      # نموذج المستخدم
│   ├── order_model.dart     # نموذج الطلب
│   ├── service_category_model.dart
│   ├── offer_model.dart
│   ├── store_model.dart
│   ├── product_model.dart
│   └── ...
├── providers/
│   └── auth_provider.dart   # مزود المصادقة
├── screens/
│   ├── splash_screen.dart
│   ├── onboarding_screen.dart
│   ├── phone_login_screen.dart
│   ├── otp_verification_screen.dart
│   ├── location_picker_screen.dart
│   ├── main_navigation.dart
│   ├── home_screen.dart
│   ├── orders_screen.dart
│   ├── offers_screen.dart
│   ├── store_screen.dart
│   └── settings_screen.dart
├── services/
│   └── api_service.dart     # خدمة API
└── main.dart                # نقطة الدخول
```

## 🚀 تشغيل التطبيق

### المتطلبات:
- Flutter SDK ^3.7.0
- Dart ^3.0.0
- Android Studio / VS Code

### الخطوات:

```bash
# 1. الانتقال لمجلد التطبيق
cd flutter_app

# 2. تثبيت الـ dependencies
flutter pub get

# 3. تشغيل التطبيق
flutter run
```

## 🔧 إعداد Backend

1. تأكد من تشغيل XAMPP (Apache + MySQL)
2. قم بتشغيل migrations قاعدة البيانات:
   ```sql
   -- تشغيل ملف database/ertah_db.sql
   -- تشغيل ملف database/migrations/add_mobile_tables.sql
   ```
3. تحديث رابط API في `lib/config/app_config.dart`:
   ```dart
   static const String baseUrl = 'http://YOUR_IP:80/ertah/admin-panel/api/mobile';
   ```

## 📱 تشغيل على Android

```bash
# بناء APK
flutter build apk --release

# الملف سيكون في:
# build/app/outputs/flutter-apk/app-release.apk
```

## 🍎 تشغيل على iOS

```bash
# بناء للـ iOS
flutter build ios --release

# أو فتح في Xcode
open ios/Runner.xcworkspace
```

## 🎨 الألوان الرئيسية

| اللون | الكود | الاستخدام |
|-------|-------|----------|
| الذهبي/الأصفر | `#FBCC26` | اللون الأساسي |
| البنفسجي | `#7466ED` | اللون الثانوي |
| الأخضر | `#10B981` | النجاح |
| الأحمر | `#EF4444` | الخطأ |
| الرمادي | `#6B7280` | النصوص |

## 📞 API Endpoints

```
POST /auth?action=send-otp       - إرسال OTP
POST /auth?action=verify-otp     - التحقق من OTP
POST /auth?action=register       - تسجيل مستخدم

GET  /users?action=profile       - الملف الشخصي
POST /users?action=profile       - تحديث الملف الشخصي
GET  /users?action=addresses     - العناوين
POST /users?action=addresses     - إضافة عنوان

GET  /home                        - بيانات الصفحة الرئيسية
GET  /services?action=list        - قائمة الخدمات
GET  /orders?action=list          - قائمة الطلبات
POST /orders?action=create        - إنشاء طلب
```

## 🔮 التطويرات المستقبلية

- [ ] إضافة الخرائط (Google Maps)
- [ ] إشعارات Push Notifications
- [ ] الدفع الإلكتروني
- [ ] تتبع الطلب في الوقت الفعلي
- [ ] الدردشة مع مقدم الخدمة
- [ ] الوضع المظلم (Dark Mode)

## 📝 ملاحظات

- التطبيق يعمل حالياً في وضع Demo مع بيانات تجريبية
- للتشغيل الفعلي، يجب ربطه بـ Backend حقيقي
- رمز OTP التجريبي: أي 4 أرقام

---

تم التطوير بواسطة **Antigravity AI** 🚀
