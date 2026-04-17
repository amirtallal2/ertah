// Auth Provider
// مزود المصادقة

import 'package:flutter/material.dart';

import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/auth_storage_service.dart';
import '../services/auth_service.dart';
import '../services/providers_service.dart';

class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  String? _token;
  bool _isLoading = false;
  bool _isInitialized = false;
  bool _isGuest = false;
  bool _needsProfileCompletion = false;
  Map<String, dynamic>? _providerProfile;

  UserModel? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;
  bool get isLoggedIn => _user != null && _token != null;
  bool get isGuest => _isGuest;
  bool get isInitialized => _isInitialized;
  bool get needsProfileCompletion => _needsProfileCompletion;
  Map<String, dynamic>? get providerProfile => _providerProfile;
  String get providerStatus =>
      (_providerProfile?['status'] ??
              _providerProfile?['provider_status'] ??
              '')
          .toString()
          .trim()
          .toLowerCase();
  bool get isApprovedProvider => providerStatus == 'approved';
  bool get isAvailable {
    final raw = _providerProfile?['is_available'];
    return raw == true || raw == 1;
  }

  /// Check if user can access authenticated features (logged in, not guest)
  bool get canAccessFeatures => isLoggedIn && !_isGuest;

  final ApiService _apiService = ApiService();
  final AuthStorageService _authStorage = AuthStorageService();
  final AuthService _authService = AuthService();
  final ProvidersService _providersService = ProvidersService();

  Future<void> initialize() async {
    if (_isInitialized) return;

    _isLoading = true;

    try {
      _token = await _authStorage.readToken();

      if (_token != null) {
        _apiService.setAuthToken(_token);
        await _fetchProviderProfile();
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
      if (response.success && response.data is Map<String, dynamic>) {
        final accountType =
            (response.data as Map<String, dynamic>)['account_type']
                ?.toString()
                .toLowerCase();
        if (accountType != null &&
            accountType.isNotEmpty &&
            accountType != 'provider') {
          _isLoading = false;
          notifyListeners();
          return ApiResponse(
            success: false,
            message: 'هذا الرقم مرتبط بحساب عميل. استخدم تطبيق العميل.',
          );
        }
      }
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
        final accountType = data['account_type']?.toString().toLowerCase();
        if (accountType != null &&
            accountType.isNotEmpty &&
            accountType != 'provider') {
          _isLoading = false;
          notifyListeners();
          return AuthResult(
            success: false,
            message: 'هذا الرقم مسجل كحساب عميل. استخدم تطبيق العميل.',
          );
        }
        final userData = data['user'] is Map<String, dynamic>
            ? data['user'] as Map<String, dynamic>
            : <String, dynamic>{};

        _token = data['token']?.toString();
        _user = UserModel.fromJson(userData);
        _providerProfile = userData;
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

  Future<void> _fetchProviderProfile() async {
    try {
      final response = await _providersService.getProfile();
      if (response.success && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        _providerProfile = data;
        _user = UserModel.fromJson(data);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);
      } else if (response.isUnauthorized) {
        await logout();
      }
    } catch (e) {
      debugPrint('Fetch provider profile error: $e');
    }
  }

  Future<ApiResponse> completeProviderProfile({
    required String fullName,
    String? city,
    String? country,
    String? bio,
    int? experienceYears,
    List<int>? categoryIds,
    String? district,
    String? email,
    String? whatsappNumber,
    String? avatarPath,
    String? residencyDocumentPath,
    String? ajeerCertificatePath,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _providersService.updateProfile(
        fullName: fullName,
        city: city,
        country: country,
        district: district,
        bio: bio,
        experienceYears: experienceYears,
        categoryIds: categoryIds,
        email: email,
        whatsappNumber: whatsappNumber,
        avatarPath: avatarPath,
        residencyDocumentPath: residencyDocumentPath,
        ajeerCertificatePath: ajeerCertificatePath,
      );

      if (response.success && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        _providerProfile = data;
        _user = UserModel.fromJson(data);
        _needsProfileCompletion = _resolveNeedsProfileCompletion(data, _user);
      }

      _isLoading = false;
      notifyListeners();
      return response;
    } catch (e) {
      _isLoading = false;
      notifyListeners();
      return ApiResponse(success: false, message: 'تعذر تحديث الملف الشخصي');
    }
  }

  Future<ApiResponse> setAvailability(bool isAvailable) async {
    final previous = _providerProfile == null
        ? null
        : Map<String, dynamic>.from(_providerProfile!);

    if (_providerProfile != null) {
      _providerProfile!['is_available'] = isAvailable;
      notifyListeners();
    }

    final response = await _providersService.setAvailability(isAvailable);
    if (!response.success && previous != null) {
      _providerProfile = previous;
      notifyListeners();
    }
    return response;
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
        final accountType = data['account_type']?.toString().toLowerCase();
        if (accountType != null &&
            accountType.isNotEmpty &&
            accountType != 'provider') {
          _isLoading = false;
          notifyListeners();
          return false;
        }
        _token = data['token']?.toString();
        _user = UserModel.fromJson(data['user'] ?? <String, dynamic>{});
        _needsProfileCompletion = true;

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

  Future<void> refreshUser() async {
    await _fetchProviderProfile();
    notifyListeners();
  }

  /// Continue as guest - allows browsing without account
  void continueAsGuest() {
    _isGuest = true;
    _user = null;
    _token = null;
    _needsProfileCompletion = false;
    _providerProfile = null;
    _isInitialized = true;
    notifyListeners();
  }

  Future<void> logout() async {
    _user = null;
    _token = null;
    _isGuest = false;
    _needsProfileCompletion = false;
    _providerProfile = null;
    _apiService.setAuthToken(null);

    await _authStorage.clearToken();

    notifyListeners();
  }

  void updateUser(UserModel updatedUser) {
    _user = updatedUser;
    _needsProfileCompletion = _resolveNeedsProfileCompletion(
      _providerProfile,
      _user,
    );
    notifyListeners();
  }

  bool _isProviderProfileIncomplete(
    Map<String, dynamic>? profile,
    UserModel? user,
  ) {
    final fullName = ((profile?['full_name'] ?? user?.fullName) ?? '')
        .toString()
        .trim();
    final avatar = ((profile?['avatar'] ?? user?.avatar) ?? '')
        .toString()
        .trim();
    final residencyDocument =
        ((profile?['residency_document_path'] ??
                    profile?['residency_document']) ??
                '')
            .toString()
            .trim();
    final hasCategory = _extractProviderCategoryIds(
      profile?['category_ids'],
    ).isNotEmpty;
    final avatarNormalized = avatar.toLowerCase();
    final avatarPath = avatarNormalized
        .replaceAll('\\', '/')
        .replaceAll(RegExp(r'^/+|/+$'), '');
    final residencyNormalized = residencyDocument.toLowerCase();

    return fullName.isEmpty ||
        avatar.isEmpty ||
        avatarNormalized == 'null' ||
        avatarNormalized == 'undefined' ||
        avatarPath == 'default-provider.png' ||
        avatarPath.endsWith('/default-provider.png') ||
        residencyDocument.isEmpty ||
        residencyNormalized == 'null' ||
        residencyNormalized == 'undefined' ||
        !hasCategory;
  }

  bool _resolveNeedsProfileCompletion(
    Map<String, dynamic>? data,
    UserModel? user,
  ) {
    final isLocallyIncomplete = _isProviderProfileIncomplete(data, user);

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

  List<int> _extractProviderCategoryIds(dynamic raw) {
    if (raw is List) {
      return raw
          .map((item) => int.tryParse(item.toString()) ?? 0)
          .where((id) => id != 0)
          .toList();
    }

    final normalized = raw?.toString().trim() ?? '';
    if (normalized.isEmpty) {
      return const <int>[];
    }

    return normalized
        .split(',')
        .map((item) => int.tryParse(item.trim()) ?? 0)
        .where((id) => id != 0)
        .toList();
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
      membershipLevel: 'provider',
      referralCode: null,
      isActive: true,
      isVerified: true,
      createdAt: DateTime.now(),
      updatedAt: DateTime.now(),
    );
    _providerProfile = {
      'is_available': true,
      'profile_completed': true,
      'needs_profile_completion': false,
    };
    _needsProfileCompletion = false;
    _token = 'demo_token';

    _isLoading = false;
    notifyListeners();
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
