import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AppLocalizations {
  final Locale locale;
  static final Map<String, Map<String, String>> _cache = {};

  AppLocalizations(this.locale);

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  late Map<String, String> _localizedStrings;
  late Map<String, String> _fallbackEnglish;
  late Map<String, String> _fallbackArabic;

  Future<bool> load() async {
    _localizedStrings = await _loadLanguage(locale.languageCode);
    _fallbackEnglish = locale.languageCode == 'en'
        ? _localizedStrings
        : await _loadLanguage('en');
    _fallbackArabic = locale.languageCode == 'ar'
        ? _localizedStrings
        : await _loadLanguage('ar');
    return true;
  }

  String translate(String key) {
    final value = _localizedStrings[key];
    if (value != null && value.trim().isNotEmpty) return value;

    final englishFallback = _fallbackEnglish[key];
    if (englishFallback != null && englishFallback.trim().isNotEmpty) {
      return englishFallback;
    }

    final arabicFallback = _fallbackArabic[key];
    if (arabicFallback != null && arabicFallback.trim().isNotEmpty) {
      return arabicFallback;
    }

    return _humanizeKey(key);
  }

  Future<Map<String, String>> _loadLanguage(String languageCode) async {
    final cached = _cache[languageCode];
    if (cached != null) return cached;

    try {
      final jsonString = await rootBundle.loadString(
        'assets/lang/$languageCode.json',
      );
      final jsonMap = json.decode(jsonString) as Map<String, dynamic>;
      final mapped = jsonMap.map((key, value) {
        return MapEntry(key, value.toString());
      });
      _cache[languageCode] = mapped;
      return mapped;
    } catch (_) {
      return <String, String>{};
    }
  }

  String _humanizeKey(String key) {
    return key.replaceAll('_', ' ').trim();
  }
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) {
    return ['ar', 'en', 'ur'].contains(locale.languageCode);
  }

  @override
  Future<AppLocalizations> load(Locale locale) async {
    AppLocalizations localizations = AppLocalizations(locale);
    await localizations.load();
    return localizations;
  }

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

// Extension for easier access
extension AppLocalizationsX on BuildContext {
  String tr(String key) => AppLocalizations.of(this)?.translate(key) ?? key;
}
