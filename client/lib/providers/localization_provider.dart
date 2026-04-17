import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocalizationProvider extends ChangeNotifier with WidgetsBindingObserver {
  static const Locale _fallbackLocale = Locale('ar', 'SA');
  static const String _localeCodeKey = 'selected_app_locale_code';
  static const String _localePinnedKey = 'selected_app_locale_pinned';
  Locale _locale = _fallbackLocale;
  bool _isPinnedByUser = false;

  Locale get locale => _locale;

  LocalizationProvider() {
    WidgetsBinding.instance.addObserver(this);
    _initializeLocale();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeLocales(List<Locale>? locales) {
    if (_isPinnedByUser) return;
    _syncWithDeviceLocale(locales: locales);
  }

  Future<void> _initializeLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final savedCode = (prefs.getString(_localeCodeKey) ?? '').trim();
    _isPinnedByUser =
        (prefs.getBool(_localePinnedKey) ?? false) || savedCode.isNotEmpty;

    if (_isPinnedByUser && savedCode.isNotEmpty) {
      final savedLocale = _normalizeLocale(Locale(savedCode));
      if (_locale != savedLocale) {
        _locale = savedLocale;
        notifyListeners();
      }
      return;
    }

    _syncWithDeviceLocale();
  }

  void _syncWithDeviceLocale({List<Locale>? locales, bool notify = true}) {
    final resolved = _resolveSupportedLocale(
      locales ?? WidgetsBinding.instance.platformDispatcher.locales,
    );
    if (_locale == resolved) return;
    _locale = resolved;
    if (notify) {
      notifyListeners();
    }
  }

  // Keep this method for existing UI actions, but normalization is always
  // limited to supported app languages.
  Future<void> setLocale(Locale locale) async {
    final normalized = _normalizeLocale(locale);
    final changed = _locale != normalized;
    _locale = normalized;
    _isPinnedByUser = true;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeCodeKey, normalized.languageCode);
    await prefs.setBool(_localePinnedKey, true);

    if (changed) {
      notifyListeners();
    }
  }

  static Locale _resolveSupportedLocale(List<Locale> locales) {
    for (final locale in locales) {
      final normalized = _normalizeLocale(locale);
      if (normalized != _fallbackLocale ||
          locale.languageCode.toLowerCase() == 'ar') {
        return normalized;
      }
    }
    return _fallbackLocale;
  }

  static Locale _normalizeLocale(Locale locale) {
    switch (locale.languageCode.toLowerCase()) {
      case 'en':
        return const Locale('en', 'US');
      case 'ur':
        return const Locale('ur', 'PK');
      case 'ar':
        return const Locale('ar', 'SA');
      default:
        return _fallbackLocale;
    }
  }
}
