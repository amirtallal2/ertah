// Complaints Service
// خدمة الشكاوى

import 'api_service.dart';

class ComplaintsService {
  final ApiService _api = ApiService();

  /// Get users complaints
  Future<ApiResponse> getComplaints() async {
    return await _api.get('/mobile/complaints.php', params: {'action': 'list'});
  }

  /// Submit a new complaint
  Future<ApiResponse> submitComplaint({
    required String subject,
    required String description,
    int? orderId,
    int? providerId,
    String? priority,
  }) async {
    final body = {
      'subject': subject,
      'description': description,
      if (orderId != null) 'order_id': orderId,
      if (providerId != null) 'provider_id': providerId,
      if ((priority ?? '').trim().isNotEmpty) 'priority': priority,
    };
    return await _api.post('/mobile/complaints.php?action=create', body: body);
  }
}
