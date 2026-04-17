// User Service
// خدمة المستخدم

import 'api_service.dart';

class UserService {
  final ApiService _api = ApiService();

  /// Get user profile
  Future<ApiResponse> getProfile() async {
    return await _api.get('/mobile/users.php?action=profile');
  }

  /// Update user profile
  Future<ApiResponse> updateProfile({
    String? fullName,
    String? email,
    String? avatarPath,
  }) async {
    if (avatarPath != null) {
      return await _api.multipart(
        '/mobile/users.php?action=profile',
        method: 'POST',
        fields: {
          if (fullName != null) 'full_name': fullName,
          if (email != null) 'email': email,
        },
        files: {'avatar': avatarPath},
      );
    }

    return await _api.post(
      '/mobile/users.php?action=profile',
      body: {
        if (fullName != null) 'full_name': fullName,
        if (email != null) 'email': email,
      },
    );
  }

  /// Get user addresses
  Future<ApiResponse> getAddresses() async {
    return await _api.get('/mobile/users.php?action=addresses');
  }

  /// Add new address
  Future<ApiResponse> addAddress({
    required String address,
    String? label,
    String type = 'home',
    String? details,
    double? lat,
    double? lng,
    bool isDefault = false,
  }) async {
    return await _api.post(
      '/mobile/users.php?action=addresses',
      body: {
        'address': address,
        'type': type,
        if (label != null) 'label': label,
        if (details != null) 'details': details,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        'is_default': isDefault,
      },
    );
  }

  /// Update address
  Future<ApiResponse> updateAddress({
    required int id,
    String? address,
    String? label,
    String? type,
    String? details,
    double? lat,
    double? lng,
    bool? isDefault,
  }) async {
    return await _api.put(
      '/mobile/users.php?action=addresses&id=$id',
      body: {
        'id': id,
        if (address != null) 'address': address,
        if (label != null) 'label': label,
        if (type != null) 'type': type,
        if (details != null) 'details': details,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (isDefault != null) 'is_default': isDefault,
      },
    );
  }

  /// Delete address
  Future<ApiResponse> deleteAddress(int id) async {
    return await _api.delete('/mobile/users.php?action=addresses&id=$id');
  }

  /// Get wallet info
  Future<ApiResponse> getWallet() async {
    return await _api.get('/mobile/users.php?action=wallet');
  }

  /// Get transactions
  Future<ApiResponse> getTransactions({int page = 1, int perPage = 20}) async {
    return await _api.get(
      '/mobile/users.php?action=transactions',
      params: {'page': page, 'per_page': perPage},
    );
  }

  /// Get notifications
  Future<ApiResponse> getNotifications({int page = 1, int perPage = 20}) async {
    return await _api.get(
      '/mobile/users.php?action=notifications',
      params: {'page': page, 'per_page': perPage},
    );
  }

  /// Update push device token (OneSignal subscription ID, etc.)
  Future<ApiResponse> updateDeviceToken({
    required String deviceToken,
    String provider = 'onesignal',
  }) async {
    return await _api.post(
      '/mobile/users.php?action=device_token',
      body: {'device_token': deviceToken, 'provider': provider},
    );
  }
}
