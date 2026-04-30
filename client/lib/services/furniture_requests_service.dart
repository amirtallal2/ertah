// Furniture Requests Service
// خدمة طلبات نقل العفش

import 'api_service.dart';

class FurnitureRequestsService {
  final ApiService _api = ApiService();

  Future<ApiResponse> getConfig() async {
    return await _api.get('/mobile/furniture_requests.php?action=config');
  }

  Future<ApiResponse> createRequest({
    int? serviceId,
    int? areaId,
    required String pickupAddress,
    required String dropoffAddress,
    String? pickupCity,
    String? dropoffCity,
    String? moveDate,
    String? preferredTime,
    String? notes,
    Map<String, dynamic>? details,
    double? estimatedWeightKg,
    double? estimatedDistanceMeters,
    List<int>? serviceIds,
    List<Map<String, dynamic>>? selectedServices,
  }) async {
    final body = <String, dynamic>{
      if (serviceId != null && serviceId > 0) 'service_id': serviceId,
      if (areaId != null && areaId > 0) 'area_id': areaId,
      'pickup_address': pickupAddress,
      'dropoff_address': dropoffAddress,
      if (pickupCity != null && pickupCity.trim().isNotEmpty)
        'pickup_city': pickupCity.trim(),
      if (dropoffCity != null && dropoffCity.trim().isNotEmpty)
        'dropoff_city': dropoffCity.trim(),
      if (moveDate != null && moveDate.trim().isNotEmpty)
        'move_date': moveDate.trim(),
      if (preferredTime != null && preferredTime.trim().isNotEmpty)
        'preferred_time': preferredTime.trim(),
      if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      if (details != null) 'details': details,
      if (estimatedWeightKg != null && estimatedWeightKg > 0)
        'estimated_weight_kg': estimatedWeightKg,
      if (estimatedDistanceMeters != null && estimatedDistanceMeters > 0)
        'estimated_distance_meters': estimatedDistanceMeters,
      if (serviceIds != null && serviceIds.isNotEmpty)
        'service_ids': serviceIds,
      if (selectedServices != null && selectedServices.isNotEmpty)
        'selected_services': selectedServices,
    };

    return await _api.post(
      '/mobile/furniture_requests.php?action=create',
      body: body,
    );
  }

  Future<ApiResponse> getMyRequests() async {
    return await _api.get('/mobile/furniture_requests.php?action=list');
  }

  Future<ApiResponse> getRequestDetail(int id) async {
    return await _api.get(
      '/mobile/furniture_requests.php?action=detail&id=$id',
    );
  }
}
