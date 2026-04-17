// Addresses Service
// خدمة العناوين

import 'api_service.dart';

class AddressesService {
  final ApiService _api = ApiService();

  /// Get user addresses
  Future<ApiResponse> getAddresses() async {
    return await _api.get('/mobile/addresses.php?action=list');
  }

  /// Add new address
  Future<ApiResponse> addAddress({
    required String title,
    required String address,
    required double lat,
    required double lng,
    String? notes,
    String? countryCode,
    String? cityName,
    String? villageName,
  }) async {
    return await _api.post(
      '/mobile/addresses.php?action=add',
      body: {
        'title': title,
        'address': address,
        'lat': lat,
        'lng': lng,
        if (notes != null) 'notes': notes,
        if (countryCode != null && countryCode.trim().isNotEmpty)
          'country_code': countryCode.trim().toUpperCase(),
        if (cityName != null && cityName.trim().isNotEmpty)
          'city_name': cityName.trim(),
        if (villageName != null && villageName.trim().isNotEmpty)
          'village_name': villageName.trim(),
      },
    );
  }

  /// Delete address
  Future<ApiResponse> deleteAddress(int id) async {
    return await _api.post(
      '/mobile/addresses.php?action=delete',
      body: {'address_id': id},
    );
  }
}
