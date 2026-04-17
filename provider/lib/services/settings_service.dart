/// Settings Service
/// خدمة الإعدادات

import 'api_service.dart';

class SettingsService {
  final ApiService _api = ApiService();

  /// Get app settings
  Future<ApiResponse> getAppSettings() async {
    return await _api.get('/mobile/settings.php?action=app');
  }

  /// Get about us
  Future<ApiResponse> getAbout() async {
    return await _api.get('/mobile/settings.php?action=about');
  }

  /// Get terms and conditions
  Future<ApiResponse> getTerms() async {
    return await _api.get('/mobile/settings.php?action=terms');
  }

  /// Get privacy policy
  Future<ApiResponse> getPrivacy() async {
    return await _api.get('/mobile/settings.php?action=privacy');
  }

  /// Get contact info
  Future<ApiResponse> getContact() async {
    return await _api.get('/mobile/settings.php?action=contact');
  }
}
