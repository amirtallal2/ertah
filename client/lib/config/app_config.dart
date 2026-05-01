// App Configuration
// إعدادات التطبيق

import 'package:flutter/foundation.dart';

class AppConfig {
  // API Configuration
  // للتطوير المحلي استخدم localhost
  // للإنتاج استخدم ertah.org

  // Override explicitly with: --dart-define=IS_PRODUCTION=true|false
  static const bool _hasProductionOverride = bool.hasEnvironment(
    'IS_PRODUCTION',
  );
  static const bool _productionOverride = bool.fromEnvironment('IS_PRODUCTION');

  // Domain configuration
  static const String _productionDomain = 'ertah.org';

  // Override when testing on real devices, e.g.:
  // --dart-define=DEV_HOST=192.168.1.11
  static const String _devHostOverride = String.fromEnvironment(
    'DEV_HOST',
    defaultValue: '',
  );

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

  static String _normalizeHost(String hostValue) {
    var host = hostValue.trim();
    host = host.replaceFirst(RegExp(r'^https?://'), '');
    host = host.replaceAll(RegExp(r'/$'), '');
    return host;
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
  static const String _fallbackImageUrl = 'https://placehold.co/1x1.png';
  static final RegExp _basicFileTokenRegex = RegExp(
    r'[0-9A-Za-z\u0600-\u06FF]',
  );

  static bool _isMissingValue(String value) {
    final lowered = value.trim().toLowerCase();
    return lowered.isEmpty ||
        lowered == 'null' ||
        lowered == 'undefined' ||
        lowered == 'nan';
  }

  static bool _looksLikeSymbolOnly(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return true;
    if (trimmed.contains('/') || trimmed.contains('\\')) return false;
    if (_basicFileTokenRegex.hasMatch(trimmed)) return false;
    return trimmed.runes.length <= 8;
  }

  static bool _hasInvalidTailSegment(String value) {
    final withoutQuery = value.split('?').first.split('#').first;
    final clean = withoutQuery.replaceAll(RegExp(r'/+$'), '');
    if (clean.isEmpty) return true;

    final lastSegment = clean.split('/').last.trim();
    if (lastSegment.isEmpty) return true;
    if (lastSegment.contains('.')) return false;

    return _looksLikeSymbolOnly(lastSegment);
  }

  /// Fix media URL to use correct base path
  static String fixMediaUrl(String? url) {
    final raw = (url ?? '').trim();
    if (_isMissingValue(raw)) {
      return _fallbackImageUrl;
    }
    // Prevent turning emoji/icon placeholders (e.g. 🔧) into invalid URLs.
    if (_looksLikeSymbolOnly(raw) || _hasInvalidTailSegment(raw)) {
      return _fallbackImageUrl;
    }
    // Rewrite localhost URLs to production domain
    if (raw.startsWith('http://localhost')) {
      return raw.replaceFirst(
        'http://localhost/ertah/admin-panel',
        adminPanelUrl,
      );
    }
    // Already a full URL — return as-is
    if (raw.startsWith('http')) {
      return raw;
    }
    final normalized = raw.replaceFirst(RegExp(r'^/+'), '');
    // Relative path starting with uploads/
    if (normalized.startsWith('uploads/')) {
      return '$adminPanelUrl/$normalized';
    }
    // Bare filename — assume it's in uploads/
    return '$adminPanelUrl/uploads/$normalized';
  }
}
