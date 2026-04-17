import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Controls popup banner exposure so users are not interrupted too often.
class PopupBannerPolicyService {
  static const Duration _globalCooldown = Duration(hours: 18);
  static const Duration _dismissCooldown = Duration(days: 3);
  static const int _maxDailyDisplays = 1;
  static const int _maxDisplaysPerBanner = 3;

  static const String _dailyDateKey = 'popup_banner_daily_date';
  static const String _dailyCountKey = 'popup_banner_daily_count';
  static const String _lastShownAtKey = 'popup_banner_last_shown_at';
  static const String _bannerShowCountsKey = 'popup_banner_show_counts';
  static const String _bannerDismissedAtKey = 'popup_banner_dismissed_at';

  static bool _shownThisSession = false;

  Future<bool> shouldShowBanner(Map<String, dynamic> banner) async {
    if (_shownThisSession) return false;
    if (!_isBannerActive(banner) || !_hasImage(banner)) return false;

    final prefs = await SharedPreferences.getInstance();
    final now = DateTime.now();
    await _resetDailyCounterIfNeeded(prefs, now);

    final dailyCount = prefs.getInt(_dailyCountKey) ?? 0;
    if (dailyCount >= _maxDailyDisplays) return false;

    final lastShownAtMs = prefs.getInt(_lastShownAtKey);
    if (lastShownAtMs != null) {
      final lastShownAt = DateTime.fromMillisecondsSinceEpoch(lastShownAtMs);
      if (now.difference(lastShownAt) < _globalCooldown) return false;
    }

    final bannerKey = _bannerKey(banner);

    final dismissMap = _readIntMap(prefs.getString(_bannerDismissedAtKey));
    final dismissedAtMs = dismissMap[bannerKey];
    if (dismissedAtMs != null) {
      final dismissedAt = DateTime.fromMillisecondsSinceEpoch(dismissedAtMs);
      if (now.difference(dismissedAt) < _dismissCooldown) return false;
    }

    final showCountMap = _readIntMap(prefs.getString(_bannerShowCountsKey));
    final bannerShowCount = showCountMap[bannerKey] ?? 0;
    if (bannerShowCount >= _maxDisplaysPerBanner) return false;

    return true;
  }

  Future<void> markBannerShown(Map<String, dynamic> banner) async {
    final prefs = await SharedPreferences.getInstance();
    final now = DateTime.now();
    await _resetDailyCounterIfNeeded(prefs, now);

    final dailyCount = prefs.getInt(_dailyCountKey) ?? 0;
    await prefs.setInt(_dailyCountKey, dailyCount + 1);
    await prefs.setInt(_lastShownAtKey, now.millisecondsSinceEpoch);

    final bannerKey = _bannerKey(banner);
    final showCountMap = _readIntMap(prefs.getString(_bannerShowCountsKey));
    showCountMap[bannerKey] = (showCountMap[bannerKey] ?? 0) + 1;
    await prefs.setString(_bannerShowCountsKey, jsonEncode(showCountMap));

    _shownThisSession = true;
  }

  Future<void> markBannerDismissed(Map<String, dynamic> banner) async {
    final prefs = await SharedPreferences.getInstance();
    final bannerKey = _bannerKey(banner);
    final dismissMap = _readIntMap(prefs.getString(_bannerDismissedAtKey));
    dismissMap[bannerKey] = DateTime.now().millisecondsSinceEpoch;
    await prefs.setString(_bannerDismissedAtKey, jsonEncode(dismissMap));
  }

  Future<void> _resetDailyCounterIfNeeded(
    SharedPreferences prefs,
    DateTime now,
  ) async {
    final today =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final savedDay = prefs.getString(_dailyDateKey);
    if (savedDay == today) return;
    await prefs.setString(_dailyDateKey, today);
    await prefs.setInt(_dailyCountKey, 0);
  }

  bool _isBannerActive(Map<String, dynamic> banner) {
    final raw = banner['is_active'];
    if (raw == null) return true;
    if (raw is bool) return raw;
    final normalized = raw.toString().trim().toLowerCase();
    if (normalized.isEmpty) return true;
    return ['1', 'true', 'yes', 'on'].contains(normalized);
  }

  bool _hasImage(Map<String, dynamic> banner) {
    final image = (banner['image'] ?? '').toString().trim();
    return image.isNotEmpty;
  }

  String _bannerKey(Map<String, dynamic> banner) {
    final id = (banner['id'] ?? '').toString().trim();
    final updatedAt = (banner['updated_at'] ?? '').toString().trim();
    final createdAt = (banner['created_at'] ?? '').toString().trim();
    final image = (banner['image'] ?? '').toString().trim();

    if (id.isNotEmpty && updatedAt.isNotEmpty) return 'id:$id@u:$updatedAt';
    if (id.isNotEmpty && createdAt.isNotEmpty) return 'id:$id@c:$createdAt';
    if (id.isNotEmpty) return 'id:$id';
    if (image.isNotEmpty) return 'img:$image';
    return 'fallback:${banner.hashCode}';
  }

  Map<String, int> _readIntMap(String? rawJson) {
    if (rawJson == null || rawJson.trim().isEmpty) {
      return <String, int>{};
    }

    try {
      final decoded = jsonDecode(rawJson);
      if (decoded is! Map) return <String, int>{};

      final map = <String, int>{};
      decoded.forEach((key, value) {
        final parsed = int.tryParse(value.toString());
        if (parsed != null) {
          map[key.toString()] = parsed;
        }
      });
      return map;
    } catch (_) {
      return <String, int>{};
    }
  }
}
