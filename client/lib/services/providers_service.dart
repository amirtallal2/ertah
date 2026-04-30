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

  /// Get available provider categories from admin settings
  Future<ApiResponse> getCategories() async {
    final response = await _api.get('/mobile/providers.php?action=categories');
    if (!response.success) {
      return response;
    }

    final categories = _selectSpecialties(_extractCategories(response.data));
    return ApiResponse(
      success: true,
      data: categories,
      message: response.message,
      statusCode: response.statusCode,
    );
  }
}
