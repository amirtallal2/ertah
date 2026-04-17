import 'package:flutter/widgets.dart';

import '../config/app_theme.dart';
import '../services/settings_service.dart';

class AppSettingsProvider extends ChangeNotifier with WidgetsBindingObserver {
  final SettingsService _settingsService = SettingsService();

  String _appFont = AppTheme.cairoKey;
  String _appName = '';
  String? _appLogoUrl;
  double _sparePartsMinOrderWithInstallation = 0;
  bool _isInitialized = false;
  bool _isLoading = false;

  String get appFont => _appFont;
  String get appName => _appName;
  String? get appLogoUrl => _appLogoUrl;
  double get sparePartsMinOrderWithInstallation =>
      _sparePartsMinOrderWithInstallation;
  bool get isInitialized => _isInitialized;

  AppSettingsProvider() {
    WidgetsBinding.instance.addObserver(this);
    initialize();
  }

  Future<void> initialize() async {
    if (_isLoading || _isInitialized) return;
    await refresh(forceNotify: true);
  }

  Future<void> refresh({bool forceNotify = false}) async {
    if (_isLoading) return;
    _isLoading = true;

    try {
      final response = await _settingsService.getAppSettings();
      if (response.success && response.data is Map) {
        final data = Map<String, dynamic>.from(response.data as Map);
        final nextFont = _normalizeFont(data['app_font']);
        final nextLogo = _normalizeUrl(data['app_logo']);
        final nextName = _normalizeText(data['app_name']);
        final nextMinOrder = _normalizeDouble(
          data['spare_parts_min_order_with_installation'],
        );
        final hasChanged =
            nextFont != _appFont ||
            nextLogo != _appLogoUrl ||
            nextName != _appName ||
            nextMinOrder != _sparePartsMinOrderWithInstallation;
        _appFont = nextFont;
        _appLogoUrl = nextLogo;
        _appName = nextName;
        _sparePartsMinOrderWithInstallation = nextMinOrder;
        if (hasChanged || forceNotify) {
          notifyListeners();
        }
      }
    } catch (_) {
      _appFont = AppTheme.cairoKey;
      _appLogoUrl = null;
      _appName = '';
      _sparePartsMinOrderWithInstallation = 0;
    } finally {
      _isLoading = false;
      _isInitialized = true;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      refresh();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  String _normalizeFont(dynamic value) {
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    return normalized == AppTheme.zainKey ? AppTheme.zainKey : AppTheme.cairoKey;
  }

  String? _normalizeUrl(dynamic value) {
    final normalized = value?.toString().trim() ?? '';
    return normalized.isEmpty ? null : normalized;
  }

  String _normalizeText(dynamic value) {
    final normalized = value?.toString().trim() ?? '';
    return normalized;
  }

  double _normalizeDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }
    final parsed = double.tryParse(value?.toString().trim() ?? '');
    return parsed ?? 0;
  }
}
