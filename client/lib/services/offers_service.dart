// Offers Service
// خدمة العروض

import 'api_service.dart';

class OffersService {
  final ApiService _api = ApiService();

  List<dynamic> _extractOffersList(dynamic payload) {
    if (payload is List) {
      return payload;
    }
    if (payload is Map) {
      final map = Map<String, dynamic>.from(
        payload.map((key, value) => MapEntry(key.toString(), value)),
      );
      final offers = map['offers'];
      if (offers is List) {
        return offers;
      }
      final data = map['data'];
      if (data is List) {
        return data;
      }
    }
    return const [];
  }

  Map<String, dynamic>? _extractOfferDetail(dynamic payload) {
    if (payload is Map) {
      final map = Map<String, dynamic>.from(
        payload.map((key, value) => MapEntry(key.toString(), value)),
      );
      final offer = map['offer'];
      if (offer is Map) {
        return Map<String, dynamic>.from(
          offer.map((key, value) => MapEntry(key.toString(), value)),
        );
      }
      return map;
    }
    if (payload is List && payload.isNotEmpty && payload.first is Map) {
      final first = payload.first as Map;
      return Map<String, dynamic>.from(
        first.map((key, value) => MapEntry(key.toString(), value)),
      );
    }
    return null;
  }

  Map<String, dynamic> _withLocationParams({
    Map<String, dynamic>? base,
    double? lat,
    double? lng,
    String? countryCode,
  }) {
    final params = <String, dynamic>{...(base ?? const {})};
    if (lat != null && lng != null) {
      params['lat'] = lat;
      params['lng'] = lng;
    }
    if (countryCode != null && countryCode.trim().isNotEmpty) {
      params['country_code'] = countryCode.trim().toUpperCase();
    }
    return params;
  }

  /// Get all offers
  Future<ApiResponse> getOffers({
    double? lat,
    double? lng,
    String? countryCode,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
    );
    final response = await _api.get(
      '/mobile/offers.php?action=list',
      params: params,
    );
    if (!response.success) {
      return response;
    }

    return ApiResponse(
      success: true,
      data: _extractOffersList(response.data),
      message: response.message,
      statusCode: response.statusCode,
    );
  }

  /// Get offer detail
  Future<ApiResponse> getOfferDetail(
    int id, {
    double? lat,
    double? lng,
    String? countryCode,
  }) async {
    final params = _withLocationParams(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
    );
    final response = await _api.get(
      '/mobile/offers.php?action=detail&id=$id',
      params: params,
    );
    if (!response.success) {
      return response;
    }

    return ApiResponse(
      success: true,
      data: _extractOfferDetail(response.data),
      message: response.message,
      statusCode: response.statusCode,
    );
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
