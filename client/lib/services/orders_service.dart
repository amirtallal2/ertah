// Orders Service
// خدمة الطلبات

import 'dart:convert';
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

  /// Create new order
  Future<ApiResponse> createOrder({
    required int categoryId,
    required String address,
    double? lat,
    double? lng,
    String? countryCode,
    String? notes,
    String? scheduledDate,
    String? scheduledTime,
    List<String>? mediaFiles,
    Map<String, dynamic>? problemDetails,
    List<int>? serviceIds,
    bool? isCustomService,
    String? customServiceTitle,
    String? customServiceDescription,
  }) async {
    final body = <String, dynamic>{
      'category_id': categoryId,
      'address': address,
      if (lat != null) 'lat': lat,
      if (lng != null) 'lng': lng,
      if (countryCode != null && countryCode.trim().isNotEmpty)
        'country_code': countryCode.trim().toUpperCase(),
      if (notes != null) 'notes': notes,
      if (scheduledDate != null) 'scheduled_date': scheduledDate,
      if (scheduledTime != null) 'scheduled_time': scheduledTime,
      if (serviceIds != null && serviceIds.isNotEmpty)
        'service_ids': serviceIds,
      if (isCustomService != null) 'is_custom_service': isCustomService,
      if (customServiceTitle != null && customServiceTitle.trim().isNotEmpty)
        'custom_service_title': customServiceTitle.trim(),
      if (customServiceDescription != null &&
          customServiceDescription.trim().isNotEmpty)
        'custom_service_description': customServiceDescription.trim(),
    };

    if (problemDetails != null) {
      body['problem_details'] = problemDetails;
    }

    if (mediaFiles != null && mediaFiles.isNotEmpty) {
      // Convert all body fields to strings for multipart fields
      final fields = body.map((key, value) {
        if (value is Map || value is List) {
          return MapEntry(key, jsonEncode(value));
        }
        return MapEntry(key, value.toString());
      });

      return await _api.multipart(
        '/mobile/orders.php?action=create',
        method: 'POST',
        fields: fields,
        files: {'media_files[]': mediaFiles},
      );
    } else {
      return await _api.post('/mobile/orders.php?action=create', body: body);
    }
  }

  /// Cancel order
  Future<ApiResponse> cancelOrder(int orderId, {String? reason}) async {
    return await _api.post(
      '/mobile/orders.php?action=cancel',
      body: {'order_id': orderId, if (reason != null) 'reason': reason},
    );
  }

  /// Rate order
  Future<ApiResponse> rateOrder({
    required int orderId,
    required int rating,
    String? comment,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=rate',
      body: {
        'order_id': orderId,
        'rating': rating,
        if (comment != null) 'comment': comment,
      },
    );
  }

  /// Pay for order
  Future<ApiResponse> payOrder({
    required int orderId,
    required String paymentMethod,
    required double amount,
    String? transactionId,
    String? promoCode,
  }) async {
    final normalizedPromoCode = promoCode?.trim().toUpperCase();
    return await _api.post(
      '/mobile/orders.php?action=pay',
      body: {
        'order_id': orderId,
        'payment_method': paymentMethod, // 'wallet' or 'card'
        'amount': amount,
        if (transactionId != null) 'transaction_id': transactionId,
        if (normalizedPromoCode != null && normalizedPromoCode.isNotEmpty)
          'promo_code': normalizedPromoCode,
      },
    );
  }

  /// Create MyFatoorah payment link
  Future<ApiResponse> executeMyFatoorahPayment({
    required int orderId,
    required double amount,
    String? promoCode,
    String? paymentMethodCode,
  }) async {
    final normalizedPromoCode = promoCode?.trim().toUpperCase();
    final normalizedMethodCode = paymentMethodCode?.trim().toLowerCase();
    return await _api.post(
      '/mobile/orders.php?action=myfatoorah_execute',
      body: {
        'order_id': orderId,
        'amount': amount,
        if (normalizedPromoCode != null && normalizedPromoCode.isNotEmpty)
          'promo_code': normalizedPromoCode,
        if (normalizedMethodCode != null && normalizedMethodCode.isNotEmpty)
          'payment_method_code': normalizedMethodCode,
      },
    );
  }

  /// Verify MyFatoorah payment status
  Future<ApiResponse> getMyFatoorahPaymentStatus({
    required int orderId,
    String? invoiceId,
    String? paymentId,
    double? amount,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=myfatoorah_status',
      body: {
        'order_id': orderId,
        if (invoiceId != null && invoiceId.isNotEmpty) 'invoice_id': invoiceId,
        if (paymentId != null && paymentId.isNotEmpty) 'payment_id': paymentId,
        if (amount != null) 'amount': amount,
      },
    );
  }

  /// Approve or Reject Invoice
  Future<ApiResponse> approveInvoice({
    required int orderId,
    required bool isApproved,
  }) async {
    return await _api.post(
      '/mobile/orders.php?action=approve_invoice',
      body: {
        'order_id': orderId,
        'approval_action': isApproved ? 'approve' : 'reject',
      },
    );
  }
}
