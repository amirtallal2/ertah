/// Offers Service
/// خدمة العروض

import 'api_service.dart';

class OffersService {
  final ApiService _api = ApiService();

  /// Get all offers
  Future<ApiResponse> getOffers() async {
    return await _api.get('/mobile/offers.php?action=list');
  }

  /// Get offer detail
  Future<ApiResponse> getOfferDetail(int id) async {
    return await _api.get('/mobile/offers.php?action=detail&id=$id');
  }

  /// Validate promo code
  Future<ApiResponse> validatePromoCode(
    String code, {
    double orderAmount = 0,
  }) async {
    final response = await _api.post(
      '/mobile/offers.php?action=validate-promo',
      body: {'code': code, 'order_amount': orderAmount},
    );
    if (response.success) {
      return response;
    }

    // Compatibility with underscore route on some backend versions.
    return await _api.post(
      '/mobile/offers.php?action=validate_promo',
      body: {'code': code, 'order_amount': orderAmount},
    );
  }
}
