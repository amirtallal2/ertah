// Auth Provider
// مزود المصادقة

import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/auth_storage_service.dart';
import '../services/auth_service.dart';
import '../services/user_service.dart';

class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  String? _token;
  bool _isLoading = false;
  bool _isInitialized = false;
  bool _isGuest = false;
  bool _needsProfileCompletion = false;

  UserModel? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;
  bool get isLoggedIn => _user != null && _token != null;
  bool get isGuest => _isGuest;
  bool get isInitialized => _isInitialized;
  bool get needsProfileCompletion => _needsProfileCompletion;

  /// Check if user can access authenticated features (logged in, not guest)
  bool get canAccessFeatures => isLoggedIn && !_isGuest;

  bool _isNoChangesMessage(String? message) {
    final normalized = (message ?? '').trim().toLowerCase();
    if (normalized.isEmpty) {
      return false;
    }

    return normalized.contains('no data to update') ||
        normalized.contains('nothing to update') ||
        normalized.contains('لا توجد تغييرات للحفظ');
  }

  final ApiService _apiService = ApiService();
  final AuthStorageService _authStorage = AuthStorageService();
  final AuthService _authService = AuthService();
  final UserService _userService = UserService();

  Future<void> initialize() async {
    if (_isInitialized) return;

    _isLoading = true;

    try {
      _token = await _authStorage.readToken();

      if (_token != null) {
        _apiService.setAuthToken(_token);
        await _fetchUserProfile();
      }
    } catch (e) {
      debugPrint('Auth initialization error: $e');
    }

    _isInitialized = true;
    _isLoading = false;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      notifyListeners();
    });
  }

  Future<ApiResponse> sendOtp(String phone) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _authService.sendOtp(phone);
      _isLoading = false;
      notifyListeners();
      return response;
    } catch (e) {
      _isLoading = false;
      notifyListeners();
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<AuthResult> verifyOtp(String phone, String otp) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _authService.verifyOtp(phone, otp);

      if (response.success && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        final userData = data['user'] is Map<String, dynamic>
            ? data['user'] as Map<String, dynamic>
            : <String, dynamic>{};

        _token = data['token']?.toString();
        _user = UserModel.fromJson(userData);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);

        if (_token != null) {
          _apiService.setAuthToken(_token);
          await _authStorage.saveToken(_token!);
        }

        _isLoading = false;
        notifyListeners();

        final isNewUser = data['is_new_user'] == true;
        return AuthResult(
          success: true,
          isNewUser: isNewUser,
          needsProfileCompletion: _needsProfileCompletion,
        );
      }

      _isLoading = false;
      notifyListeners();
      return AuthResult(
        success: false,
        message: response.message ?? 'رمز التحقق غير صحيح',
      );
    } catch (e) {
      _isLoading = false;
      notifyListeners();
      return AuthResult(success: false, message: 'حدث خطأ في التحقق');
    }
  }

  Future<bool> register({
    required String phone,
    required String fullName,
    String? email,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _authService.register(
        phone: phone,
        fullName: fullName,
        email: email,
      );

      if (response.success && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        final userData = data['user'] is Map<String, dynamic>
            ? data['user'] as Map<String, dynamic>
            : <String, dynamic>{};

        _token = data['token']?.toString();
        _user = UserModel.fromJson(userData);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);

        if (_token != null) {
          _apiService.setAuthToken(_token);
          await _authStorage.saveToken(_token!);
        }
      }

      _isLoading = false;
      notifyListeners();
      return response.success;
    } catch (e) {
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> _fetchUserProfile() async {
    try {
      final response = await _userService.getProfile();
      if (response.success && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        _user = UserModel.fromJson(data);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);
      } else if (response.isUnauthorized) {
        await logout();
      }
    } catch (e) {
      debugPrint('Fetch profile error: $e');
    }
  }

  Future<ApiResponse> updateProfile({
    String? fullName,
    String? email,
    String? phone,
    String? avatarPath,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      ApiResponse response;

      if (avatarPath != null) {
        response = await _apiService.multipart(
          '/mobile/users.php?action=profile',
          method: 'POST',
          fields: {
            if (fullName != null) 'full_name': fullName,
            if (email != null) 'email': email,
            if (phone != null) 'phone': phone,
          },
          files: {'avatar': avatarPath},
        );
      } else {
        response = await _userService.updateProfile(
          fullName: fullName,
          email: email,
          phone: phone,
        );
      }

      final treatedAsSuccess =
          response.success || _isNoChangesMessage(response.message);

      if (treatedAsSuccess && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        _user = UserModel.fromJson(data);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);
      }

      _isLoading = false;
      notifyListeners();

      if (!response.success && treatedAsSuccess) {
        return ApiResponse(
          success: true,
          data: response.data,
          message: response.message,
          errors: response.errors,
          statusCode: response.statusCode,
        );
      }

      return response;
    } catch (e) {
      _isLoading = false;
      notifyListeners();
      return ApiResponse(success: false, message: 'حدث خطأ في الاتصال: $e');
    }
  }

  Future<void> refreshUser() async {
    await _fetchUserProfile();
    notifyListeners();
  }

  /// Continue as guest - allows browsing without account
  void continueAsGuest() {
    _isGuest = true;
    _user = null;
    _token = null;
    _needsProfileCompletion = false;
    _isInitialized = true;
    notifyListeners();
  }

  Future<void> logout() async {
    _user = null;
    _token = null;
    _isGuest = false;
    _needsProfileCompletion = false;
    _apiService.setAuthToken(null);

    await _authStorage.clearToken();

    notifyListeners();
  }

  void updateUser(UserModel updatedUser) {
    _user = updatedUser;
    _needsProfileCompletion = _isProfileIncomplete(updatedUser);
    notifyListeners();
  }

  // For demo/testing without API
  Future<void> mockLogin() async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(seconds: 1));

    _user = UserModel(
      id: 1,
      fullName: 'أحمد محمد',
      phone: '+966500000000',
      email: 'ahmed@example.com',
      walletBalance: 250.0,
      points: 1200,
      membershipLevel: 'gold',
      referralCode: 'AHMED123',
      isActive: true,
      isVerified: true,
      createdAt: DateTime.now(),
      updatedAt: DateTime.now(),
    );
    _token = 'demo_token';
    _needsProfileCompletion = false;

    _isLoading = false;
    notifyListeners();
  }

  bool _resolveNeedsProfileCompletion(
    Map<String, dynamic>? data,
    UserModel? user,
  ) {
    final isLocallyIncomplete = _isProfileIncomplete(user);

    if (data != null && data['profile_completed'] != null) {
      final isProfileCompleted = _isTruthy(data['profile_completed']);
      if (isProfileCompleted) {
        return false;
      }
    }

    if (data != null && data['needs_profile_completion'] != null) {
      final needsCompletion = _isTruthy(data['needs_profile_completion']);

      if (!needsCompletion) {
        return false;
      }

      // Guard against backend false-positive flags when user data is already complete.
      return isLocallyIncomplete;
    }

    return isLocallyIncomplete;
  }

  bool _isTruthy(dynamic raw) {
    if (raw is bool) return raw;
    if (raw is num) return raw != 0;
    final normalized = raw?.toString().trim().toLowerCase() ?? '';
    return normalized == 'true' ||
        normalized == '1' ||
        normalized == 'yes' ||
        normalized == 'on';
  }

  bool _isProfileIncomplete(UserModel? user) {
    if (user == null) return true;
    final fullName = user.fullName.trim();
    return fullName.isEmpty;
  }
}

class AuthResult {
  final bool success;
  final bool isNewUser;
  final bool needsProfileCompletion;
  final String? message;

  AuthResult({
    required this.success,
    this.isNewUser = false,
    this.needsProfileCompletion = false,
    this.message,
  });
}
