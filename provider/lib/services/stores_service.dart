/// Stores Service
/// خدمة المتاجر والمنتجات

import 'api_service.dart';

class StoresService {
  final ApiService _api = ApiService();

  /// Get all stores
  Future<ApiResponse> getStores() async {
    return await _api.get('/mobile/stores.php?action=list');
  }

  /// Get products
  Future<ApiResponse> getProducts({
    int? storeId,
    int? categoryId,
    String? search,
    int page = 1,
    int perPage = 20,
  }) async {
    final params = <String, dynamic>{'page': page, 'per_page': perPage};
    if (storeId != null) params['store_id'] = storeId;
    if (categoryId != null) params['category_id'] = categoryId;
    if (search != null) params['search'] = search;

    return await _api.get('/mobile/stores.php?action=products', params: params);
  }

  /// Get product categories
  Future<ApiResponse> getProductCategories() async {
    final response = await _api.get(
      '/mobile/stores.php?action=product-categories',
    );
    if (response.success) {
      return response;
    }

    // Compatibility with underscore route on some backend versions.
    return await _api.get('/mobile/stores.php?action=product_categories');
  }
}
