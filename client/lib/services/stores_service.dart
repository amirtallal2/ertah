/// Stores Service
/// خدمة المتاجر والمنتجات

import 'api_service.dart';

class StoresService {
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
    // Product/store browsing should remain visible even when the saved user
    // location is outside a configured service area.
    params['allow_outside'] = 1;
    return params;
  }

  /// Get all stores
  Future<ApiResponse> getStores({
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
    return await _api.get('/mobile/stores.php?action=list', params: params);
  }

  /// Get products
  Future<ApiResponse> getProducts({
    int? storeId,
    int? categoryId,
    String? search,
    int page = 1,
    int perPage = 20,
    double? lat,
    double? lng,
    String? countryCode,
    bool? allowOutside,
  }) async {
    final params = _withLocationParams(
      base: {'page': page, 'per_page': perPage},
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: allowOutside,
    );
    if (storeId != null) params['store_id'] = storeId;
    if (categoryId != null) params['category_id'] = categoryId;
    if (search != null) params['search'] = search;

    return await _api.get('/mobile/stores.php?action=products', params: params);
  }

  /// Get product categories
  Future<ApiResponse> getProductCategories({
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
    final response = await _api.get(
      '/mobile/stores.php?action=product-categories',
      params: params,
    );
    if (response.success) {
      return response;
    }

    // Compatibility with underscore route on some backend versions.
    return await _api.get(
      '/mobile/stores.php?action=product_categories',
      params: params,
    );
  }
}
