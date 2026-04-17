/// Auth Service
/// خدمة المصادقة

import 'api_service.dart';

class AuthService {
  final ApiService _api = ApiService();

  /// Send OTP to phone
  Future<ApiResponse> sendOtp(String phone) async {
    return await _api.post(
      '/mobile/auth.php?action=send-otp',
      body: {'phone': phone, 'account_type': 'provider'},
    );
  }

  /// Verify OTP and login/register
  Future<ApiResponse> verifyOtp(String phone, String otp) async {
    return await _api.post(
      '/mobile/auth.php?action=verify-otp',
      body: {'phone': phone, 'otp': otp, 'account_type': 'provider'},
    );
  }

  /// Register user with details
  Future<ApiResponse> register({
    required String phone,
    required String fullName,
    String? email,
  }) async {
    return await _api.post(
      '/mobile/auth.php?action=register',
      body: {
        'phone': phone,
        'full_name': fullName,
        'account_type': 'provider',
        if (email != null) 'email': email,
      },
    );
  }

  /// Login existing user
  Future<ApiResponse> login(String phone) async {
    return await _api.post(
      '/mobile/auth.php?action=login',
      body: {'phone': phone, 'account_type': 'provider'},
    );
  }

  /// Refresh token
  Future<ApiResponse> refreshToken(String token) async {
    return await _api.post(
      '/mobile/auth.php?action=refresh-token',
      body: {'token': token},
    );
  }
}
