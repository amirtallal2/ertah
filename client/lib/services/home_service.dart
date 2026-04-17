/// Home Service
/// خدمة الصفحة الرئيسية

import 'api_service.dart';

class HomeService {
  final ApiService _api = ApiService();

  Map<String, dynamic> _withLocationParams({
    Map<String, dynamic>? base,
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) {
    final params = <String, dynamic>{...(base ?? const {})};
    if (lat != null && lng != null) {
      params['lat'] = lat;
      params['lng'] = lng;
    }
    if (countryCode != null && countryCode.trim().isNotEmpty) {
      params['country_code'] = countryCode.trim().toUpperCase();
    }
    // Catalog browsing in the client should always show the published
    // production data. Coverage enforcement stays in order creation flows.
    params['allow_outside'] = 1;
    return params;
  }

  /// Get all home data (banners, categories, stores, offers, cities)
  Future<ApiResponse> getHomeData({
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    return await _api.get('/mobile/home.php', params: params);
  }

  /// Get service categories
  Future<ApiResponse> getCategories({
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    return await _api.get('/mobile/services.php?action=list', params: params);
  }

  /// Get category detail
  Future<ApiResponse> getCategoryDetail(
    int id, {
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    return await _api.get(
      '/mobile/services.php?action=detail&id=$id',
      params: params,
    );
  }

  /// Get all spare parts
  Future<ApiResponse> getSpareParts({
    double? lat,
    double? lng,
    String? countryCode,
    int? categoryId,
    List<int> serviceIds = const [],
    bool? allowOutside,
  }) async {
    final base = <String, dynamic>{};
    if (categoryId != null && categoryId > 0) {
      base['category_id'] = categoryId;
    }

    final params = _withLocationParams(
      base: base,
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    if (serviceIds.isNotEmpty) {
      params['service_ids'] = serviceIds.join(',');
    }
    return await _api.get('/mobile/spare_parts.php', params: params);
  }

  /// Get problem detail options for a category/sub-services
  Future<ApiResponse> getProblemTypes({
    required int categoryId,
    List<int> serviceIds = const [],
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      base: {'category_id': categoryId},
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    if (serviceIds.isNotEmpty) {
      params['service_ids'] = serviceIds.join(',');
    }

    return await _api.get(
      '/mobile/services.php?action=problem_types',
      params: params,
    );
  }

  /// Get featured services (admin-controlled)
  Future<ApiResponse> getFeaturedServices({
    int? limit,
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    if (limit != null && limit > 0) {
      params['limit'] = limit;
    }
    return await _api.get(
      '/mobile/services.php?action=featured',
      params: params,
    );
  }
}
