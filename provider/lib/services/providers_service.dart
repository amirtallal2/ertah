// Providers Service
// خدمة مقدمي الخدمات

import 'api_service.dart';

class ProvidersService {
  final ApiService _api = ApiService();

  Map<String, dynamic>? _asMap(dynamic raw) {
    if (raw is! Map) return null;
    return Map<String, dynamic>.from(
      raw.map((key, value) => MapEntry(key.toString(), value)),
    );
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return <Map<String, dynamic>>[];
    return raw
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        )
        .toList();
  }

  String _normalizedLabel(Map<String, dynamic> item) {
    final ar = (item['name_ar'] ?? '').toString().trim().toLowerCase();
    final en = (item['name_en'] ?? '').toString().trim().toLowerCase();
    return '$ar|$en';
  }

  bool _isContainerSpecialty(Map<String, dynamic> item) {
    final id = int.tryParse('${item['id']}') ?? 0;
    final module = (item['special_module'] ?? '').toString().toLowerCase();
    final label = _normalizedLabel(item);
    return id == -102 ||
        module.contains('container') ||
        label.contains('container') ||
        label.contains('حاويات');
  }

  bool _isOtherServiceCategory(Map<String, dynamic> item) {
    final label = _normalizedLabel(item)
        .replaceAll('أ', 'ا')
        .replaceAll('إ', 'ا')
        .replaceAll('آ', 'ا')
        .replaceAll('ى', 'ي');
    return (label.contains('خدم') && label.contains('اخر')) ||
        label.contains('other service') ||
        label.contains('other services');
  }

  List<Map<String, dynamic>> _extractCategories(dynamic payload) {
    if (payload is List) {
      return _asMapList(payload);
    }

    final map = _asMap(payload);
    if (map == null) return <Map<String, dynamic>>[];

    final directCategories = _asMapList(map['categories']);
    if (directCategories.isNotEmpty) {
      return directCategories;
    }

    final nestedData = map['data'];
    if (nestedData is List) {
      return _asMapList(nestedData);
    }

    final nestedMap = _asMap(nestedData);
    if (nestedMap != null) {
      final nestedCategories = _asMapList(nestedMap['categories']);
      if (nestedCategories.isNotEmpty) {
        return nestedCategories;
      }
    }

    return <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _selectSpecialties(
    List<Map<String, dynamic>> raw,
  ) {
    if (raw.isEmpty) return raw;

    final items = raw
        .where((item) => !_isContainerSpecialty(item))
        .where((item) => !_isOtherServiceCategory(item))
        .where((item) => (int.tryParse('${item['id']}') ?? 0) != 0)
        .map((item) {
          final normalized = Map<String, dynamic>.from(item);
          normalized['id'] = int.tryParse('${item['id']}') ?? 0;
          normalized['parent_id'] = int.tryParse('${item['parent_id']}');
          return normalized;
        })
        .toList();

    final parentIds = items
        .map((item) => item['parent_id'])
        .whereType<int>()
        .toSet();

    final leafOrStandalone = items.where((item) {
      final id = item['id'] as int? ?? 0;
      if (id == 0) return false;
      return !parentIds.contains(id);
    }).toList();

    final seenIds = <int>{};
    final seenLabels = <String>{};
    final deduplicated = <Map<String, dynamic>>[];

    for (final item in leafOrStandalone) {
      final id = item['id'] as int;
      final label = _normalizedLabel(item);
      if (seenIds.contains(id)) continue;
      if (label != '|' && seenLabels.contains(label)) continue;

      seenIds.add(id);
      if (label != '|') {
        seenLabels.add(label);
      }
      deduplicated.add(item);
    }

    deduplicated.sort((a, b) {
      final aOrder = int.tryParse('${a['sort_order']}') ?? 0;
      final bOrder = int.tryParse('${b['sort_order']}') ?? 0;
      if (aOrder != bOrder) return aOrder.compareTo(bOrder);
      final aName = (a['name_ar'] ?? a['name_en'] ?? '').toString();
      final bName = (b['name_ar'] ?? b['name_en'] ?? '').toString();
      return aName.compareTo(bName);
    });

    return deduplicated;
  }

  List<Map<String, dynamic>> _selectRootCategories(
    List<Map<String, dynamic>> raw,
  ) {
    if (raw.isEmpty) return raw;

    final items = raw
        .where((item) => !_isContainerSpecialty(item))
        .where((item) => !_isOtherServiceCategory(item))
        .where((item) => (int.tryParse('${item['id']}') ?? 0) != 0)
        .map((item) {
          final normalized = Map<String, dynamic>.from(item);
          normalized['id'] = int.tryParse('${item['id']}') ?? 0;
          normalized['parent_id'] = int.tryParse('${item['parent_id']}');
          return normalized;
        })
        .where((item) {
          final parentId = item['parent_id'] as int?;
          return parentId == null || parentId == 0;
        })
        .toList();

    final seenIds = <int>{};
    final seenLabels = <String>{};
    final deduplicated = <Map<String, dynamic>>[];

    for (final item in items) {
      final id = item['id'] as int;
      final label = _normalizedLabel(item);
      if (seenIds.contains(id)) continue;
      if (label != '|' && seenLabels.contains(label)) continue;

      seenIds.add(id);
      if (label != '|') {
        seenLabels.add(label);
      }
      deduplicated.add(item);
    }

    deduplicated.sort((a, b) {
      final aOrder = int.tryParse('${a['sort_order']}') ?? 0;
      final bOrder = int.tryParse('${b['sort_order']}') ?? 0;
      if (aOrder != bOrder) return aOrder.compareTo(bOrder);
      final aName = (a['name_ar'] ?? a['name_en'] ?? '').toString();
      final bName = (b['name_ar'] ?? b['name_en'] ?? '').toString();
      return aName.compareTo(bName);
    });

    return deduplicated;
  }

  /// Register as provider
  Future<ApiResponse> register({
    required String fullName,
    required String phone,
    String? email,
    String? address,
    int? serviceId,
    String? experience,
    String? description,
  }) async {
    return await _api.post(
      '/mobile/providers.php?action=register',
      body: {
        'full_name': fullName,
        'phone': phone,
        if (email != null) 'email': email,
        if (address != null) 'address': address,
        if (serviceId != null) 'service_id': serviceId,
        if (experience != null) 'experience': experience,
        if (description != null) 'description': description,
      },
    );
  }

  /// Check provider registration status
  Future<ApiResponse> checkStatus(String phone) async {
    return await _api.get('/mobile/providers.php?action=status&phone=$phone');
  }

  /// Get provider profile by auth token
  Future<ApiResponse> getProfile() async {
    return await _api.get('/mobile/providers.php?action=profile');
  }

  /// Get provider financial summary and ledger.
  Future<ApiResponse> getFinancialAccount() async {
    return await _api.get('/mobile/providers.php?action=financial');
  }

  /// Update provider profile details
  Future<ApiResponse> updateProfile({
    String? fullName,
    String? email,
    String? whatsappNumber,
    String? city,
    String? country,
    String? district,
    String? locationAddress,
    double? lat,
    double? lng,
    String? bio,
    int? experienceYears,
    List<int>? categoryIds,
    String? avatarPath,
    String? residencyDocumentPath,
    String? ajeerCertificatePath,
    bool? isAvailable,
  }) async {
    final fields = <String, String>{
      if (fullName != null) 'full_name': fullName,
      if (email != null) 'email': email,
      if (whatsappNumber != null) 'whatsapp_number': whatsappNumber,
      if (city != null) 'city': city,
      if (country != null) 'country': country,
      if (district != null) 'district': district,
      if (locationAddress != null) 'location_address': locationAddress,
      if (lat != null) 'lat': '$lat',
      if (lng != null) 'lng': '$lng',
      if (bio != null) 'bio': bio,
      if (experienceYears != null) 'experience_years': '$experienceYears',
      if (categoryIds != null) 'category_ids': categoryIds.join(','),
      if (isAvailable != null) 'is_available': isAvailable ? '1' : '0',
    };

    if ((avatarPath != null && avatarPath.isNotEmpty) ||
        (residencyDocumentPath != null && residencyDocumentPath.isNotEmpty) ||
        (ajeerCertificatePath != null && ajeerCertificatePath.isNotEmpty)) {
      final files = <String, String>{};
      if (avatarPath != null && avatarPath.isNotEmpty) {
        files['avatar'] = avatarPath;
      }
      if (residencyDocumentPath != null && residencyDocumentPath.isNotEmpty) {
        files['residency_document'] = residencyDocumentPath;
      }
      if (ajeerCertificatePath != null && ajeerCertificatePath.isNotEmpty) {
        files['ajeer_certificate'] = ajeerCertificatePath;
      }

      return await _api.multipart(
        '/mobile/providers.php?action=profile',
        method: 'POST',
        fields: fields,
        files: files,
      );
    }

    return await _api.post(
      '/mobile/providers.php?action=profile',
      body: {
        if (fullName != null) 'full_name': fullName,
        if (email != null) 'email': email,
        if (whatsappNumber != null) 'whatsapp_number': whatsappNumber,
        if (city != null) 'city': city,
        if (country != null) 'country': country,
        if (district != null) 'district': district,
        if (locationAddress != null) 'location_address': locationAddress,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (bio != null) 'bio': bio,
        if (experienceYears != null) 'experience_years': experienceYears,
        if (categoryIds != null) 'category_ids': categoryIds,
        if (isAvailable != null) 'is_available': isAvailable,
      },
    );
  }

  Future<ApiResponse> saveProfileLocation({
    required String address,
    required double lat,
    required double lng,
  }) async {
    return await updateProfile(locationAddress: address, lat: lat, lng: lng);
  }

  /// Toggle provider availability
  Future<ApiResponse> setAvailability(bool isAvailable) async {
    return await _api.post(
      '/mobile/providers.php?action=availability',
      body: {'is_available': isAvailable},
    );
  }

  /// Fetch active service categories for provider selection
  Future<ApiResponse> getCategories({bool rootOnly = false}) async {
    final response = await _api.get(
      '/mobile/providers.php?action=categories',
      params: {if (rootOnly) 'root_only': 1},
    );

    if (response.success) {
      final categories = rootOnly
          ? _selectRootCategories(_extractCategories(response.data))
          : _selectSpecialties(_extractCategories(response.data));
      if (categories.isNotEmpty) {
        return ApiResponse(
          success: true,
          data: categories,
          message: response.message,
          statusCode: response.statusCode,
        );
      }
    }

    final fallback = await _api.get('/mobile/services.php?action=list');
    if (!fallback.success) {
      return response.success ? fallback : response;
    }

    final categories = rootOnly
        ? _selectRootCategories(_extractCategories(fallback.data))
        : _selectSpecialties(_extractCategories(fallback.data));
    return ApiResponse(
      success: categories.isNotEmpty,
      data: categories,
      message: categories.isNotEmpty
          ? (fallback.message ?? response.message)
          : (response.message ?? fallback.message),
      statusCode: categories.isNotEmpty
          ? (fallback.statusCode ?? response.statusCode)
          : (response.statusCode ?? fallback.statusCode),
    );
  }

  /// Request provider account deletion
  Future<ApiResponse> deleteAccount({String? reason}) async {
    final trimmed = reason?.trim() ?? '';
    return await _api.post(
      '/mobile/users.php?action=delete_account',
      body: {'confirm': true, if (trimmed.isNotEmpty) 'reason': trimmed},
    );
  }
}
