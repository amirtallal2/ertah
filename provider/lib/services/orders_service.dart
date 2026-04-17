// Orders Service
// خدمة الطلبات

import 'api_service.dart';

class OrdersService {
  final ApiService _api = ApiService();

  /// Get user orders
  Future<ApiResponse> getOrders({
    String? status,
    int page = 1,
    int perPage = 20,
  }) async {
    final params = <String, dynamic>{'page': page, 'per_page': perPage};
    if (status != null) params['status'] = status;

    return await _api.get('/mobile/orders.php?action=list', params: params);
  }

  /// Get order detail
  Future<ApiResponse> getOrderDetail(int id) async {
    return await _api.get('/mobile/orders.php?action=detail&id=$id');
  }

  /// Accept or reject an assigned order (Provider)
  Future<ApiResponse> respondToAssignment({
    required int orderId,
    required bool accept,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=provider_assignment_response',
      body: {'order_id': orderId, 'decision': accept ? 'accept' : 'reject'},
    );
  }

  /// Update operational order status (Provider)
  Future<ApiResponse> updateOrderStatus({
    required int orderId,
    required String status,
    double? lat,
    double? lng,
    double? accuracy,
    double? speed,
    double? heading,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=provider_update_status',
      body: {
        'order_id': orderId,
        'status': status,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (accuracy != null) 'accuracy': accuracy,
        if (speed != null) 'speed': speed,
        if (heading != null) 'heading': heading,
      },
    );
  }

  /// Update provider live location for an order
  Future<ApiResponse> updateLiveLocation({
    required int orderId,
    required double lat,
    required double lng,
    double? accuracy,
    double? speed,
    double? heading,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=provider_location_update',
      body: {
        'order_id': orderId,
        'lat': lat,
        'lng': lng,
        if (accuracy != null) 'accuracy': accuracy,
        if (speed != null) 'speed': speed,
        if (heading != null) 'heading': heading,
      },
    );
  }

  /// Start Job (Provider)
  Future<ApiResponse> startJob(int orderId) async {
    return await _api.post(
      '/mobile/orders.php?action=start_job',
      body: {'order_id': orderId},
    );
  }

  /// Complete Job (Provider)
  Future<ApiResponse> completeJob(
    int orderId, {
    String? completionProofImagePath,
  }) async {
    if (completionProofImagePath != null &&
        completionProofImagePath.trim().isNotEmpty) {
      return await _api.multipart(
        '/mobile/orders.php?action=complete_job',
        method: 'POST',
        fields: {'order_id': '$orderId'},
        files: {'completion_media_files[]': completionProofImagePath.trim()},
      );
    }

    return await _api.post(
      '/mobile/orders.php?action=complete_job',
      body: {'order_id': orderId},
    );
  }

  /// Submit Invoice (Provider)
  Future<ApiResponse> submitInvoice({
    required int orderId,
    required double laborCost,
    required double partsCost,
    String? notes,
    List<Map<String, dynamic>> spareParts = const [],
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=submit_invoice',
      body: {
        'order_id': orderId,
        'labor_cost': laborCost,
        'parts_cost': partsCost,
        if (notes != null) 'notes': notes,
        if (spareParts.isNotEmpty) 'spare_parts': spareParts,
      },
    );
  }

  /// Save estimate range for the order
  Future<ApiResponse> setEstimate({
    required int orderId,
    required double minEstimate,
    required double maxEstimate,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=set_estimate',
      body: {
        'order_id': orderId,
        'min': minEstimate,
        'max': maxEstimate,
        'min_estimate': minEstimate,
        'max_estimate': maxEstimate,
      },
    );
  }
}
