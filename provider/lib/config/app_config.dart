// App Configuration
// إعدادات التطبيق

import 'package:flutter/foundation.dart';

class AppConfig {
  // API Configuration
  // للتطوير المحلي استخدم localhost
  // للإنتاج استخدم ertah.org

  // Override explicitly with:
  // --dart-define=IS_PRODUCTION=true|false
  static const bool _hasProductionOverride = bool.hasEnvironment(
    'IS_PRODUCTION',
  );
  static const bool _productionOverride = bool.fromEnvironment('IS_PRODUCTION');
  static const String _productionDomain = 'ertah.org';

  // Override when testing on real devices, e.g.:
  // --dart-define=DEV_HOST=192.168.1.11
  static const String _devHostOverride = String.fromEnvironment(
    'DEV_HOST',
    defaultValue: '',
  );

  static String _normalizeHost(String hostValue) {
    var host = hostValue.trim();
    host = host.replaceFirst(RegExp(r'^https?://'), '');
    host = host.replaceAll(RegExp(r'/$'), '');
    return host;
  }

  static bool get isProduction {
    if (_hasProductionOverride) {
      return _productionOverride;
    }
    // Local web runs should use local API by default.
    if (kIsWeb) {
      return false;
    }
    // Mobile/desktop default to production unless explicitly overridden.
    return true;
  }

  static String get _effectiveDevHost {
    final override = _normalizeHost(_devHostOverride);
    if (override.isNotEmpty) {
      return override;
    }

    if (kIsWeb) {
      final browserHost = _normalizeHost(Uri.base.host);
      if (browserHost.isNotEmpty && browserHost != 'localhost') {
        return browserHost;
      }
      return '127.0.0.1';
    }

    return '127.0.0.1';
  }

  static String get productionBaseUrl {
    return 'https://$_productionDomain/admin-panel/api';
  }

  static String get productionFallbackBaseUrl {
    return 'https://$_productionDomain/api';
  }

  static String get baseUrl {
    if (isProduction) {
      return productionBaseUrl;
    }
    return 'http://$_effectiveDevHost/ertah/admin-panel/api';
  }

  static String get adminPanelUrl {
    if (isProduction) {
      return 'https://$_productionDomain/admin-panel';
    }
    return 'http://$_effectiveDevHost/ertah/admin-panel';
  }

  // App Info
  static const String appName = 'Darfix';
  static const String appNameEn = 'Darfix';
  static const String appVersion = '1.0.2';
  static const String appTagline = 'خدمات منزلية شاملة';

  // App Logo
  static const String logoAsset = 'assets/images/logo.png';

  // Support
  static const String supportEmail = 'support@darfix.org';
  static const String supportPhone = '+966500000000';
  static const String whatsappNumber = '+966500000000';

  // Social Media
  static const String facebookUrl = 'https://facebook.com/darfixapp';
  static const String twitterUrl = 'https://twitter.com/darfixapp';
  static const String instagramUrl = 'https://instagram.com/darfixapp';

  // App Links
  static const String termsUrl = 'https://darfix.org/terms';
  static const String privacyUrl = 'https://darfix.org/privacy';
  static const String aboutUrl = 'https://darfix.org/about';

  // API Endpoints
  static const String loginEndpoint = '/auth/login';
  static const String registerEndpoint = '/auth/register';
  static const String verifyOtpEndpoint = '/auth/verify-otp';
  static const String sendOtpEndpoint = '/auth/send-otp';
  static const String userProfileEndpoint = '/users/profile';
  static const String updateProfileEndpoint = '/users/update';
  static const String servicesEndpoint = '/services';
  static const String categoriesEndpoint = '/categories';
  static const String providersEndpoint = '/providers';
  static const String ordersEndpoint = '/orders';
  static const String offersEndpoint = '/offers';
  static const String productsEndpoint = '/products';
  static const String storesEndpoint = '/stores';
  static const String bannersEndpoint = '/banners';
  static const String notificationsEndpoint = '/notifications';
  static const String walletEndpoint = '/wallet';
  static const String addressesEndpoint = '/addresses';
  static const String reviewsEndpoint = '/reviews';
  static const String complaintsEndpoint = '/complaints';
  static const String citiesEndpoint = '/cities';

  // Push Notifications (OneSignal)
  // Set via --dart-define=ONESIGNAL_APP_ID=xxxx or from app settings fallback.
  static const String oneSignalAppId = String.fromEnvironment(
    'ONESIGNAL_APP_ID',
    defaultValue: '13bf8a95-130c-4e86-b4cc-e91bd3b12322',
  );

  // Timeouts
  static const int connectionTimeout = 30000; // 30 seconds
  static const int receiveTimeout = 30000;

  // Cache
  static const int cacheMaxAge = 7; // days
  static const int cacheMaxStale = 30; // days

  // Pagination
  static const int itemsPerPage = 20;

  // Image
  static const int maxImageSize = 5 * 1024 * 1024; // 5 MB
  static const List<String> allowedImageTypes = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
  ];

  // OTP
  static const int otpLength = 4;
  static const int otpResendTimeout = 60; // seconds

  // Map
  static const double defaultLatitude = 24.7136; // Riyadh
  static const double defaultLongitude = 46.6753;
  static const double defaultZoom = 15.0;

  /// Fix media URL to use correct base path
  static String fixMediaUrl(String? url) {
    if (url == null || url.isEmpty || url == 'null') {
      return 'https://placehold.co/1x1.png';
    }
    if (url.startsWith('http://localhost') && !isProduction) {
      return url.replaceFirst(
        'http://localhost/ertah/admin-panel',
        adminPanelUrl,
      );
    }
    if (url.startsWith('http')) {
      return url;
    }
    if (url.startsWith('uploads/')) {
      return '$adminPanelUrl/$url';
    }
    return '$adminPanelUrl/uploads/$url';
  }
}
