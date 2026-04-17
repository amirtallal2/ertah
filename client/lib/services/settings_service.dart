// Settings Service
// خدمة الإعدادات

import 'api_service.dart';

class SettingsService {
  final ApiService _api = ApiService();

  Map<String, dynamic>? _langParams(String? lang) {
    final normalized = (lang ?? '').trim().toLowerCase();
    if (normalized.isEmpty) return null;
    return {'lang': normalized};
  }

  /// Get app settings
  Future<ApiResponse> getAppSettings({String? lang}) async {
    return await _api.get(
      '/mobile/settings.php?action=app',
      params: _langParams(lang),
    );
  }

  /// Get about us
  Future<ApiResponse> getAbout({String? lang}) async {
    return await _api.get(
      '/mobile/settings.php?action=about',
      params: _langParams(lang),
    );
  }

  /// Get terms and conditions
  Future<ApiResponse> getTerms({String? lang}) async {
    return await _api.get(
      '/mobile/settings.php?action=terms',
      params: _langParams(lang),
    );
  }

  /// Get privacy policy
  Future<ApiResponse> getPrivacy({String? lang}) async {
    return await _api.get(
      '/mobile/settings.php?action=privacy',
      params: _langParams(lang),
    );
  }

  /// Get contact info
  Future<ApiResponse> getContact() async {
    return await _api.get('/mobile/settings.php?action=contact');
  }
}
