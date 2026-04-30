import 'dart:async';
import 'dart:convert';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/models.dart';
import '../services/app_localizations.dart';
import '../services/orders_service.dart';
import '../utils/saudi_riyal_icon.dart';
import 'payment_screen.dart';
import 'service_rating_screen.dart';

class OrderTrackingScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  final String orderId;
  final VoidCallback onBackToHome;

  const OrderTrackingScreen({
    super.key,
    required this.service,
    required this.orderId,
    required this.onBackToHome,
  });

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  final OrdersService _ordersService = OrdersService();

  bool _isLoading = true;
  bool _isActionRunning = false;
  bool _isRefreshingOrder = false;
  Map<String, dynamic>? _order;
  Timer? _autoRefreshTimer;
  final Map<String, String> _liveLocationAddressCache = {};
  String? _liveLocationAddress;
  String? _liveLocationAddressKey;
  int _liveLocationLookupToken = 0;
  final Map<String, String> _orderAddressCache = {};
  String? _orderAddressResolved;
  int _orderAddressLookupToken = 0;

  @override
  void initState() {
    super.initState();
    _fetchOrderDetails();
  }

  @override
  void dispose() {
    _autoRefreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _fetchOrderDetails() async {
    if (_isRefreshingOrder) return;
    _isRefreshingOrder = true;

    try {
      final response = await _ordersService.getOrderDetail(
        int.tryParse(widget.orderId) ?? 0,
      );

      if (!mounted) return;
      setState(() {
        _order = response.success && response.data is Map
            ? Map<String, dynamic>.from(
                (response.data as Map).map(
                  (key, value) => MapEntry(key.toString(), value),
                ),
              )
            : null;
        _isLoading = false;
      });
      _resolveLiveLocationAddress();
      _resolveOrderAddress();
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    } finally {
      _isRefreshingOrder = false;
      _syncAutoRefreshTimer();
    }
  }

  bool _shouldAutoRefreshOrder() {
    final status = _normalize(_order?['status']);
    return const {
      'pending',
      'assigned',
      'accepted',
      'on_the_way',
      'arrived',
      'in_progress',
    }.contains(status);
  }

  void _syncAutoRefreshTimer() {
    final shouldRefresh = _shouldAutoRefreshOrder();
    if (!shouldRefresh) {
      _autoRefreshTimer?.cancel();
      _autoRefreshTimer = null;
      return;
    }

    if (_autoRefreshTimer != null) {
      return;
    }

    _autoRefreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!mounted) return;
      if (!_shouldAutoRefreshOrder()) {
        _autoRefreshTimer?.cancel();
        _autoRefreshTimer = null;
        return;
      }
      _fetchOrderDetails();
    });
  }

  Future<void> _submitInvoiceDecision({
    required bool approved,
    String? successMessage,
  }) async {
    if (_isActionRunning) return;

    var openPaymentAfterApprove = false;

    setState(() => _isActionRunning = true);
    try {
      final response = await _ordersService.approveInvoice(
        orderId: int.tryParse(widget.orderId) ?? 0,
        isApproved: approved,
      );

      if (!mounted) return;

      if (response.success && approved) {
        openPaymentAfterApprove = true;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.success
                ? (successMessage ??
                      (approved
                          ? context.tr('invoice_approved_success')
                          : context.tr('invoice_rejected_success')))
                : (response.message ?? context.tr('connection_error')),
          ),
          backgroundColor: response.success ? Colors.green : Colors.red,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('connection_error')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isActionRunning = false);
      }
      await _fetchOrderDetails();

      if (openPaymentAfterApprove && mounted) {
        await _openPaymentScreen(autoStartCardPayment: true);
      }
    }
  }

  Future<void> _requestInvoiceAdjustment() async {
    final shouldSend = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('order_tracking_invoice_adjust_title')),
        content: Text(context.tr('order_tracking_invoice_adjust_message')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.tr('cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.tr('submit_request')),
          ),
        ],
      ),
    );

    if (shouldSend == true) {
      if (!mounted) return;
      await _submitInvoiceDecision(
        approved: false,
        successMessage: context.tr('order_tracking_invoice_adjust_success'),
      );
    }
  }

  Future<void> _openPaymentScreen({bool autoStartCardPayment = false}) async {
    if (_order == null) return;

    final amount = _payableAmount;

    if (amount <= 0) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('order_tracking_no_payable_amount'))),
      );
      return;
    }

    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PaymentScreen(
          amount: amount,
          serviceName: _categoryName,
          orderId: _orderId,
          providerName: _providerName,
          autoStartCardPayment: autoStartCardPayment,
          onPaymentSuccess: () {
            Navigator.of(context).pop();
          },
        ),
      ),
    );

    await _fetchOrderDetails();
  }

  Future<void> _openRatingScreen() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ServiceRatingScreen(
          service: widget.service,
          providerName: _providerName,
          orderNumber: widget.orderId,
          onSubmit: () {
            Navigator.pop(context);
          },
        ),
      ),
    );

    await _fetchOrderDetails();
  }

  Future<void> _cancelOrder() async {
    if (!_canCancelOrder || _isActionRunning) return;

    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('order_tracking_cancel_title')),
        content: Text(context.tr('order_tracking_cancel_message')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.tr('cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: Text(context.tr('order_tracking_confirm_cancel')),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isActionRunning = true);
    try {
      final response = await _ordersService.cancelOrder(_orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.success
                ? (response.message ?? context.tr('order_tracking_cancelled'))
                : (response.message ??
                      context.tr('order_tracking_cancel_failed')),
          ),
          backgroundColor: response.success ? Colors.green : Colors.red,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_tracking_cancel_failed_now')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isActionRunning = false);
      }
      await _fetchOrderDetails();
    }
  }

  Future<void> _callNumber(String rawPhone) async {
    final cleaned = rawPhone.replaceAll(RegExp(r'[^0-9+]'), '');
    if (cleaned.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('phone_not_available'))),
      );
      return;
    }

    final uri = Uri.parse('tel:$cleaned');
    final opened = await launchUrl(uri);
    if (!opened && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('open_dialer_failed'))));
    }
  }

  String _normalizeWhatsAppPhone(String rawPhone) {
    var value = rawPhone.replaceAll(RegExp(r'[^0-9+]'), '').trim();
    if (value.startsWith('+')) {
      value = value.substring(1);
    }
    return value;
  }

  Future<void> _openWhatsApp(String rawPhone) async {
    final cleaned = _normalizeWhatsAppPhone(rawPhone);
    if (cleaned.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_tracking_whatsapp_not_available')),
        ),
      );
      return;
    }

    final uri = Uri.parse('https://wa.me/$cleaned');
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_tracking_open_whatsapp_failed')),
        ),
      );
    }
  }

  Future<void> _openMapLocation(double lat, double lng) async {
    final uri = Uri.parse('https://www.google.com/maps?q=$lat,$lng');
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('order_tracking_open_map_failed'))),
      );
    }
  }

  Future<void> _openDirections(
    double fromLat,
    double fromLng,
    double toLat,
    double toLng,
  ) async {
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1&origin=$fromLat,$fromLng&destination=$toLat,$toLng&travelmode=driving',
    );
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_tracking_open_directions_failed')),
        ),
      );
    }
  }

  String get _status => _normalize(_order?['status']);
  String get _rawInvoiceStatus => _normalize(_order?['invoice_status']);

  String get _invoiceStatus => _normalizeInvoiceStatus(_rawInvoiceStatus);
  String get _paymentStatus => _normalize(_order?['payment_status']);

  int get _orderId => int.tryParse('${_order?['id'] ?? 0}') ?? 0;

  String _firstNonEmpty(List<String> values) {
    for (final value in values) {
      final trimmed = value.trim();
      if (trimmed.isNotEmpty) {
        return trimmed;
      }
    }
    return '';
  }

  String _humanizeToken(String value) {
    return value.replaceAll('_', ' ').trim();
  }

  String get _categoryName {
    final localeCode = Localizations.localeOf(context).languageCode;
    final apiAr = _firstNonEmpty([
      (_order?['display_service_name'] ?? '').toString(),
      (_order?['category_name_ar'] ?? '').toString(),
      (_order?['category_name'] ?? '').toString(),
    ]);
    final apiEn = (_order?['category_name_en'] ?? '').toString().trim();
    final apiUr = (_order?['category_name_ur'] ?? '').toString().trim();

    final modelAr = widget.service.nameAr.trim();
    final modelEn = (widget.service.nameEn ?? '').trim();
    final fallback = context.tr('not_specified');

    if (localeCode == 'en') {
      return _firstNonEmpty([apiEn, modelEn, apiUr, apiAr, modelAr, fallback]);
    }
    if (localeCode == 'ur') {
      return _firstNonEmpty([apiUr, apiEn, modelEn, apiAr, modelAr, fallback]);
    }
    return _firstNonEmpty([apiAr, modelAr, apiUr, apiEn, modelEn, fallback]);
  }

  String _localizedProblemTypeToken(String rawToken) {
    final token = _normalize(
      rawToken,
    ).replaceAll('-', '_').replaceAll(' ', '_');
    if (token.isEmpty) return '';

    final localeCode = Localizations.localeOf(context).languageCode;
    final isEn = localeCode == 'en';
    final isUr = localeCode == 'ur';

    switch (token) {
      case 'container_rental':
        return isEn
            ? 'Container Rental Service'
            : isUr
            ? 'کنٹینر کرایہ سروس'
            : 'طلب خدمة الحاويات';
      case 'furniture_moving':
        return isEn
            ? 'Furniture Moving Service'
            : isUr
            ? 'فرنیچر منتقلی سروس'
            : 'طلب نقل العفش';
      case 'spare_parts_with_installation':
        return isEn
            ? 'Spare Parts With Installation'
            : isUr
            ? 'اسپیئر پارٹس مع تنصیب'
            : 'قطع غيار مع التركيب';
      case 'spare_parts_order':
      case 'spare_parts':
        return isEn
            ? 'Spare Parts Request'
            : isUr
            ? 'اسپیئر پارٹس کی درخواست'
            : 'طلب قطع غيار';
      default:
        return '';
    }
  }

  String get _providerName {
    final fromApi = (_order?['provider_name'] ?? '').toString().trim();
    if (fromApi.isNotEmpty) return fromApi;
    return context.tr('service_provider');
  }

  double get _invoiceItemsTotal {
    final items = _invoiceItems();
    double sum = 0;
    for (final item in items) {
      final lineTotal = _toDouble(item['total_price']);
      if (lineTotal > 0) {
        sum += lineTotal;
        continue;
      }
      final unitPrice = _toDouble(item['unit_price']);
      final qty = int.tryParse('${item['quantity'] ?? 1}') ?? 1;
      if (unitPrice > 0) {
        sum += unitPrice * qty;
      }
    }
    if (sum <= 0) return 0;
    return double.parse(sum.toStringAsFixed(2));
  }

  double get _containerRequestTotal {
    if (!_isContainerOrder) return 0;
    final details = _problemDetails;
    final raw = details['container_request'];
    if (raw is! Map) return 0;
    final container = Map<String, dynamic>.from(raw);

    final finalPrice = _toDouble(container['final_price']);
    if (finalPrice > 0) return finalPrice;
    final estimatedPrice = _toDouble(container['estimated_price']);
    if (estimatedPrice > 0) return estimatedPrice;

    final dailyPrice = _toDouble(container['daily_price']);
    final durationDays = _toDouble(container['duration_days']);
    final quantity = _toDouble(container['quantity']);
    final deliveryFee = _toDouble(container['delivery_fee']);
    if (dailyPrice > 0) {
      final days = durationDays > 0 ? durationDays : 1;
      final qty = quantity > 0 ? quantity : 1;
      return (dailyPrice * days * qty) + deliveryFee;
    }

    final pricePerKg = _toDouble(container['price_per_kg']);
    final pricePerMeter = _toDouble(container['price_per_meter']);
    final weightKg = _toDouble(container['estimated_weight_kg']);
    final distanceMeters = _toDouble(container['estimated_distance_meters']);
    final minimumCharge = _toDouble(container['minimum_charge']);
    double calculated =
        (pricePerKg * weightKg) + (pricePerMeter * distanceMeters);
    if (calculated > 0) {
      if (minimumCharge > 0 && calculated < minimumCharge) {
        calculated = minimumCharge;
      }
      return calculated + deliveryFee;
    }

    return 0;
  }

  double get _derivedInvoiceTotal {
    final itemsTotal = _invoiceItemsTotal;
    if (itemsTotal > 0) return itemsTotal;
    final containerTotal = _containerRequestTotal;
    if (containerTotal > 0) return containerTotal;
    return 0;
  }

  bool get _shouldTreatInvoiceAsPending {
    final normalized = _rawInvoiceStatus;
    if (normalized.isNotEmpty && normalized != 'none' && normalized != 'null') {
      return false;
    }
    if (!(_isSparePartsWithInstallation || _isContainerOrder)) {
      return false;
    }
    return _derivedInvoiceTotal > 0;
  }

  String _normalizeInvoiceStatus(String rawStatus) {
    final normalized = rawStatus.isEmpty || rawStatus == 'null'
        ? 'none'
        : rawStatus;
    if (normalized == 'none' && _shouldTreatInvoiceAsPending) {
      return 'pending';
    }
    return normalized;
  }

  double get _laborCost => _toDouble(_order?['labor_cost']);
  double get _partsCost => _toDouble(_order?['parts_cost']);
  double get _totalAmount {
    final rawTotal = _toDouble(_order?['total_amount']);
    if (rawTotal > 0) return rawTotal;
    final derivedTotal = _derivedInvoiceTotal;
    if (derivedTotal > 0) return derivedTotal;
    return 0;
  }

  double get _payableAmount {
    if (_totalAmount > 0) return _totalAmount;
    final fallback = _laborCost + _partsCost;
    return fallback > 0 ? fallback : 0;
  }

  double get _inspectionFee => _toDouble(_order?['inspection_fee']);
  double? get _minEstimate => _toNullableDouble(_order?['min_estimate']);
  double? get _maxEstimate => _toNullableDouble(_order?['max_estimate']);

  bool get _isRated => _order?['is_rated'] == true || _order?['is_rated'] == 1;

  Map<String, dynamic> get _problemDetails {
    final raw = _order?['problem_details'];
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }
    if (raw is String) {
      final trimmed = raw.trim();
      if (trimmed.isNotEmpty) {
        try {
          final decoded = jsonDecode(trimmed);
          if (decoded is Map) {
            return Map<String, dynamic>.from(decoded);
          }
        } catch (_) {
          // ignore invalid JSON
        }
      }
    }
    return {};
  }

  String _inspectionPolicyDetails() {
    final rawPolicy = _problemDetails['inspection_policy'];
    if (rawPolicy is! Map) return '';
    final policy = Map<String, dynamic>.from(rawPolicy);
    final localeCode = Localizations.localeOf(context).languageCode;
    if (localeCode == 'en') {
      return _firstNonEmpty([
        (policy['details_en'] ?? '').toString(),
        (policy['details_ar'] ?? '').toString(),
        (policy['details_ur'] ?? '').toString(),
      ]);
    }
    if (localeCode == 'ur') {
      return _firstNonEmpty([
        (policy['details_ur'] ?? '').toString(),
        (policy['details_en'] ?? '').toString(),
        (policy['details_ar'] ?? '').toString(),
      ]);
    }
    return _firstNonEmpty([
      (policy['details_ar'] ?? '').toString(),
      (policy['details_en'] ?? '').toString(),
      (policy['details_ur'] ?? '').toString(),
    ]);
  }

  bool get _isSparePartsWithInstallation {
    final details = _problemDetails;
    if (details.isEmpty) return false;

    final rawToken =
        (details['type'] ??
                details['module'] ??
                details['special_module'] ??
                '')
            .toString();
    final token = _normalize(
      rawToken,
    ).replaceAll('-', '_').replaceAll(' ', '_');
    if (token == 'spare_parts_with_installation') {
      return true;
    }
    final pricingMode = _normalize(
      details['pricing_mode'],
    ).replaceAll('-', '_').replaceAll(' ', '_');
    if (pricingMode == 'with_installation' && token.contains('spare_parts')) {
      return true;
    }
    final requiresInstallation = details['requires_installation'];
    if (requiresInstallation == true ||
        requiresInstallation == 1 ||
        requiresInstallation == '1') {
      return true;
    }

    if (token.contains('spare_parts') && token.contains('install')) {
      return true;
    }

    return false;
  }

  bool get _isContainerOrder {
    final details = _problemDetails;
    if (details.isEmpty) return false;
    if (details['container_request'] != null) return true;

    final rawToken =
        (details['type'] ??
                details['module'] ??
                details['special_module'] ??
                '')
            .toString();
    final token = _normalize(rawToken);
    return token == 'container_rental' || token.contains('container');
  }

  Map<String, dynamic> get _containerStoreInfo {
    Map<String, dynamic> normalizeMap(dynamic raw) {
      if (raw is! Map) return <String, dynamic>{};
      return Map<String, dynamic>.from(
        raw.map((key, value) => MapEntry(key.toString(), value)),
      );
    }

    final topLevel = normalizeMap(_order?['container_store']);
    if (topLevel.isNotEmpty) return topLevel;

    final details = _problemDetails;
    final detailsStore = normalizeMap(details['container_store']);
    if (detailsStore.isNotEmpty) return detailsStore;

    final container = normalizeMap(details['container_request']);
    final assignedStore = normalizeMap(container['assigned_container_store']);
    if (assignedStore.isNotEmpty) return assignedStore;

    final nestedStore = normalizeMap(container['container_store']);
    if (nestedStore.isNotEmpty) return nestedStore;

    final storeId =
        int.tryParse(
          '${container['container_store_id'] ?? details['container_store_id'] ?? 0}',
        ) ??
        0;
    final storeName = _firstNonEmpty([
      (container['container_store_name'] ?? '').toString(),
      (details['container_store_name'] ?? '').toString(),
    ]);
    if (storeId <= 0 && storeName.isEmpty) {
      return <String, dynamic>{};
    }

    return {
      'store_id': storeId,
      'id': storeId,
      'name': storeName,
      'name_ar': storeName,
      'phone': (container['container_store_phone'] ?? '').toString(),
      'whatsapp': (container['container_store_whatsapp'] ?? '').toString(),
      'email': (container['container_store_email'] ?? '').toString(),
      'address': (container['container_store_address'] ?? '').toString(),
      'logo': (container['container_store_logo'] ?? '').toString(),
    };
  }

  String _containerStoreName(Map<String, dynamic> store) {
    final localeCode = Localizations.localeOf(context).languageCode;
    if (localeCode == 'en') {
      return _firstNonEmpty([
        (store['name_en'] ?? '').toString(),
        (store['name'] ?? '').toString(),
        (store['name_ar'] ?? '').toString(),
        (store['name_ur'] ?? '').toString(),
      ]);
    }
    if (localeCode == 'ur') {
      return _firstNonEmpty([
        (store['name_ur'] ?? '').toString(),
        (store['name_en'] ?? '').toString(),
        (store['name'] ?? '').toString(),
        (store['name_ar'] ?? '').toString(),
      ]);
    }
    return _firstNonEmpty([
      (store['name_ar'] ?? '').toString(),
      (store['name'] ?? '').toString(),
      (store['name_en'] ?? '').toString(),
      (store['name_ur'] ?? '').toString(),
    ]);
  }

  bool get _canPayNow {
    if (_paymentStatus == 'paid' || _payableAmount <= 0) {
      return false;
    }
    final allowPendingInvoice =
        _isSparePartsWithInstallation || _isContainerOrder;
    return _invoiceStatus == 'approved' ||
        (allowPendingInvoice && _invoiceStatus == 'pending');
  }

  bool get _hasPendingInvoice => _rawInvoiceStatus == 'pending';

  bool get _hasProvider =>
      (_order?['provider_name'] ?? '').toString().trim().isNotEmpty;

  bool get _canCancelOrder {
    final isCancelableStatus = ['pending', 'assigned'].contains(_status);
    if (!isCancelableStatus) return false;

    final live = _order?['provider_live_location'];
    final hasLiveLocation =
        live is Map &&
        _toNullableDouble(live['lat']) != null &&
        _toNullableDouble(live['lng']) != null;

    return !hasLiveLocation;
  }

  bool get _isConfirmationDone {
    final confirmationStatus = _normalize(_order?['confirmation_status']);
    final confirmedAt = (_order?['confirmed_at'] ?? '').toString().trim();
    return confirmationStatus == 'confirmed' ||
        confirmedAt.isNotEmpty ||
        ['on_the_way', 'arrived', 'in_progress', 'completed'].contains(_status);
  }

  bool get _isOperationsReviewed {
    return _minEstimate != null ||
        _maxEstimate != null ||
        [
          'assigned',
          'accepted',
          'on_the_way',
          'arrived',
          'in_progress',
          'completed',
        ].contains(_status);
  }

  String _normalize(dynamic value) {
    return (value ?? '').toString().trim().toLowerCase();
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  double? _toNullableDouble(dynamic value) {
    if (value == null) return null;
    final parsed = double.tryParse(value.toString());
    return parsed;
  }

  String _latLngKey(double lat, double lng) {
    return '${lat.toStringAsFixed(6)},${lng.toStringAsFixed(6)}';
  }

  bool _looksLikeCoordinates(String value) {
    final text = value.trim();
    if (text.isEmpty) return false;
    final coordinatePattern = RegExp(
      r'^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$',
    );
    return coordinatePattern.hasMatch(text);
  }

  String _composeAddressFromPlacemark(Placemark place) {
    final parts = <String>[];

    void addPart(String? value) {
      final trimmed = (value ?? '').trim();
      if (trimmed.isNotEmpty && !parts.contains(trimmed)) {
        parts.add(trimmed);
      }
    }

    addPart(place.street);
    addPart(place.subLocality);
    addPart(place.locality);
    addPart(place.administrativeArea);
    addPart(place.country);

    return parts.join('، ');
  }

  Future<void> _resolveLiveLocationAddress() async {
    final liveLocation = _order?['provider_live_location'];
    if (liveLocation is! Map) {
      if (!mounted) return;
      setState(() {
        _liveLocationAddress = null;
        _liveLocationAddressKey = null;
      });
      return;
    }

    final parsedLat = _toNullableDouble(liveLocation['lat']);
    final parsedLng = _toNullableDouble(liveLocation['lng']);
    final rawAddress = (liveLocation['address'] ?? liveLocation['label'] ?? '')
        .toString()
        .trim();

    if (rawAddress.isNotEmpty && !_looksLikeCoordinates(rawAddress)) {
      if (!mounted) return;
      setState(() {
        _liveLocationAddress = rawAddress;
        _liveLocationAddressKey = parsedLat != null && parsedLng != null
            ? _latLngKey(parsedLat, parsedLng)
            : 'address-only';
      });
      return;
    }

    if (parsedLat == null || parsedLng == null) {
      if (!mounted) return;
      setState(() {
        _liveLocationAddress = null;
        _liveLocationAddressKey = null;
      });
      return;
    }

    final key = _latLngKey(parsedLat, parsedLng);
    final cached = _liveLocationAddressCache[key];
    if (cached != null && cached.trim().isNotEmpty) {
      if (!mounted) return;
      setState(() {
        _liveLocationAddress = cached;
        _liveLocationAddressKey = key;
      });
      return;
    }

    if (mounted) {
      setState(() {
        _liveLocationAddress = '';
        _liveLocationAddressKey = key;
      });
    }

    final lookupToken = ++_liveLocationLookupToken;

    try {
      final placemarks = await placemarkFromCoordinates(parsedLat, parsedLng);
      if (!mounted || lookupToken != _liveLocationLookupToken) return;

      var resolved = '';
      if (placemarks.isNotEmpty) {
        resolved = _composeAddressFromPlacemark(placemarks.first);
      }
      if (resolved.trim().isEmpty) {
        resolved = context.tr('address_not_specified');
      }

      _liveLocationAddressCache[key] = resolved;
      setState(() {
        _liveLocationAddress = resolved;
        _liveLocationAddressKey = key;
      });
    } catch (_) {
      if (!mounted || lookupToken != _liveLocationLookupToken) return;
      setState(() {
        _liveLocationAddress = context.tr('address_not_specified');
        _liveLocationAddressKey = key;
      });
    }
  }

  String _resolvedOrderAddressText() {
    final resolved = (_orderAddressResolved ?? '').trim();
    if (resolved.isNotEmpty && !_looksLikeCoordinates(resolved)) {
      return resolved;
    }

    final raw = (_order?['address'] ?? '').toString().trim();
    if (raw.isNotEmpty && !_looksLikeCoordinates(raw)) {
      return raw;
    }

    return resolved.isNotEmpty ? resolved : context.tr('address_not_specified');
  }

  Future<void> _resolveOrderAddress() async {
    final rawAddress = (_order?['address'] ?? '').toString().trim();
    final lat = _toNullableDouble(_order?['lat']);
    final lng = _toNullableDouble(_order?['lng']);

    if (rawAddress.isNotEmpty && !_looksLikeCoordinates(rawAddress)) {
      if (!mounted) return;
      setState(() {
        _orderAddressResolved = rawAddress;
      });
      return;
    }

    if (lat == null || lng == null) {
      final fallback = rawAddress.isNotEmpty
          ? rawAddress
          : context.tr('address_not_specified');
      if (!mounted) return;
      setState(() {
        _orderAddressResolved = fallback;
      });
      return;
    }

    final key = _latLngKey(lat, lng);
    final cached = _orderAddressCache[key];
    if (cached != null && cached.trim().isNotEmpty) {
      if (!mounted) return;
      setState(() {
        _orderAddressResolved = cached;
      });
      return;
    }

    if (mounted) {
      setState(() {
        _orderAddressResolved = context.tr('detecting_location');
      });
    }

    final lookupToken = ++_orderAddressLookupToken;
    try {
      final placemarks = await placemarkFromCoordinates(lat, lng);
      if (!mounted || lookupToken != _orderAddressLookupToken) return;

      var resolved = '';
      if (placemarks.isNotEmpty) {
        resolved = _composeAddressFromPlacemark(placemarks.first);
      }
      if (resolved.trim().isEmpty) {
        resolved = rawAddress.isNotEmpty
            ? rawAddress
            : context.tr('address_not_specified');
      }

      _orderAddressCache[key] = resolved;
      setState(() {
        _orderAddressResolved = resolved;
      });
    } catch (_) {
      if (!mounted || lookupToken != _orderAddressLookupToken) return;
      setState(() {
        _orderAddressResolved = rawAddress.isNotEmpty
            ? rawAddress
            : context.tr('address_not_specified');
      });
    }
  }

  String _problemTypeLabel() {
    final details = _problemDetails;
    if (details.isEmpty) {
      return '';
    }

    final problemTypes = <String>[];
    void addProblemTypes(dynamic value) {
      if (value == null) return;
      if (value is String) {
        final trimmed = value.trim();
        if (trimmed.isEmpty) return;
        try {
          final decoded = jsonDecode(trimmed);
          addProblemTypes(decoded);
          return;
        } catch (_) {
          final parts = trimmed.split(RegExp(r'\s*[,،]\s*'));
          if (parts.length > 1) {
            for (final part in parts) {
              addProblemTypes(part);
            }
            return;
          }
          final label = _localizedProblemTypeToken(trimmed);
          final resolved = label.isNotEmpty
              ? label
              : (trimmed.contains('_') ? _humanizeToken(trimmed) : trimmed);
          if (resolved.isNotEmpty && !problemTypes.contains(resolved)) {
            problemTypes.add(resolved);
          }
          return;
        }
      }
      if (value is List) {
        for (final item in value) {
          addProblemTypes(item);
        }
        return;
      }
      if (value is Map) {
        final localeCode = Localizations.localeOf(context).languageCode;
        final label = localeCode == 'en'
            ? _firstNonEmpty([
                (value['title_en'] ?? value['name_en'] ?? '').toString(),
                (value['title'] ?? value['name'] ?? value['type'] ?? '')
                    .toString(),
                (value['title_ar'] ?? value['name_ar'] ?? '').toString(),
              ])
            : localeCode == 'ur'
            ? _firstNonEmpty([
                (value['title_ur'] ?? value['name_ur'] ?? '').toString(),
                (value['title_en'] ?? value['name_en'] ?? '').toString(),
                (value['title'] ?? value['name'] ?? value['type'] ?? '')
                    .toString(),
                (value['title_ar'] ?? value['name_ar'] ?? '').toString(),
              ])
            : _firstNonEmpty([
                (value['title_ar'] ?? value['name_ar'] ?? '').toString(),
                (value['title'] ?? value['name'] ?? value['type'] ?? '')
                    .toString(),
                (value['title_en'] ?? value['name_en'] ?? '').toString(),
              ]);
        if (label.trim().isNotEmpty) {
          addProblemTypes(label);
          return;
        }
        for (final item in value.values) {
          addProblemTypes(item);
        }
      }
    }

    addProblemTypes(_order?['problem_type_labels']);
    addProblemTypes(details['problem_type_labels']);
    addProblemTypes(details['problem_types']);
    addProblemTypes(details['types']);
    addProblemTypes(details['problem_type_titles']);
    addProblemTypes(details['selected_problem_types']);
    if (problemTypes.isNotEmpty) {
      return problemTypes.join('، ');
    }

    final directMapped = _localizedProblemTypeToken(
      (details['type'] ?? details['module'] ?? '').toString(),
    );
    if (directMapped.isNotEmpty) {
      return directMapped;
    }

    String read(dynamic value) => (value ?? '').toString().trim();
    final localeCode = Localizations.localeOf(context).languageCode;

    final localized = localeCode == 'en'
        ? _firstNonEmpty([
            read(details['type_en']),
            read(details['name_en']),
            read(details['type']),
            read(details['type_ar']),
            read(details['name_ar']),
          ])
        : localeCode == 'ur'
        ? _firstNonEmpty([
            read(details['type_ur']),
            read(details['name_ur']),
            read(details['type_en']),
            read(details['name_en']),
            read(details['type']),
            read(details['type_ar']),
            read(details['name_ar']),
          ])
        : _firstNonEmpty([
            read(details['type_ar']),
            read(details['name_ar']),
            read(details['type']),
            read(details['type_en']),
            read(details['name_en']),
          ]);

    final mappedLocalized = _localizedProblemTypeToken(localized);
    if (mappedLocalized.isNotEmpty) {
      return mappedLocalized;
    }

    return localized.contains('_') ? _humanizeToken(localized) : localized;
  }

  String _statusLabel(String status) {
    switch (_normalize(status)) {
      case 'pending':
        return context.tr('status_pending');
      case 'assigned':
        return context.tr('provider_status_assigned');
      case 'accepted':
        return context.tr('status_accepted');
      case 'confirmed':
        return context.tr('status_confirmed');
      case 'on_the_way':
        return context.tr('provider_status_on_the_way');
      case 'arrived':
        return context.tr('status_arrived');
      case 'in_progress':
        return context.tr('status_in_progress');
      case 'completed':
        return context.tr('status_completed');
      case 'cancelled':
        return context.tr('status_cancelled');
      case 'rejected':
        return context.tr('status_rejected');
      case 'unreachable':
        return context.tr('order_tracking_confirmation_unreachable');
      default:
        return _humanizeToken(status);
    }
  }

  String _assignmentStatusLabel(String status) {
    switch (_normalize(status)) {
      case 'pending':
        return context.tr('status_pending');
      case 'assigned':
        return context.tr('provider_status_assigned');
      case 'accepted':
        return context.tr('status_accepted');
      case 'on_the_way':
        return context.tr('provider_status_on_the_way');
      case 'arrived':
        return context.tr('status_arrived');
      case 'in_progress':
        return context.tr('status_in_progress');
      case 'completed':
        return context.tr('status_completed');
      case 'rejected':
        return context.tr('status_rejected');
      case 'cancelled':
        return context.tr('status_cancelled');
      default:
        return _statusLabel(status);
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'completed':
        return Colors.green;
      case 'cancelled':
      case 'rejected':
        return Colors.red;
      case 'in_progress':
      case 'on_the_way':
      case 'arrived':
        return Colors.blue;
      case 'accepted':
      case 'assigned':
        return Colors.indigo;
      default:
        return Colors.orange;
    }
  }

  String _formatDateTime(dynamic raw) {
    final value = (raw ?? '').toString().trim();
    if (value.isEmpty) return '-';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;

    final localeCode = Localizations.localeOf(context).languageCode;
    return DateFormat('d MMM yyyy - hh:mm a', localeCode).format(parsed);
  }

  List<String> _extractProblemImages() {
    final urls = <String>{};

    for (final key in ['problem_images', 'attachments']) {
      final value = _order?[key];
      if (value is List) {
        for (final item in value) {
          if (item is String && item.trim().isNotEmpty) {
            urls.add(AppConfig.fixMediaUrl(item));
          } else if (item is Map) {
            final raw = (item['url'] ?? item['path'] ?? item['image'] ?? '')
                .toString();
            if (raw.trim().isNotEmpty) {
              urls.add(AppConfig.fixMediaUrl(raw));
            }
          }
        }
      }
    }

    return urls.toList();
  }

  List<String> _extractInspectionImages() {
    final urls = <String>{};
    final value = _order?['inspection_images'];
    if (value is List) {
      for (final item in value) {
        if (item is String && item.trim().isNotEmpty) {
          urls.add(AppConfig.fixMediaUrl(item));
        }
      }
    }
    return urls.toList();
  }

  List<Map<String, dynamic>> _invoiceItems() {
    final raw = _order?['invoice_items'];
    if (raw is! List) return <Map<String, dynamic>>[];
    return raw
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        )
        .toList();
  }

  List<Map<String, dynamic>> _requestedSpareParts() {
    final raw = _order?['requested_spare_parts'];
    if (raw is! List) return <Map<String, dynamic>>[];
    return raw
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        )
        .toList();
  }

  String _invoiceItemPricingModeLabel(Map<String, dynamic> item) {
    final mode = _normalize(item['pricing_mode']);
    final requiresInstallationRaw = item['requires_installation'];
    final requiresInstallation = requiresInstallationRaw is bool
        ? requiresInstallationRaw
        : requiresInstallationRaw == null
        ? mode != 'without_installation'
        : requiresInstallationRaw.toString() == '1';

    final withInstallation =
        mode == 'with_installation' ||
        (mode != 'without_installation' && requiresInstallation);
    if (withInstallation) {
      return context.tr('order_tracking_with_installation');
    }

    return context.tr('order_tracking_without_installation');
  }

  Widget _sectionCard({
    required String title,
    required List<Widget> children,
    IconData icon = Icons.info_outline,
    Color? iconColor,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: iconColor ?? AppColors.primary),
              const SizedBox(width: 8),
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _infoRow(String label, String value, {Widget? trailing}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              label,
              style: const TextStyle(color: AppColors.gray500, fontSize: 12),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                color: AppColors.gray800,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

  Widget _buildSummaryCard() {
    final statusColor = _statusColor(_status);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '#${widget.orderId}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _categoryName,
                      style: const TextStyle(
                        color: AppColors.gray600,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Text(
                  _statusLabel(_status),
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _infoRow(
            context.tr('order_number'),
            (_order?['order_number'] ?? widget.orderId).toString(),
          ),
          _infoRow(
            context.tr('order_tracking_created_at'),
            _formatDateTime(_order?['created_at']),
          ),
          _infoRow(
            context.tr('order_tracking_schedule'),
            '${_order?['scheduled_date'] ?? '-'} ${_order?['scheduled_time'] ?? ''}'
                .trim(),
          ),
          _infoRow(context.tr('address'), _resolvedOrderAddressText()),
          if (_canCancelOrder) ...[
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _isActionRunning ? null : _cancelOrder,
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  side: const BorderSide(color: Colors.red),
                ),
                icon: const Icon(Icons.cancel_outlined),
                label: Text(context.tr('order_tracking_cancel_order')),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildRequestDetailsCard() {
    final problemType = _problemTypeLabel();
    final description =
        (_order?['problem_description'] ?? _order?['notes'] ?? '')
            .toString()
            .trim();
    final images = _extractProblemImages();

    return _sectionCard(
      title: context.tr('order_tracking_report_details'),
      icon: Icons.description_outlined,
      children: [
        _infoRow(
          context.tr('problem_type_label'),
          problemType.isEmpty
              ? context.tr('problem_type_not_specified')
              : problemType,
        ),
        _infoRow(
          context.tr('description'),
          description.isEmpty ? '-' : description,
        ),
        if (images.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(
            context.tr('order_tracking_attached_images'),
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 92,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: images.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final imageUrl = images[index];
                return ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: CachedNetworkImage(
                    imageUrl: imageUrl,
                    width: 92,
                    height: 92,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => Container(
                      width: 92,
                      height: 92,
                      color: AppColors.gray100,
                      child: const Icon(Icons.broken_image_outlined),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildRequestedSparePartsCard() {
    final parts = _requestedSpareParts();
    if (parts.isEmpty) {
      return const SizedBox.shrink();
    }

    return _sectionCard(
      title: context.tr('order_tracking_requested_spares'),
      icon: Icons.inventory_2_outlined,
      iconColor: Colors.teal,
      children: [
        ...parts.map((part) {
          final name = (part['name'] ?? context.tr('spare_part')).toString();
          final qty = int.tryParse('${part['quantity'] ?? 1}') ?? 1;
          final storeName = (part['store_name'] ?? '').toString().trim();
          final lineTotal = _toDouble(part['total_price']);
          final unitPrice = _toDouble(part['unit_price']);
          final pricingLabel = _invoiceItemPricingModeLabel(part);

          return Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.gray200),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$name × $qty',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        pricingLabel,
                        style: const TextStyle(
                          color: AppColors.gray600,
                          fontSize: 12,
                        ),
                      ),
                      if (storeName.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          '${context.tr('store')}: $storeName',
                          style: const TextStyle(
                            color: AppColors.gray500,
                            fontSize: 12,
                          ),
                        ),
                      ],
                      if (unitPrice > 0) ...[
                        const SizedBox(height: 2),
                        SaudiRiyalText(
                          text: '${unitPrice.toStringAsFixed(2)} × $qty',
                          style: const TextStyle(
                            color: AppColors.gray500,
                            fontSize: 12,
                          ),
                          iconSize: 12,
                        ),
                      ],
                    ],
                  ),
                ),
                SaudiRiyalText(
                  text: lineTotal.toStringAsFixed(2),
                  style: const TextStyle(
                    color: AppColors.gray900,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                  iconSize: 13,
                ),
              ],
            ),
          );
        }),
      ],
    );
  }

  Widget _buildOperationsCard() {
    final minEstimateText = _minEstimate == null
        ? '-'
        : _minEstimate!.toStringAsFixed(2);
    final maxEstimateText = _maxEstimate == null
        ? '-'
        : _maxEstimate!.toStringAsFixed(2);

    return _sectionCard(
      title: context.tr('order_tracking_operations_review'),
      icon: Icons.manage_search,
      iconColor: Colors.indigo,
      children: [
        _infoRow(
          context.tr('order_tracking_review_status'),
          _isOperationsReviewed
              ? context.tr('order_tracking_review_done')
              : context.tr('order_tracking_review_pending'),
        ),
        Row(
          children: [
            Expanded(
              child: _priceBadge(
                context.tr('order_tracking_min_estimate'),
                minEstimateText,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _priceBadge(
                context.tr('order_tracking_max_estimate'),
                maxEstimateText,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _infoRow(
          context.tr('order_tracking_inspection_fee'),
          _inspectionFee <= 0
              ? context.tr('order_tracking_free_inspection')
              : '${_inspectionFee.toStringAsFixed(2)} ${context.tr('sar')}',
        ),
        if (_inspectionPolicyDetails().isNotEmpty)
          _infoRow(
            context.tr('order_tracking_inspection_details'),
            _inspectionPolicyDetails(),
          ),
        _infoRow(
          context.tr('order_tracking_operations_notes'),
          _operationsNotesText(),
        ),
      ],
    );
  }

  String _operationsNotesText() {
    final candidates = [
      _order?['admin_notes'],
      _order?['inspection_notes'],
      _order?['confirmation_notes'],
      _order?['notes'],
    ];

    for (final candidate in candidates) {
      final text = (candidate ?? '').toString().trim();
      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }

    return '-';
  }

  Widget _priceBadge(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFB),
        border: Border.all(color: AppColors.gray200),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(color: AppColors.gray500, fontSize: 12),
          ),
          const SizedBox(height: 4),
          if (value == '-')
            const Text(
              '-',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: AppColors.gray800,
              ),
            )
          else
            SaudiRiyalText(
              text: value,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: AppColors.gray800,
              ),
              iconSize: 12,
            ),
        ],
      ),
    );
  }

  Widget _buildConfirmationCard() {
    final confirmationStatus = _normalize(_order?['confirmation_status']);
    final dueAt = _formatDateTime(_order?['confirmation_due_at']);
    final confirmedAt = _formatDateTime(_order?['confirmed_at']);
    final attempts =
        int.tryParse('${_order?['confirmation_attempts'] ?? 0}') ?? 0;

    final statusText = _confirmationStatusLabel(confirmationStatus);

    return _sectionCard(
      title: context.tr('order_tracking_pre_execution_confirmation'),
      icon: Icons.phone_in_talk,
      iconColor: Colors.deepOrange,
      children: [
        _infoRow(context.tr('order_tracking_confirmation_status'), statusText),
        _infoRow(context.tr('order_tracking_expected_call_time'), dueAt),
        _infoRow(
          context.tr('order_tracking_confirmation_attempts'),
          '$attempts',
        ),
        _infoRow(
          context.tr('order_tracking_confirmation_notes'),
          (_order?['confirmation_notes'] ?? '-').toString(),
        ),
        if (_isConfirmationDone)
          _infoRow(context.tr('order_tracking_confirmed_at'), confirmedAt)
        else
          _infoRow(
            context.tr('order_tracking_info'),
            context.tr('order_tracking_confirmation_info_note'),
          ),
      ],
    );
  }

  Widget _buildProviderCard() {
    final assignedProvidersRaw = _order?['assigned_providers'];
    final assignedProviders = assignedProvidersRaw is List
        ? assignedProvidersRaw
              .whereType<Map>()
              .map(
                (item) => Map<String, dynamic>.from(
                  item.map((key, value) => MapEntry(key.toString(), value)),
                ),
              )
              .toList()
        : <Map<String, dynamic>>[];

    if (!_hasProvider && assignedProviders.isEmpty) {
      return _sectionCard(
        title: context.tr('order_tracking_service_team'),
        icon: Icons.support_agent,
        children: [
          Text(
            context.tr('order_tracking_assigning_provider'),
            style: TextStyle(color: AppColors.gray600),
          ),
        ],
      );
    }

    final providerPhone = (_order?['provider_phone'] ?? '').toString().trim();
    final providerWhatsapp = (_order?['provider_whatsapp'] ?? '')
        .toString()
        .trim();
    final customerLat = _toNullableDouble(_order?['lat']);
    final customerLng = _toNullableDouble(_order?['lng']);
    final liveLocation = _order?['provider_live_location'];
    double? liveLat;
    double? liveLng;
    String liveLocationText = context.tr('address_not_specified');
    if (liveLocation is Map) {
      final parsedLat = _toNullableDouble(liveLocation['lat']);
      final parsedLng = _toNullableDouble(liveLocation['lng']);
      if (parsedLat != null && parsedLng != null) {
        liveLat = parsedLat;
        liveLng = parsedLng;
        final key = _latLngKey(parsedLat, parsedLng);
        final resolvedAddress = (_liveLocationAddress ?? '').trim();
        if (_liveLocationAddressKey == key && resolvedAddress.isNotEmpty) {
          liveLocationText = resolvedAddress;
        } else {
          liveLocationText = context.tr('detecting_location');
        }
      } else {
        final rawAddress =
            (liveLocation['address'] ?? liveLocation['label'] ?? '')
                .toString()
                .trim();
        if (rawAddress.isNotEmpty && !_looksLikeCoordinates(rawAddress)) {
          liveLocationText = rawAddress;
        }
      }
    }
    final inspectionImages = _extractInspectionImages();

    return _sectionCard(
      title: context.tr('order_tracking_execution_team'),
      icon: Icons.engineering,
      iconColor: Colors.blue,
      children: [
        _infoRow(context.tr('provider_label'), _providerName),
        _infoRow(
          context.tr('order_tracking_technician_status'),
          _statusLabel(_status),
        ),
        _infoRow(
          context.tr('order_tracking_technician_phone'),
          providerPhone.isEmpty ? '-' : providerPhone,
        ),
        _infoRow(
          context.tr('order_tracking_technician_whatsapp'),
          providerWhatsapp.isEmpty ? '-' : providerWhatsapp,
        ),
        _infoRow(context.tr('order_tracking_live_location'), liveLocationText),
        if (assignedProviders.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            context.tr('order_tracking_assigned_technicians'),
            style: TextStyle(
              color: AppColors.gray700,
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          ...assignedProviders.map((provider) {
            final name =
                (provider['provider_name'] ??
                        context.tr('order_tracking_technician'))
                    .toString();
            final assignmentStatus =
                (provider['assignment_status'] ?? 'assigned').toString();
            final phone = (provider['provider_phone'] ?? '').toString().trim();
            final isPrimary = provider['is_primary'] == true;

            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.gray200),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isPrimary
                              ? '$name (${context.tr('order_tracking_primary')})'
                              : name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _assignmentStatusLabel(assignmentStatus),
                          style: const TextStyle(
                            color: AppColors.gray600,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (phone.isNotEmpty)
                    IconButton(
                      onPressed: () => _callNumber(phone),
                      icon: const Icon(Icons.phone, size: 18),
                      tooltip: context.tr('call'),
                    ),
                ],
              ),
            );
          }),
        ],
        if (inspectionImages.isNotEmpty) ...[
          const SizedBox(height: 10),
          Text(
            context.tr('order_tracking_inspection_images'),
            style: TextStyle(
              color: AppColors.gray700,
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 92,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: inspectionImages.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final imageUrl = inspectionImages[index];
                return ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: CachedNetworkImage(
                    imageUrl: imageUrl,
                    width: 92,
                    height: 92,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => Container(
                      width: 92,
                      height: 92,
                      color: AppColors.gray100,
                      child: const Icon(Icons.broken_image_outlined),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final quickActions = <Widget>[
              _buildContactActionTile(
                icon: Icons.phone_in_talk_rounded,
                label: context.tr('order_tracking_call_technician'),
                color: Colors.blue,
                onTap: providerPhone.isEmpty
                    ? null
                    : () => _callNumber(providerPhone),
              ),
              _buildContactActionTile(
                icon: Icons.support_agent_rounded,
                label: context.tr('order_tracking_call_operations'),
                color: Colors.indigo,
                onTap: () => _callNumber(AppConfig.supportPhone),
              ),
              if (providerWhatsapp.isNotEmpty)
                _buildContactActionTile(
                  icon: Icons.mark_chat_unread_rounded,
                  label: context.tr('order_tracking_whatsapp_technician'),
                  color: const Color(0xFF16A34A),
                  onTap: () => _openWhatsApp(providerWhatsapp),
                ),
              if (liveLat != null && liveLng != null)
                _buildContactActionTile(
                  icon: Icons.my_location_rounded,
                  label: context.tr('order_tracking_open_technician_location'),
                  color: Colors.teal,
                  onTap: () => _openMapLocation(liveLat!, liveLng!),
                ),
              if (liveLat != null &&
                  liveLng != null &&
                  customerLat != null &&
                  customerLng != null)
                _buildContactActionTile(
                  icon: Icons.alt_route_rounded,
                  label: context.tr('order_tracking_track_technician_route'),
                  color: AppColors.primaryDark,
                  onTap: () => _openDirections(
                    liveLat!,
                    liveLng!,
                    customerLat,
                    customerLng,
                  ),
                ),
            ];

            if (quickActions.isEmpty) {
              return const SizedBox.shrink();
            }

            final columns = constraints.maxWidth >= 540
                ? 3
                : (constraints.maxWidth >= 320 ? 2 : 1);
            final horizontalGap = 8.0;
            final itemWidth =
                (constraints.maxWidth - ((columns - 1) * horizontalGap)) /
                columns;

            return Wrap(
              spacing: horizontalGap,
              runSpacing: 8,
              children: quickActions
                  .map((action) => SizedBox(width: itemWidth, child: action))
                  .toList(),
            );
          },
        ),
      ],
    );
  }

  Widget _buildContainerStoreCard() {
    final store = _containerStoreInfo;
    final storeName = _containerStoreName(store);

    if (storeName.isEmpty) {
      return _sectionCard(
        title: context.tr('order_tracking_container_store'),
        icon: Icons.storefront_rounded,
        iconColor: AppColors.primaryDark,
        children: [
          Text(
            context.tr('order_tracking_assigning_container_store'),
            style: const TextStyle(color: AppColors.gray600),
          ),
        ],
      );
    }

    final phone = (store['phone'] ?? '').toString().trim();
    final whatsapp = (store['whatsapp'] ?? phone).toString().trim();
    final email = (store['email'] ?? '').toString().trim();
    final address = (store['address'] ?? '').toString().trim();
    final logo = (store['logo'] ?? '').toString().trim();
    final contactPerson = (store['contact_person'] ?? '').toString().trim();

    return _sectionCard(
      title: context.tr('order_tracking_container_store'),
      icon: Icons.storefront_rounded,
      iconColor: AppColors.primaryDark,
      children: [
        Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(16),
              ),
              clipBehavior: Clip.antiAlias,
              child: logo.isNotEmpty
                  ? CachedNetworkImage(
                      imageUrl: logo,
                      fit: BoxFit.cover,
                      errorWidget: (_, __, ___) => const Icon(
                        Icons.storefront_rounded,
                        color: AppColors.primaryDark,
                      ),
                    )
                  : const Icon(
                      Icons.storefront_rounded,
                      color: AppColors.primaryDark,
                    ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    storeName,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.gray900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    context.tr('order_tracking_container_store_assigned'),
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.gray600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (contactPerson.isNotEmpty)
          _infoRow(context.tr('order_tracking_contact_person'), contactPerson),
        _infoRow(context.tr('phone'), phone.isEmpty ? '-' : phone),
        if (email.isNotEmpty) _infoRow(context.tr('email'), email),
        _infoRow(
          context.tr('address'),
          address.isEmpty ? context.tr('address_not_specified') : address,
        ),
        const SizedBox(height: 8),
        LayoutBuilder(
          builder: (context, constraints) {
            final quickActions = <Widget>[
              _buildContactActionTile(
                icon: Icons.phone_in_talk_rounded,
                label: context.tr('order_tracking_call_container_store'),
                color: Colors.blue,
                onTap: phone.isEmpty ? null : () => _callNumber(phone),
              ),
              if (whatsapp.isNotEmpty)
                _buildContactActionTile(
                  icon: Icons.mark_chat_unread_rounded,
                  label: context.tr('order_tracking_whatsapp_container_store'),
                  color: const Color(0xFF16A34A),
                  onTap: () => _openWhatsApp(whatsapp),
                ),
              _buildContactActionTile(
                icon: Icons.support_agent_rounded,
                label: context.tr('order_tracking_call_operations'),
                color: Colors.indigo,
                onTap: () => _callNumber(AppConfig.supportPhone),
              ),
            ];

            final columns = constraints.maxWidth >= 540
                ? 3
                : (constraints.maxWidth >= 320 ? 2 : 1);
            final horizontalGap = 8.0;
            final itemWidth =
                (constraints.maxWidth - ((columns - 1) * horizontalGap)) /
                columns;

            return Wrap(
              spacing: horizontalGap,
              runSpacing: 8,
              children: quickActions
                  .map((action) => SizedBox(width: itemWidth, child: action))
                  .toList(),
            );
          },
        ),
      ],
    );
  }

  Widget _buildContactActionTile({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback? onTap,
  }) {
    final isEnabled = onTap != null;
    final borderColor = isEnabled
        ? color.withValues(alpha: 0.34)
        : AppColors.gray200;
    final iconBackground = isEnabled
        ? color.withValues(alpha: 0.14)
        : AppColors.gray100;
    final iconColor = isEnabled ? color : AppColors.gray400;

    return Opacity(
      opacity: isEnabled ? 1 : 0.6,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: borderColor),
            ),
            child: Row(
              children: [
                Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: iconBackground,
                    borderRadius: BorderRadius.circular(9),
                  ),
                  child: Icon(icon, size: 16, color: iconColor),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: isEnabled ? AppColors.gray800 : AppColors.gray500,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildInvoiceCard() {
    final items = _invoiceItems();

    final hasInvoiceValues =
        _laborCost > 0 || _partsCost > 0 || _totalAmount > 0;
    final hasAnyInvoiceState =
        _invoiceStatus != '' &&
        _invoiceStatus != 'none' &&
        _invoiceStatus != 'null';

    if (!hasInvoiceValues && !hasAnyInvoiceState) {
      return _sectionCard(
        title: context.tr('order_tracking_invoice_execution'),
        icon: Icons.receipt_long,
        iconColor: Colors.green,
        children: [
          Text(
            context.tr('order_tracking_waiting_final_invoice'),
            style: TextStyle(color: AppColors.gray600),
          ),
        ],
      );
    }

    return _sectionCard(
      title: context.tr('order_tracking_invoice_execution'),
      icon: Icons.receipt_long,
      iconColor: Colors.green,
      children: [
        _infoRow(
          context.tr('order_tracking_invoice_status'),
          _invoiceStatusLabel(),
        ),
        _priceRow(context.tr('labor_cost_label'), _laborCost),
        _priceRow(context.tr('parts_cost_label'), _partsCost),
        const Divider(height: 18),
        _priceRow(context.tr('total_amount'), _totalAmount, isTotal: true),
        if (items.isNotEmpty) ...[
          const SizedBox(height: 10),
          Text(
            context.tr('order_tracking_item_details'),
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
          ),
          const SizedBox(height: 8),
          ...items.map((item) {
            final name =
                (item['name'] ??
                        item['spare_part_name'] ??
                        context.tr('order_tracking_invoice_item'))
                    .toString();
            final qty = int.tryParse('${item['quantity'] ?? 1}') ?? 1;
            final unitPrice = _toDouble(item['unit_price']);
            final lineTotal = _toDouble(item['total_price']);
            final pricingLabel = _invoiceItemPricingModeLabel(item);
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.gray200),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '$name × $qty',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          pricingLabel,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.gray600,
                          ),
                        ),
                        if (unitPrice > 0) ...[
                          const SizedBox(height: 2),
                          SaudiRiyalText(
                            text: '${unitPrice.toStringAsFixed(2)} × $qty',
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.gray500,
                            ),
                            iconSize: 12,
                          ),
                        ],
                      ],
                    ),
                  ),
                  SaudiRiyalText(
                    text: lineTotal.toStringAsFixed(2),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppColors.gray800,
                    ),
                    iconSize: 13,
                  ),
                ],
              ),
            );
          }),
        ],
        const SizedBox(height: 12),
        if (_hasPendingInvoice) ...[
          Text(
            context.tr('order_tracking_choose_invoice_action'),
            style: TextStyle(color: AppColors.gray600, fontSize: 12),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  onPressed: _isActionRunning
                      ? null
                      : () => _submitInvoiceDecision(approved: true),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                  ),
                  child: Text(context.tr('approve')),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton(
                  onPressed: _isActionRunning
                      ? null
                      : _requestInvoiceAdjustment,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.orange,
                    foregroundColor: Colors.white,
                  ),
                  child: Text(context.tr('order_tracking_request_adjustment')),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: _isActionRunning
                  ? null
                  : () => _submitInvoiceDecision(approved: false),
              style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
              child: Text(context.tr('reject')),
            ),
          ),
        ],
        if (_paymentStatus == 'paid') ...[
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
            decoration: BoxDecoration(
              color: Colors.green.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.green.withValues(alpha: 0.3)),
            ),
            child: Text(
              context.tr('payment_successful'),
              style: TextStyle(
                color: Colors.green,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ] else if (_canPayNow) ...[
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _openPaymentScreen,
              icon: const Icon(Icons.payments_outlined),
              label: Text(context.tr('order_tracking_pay_now')),
            ),
          ),
        ],
        if (_invoiceStatus == 'rejected')
          Padding(
            padding: EdgeInsets.only(top: 8),
            child: Text(
              context.tr('order_tracking_invoice_rejected_note'),
              style: TextStyle(
                color: Colors.deepOrange,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }

  String _invoiceStatusLabel() {
    switch (_invoiceStatus) {
      case 'pending':
        return context.tr('order_tracking_invoice_pending_approval');
      case 'approved':
        return context.tr('order_tracking_invoice_approved');
      case 'paid':
        return context.tr('order_tracking_invoice_paid');
      case 'rejected':
        return context.tr('order_tracking_invoice_rejected');
      case 'none':
      case '':
      case 'null':
        return context.tr('order_tracking_invoice_not_issued');
      default:
        return _humanizeToken(_invoiceStatus);
    }
  }

  String _confirmationStatusLabel(String rawStatus) {
    switch (_normalize(rawStatus)) {
      case '':
      case 'pending':
        return context.tr('order_tracking_waiting_confirmation_call');
      case 'confirmed':
        return context.tr('order_tracking_confirmed_schedule');
      case 'unreachable':
        return context.tr('order_tracking_confirmation_unreachable');
      case 'cancelled':
        return context.tr('order_tracking_confirmation_cancelled');
      default:
        return _statusLabel(rawStatus);
    }
  }

  Widget _priceRow(String label, double value, {bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
              fontSize: isTotal ? 15 : 13,
            ),
          ),
          SaudiRiyalText(
            text: value.toStringAsFixed(2),
            style: TextStyle(
              fontWeight: isTotal ? FontWeight.bold : FontWeight.w600,
              color: isTotal ? AppColors.primaryDark : AppColors.gray800,
              fontSize: isTotal ? 15 : 13,
            ),
            iconSize: isTotal ? 15 : 13,
          ),
        ],
      ),
    );
  }

  Widget _buildTimelineCard() {
    final stages = [
      {
        'title': context.tr('order_tracking_stage_received_title'),
        'desc': context.tr('order_tracking_stage_received_desc'),
        'done': true,
      },
      {
        'title': context.tr('order_tracking_stage_operations_title'),
        'desc': context.tr('order_tracking_stage_operations_desc'),
        'done': _isOperationsReviewed,
      },
      {
        'title': context.tr('order_tracking_stage_confirmation_title'),
        'desc': context.tr('order_tracking_stage_confirmation_desc'),
        'done': _isConfirmationDone,
      },
      {
        'title': context.tr('order_tracking_stage_execution_title'),
        'desc': context.tr('order_tracking_stage_execution_desc'),
        'done': ['arrived', 'in_progress', 'completed'].contains(_status),
      },
      {
        'title': context.tr('order_tracking_stage_invoice_title'),
        'desc': context.tr('order_tracking_stage_invoice_desc'),
        'done': _paymentStatus == 'paid',
      },
      {
        'title': context.tr('order_tracking_stage_closure_title'),
        'desc': context.tr('order_tracking_stage_closure_desc'),
        'done': _status == 'completed',
      },
    ];

    return _sectionCard(
      title: context.tr('order_status_timeline'),
      icon: Icons.timeline,
      children: [
        ListView.builder(
          itemCount: stages.length,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemBuilder: (context, index) {
            final stage = stages[index];
            final done = stage['done'] == true;
            final isLast = index == stages.length - 1;

            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  children: [
                    Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        color: done ? Colors.green : AppColors.gray200,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        done ? Icons.check : Icons.circle,
                        size: done ? 15 : 8,
                        color: Colors.white,
                      ),
                    ),
                    if (!isLast)
                      Container(
                        width: 2,
                        height: 38,
                        color: done ? Colors.green : AppColors.gray200,
                      ),
                  ],
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          stage['title'].toString(),
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: done ? AppColors.gray800 : AppColors.gray500,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          stage['desc'].toString(),
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.gray500,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _buildPostSaleCard() {
    if (_status != 'completed') {
      return _sectionCard(
        title: context.tr('order_tracking_post_sale'),
        icon: Icons.verified_user_outlined,
        iconColor: Colors.teal,
        children: [
          Text(
            context.tr('order_tracking_post_sale_after_completion'),
            style: TextStyle(color: AppColors.gray600),
          ),
        ],
      );
    }

    return _sectionCard(
      title: context.tr('order_tracking_closure_post_sale'),
      icon: Icons.verified_user_outlined,
      iconColor: Colors.teal,
      children: [
        Text(
          context.tr('order_tracking_completed_share_rating'),
          style: TextStyle(color: AppColors.gray700),
        ),
        const SizedBox(height: 10),
        if (!_isRated)
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _openRatingScreen,
              icon: const Icon(Icons.star_rate_rounded),
              label: Text(context.tr('rate_service')),
            ),
          )
        else
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
            decoration: BoxDecoration(
              color: Colors.green.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              context.tr('order_tracking_rating_sent_thanks'),
              style: TextStyle(
                color: Colors.green,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed: () => _callNumber(AppConfig.supportPhone),
          icon: const Icon(Icons.support_agent),
          label: Text(context.tr('order_tracking_follow_up_after_service')),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_order == null) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('track_order_title'))),
        body: Center(child: Text(context.tr('order_not_found'))),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('track_order_title')),
        centerTitle: true,
        leading: IconButton(
          onPressed: widget.onBackToHome,
          icon: const Icon(Icons.arrow_back),
        ),
        actions: [
          IconButton(
            onPressed: _fetchOrderDetails,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchOrderDetails,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              _buildSummaryCard(),
              const SizedBox(height: 12),
              _buildRequestDetailsCard(),
              const SizedBox(height: 12),
              _buildRequestedSparePartsCard(),
              if (_requestedSpareParts().isNotEmpty) const SizedBox(height: 12),
              _buildOperationsCard(),
              const SizedBox(height: 12),
              _buildConfirmationCard(),
              const SizedBox(height: 12),
              _isContainerOrder
                  ? _buildContainerStoreCard()
                  : _buildProviderCard(),
              const SizedBox(height: 12),
              _buildInvoiceCard(),
              const SizedBox(height: 12),
              _buildTimelineCard(),
              const SizedBox(height: 12),
              _buildPostSaleCard(),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
