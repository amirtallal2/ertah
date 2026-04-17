/// Complaints Service
/// خدمة الشكاوى

import 'api_service.dart';

class ComplaintsService {
  final ApiService _api = ApiService();

  /// Get users complaints
  Future<ApiResponse> getComplaints() async {
    return await _api.get('/mobile/complaints.php?action=list');
  }

  /// Submit a new complaint
  Future<ApiResponse> submitComplaint({
    required String title,
    required String description,
    String? orderId,
  }) async {
    final body = {
      'title': title,
      'description': description,
      if (orderId != null) 'order_id': orderId,
    };
    return await _api.post('/mobile/complaints.php?action=create', body: body);
  }
}
