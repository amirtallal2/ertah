import 'package:flutter/foundation.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../config/app_config.dart';
import '../providers/auth_provider.dart';
import 'settings_service.dart';
import 'user_service.dart';

class PushNotificationService {
  PushNotificationService._();

  static final PushNotificationService instance = PushNotificationService._();

  final UserService _userService = UserService();
  final SettingsService _settingsService = SettingsService();

  bool _initialized = false;
  bool _skipInitializationForMissingAppId = false;
  bool _missingAppIdLogged = false;
  int? _boundUserId;
  int? _pendingOrderId;
  VoidCallback? onOrderNotificationTap;

  Future<void> initialize({String? appIdOverride}) async {
    if (_initialized) return;
    final override = (appIdOverride ?? '').trim();
    if (_skipInitializationForMissingAppId && override.isEmpty) {
      return;
    }

    final appId = await _resolveAppId(override.isEmpty ? null : override);
    if (appId.isEmpty) {
      _skipInitializationForMissingAppId = override.isEmpty;
      if (!_missingAppIdLogged) {
        debugPrint('PushNotificationService: OneSignal App ID is not set.');
        _missingAppIdLogged = true;
      }
      return;
    }

    _skipInitializationForMissingAppId = false;
    _missingAppIdLogged = false;

    try {
      OneSignal.initialize(appId);
      await OneSignal.Notifications.requestPermission(true);

      OneSignal.Notifications.addClickListener((event) {
        final additionalData = event.notification.additionalData;
        final orderId = _extractOrderId(additionalData);
        if (orderId != null) {
          _pendingOrderId = orderId;
          onOrderNotificationTap?.call();
        }
      });

      _initialized = true;
    } catch (e) {
      debugPrint('PushNotificationService init error: $e');
    }
  }

  Future<String> _resolveAppId(String? appIdOverride) async {
    final direct = (appIdOverride ?? AppConfig.oneSignalAppId).trim();
    if (direct.isNotEmpty) return direct;

    try {
      final response = await _settingsService.getAppSettings();
      if (response.success && response.data is Map) {
        final data = Map<String, dynamic>.from(
          (response.data as Map).map(
            (key, value) => MapEntry(key.toString(), value),
          ),
        );
        final fromSettings =
            (data['onesignal_app_id'] ?? data['one_signal_app_id'] ?? '')
                .toString()
                .trim();
        if (fromSettings.isNotEmpty) {
          return fromSettings;
        }
      }
    } catch (_) {}

    return '';
  }

  Future<void> syncWithAuth(AuthProvider authProvider) async {
    if (!_initialized) {
      await initialize();
      if (!_initialized) return;
    }

    final canBind =
        authProvider.isLoggedIn &&
        !authProvider.isGuest &&
        authProvider.user != null;

    if (!canBind) {
      if (_boundUserId != null) {
        try {
          OneSignal.logout();
        } catch (_) {}
      }
      _boundUserId = null;
      return;
    }

    final userId = authProvider.user!.id;
    if (_boundUserId != userId) {
      try {
        OneSignal.login(userId.toString());
        _boundUserId = userId;
      } catch (e) {
        debugPrint('PushNotificationService login sync error: $e');
      }
    }

    await _syncDeviceTokenToBackend();
  }

  int? takePendingOrderId() {
    final orderId = _pendingOrderId;
    _pendingOrderId = null;
    return orderId;
  }

  int? _extractOrderId(Map<String, dynamic>? data) {
    if (data == null || data.isEmpty) return null;

    for (final key in const ['order_id', 'orderId', 'id']) {
      final value = data[key];
      if (value == null) continue;
      final parsed = int.tryParse(value.toString());
      if (parsed != null && parsed > 0) {
        return parsed;
      }
    }

    final nestedOrder = data['order'];
    if (nestedOrder is Map) {
      final nestedOrderMap = Map<String, dynamic>.from(
        nestedOrder.map((key, value) => MapEntry(key.toString(), value)),
      );
      for (final key in const ['id', 'order_id', 'orderId']) {
        final value = nestedOrderMap[key];
        if (value == null) continue;
        final parsed = int.tryParse(value.toString());
        if (parsed != null && parsed > 0) {
          return parsed;
        }
      }
    }

    final deepLink =
        data['deep_link']?.toString() ??
        data['deeplink']?.toString() ??
        data['link']?.toString() ??
        '';
    if (deepLink.isNotEmpty) {
      final directMatch = RegExp(r'^order:(\d+)$').firstMatch(deepLink);
      if (directMatch != null) {
        final parsed = int.tryParse(directMatch.group(1)!);
        if (parsed != null && parsed > 0) {
          return parsed;
        }
      }

      final genericMatch = RegExp(r'(\d+)').firstMatch(deepLink);
      if (genericMatch != null) {
        final parsed = int.tryParse(genericMatch.group(1)!);
        if (parsed != null && parsed > 0) {
          return parsed;
        }
      }
    }

    return null;
  }

  Future<void> _syncDeviceTokenToBackend() async {
    try {
      final subscriptionId = OneSignal.User.pushSubscription.id;
      if (subscriptionId == null || subscriptionId.trim().isEmpty) {
        return;
      }

      await _userService.updateDeviceToken(
        deviceToken: subscriptionId.trim(),
        provider: 'onesignal',
      );
    } catch (e) {
      debugPrint('PushNotificationService token sync error: $e');
    }
  }
}
