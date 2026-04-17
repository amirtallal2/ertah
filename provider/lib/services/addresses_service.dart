/// Addresses Service
/// خدمة العناوين

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
  }) async {
    return await _api.post(
      '/mobile/addresses.php?action=add',
      body: {
        'title': title,
        'address': address,
        'lat': lat,
        'lng': lng,
        if (notes != null) 'notes': notes,
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
