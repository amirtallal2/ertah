import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/app_config.dart';
import '../services/api_service.dart';
import '../services/app_localizations.dart';
import '../services/home_service.dart';
import '../services/orders_service.dart';
import '../utils/saudi_riyal_icon.dart';

class OrderDetailsScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailsScreen({super.key, required this.orderId});

  @override
  State<OrderDetailsScreen> createState() => _OrderDetailsScreenState();
}

class _OrderDetailsScreenState extends State<OrderDetailsScreen> {
  static const Color _yellowTextColor = Color(0xFF1F2937);
  static const Color _yellowBorderColor = Color(0xFFC99A00);
  static const Color _selectedChipColor = Color(0xFFFBCC26);
  final OrdersService _ordersService = OrdersService();
  final HomeService _homeService = HomeService();
  Map<String, dynamic>? _order;
  bool _isLoading = true;
  bool _isLoadingSpareCatalog = false;
  bool _invoiceDraftInitialized = false;
  bool _isSendingLiveLocation = false;
  Timer? _autoLocationTimer;
  Timer? _autoRefreshTimer;
  final ImagePicker _completionProofImagePicker = ImagePicker();
  String? _completionProofImagePath;

  final TextEditingController _laborCostController = TextEditingController();
  final TextEditingController _partsCostController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _minEstimateController = TextEditingController();
  final TextEditingController _maxEstimateController = TextEditingController();

  final List<_InvoiceSpareDraft> _invoiceSpareDrafts = [];
  List<Map<String, dynamic>> _spareCatalog = const [];

  bool _isActionRunning = false;
  String? _resolvedOrderAddress;
  int _orderAddressLookupToken = 0;
  final Map<String, String> _orderAddressCache = {};

  String _statusText(String status) {
    switch (status) {
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
      case 'cancelled':
        return context.tr('status_cancelled');
      case 'rejected':
        return context.tr('status_rejected');
      default:
        return status;
    }
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  double? _toNullableDouble(dynamic value) {
    if (value == null) return null;
    return double.tryParse(value.toString());
  }

  List<int> _extractServiceIds(dynamic raw) {
    if (raw is List) {
      return raw
          .map((item) => int.tryParse(item.toString()) ?? 0)
          .where((id) => id > 0)
          .toSet()
          .toList();
    }

    final normalized = raw?.toString().trim() ?? '';
    if (normalized.isEmpty) {
      return const <int>[];
    }

    return normalized
        .split(',')
        .map((item) => int.tryParse(item.trim()) ?? 0)
        .where((id) => id > 0)
        .toSet()
        .toList();
  }

  List<int> _currentOrderServiceIds() {
    final direct = _extractServiceIds(_order?['service_ids']);
    if (direct.isNotEmpty) {
      return direct;
    }

    final problemDetails = _order?['problem_details'];
    if (problemDetails is Map) {
      final serviceTypeIds = _extractServiceIds(
        problemDetails['service_type_ids'],
      );
      if (serviceTypeIds.isNotEmpty) {
        return serviceTypeIds;
      }
      return _extractServiceIds(problemDetails['sub_services']);
    }

    return const <int>[];
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

  String _displayOrderAddress() {
    final resolved = (_resolvedOrderAddress ?? '').trim();
    if (resolved.isNotEmpty && !_looksLikeCoordinates(resolved)) {
      return resolved;
    }

    final raw = (_order?['address'] ?? '').toString().trim();
    if (raw.isNotEmpty && !_looksLikeCoordinates(raw)) {
      return raw;
    }

    return resolved.isNotEmpty ? resolved : context.tr('address_not_specified');
  }

  String _displayCustomerName() {
    final raw = (_order?['user_name'] ?? '').toString().trim();
    if (raw.isNotEmpty) return raw;
    return context.tr('customer_info');
  }

  String _displayCustomerPhone() {
    final raw = (_order?['user_phone'] ?? '').toString().trim();
    if (raw.isNotEmpty) return raw;
    return context.tr('not_available');
  }

  String _displayCustomerAvatarUrl() {
    final raw = (_order?['user_avatar'] ?? '').toString().trim();
    if (raw.isEmpty) return '';
    return AppConfig.fixMediaUrl(raw);
  }

  Future<void> _openOrderLocationOnMap() async {
    if (_order == null) return;

    final lat = _toNullableDouble(_order!['lat']);
    final lng = _toNullableDouble(_order!['lng']);
    Uri? mapUri;

    if (lat != null && lng != null) {
      mapUri = Uri.parse(
        'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
      );
    } else {
      final address = _displayOrderAddress().trim();
      if (address.isEmpty || address == context.tr('address_not_specified')) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('address_not_specified'))),
        );
        return;
      }
      mapUri = Uri.https('www.google.com', '/maps/search/', {
        'api': '1',
        'query': address,
      });
    }

    final launched = await launchUrl(
      mapUri,
      mode: LaunchMode.externalApplication,
    );
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('order_action_failed_default'))),
      );
    }
  }

  void _showImagePreview(String imageUrl) {
    showDialog<void>(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: const EdgeInsets.all(12),
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            Positioned.fill(
              child: InteractiveViewer(
                minScale: 0.8,
                maxScale: 4,
                child: Image.network(
                  imageUrl,
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => const Center(
                    child: Icon(
                      Icons.broken_image_outlined,
                      color: Colors.white70,
                      size: 42,
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                onPressed: () => Navigator.of(ctx).pop(),
                icon: const Icon(Icons.close, color: Colors.white),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _resolveOrderAddress() async {
    final raw = (_order?['address'] ?? '').toString().trim();
    final lat = _toNullableDouble(_order?['lat']);
    final lng = _toNullableDouble(_order?['lng']);

    if (raw.isNotEmpty && !_looksLikeCoordinates(raw)) {
      if (!mounted) return;
      setState(() => _resolvedOrderAddress = raw);
      return;
    }

    if (lat == null || lng == null) {
      if (!mounted) return;
      setState(() {
        _resolvedOrderAddress = raw.isNotEmpty
            ? raw
            : context.tr('address_not_specified');
      });
      return;
    }

    final key = _latLngKey(lat, lng);
    final cached = _orderAddressCache[key];
    if (cached != null && cached.trim().isNotEmpty) {
      if (!mounted) return;
      setState(() => _resolvedOrderAddress = cached);
      return;
    }

    if (mounted) {
      setState(() => _resolvedOrderAddress = context.tr('detecting_location'));
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
        resolved = raw.isNotEmpty ? raw : context.tr('address_not_specified');
      }

      _orderAddressCache[key] = resolved;
      setState(() => _resolvedOrderAddress = resolved);
    } catch (_) {
      if (!mounted || lookupToken != _orderAddressLookupToken) return;
      setState(() {
        _resolvedOrderAddress = raw.isNotEmpty
            ? raw
            : context.tr('address_not_specified');
      });
    }
  }

  List<Map<String, dynamic>> _normalizeMapList(dynamic value) {
    if (value is! List) return const <Map<String, dynamic>>[];
    return value
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        )
        .toList();
  }

  String _withInstallationLabel() {
    final lang = Localizations.localeOf(context).languageCode;
    switch (lang) {
      case 'en':
        return 'With installation';
      case 'ur':
        return 'تنصیب کے ساتھ';
      default:
        return 'مع التركيب';
    }
  }

  String _withoutInstallationLabel() {
    final lang = Localizations.localeOf(context).languageCode;
    switch (lang) {
      case 'en':
        return 'Without installation';
      case 'ur':
        return 'تنصیب کے بغیر';
      default:
        return 'بدون تركيب';
    }
  }

  List<String> _extractAttachmentImages() {
    final urls = <String>{};
    final value = _order?['attachments'];

    if (value is! List) {
      return const <String>[];
    }

    for (final item in value) {
      String raw = '';

      if (item is String) {
        raw = item;
      } else if (item is Map) {
        raw = (item['url'] ?? item['path'] ?? item['image'] ?? '').toString();
      }

      if (raw.trim().isNotEmpty) {
        urls.add(AppConfig.fixMediaUrl(raw));
      }
    }

    return urls.toList();
  }

  String _spareNameFromCatalog(Map<String, dynamic> part) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (part['name_ar'] ?? part['name'] ?? '').toString().trim();
    final en = (part['name_en'] ?? '').toString().trim();
    if (lang == 'ar') return ar.isNotEmpty ? ar : en;
    return en.isNotEmpty ? en : ar;
  }

  Map<String, dynamic>? _catalogPartById(int id) {
    for (final part in _spareCatalog) {
      final partId = int.tryParse('${part['id'] ?? 0}') ?? 0;
      if (partId == id) return part;
    }
    return null;
  }

  double _spareUnitPriceFromCatalog(
    Map<String, dynamic> part,
    String pricingMode,
  ) {
    final withInstallation = _toDouble(
      part['price_with_installation'] ??
          part['priceWithInstallation'] ??
          part['price'],
    );
    final withoutInstallation = _toDouble(
      part['price_without_installation'] ??
          part['priceWithoutInstallation'] ??
          withInstallation,
    );
    return pricingMode == 'without_installation'
        ? withoutInstallation
        : withInstallation;
  }

  double get _invoiceSparePartsTotal {
    double sum = 0;
    for (final item in _invoiceSpareDrafts) {
      if (item.sparePartId == null || item.sparePartId! <= 0) continue;
      if (item.quantity <= 0) continue;
      sum += item.totalPrice;
    }
    return sum;
  }

  double get _manualPartsCost {
    final value = double.tryParse(_partsCostController.text.trim()) ?? 0;
    return value < 0 ? 0 : value;
  }

  double get _effectivePartsCost => _invoiceSparePartsTotal + _manualPartsCost;

  double get _manualLaborCost {
    final value = double.tryParse(_laborCostController.text.trim()) ?? 0;
    return value < 0 ? 0 : value;
  }

  double get _invoiceDraftGrandTotal => _manualLaborCost + _effectivePartsCost;

  void _populateInvoiceDraftFromOrder() {
    if (_invoiceDraftInitialized || _order == null) return;

    final order = _order!;
    final requestedParts = _normalizeMapList(order['requested_spare_parts']);
    final invoiceItems = _normalizeMapList(order['invoice_items']);

    final source = requestedParts.isNotEmpty ? requestedParts : invoiceItems;

    _invoiceSpareDrafts.clear();
    for (final item in source) {
      final sparePartId =
          int.tryParse('${item['spare_part_id'] ?? item['id'] ?? 0}') ?? 0;
      final quantity = int.tryParse('${item['quantity'] ?? 1}') ?? 1;
      final pricingModeRaw = (item['pricing_mode'] ?? '').toString().trim();
      final requiresInstallationRaw = item['requires_installation'];
      final requiresInstallation = requiresInstallationRaw is bool
          ? requiresInstallationRaw
          : requiresInstallationRaw == null
          ? pricingModeRaw != 'without_installation'
          : requiresInstallationRaw.toString() == '1';
      final pricingMode = pricingModeRaw.isNotEmpty
          ? pricingModeRaw
          : (requiresInstallation
                ? 'with_installation'
                : 'without_installation');

      final draft = _InvoiceSpareDraft(
        sparePartId: sparePartId > 0 ? sparePartId : null,
        name: (item['name'] ?? item['spare_part_name'] ?? '').toString().trim(),
        quantity: quantity > 0 ? quantity : 1,
        pricingMode: pricingMode,
        requiresInstallation: requiresInstallation,
        unitPrice: _toDouble(item['unit_price']),
        notes: (item['notes'] ?? '').toString().trim(),
      );
      _invoiceSpareDrafts.add(draft);
    }

    final laborCost = _toDouble(order['labor_cost']);
    if (laborCost > 0) {
      _laborCostController.text = laborCost.toStringAsFixed(2);
    }

    final partsCost = _toDouble(order['parts_cost']);
    if (_invoiceSpareDrafts.isEmpty && partsCost > 0) {
      _partsCostController.text = partsCost.toStringAsFixed(2);
    } else {
      _partsCostController.text = '';
    }

    _invoiceDraftInitialized = true;
  }

  Future<void> _loadSpareCatalog() async {
    if (_isLoadingSpareCatalog) return;
    setState(() => _isLoadingSpareCatalog = true);

    try {
      final lat = _toNullableDouble(_order?['lat']);
      final lng = _toNullableDouble(_order?['lng']);
      final categoryId = int.tryParse('${_order?['category_id'] ?? 0}') ?? 0;
      final serviceIds = _currentOrderServiceIds();
      final response = await _homeService.getSpareParts(
        lat: lat,
        lng: lng,
        categoryId: categoryId > 0 ? categoryId : null,
        serviceIds: serviceIds,
      );
      if (!mounted) return;

      if (response.success && response.data is List) {
        _spareCatalog = (response.data as List)
            .whereType<Map>()
            .map(
              (item) => Map<String, dynamic>.from(
                item.map((key, value) => MapEntry(key.toString(), value)),
              ),
            )
            .toList();
      } else {
        _spareCatalog = const [];
      }

      bool changed = false;
      for (final draft in _invoiceSpareDrafts) {
        final sparePartId = draft.sparePartId;
        if (sparePartId == null || sparePartId <= 0) continue;
        final part = _catalogPartById(sparePartId);
        if (part == null) continue;

        final newName = _spareNameFromCatalog(part);
        if (newName.isNotEmpty && draft.name != newName) {
          draft.name = newName;
          changed = true;
        }

        final resolvedPrice = _spareUnitPriceFromCatalog(
          part,
          draft.pricingMode,
        );
        if (resolvedPrice > 0 &&
            (draft.unitPrice <= 0 ||
                (draft.unitPrice - resolvedPrice).abs() > 0.001)) {
          draft.unitPrice = resolvedPrice;
          changed = true;
        }
      }

      if (!mounted) return;
      setState(() {
        _isLoadingSpareCatalog = false;
      });

      if (changed && mounted) {
        setState(() {});
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoadingSpareCatalog = false);
    }
  }

  void _addSpareInvoiceLine() {
    setState(() {
      _invoiceSpareDrafts.add(
        _InvoiceSpareDraft(
          quantity: 1,
          pricingMode: 'with_installation',
          requiresInstallation: true,
        ),
      );
    });
  }

  void _removeSpareInvoiceLine(int index) {
    if (index < 0 || index >= _invoiceSpareDrafts.length) return;
    setState(() {
      _invoiceSpareDrafts.removeAt(index);
    });
  }

  void _setSpareInvoicePart(int index, int? sparePartId) {
    if (index < 0 || index >= _invoiceSpareDrafts.length) return;
    final draft = _invoiceSpareDrafts[index];
    draft.sparePartId = sparePartId;

    if (sparePartId == null || sparePartId <= 0) {
      setState(() {
        draft.name = '';
        draft.unitPrice = 0;
      });
      return;
    }

    final part = _catalogPartById(sparePartId);
    setState(() {
      if (part != null) {
        draft.name = _spareNameFromCatalog(part);
        draft.unitPrice = _spareUnitPriceFromCatalog(part, draft.pricingMode);
      }
    });
  }

  void _setSpareInvoicePricingMode(int index, String pricingMode) {
    if (index < 0 || index >= _invoiceSpareDrafts.length) return;
    final draft = _invoiceSpareDrafts[index];
    if (pricingMode != 'with_installation' &&
        pricingMode != 'without_installation') {
      return;
    }

    final part = draft.sparePartId != null
        ? _catalogPartById(draft.sparePartId!)
        : null;

    setState(() {
      draft.pricingMode = pricingMode;
      draft.requiresInstallation = pricingMode != 'without_installation';
      if (part != null) {
        draft.unitPrice = _spareUnitPriceFromCatalog(part, pricingMode);
      }
    });
  }

  void _setSpareInvoiceQuantity(int index, int quantity) {
    if (index < 0 || index >= _invoiceSpareDrafts.length) return;
    if (quantity <= 0) return;
    setState(() {
      _invoiceSpareDrafts[index].quantity = quantity;
    });
  }

  List<Map<String, dynamic>> _buildSpareInvoicePayload() {
    final payload = <Map<String, dynamic>>[];
    for (final draft in _invoiceSpareDrafts) {
      final sparePartId = draft.sparePartId ?? 0;
      if (sparePartId <= 0 || draft.quantity <= 0) continue;

      payload.add({
        'spare_part_id': sparePartId,
        'quantity': draft.quantity,
        'pricing_mode': draft.pricingMode,
        'requires_installation': draft.requiresInstallation,
        if (draft.unitPrice > 0) 'unit_price': draft.unitPrice,
        if (draft.notes.trim().isNotEmpty) 'notes': draft.notes.trim(),
      });
    }
    return payload;
  }

  @override
  void initState() {
    super.initState();
    _loadOrder();
  }

  Future<void> _loadOrder({
    bool skipDraftSync = false,
    bool skipSpareCatalog = false,
  }) async {
    try {
      final res = await _ordersService.getOrderDetail(widget.orderId);
      if (!mounted) return;

      final order = res.success && res.data is Map
          ? Map<String, dynamic>.from(
              (res.data as Map).map(
                (key, value) => MapEntry(key.toString(), value),
              ),
            )
          : null;

      setState(() {
        _order = order;
        _isLoading = false;
        if (_order != null) {
          final minEstimate = (_order!['min_estimate'] ?? '').toString();
          final maxEstimate = (_order!['max_estimate'] ?? '').toString();
          _minEstimateController.text = minEstimate == 'null'
              ? ''
              : minEstimate;
          _maxEstimateController.text = maxEstimate == 'null'
              ? ''
              : maxEstimate;
        }
      });
      _resolveOrderAddress();

      if (!skipDraftSync || !_invoiceDraftInitialized) {
        _populateInvoiceDraftFromOrder();
      }
      if (!skipSpareCatalog) {
        await _loadSpareCatalog();
      }
      _syncAutoLocationTimer();
      _syncAutoRefreshTimer();
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
      _syncAutoLocationTimer();
      _syncAutoRefreshTimer();
    }
  }

  Future<void> _completeJob() async {
    final proofPath = _completionProofImagePath?.trim() ?? '';
    if (proofPath.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('provider_completion_proof_required')),
        ),
      );
      return;
    }

    await _runOrderAction(
      () => _ordersService.completeJob(
        widget.orderId,
        completionProofImagePath: proofPath,
      ),
    );

    if (!mounted) return;
    if ((_order?['status'] ?? '').toString().toLowerCase() == 'completed') {
      setState(() {
        _completionProofImagePath = null;
      });
    }
  }

  Future<void> _respondToAssignment(bool accept) async {
    await _runOrderAction(
      () => _ordersService.respondToAssignment(
        orderId: widget.orderId,
        accept: accept,
      ),
    );
  }

  Future<void> _updateOrderStatus(String status) async {
    Position? position;
    if (status == 'on_the_way') {
      position = await _resolveCurrentPosition(silent: false);
      if (position == null) {
        return;
      }
    }

    await _runOrderAction(
      () => _ordersService.updateOrderStatus(
        orderId: widget.orderId,
        status: status,
        lat: position?.latitude,
        lng: position?.longitude,
        accuracy: position?.accuracy,
        speed: position?.speed,
        heading: position?.heading,
      ),
    );
  }

  Future<void> _setEstimate() async {
    final minEstimate = double.tryParse(_minEstimateController.text) ?? 0;
    final maxEstimate = double.tryParse(_maxEstimateController.text) ?? 0;

    if (minEstimate <= 0 || maxEstimate <= 0 || maxEstimate < minEstimate) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_invalid_estimate_range')),
        ),
      );
      return;
    }

    await _runOrderAction(
      () => _ordersService.setEstimate(
        orderId: widget.orderId,
        minEstimate: minEstimate,
        maxEstimate: maxEstimate,
      ),
    );
  }

  bool _shouldAutoTrackLocation() {
    final status = (_order?['status'] ?? '').toString().trim().toLowerCase();
    return status == 'on_the_way';
  }

  bool _shouldAutoRefreshOrder() {
    if (_order == null) return false;

    final status = (_order?['status'] ?? '').toString().trim().toLowerCase();
    if (status == 'completed' || status == 'cancelled') {
      return false;
    }

    final paymentStatus = (_order?['payment_status'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    final invoiceStatus = (_order?['invoice_status'] ?? 'none')
        .toString()
        .trim()
        .toLowerCase();

    if (paymentStatus == 'paid') {
      return false;
    }

    return invoiceStatus == 'pending' || invoiceStatus == 'approved';
  }

  void _syncAutoLocationTimer() {
    final shouldTrack = _shouldAutoTrackLocation();
    if (!shouldTrack) {
      _autoLocationTimer?.cancel();
      _autoLocationTimer = null;
      return;
    }

    if (_autoLocationTimer != null) {
      return;
    }

    _sendLiveLocation(silent: true);
    _autoLocationTimer = Timer.periodic(const Duration(seconds: 20), (timer) {
      if (!mounted) {
        timer.cancel();
        _autoLocationTimer = null;
        return;
      }
      if (!_shouldAutoTrackLocation()) {
        timer.cancel();
        _autoLocationTimer = null;
        return;
      }
      _sendLiveLocation(silent: true);
    });
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

    _autoRefreshTimer = Timer.periodic(const Duration(seconds: 20), (timer) {
      if (!mounted) {
        timer.cancel();
        _autoRefreshTimer = null;
        return;
      }
      if (!_shouldAutoRefreshOrder()) {
        timer.cancel();
        _autoRefreshTimer = null;
        return;
      }
      _loadOrder(skipDraftSync: true, skipSpareCatalog: true);
    });
  }

  Future<void> _pickCompletionProofImage() async {
    final XFile? file = await _completionProofImagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 80,
      maxWidth: 1920,
    );
    if (file == null || !mounted) return;

    setState(() {
      _completionProofImagePath = file.path;
    });
  }

  Future<Position?> _resolveCurrentPosition({required bool silent}) async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      if (!mounted || silent) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_enable_location_service')),
        ),
      );
      return null;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      if (!mounted || silent) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_location_permission_denied')),
        ),
      );
      return null;
    }

    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );
    } catch (_) {
      if (!mounted || silent) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_error_generic')),
          backgroundColor: Colors.red,
        ),
      );
      return null;
    }
  }

  Future<void> _sendLiveLocation({bool silent = false}) async {
    if (_isSendingLiveLocation) {
      return;
    }

    if (!_shouldAutoTrackLocation()) {
      if (!mounted || silent) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('provider_live_location_on_the_way_only')),
        ),
      );
      return;
    }

    _isSendingLiveLocation = true;
    try {
      final position = await _resolveCurrentPosition(silent: silent);
      if (position == null) {
        return;
      }

      final response = await _ordersService.updateLiveLocation(
        orderId: widget.orderId,
        lat: position.latitude,
        lng: position.longitude,
        accuracy: position.accuracy,
        speed: position.speed,
        heading: position.heading,
      );

      if (!mounted) return;

      if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.message ??
                  (response.success
                      ? context.tr('order_action_success_default')
                      : context.tr('order_action_failed_default')),
            ),
            backgroundColor: response.success ? Colors.green : Colors.red,
          ),
        );
      }
    } catch (_) {
      if (!mounted || silent) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_error_generic')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      _isSendingLiveLocation = false;
    }
  }

  Future<void> _runOrderAction(Future<ApiResponse> Function() action) async {
    if (_isActionRunning) return;
    setState(() => _isActionRunning = true);

    try {
      final response = await action();
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.message ??
                (response.success
                    ? context.tr('order_action_success_default')
                    : context.tr('order_action_failed_default')),
          ),
          backgroundColor: response.success ? Colors.green : Colors.red,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_error_generic')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isActionRunning = false);
      }
      await _loadOrder();
    }
  }

  Future<void> _submitInvoice() async {
    final double labor = double.tryParse(_laborCostController.text) ?? 0;
    final double parts = _effectivePartsCost;
    final sparePartsPayload = _buildSpareInvoicePayload();

    final hasIncompleteSpareRows = _invoiceSpareDrafts.any(
      (item) => (item.sparePartId ?? 0) <= 0,
    );
    if (hasIncompleteSpareRows) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('provider_incomplete_spare_line'))),
      );
      return;
    }

    if (labor <= 0 && parts <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('order_action_invoice_cost_required')),
        ),
      );
      return;
    }

    await _runOrderAction(
      () => _ordersService.submitInvoice(
        orderId: widget.orderId,
        laborCost: labor,
        partsCost: parts,
        notes: _notesController.text,
        spareParts: sparePartsPayload,
      ),
    );
  }

  int _statusProgressIndex(String status) {
    switch (status) {
      case 'pending':
      case 'assigned':
        return 0;
      case 'accepted':
        return 1;
      case 'on_the_way':
        return 2;
      case 'arrived':
        return 3;
      case 'in_progress':
        return 4;
      case 'completed':
        return 5;
      default:
        return -1;
    }
  }

  Widget _buildStatusFlowCard(String status) {
    final normalizedStatus = status.toLowerCase();
    final isCancelledFlow =
        normalizedStatus == 'cancelled' || normalizedStatus == 'rejected';
    final currentIndex = _statusProgressIndex(normalizedStatus);

    final steps = [
      {
        'label': _statusText('assigned'),
        'icon': Icons.assignment_turned_in_outlined,
      },
      {'label': _statusText('accepted'), 'icon': Icons.check_circle_outline},
      {
        'label': _statusText('on_the_way'),
        'icon': Icons.directions_car_filled_outlined,
      },
      {'label': _statusText('arrived'), 'icon': Icons.location_on_outlined},
      {'label': _statusText('in_progress'), 'icon': Icons.handyman_outlined},
      {'label': _statusText('completed'), 'icon': Icons.task_alt_outlined},
    ];

    return Card(
      elevation: 1.5,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.route_outlined,
                  color: Colors.blueGrey,
                  size: 18,
                ),
                const SizedBox(width: 8),
                Text(
                  context.tr('provider_order_status_flow'),
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ],
            ),
            if (isCancelledFlow) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Text(
                  context
                      .tr('provider_order_status_flow_stopped')
                      .replaceFirst('{status}', _statusText(normalizedStatus)),
                  style: const TextStyle(
                    color: Colors.black87,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 10),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (int i = 0; i < steps.length; i++) ...[
                    Builder(
                      builder: (_) {
                        final isDone =
                            !isCancelledFlow &&
                            currentIndex >= 0 &&
                            i < currentIndex;
                        final isActive =
                            !isCancelledFlow &&
                            currentIndex >= 0 &&
                            i == currentIndex;

                        final bgColor = isDone
                            ? Colors.green.shade600
                            : isActive
                            ? Colors.blue.shade600
                            : Colors.grey.shade200;
                        final fgColor = isDone || isActive
                            ? Colors.white
                            : Colors.black54;
                        final titleColor = isActive
                            ? Colors.blue.shade700
                            : (isDone ? Colors.green.shade700 : Colors.black54);

                        return Container(
                          width: 116,
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: isActive
                                  ? Colors.blue.shade200
                                  : Colors.grey.shade300,
                            ),
                          ),
                          child: Column(
                            children: [
                              Container(
                                width: 30,
                                height: 30,
                                decoration: BoxDecoration(
                                  color: bgColor,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  steps[i]['icon'] as IconData,
                                  size: 16,
                                  color: fgColor,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                steps[i]['label'].toString(),
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: titleColor,
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    if (i < steps.length - 1)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 6),
                        child: Icon(
                          Icons.chevron_right_rounded,
                          color: Colors.grey.shade400,
                          size: 18,
                        ),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _autoLocationTimer?.cancel();
    _autoRefreshTimer?.cancel();
    _laborCostController.dispose();
    _partsCostController.dispose();
    _notesController.dispose();
    _minEstimateController.dispose();
    _maxEstimateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_order == null) {
      return Scaffold(body: Center(child: Text(context.tr('order_not_found'))));
    }

    final status = (_order!['status'] ?? '').toString().toLowerCase();
    final invoiceStatus = (_order!['invoice_status'] ?? 'none')
        .toString()
        .toLowerCase();
    final minEst = _order!['min_estimate'];
    final maxEst = _order!['max_estimate'];
    final attachmentUrls = _extractAttachmentImages();

    Color getStatusColor(String s) {
      if (s == 'completed') return Colors.green;
      if (s == 'cancelled') return Colors.red;
      if (s == 'in_progress') return Colors.blue;
      return Colors.orange;
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      appBar: AppBar(
        title: Text(
          '${context.tr('order_number')} #${_order!['order_number']}',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        centerTitle: true,
        elevation: 0,
        backgroundColor: Colors.white,
        foregroundColor: Colors.black,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // 1. Status Banner
            Container(
              padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
              decoration: BoxDecoration(
                color: getStatusColor(status).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: getStatusColor(status).withValues(alpha: 0.3),
                ),
              ),
              child: Row(
                children: [
                  Icon(Icons.info_outline, color: getStatusColor(status)),
                  const SizedBox(width: 12),
                  Text(
                    '${context.tr('order_status')}: ${_statusText(status)}',
                    style: TextStyle(
                      color: getStatusColor(status).withValues(alpha: 0.9),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Spacer(),
                  if (invoiceStatus != 'none')
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: invoiceStatus == 'approved'
                            ? Colors.green
                            : invoiceStatus == 'rejected'
                            ? Colors.red
                            : Colors.orange,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        invoiceStatus == 'approved'
                            ? context.tr('invoice_approved')
                            : invoiceStatus == 'rejected'
                            ? context.tr('status_rejected')
                            : context.tr('invoice_pending_review'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            _buildStatusFlowCard(status),
            const SizedBox(height: 16),

            // 2. Customer Info
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.person_outline, color: Colors.grey),
                        const SizedBox(width: 8),
                        Text(
                          context.tr('customer_info'),
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 22,
                          backgroundColor: Colors.grey.shade200,
                          backgroundImage:
                              _displayCustomerAvatarUrl().isNotEmpty
                              ? NetworkImage(_displayCustomerAvatarUrl())
                              : null,
                          child: _displayCustomerAvatarUrl().isEmpty
                              ? const Icon(Icons.person, color: Colors.grey)
                              : null,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _displayCustomerName(),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                _displayCustomerPhone(),
                                style: const TextStyle(
                                  color: Colors.black54,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    InkWell(
                      onTap: _openOrderLocationOnMap,
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.location_on_outlined,
                              color: Colors.redAccent,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _displayOrderAddress(),
                                style: const TextStyle(fontSize: 13),
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Icon(
                              Icons.map_outlined,
                              color: Colors.blueGrey,
                              size: 18,
                            ),
                          ],
                        ),
                      ),
                    ),
                    _buildInfoRow(
                      Icons.calendar_today_outlined,
                      '${_order!['scheduled_date']} - ${_order!['scheduled_time']}',
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // 3. Diagnostic & Operations Estimate
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.build_circle_outlined,
                          color: Colors.grey,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          context.tr('work_details'),
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    Text(
                      '${context.tr('problem_type_label')}: ${_order!['problem_details']?['type'] ?? context.tr('problem_type_not_specified')}',
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${context.tr('customer_description_label')}: ${_order!['notes'] ?? _order!['problem_details']?['user_desc'] ?? context.tr('not_available')}',
                    ),

                    if (attachmentUrls.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      SizedBox(
                        height: 90,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          itemCount: attachmentUrls.length,
                          separatorBuilder: (_, __) => const SizedBox(width: 8),
                          itemBuilder: (ctx, i) => GestureDetector(
                            onTap: () => _showImagePreview(attachmentUrls[i]),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.network(
                                attachmentUrls[i],
                                width: 90,
                                height: 90,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => Container(
                                  width: 90,
                                  height: 90,
                                  color: Colors.grey.shade200,
                                  child: const Icon(
                                    Icons.broken_image_outlined,
                                    color: Colors.grey,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],

                    if (minEst != null || maxEst != null) ...[
                      const SizedBox(height: 20),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E1), // Amber/Yellowish
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFFFC107)),
                        ),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(
                                  Icons.lightbulb_outline,
                                  color: Color(0xFFFFA000),
                                  size: 18,
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  context.tr('operations_estimate_guidance'),
                                  style: const TextStyle(
                                    color: Color(0xFFFFA000),
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                SaudiRiyalText(
                                  text: '${minEst ?? 0}',
                                  style: const TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black87,
                                  ),
                                  iconSize: 16,
                                ),
                                const Text(
                                  ' - ',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black87,
                                  ),
                                ),
                                SaudiRiyalText(
                                  text: '${maxEst ?? 0}',
                                  style: const TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black87,
                                  ),
                                  iconSize: 16,
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 4. Action Buttons
            if ((status == 'pending' || status == 'assigned') &&
                (_order!['can_provider_accept'] == true ||
                    _order!['can_provider_reject'] == true)) ...[
              Row(
                children: [
                  if (_order!['can_provider_accept'] == true)
                    Expanded(
                      child: _buildActionButton(
                        label: context.tr('accept_order'),
                        color: Colors.green,
                        icon: Icons.check_circle_outline,
                        onTap: () => _respondToAssignment(true),
                      ),
                    ),
                  if (_order!['can_provider_accept'] == true &&
                      _order!['can_provider_reject'] == true)
                    const SizedBox(width: 10),
                  if (_order!['can_provider_reject'] == true)
                    Expanded(
                      child: _buildActionButton(
                        label: context.tr('reject_order'),
                        color: Colors.red,
                        icon: Icons.cancel_outlined,
                        onTap: () => _respondToAssignment(false),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 12),
            ],

            if (status == 'accepted' &&
                _order!['can_provider_mark_on_the_way'] == true)
              _buildActionButton(
                label: context.tr('heading_to_customer'),
                color: Colors.blue,
                icon: Icons.directions_car_filled_outlined,
                onTap: () => _updateOrderStatus('on_the_way'),
              ),

            if (status == 'on_the_way')
              _buildActionButton(
                label: context.tr('arrived_at_customer'),
                color: Colors.indigo,
                icon: Icons.location_on_outlined,
                onTap: () => _updateOrderStatus('arrived'),
              ),

            if (status == 'arrived')
              _buildActionButton(
                label: context.tr('start_execution'),
                color: Colors.green,
                icon: Icons.play_arrow,
                onTap: () => _updateOrderStatus('in_progress'),
              ),

            if (status == 'on_the_way') ...[
              const SizedBox(height: 12),
              _buildActionButton(
                label: context.tr('update_my_location'),
                color: Colors.teal,
                icon: Icons.my_location,
                onTap: _sendLiveLocation,
              ),
            ],

            if (!['completed', 'cancelled'].contains(status) &&
                invoiceStatus != 'approved') ...[
              const SizedBox(height: 20),
              Card(
                elevation: 2,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('initial_estimate_for_customer'),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _minEstimateController,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: context.tr('estimate_min_label'),
                          border: const OutlineInputBorder(),
                          suffixIcon: const Padding(
                            padding: EdgeInsetsDirectional.only(end: 10),
                            child: SaudiRiyalIcon(size: 16),
                          ),
                          suffixIconConstraints: const BoxConstraints(
                            minHeight: 20,
                            minWidth: 32,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _maxEstimateController,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: context.tr('estimate_max_label'),
                          border: const OutlineInputBorder(),
                          suffixIcon: const Padding(
                            padding: EdgeInsetsDirectional.only(end: 10),
                            child: SaudiRiyalIcon(size: 16),
                          ),
                          suffixIconConstraints: const BoxConstraints(
                            minHeight: 20,
                            minWidth: 32,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: _isActionRunning ? null : _setEstimate,
                          icon: const Icon(Icons.price_change_outlined),
                          label: Text(context.tr('save_estimate')),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],

            if (status == 'in_progress') ...[
              if (invoiceStatus == 'rejected')
                Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: Colors.orange.shade300),
                  ),
                  child: Text(
                    context.tr('provider_invoice_rejected_update_hint'),
                    style: TextStyle(
                      color: Colors.black87,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              Text(
                context.tr('issue_invoice'),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              Card(
                elevation: 2,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      TextField(
                        controller: _laborCostController,
                        keyboardType: TextInputType.number,
                        onChanged: (_) => setState(() {}),
                        decoration: InputDecoration(
                          labelText: context.tr('labor_cost_label'),
                          border: const OutlineInputBorder(),
                          prefixIcon: const Icon(Icons.person),
                          suffixIcon: const Padding(
                            padding: EdgeInsetsDirectional.only(end: 10),
                            child: SaudiRiyalIcon(size: 16),
                          ),
                          suffixIconConstraints: const BoxConstraints(
                            minHeight: 20,
                            minWidth: 32,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildSpareInvoiceEditor(),
                      const SizedBox(height: 16),
                      TextField(
                        controller: _notesController,
                        maxLines: 2,
                        decoration: InputDecoration(
                          labelText: context.tr('technician_notes'),
                          border: const OutlineInputBorder(),
                          prefixIcon: const Icon(Icons.note),
                        ),
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () => _submitInvoice(),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.purple,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          icon: const Icon(Icons.send),
                          label: Text(context.tr('send_invoice_for_approval')),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],

            if (status == 'in_progress' && invoiceStatus == 'pending')
              Container(
                margin: const EdgeInsets.only(top: 16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.orange.shade200),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.hourglass_empty, color: Colors.orange),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        context.tr('waiting_customer_invoice_approval'),
                      ),
                    ),
                  ],
                ),
              ),

            if (invoiceStatus != 'none') ...[
              const SizedBox(height: 16),
              _buildInvoiceReportCard(invoiceStatus),
            ],

            if (invoiceStatus == 'approved' && status != 'completed') ...[
              const SizedBox(height: 24),
              Card(
                elevation: 2,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('provider_completion_proof_label'),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isActionRunning
                                  ? null
                                  : _pickCompletionProofImage,
                              style: OutlinedButton.styleFrom(
                                foregroundColor: _yellowTextColor,
                                side: const BorderSide(
                                  color: _yellowBorderColor,
                                  width: 1.6,
                                ),
                              ),
                              icon: const Icon(
                                Icons.photo_camera_back_outlined,
                              ),
                              label: Text(
                                _completionProofImagePath == null
                                    ? context.tr(
                                        'provider_choose_completion_proof',
                                      )
                                    : context.tr(
                                        'provider_change_completion_proof',
                                      ),
                              ),
                            ),
                          ),
                          if (_completionProofImagePath != null) ...[
                            const SizedBox(width: 8),
                            IconButton(
                              tooltip: context.tr(
                                'provider_remove_completion_proof',
                              ),
                              onPressed: _isActionRunning
                                  ? null
                                  : () {
                                      setState(() {
                                        _completionProofImagePath = null;
                                      });
                                    },
                              icon: const Icon(Icons.delete_outline),
                            ),
                          ],
                        ],
                      ),
                      if (_completionProofImagePath != null) ...[
                        const SizedBox(height: 10),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Image.file(
                            File(_completionProofImagePath!),
                            height: 120,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              _buildActionButton(
                label: context.tr('complete_job'),
                color: Colors.green.shade700,
                icon: Icons.check_circle,
                onTap: () => _completeJob(),
              ),
            ],

            if (status == 'completed')
              Container(
                margin: const EdgeInsets.only(top: 16),
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.green.shade200),
                ),
                child: Column(
                  children: [
                    const Icon(
                      Icons.check_circle,
                      color: Colors.green,
                      size: 48,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      context.tr('job_completed_successfully'),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.green,
                      ),
                    ),
                  ],
                ),
              ),

            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20, color: Colors.grey.shade600),
        const SizedBox(width: 12),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
      ],
    );
  }

  Widget _buildSpareInvoiceEditor() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                context.tr('parts_cost_label'),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                ),
              ),
            ),
            OutlinedButton.icon(
              onPressed: _isActionRunning ? null : _addSpareInvoiceLine,
              style: OutlinedButton.styleFrom(
                foregroundColor: _yellowTextColor,
                side: const BorderSide(color: _yellowBorderColor, width: 1.6),
              ),
              icon: const Icon(Icons.add, size: 16),
              label: Text(context.tr('provider_add_spare_part')),
            ),
          ],
        ),
        if (_isLoadingSpareCatalog)
          const Padding(
            padding: EdgeInsets.only(bottom: 10),
            child: LinearProgressIndicator(minHeight: 2),
          ),
        if (_spareCatalog.isEmpty && !_isLoadingSpareCatalog)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            margin: const EdgeInsets.only(bottom: 10),
            decoration: BoxDecoration(
              color: Colors.orange.shade50,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.orange.shade200),
            ),
            child: Text(
              context.tr('provider_spare_catalog_unavailable'),
              style: TextStyle(color: Colors.black54, fontSize: 12),
            ),
          ),
        if (_invoiceSpareDrafts.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            margin: const EdgeInsets.only(bottom: 10),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade300),
            ),
            child: Text(
              context.tr('provider_no_spare_parts_added'),
              style: TextStyle(color: Colors.black54, fontSize: 12),
            ),
          ),
        ...List.generate(
          _invoiceSpareDrafts.length,
          (index) => _buildSpareInvoiceLineCard(index),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _partsCostController,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(
            labelText: context.tr('provider_manual_parts_cost_label'),
            hintText: context.tr('provider_manual_parts_cost_hint'),
            border: const OutlineInputBorder(),
            prefixIcon: const Icon(Icons.add_business_outlined),
            suffixIcon: const Padding(
              padding: EdgeInsetsDirectional.only(end: 10),
              child: SaudiRiyalIcon(size: 16),
            ),
            suffixIconConstraints: const BoxConstraints(
              minHeight: 20,
              minWidth: 32,
            ),
          ),
        ),
        const SizedBox(height: 10),
        _priceSummaryRow(
          context.tr('provider_spare_parts_catalog_total'),
          _invoiceSparePartsTotal,
        ),
        if (_manualPartsCost > 0)
          _priceSummaryRow(
            context.tr('provider_spare_parts_manual_addition'),
            _manualPartsCost,
          ),
        const Divider(height: 14),
        _priceSummaryRow(context.tr('parts_cost_label'), _effectivePartsCost),
        _priceSummaryRow(context.tr('labor_cost_label'), _manualLaborCost),
        const Divider(height: 14),
        _priceSummaryRow(
          context.tr('provider_invoice_total_before_issue'),
          _invoiceDraftGrandTotal,
          isTotal: true,
        ),
      ],
    );
  }

  Widget _buildSpareInvoiceLineCard(int index) {
    final item = _invoiceSpareDrafts[index];
    final currentPart = item.sparePartId == null
        ? null
        : _catalogPartById(item.sparePartId!);
    final selectedValue = currentPart != null ? item.sparePartId : null;
    final lineTotal = item.totalPrice;
    final withInstallationSelected = item.pricingMode != 'without_installation';
    final withoutInstallationSelected =
        item.pricingMode == 'without_installation';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  context
                      .tr('provider_spare_line_number')
                      .replaceFirst('{number}', '${index + 1}'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
              ),
              IconButton(
                onPressed: _isActionRunning
                    ? null
                    : () => _removeSpareInvoiceLine(index),
                icon: const Icon(Icons.delete_outline, color: Colors.red),
                tooltip: context.tr('provider_remove_line'),
              ),
            ],
          ),
          DropdownButtonFormField<int>(
            key: ValueKey('spare_line_${index}_${selectedValue ?? 0}'),
            initialValue: selectedValue,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.tr('provider_spare_part_label'),
              border: OutlineInputBorder(),
            ),
            items: _spareCatalog.map((part) {
              final partId = int.tryParse('${part['id'] ?? 0}') ?? 0;
              final stock = int.tryParse('${part['stock_quantity'] ?? 0}') ?? 0;
              return DropdownMenuItem<int>(
                value: partId,
                child: Text(
                  context
                      .tr('provider_spare_stock_option')
                      .replaceFirst('{name}', _spareNameFromCatalog(part))
                      .replaceFirst('{stock}', '$stock'),
                  overflow: TextOverflow.ellipsis,
                ),
              );
            }).toList(),
            onChanged: _isActionRunning
                ? null
                : (value) => _setSpareInvoicePart(index, value),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    ChoiceChip(
                      label: Text(_withInstallationLabel()),
                      labelStyle: TextStyle(
                        color: withInstallationSelected
                            ? _yellowTextColor
                            : Colors.black87,
                        fontWeight: FontWeight.w700,
                      ),
                      checkmarkColor: _yellowTextColor,
                      selectedColor: _selectedChipColor,
                      backgroundColor: Colors.white,
                      side: BorderSide(
                        color: withInstallationSelected
                            ? _yellowBorderColor
                            : Colors.black87,
                        width: 1.3,
                      ),
                      selected: withInstallationSelected,
                      onSelected: _isActionRunning
                          ? null
                          : (_) => _setSpareInvoicePricingMode(
                              index,
                              'with_installation',
                            ),
                    ),
                    ChoiceChip(
                      label: Text(_withoutInstallationLabel()),
                      labelStyle: TextStyle(
                        color: withoutInstallationSelected
                            ? _yellowTextColor
                            : Colors.black87,
                        fontWeight: FontWeight.w700,
                      ),
                      checkmarkColor: _yellowTextColor,
                      selectedColor: _selectedChipColor,
                      backgroundColor: Colors.white,
                      side: BorderSide(
                        color: withoutInstallationSelected
                            ? _yellowBorderColor
                            : Colors.black87,
                        width: 1.3,
                      ),
                      selected: withoutInstallationSelected,
                      onSelected: _isActionRunning
                          ? null
                          : (_) => _setSpareInvoicePricingMode(
                              index,
                              'without_installation',
                            ),
                    ),
                  ],
                ),
              ),
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    IconButton(
                      visualDensity: VisualDensity.compact,
                      onPressed: _isActionRunning || item.quantity <= 1
                          ? null
                          : () => _setSpareInvoiceQuantity(
                              index,
                              item.quantity - 1,
                            ),
                      icon: const Icon(Icons.remove, size: 16),
                    ),
                    Text(
                      '${item.quantity}',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                    IconButton(
                      visualDensity: VisualDensity.compact,
                      onPressed: _isActionRunning
                          ? null
                          : () => _setSpareInvoiceQuantity(
                              index,
                              item.quantity + 1,
                            ),
                      icon: const Icon(Icons.add, size: 16),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          _priceSummaryRow(context.tr('provider_unit_price'), item.unitPrice),
          _priceSummaryRow(
            context.tr('provider_line_total'),
            lineTotal,
            isTotal: true,
          ),
        ],
      ),
    );
  }

  Widget _priceSummaryRow(String label, double amount, {bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              color: Colors.black87,
              fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
            ),
          ),
          SaudiRiyalText(
            text: amount.toStringAsFixed(2),
            style: TextStyle(
              color: isTotal ? Colors.black87 : Colors.black54,
              fontWeight: isTotal ? FontWeight.bold : FontWeight.w600,
            ),
            iconSize: 13,
          ),
        ],
      ),
    );
  }

  String _invoiceStatusLabel(String status) {
    switch (status) {
      case 'approved':
        return context.tr('invoice_approved');
      case 'paid':
        return _paidInvoiceLabel();
      case 'pending':
        return context.tr('invoice_pending_review');
      case 'rejected':
        return context.tr('status_rejected');
      default:
        return status;
    }
  }

  String _invoiceItemSourceLabel(dynamic source) {
    final normalized = (source ?? '').toString().trim().toLowerCase();
    switch (normalized) {
      case 'provider_requested':
        return context.tr('provider_invoice_item_source_provider');
      case 'manual_parts_cost':
        return context.tr('provider_invoice_item_source_manual');
      case 'customer_requested':
        return context.tr('provider_invoice_item_source_customer');
      case 'catalog':
        return context.tr('provider_invoice_item_source_catalog');
      default:
        return '';
    }
  }

  String _paidInvoiceLabel() {
    final languageCode = Localizations.localeOf(context).languageCode;
    switch (languageCode) {
      case 'en':
        return 'Invoice paid';
      case 'ur':
        return 'انوائس ادا ہو چکی';
      default:
        return 'الفاتورة مدفوعة';
    }
  }

  Widget _buildInvoiceReportCard(String invoiceStatus) {
    final invoiceItems = _normalizeMapList(_order?['invoice_items']);
    final requestedParts = _normalizeMapList(_order?['requested_spare_parts']);
    final rows = invoiceItems.isNotEmpty ? invoiceItems : requestedParts;
    final isPositiveInvoiceStatus =
        invoiceStatus == 'approved' || invoiceStatus == 'paid';

    final labor = _toDouble(_order?['labor_cost']);
    final parts = _toDouble(_order?['parts_cost']);
    final total = (labor + parts) > 0
        ? (labor + parts)
        : _toDouble(_order?['total_amount']);

    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.receipt_long_outlined, color: Colors.black54),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    context.tr('provider_invoice_report'),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: isPositiveInvoiceStatus
                        ? Colors.green.withValues(alpha: 0.15)
                        : invoiceStatus == 'rejected'
                        ? Colors.red.withValues(alpha: 0.12)
                        : Colors.orange.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    _invoiceStatusLabel(invoiceStatus),
                    style: TextStyle(
                      color: isPositiveInvoiceStatus
                          ? Colors.green.shade800
                          : invoiceStatus == 'rejected'
                          ? Colors.red.shade800
                          : Colors.orange.shade900,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (rows.isNotEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.grey.shade300),
                ),
                child: Column(
                  children: rows.map((item) {
                    final mapped = Map<String, dynamic>.from(
                      item.map((key, value) => MapEntry(key.toString(), value)),
                    );
                    final quantity =
                        int.tryParse('${mapped['quantity'] ?? 1}') ?? 1;
                    final unitPrice = _toDouble(mapped['unit_price']);
                    final totalPrice = _toDouble(mapped['total_price']) > 0
                        ? _toDouble(mapped['total_price'])
                        : (unitPrice * quantity);
                    final pricingMode = (mapped['pricing_mode'] ?? '')
                        .toString()
                        .trim();
                    final modeLabel = pricingMode == 'without_installation'
                        ? _withoutInstallationLabel()
                        : _withInstallationLabel();
                    final sourceLabel = _invoiceItemSourceLabel(
                      mapped['source'],
                    );
                    final name =
                        (mapped['name'] ??
                                mapped['spare_part_name'] ??
                                mapped['title'] ??
                                context.tr('provider_spare_part_default_name'))
                            .toString();

                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  name,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 13,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  sourceLabel.isNotEmpty
                                      ? '$modeLabel • x$quantity • $sourceLabel'
                                      : '$modeLabel • x$quantity',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: Colors.black54,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          SaudiRiyalText(
                            text: totalPrice.toStringAsFixed(2),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              color: Colors.black87,
                            ),
                            iconSize: 13,
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                ),
              ),
            const SizedBox(height: 10),
            _priceSummaryRow(context.tr('labor_cost_label'), labor),
            _priceSummaryRow(context.tr('parts_cost_label'), parts),
            const Divider(height: 14),
            _priceSummaryRow(
              context.tr('provider_invoice_total'),
              total,
              isTotal: true,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButton({
    required String label,
    required Color color,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton.icon(
        onPressed: _isActionRunning ? null : onTap,
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          elevation: 4,
        ),
        icon: Icon(icon),
        label: Text(
          label,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}

class _InvoiceSpareDraft {
  _InvoiceSpareDraft({
    this.sparePartId,
    this.name = '',
    this.quantity = 1,
    this.pricingMode = 'with_installation',
    this.requiresInstallation = true,
    this.unitPrice = 0,
    this.notes = '',
  });

  int? sparePartId;
  String name;
  int quantity;
  String pricingMode;
  bool requiresInstallation;
  double unitPrice;
  String notes;

  double get totalPrice => (quantity > 0 ? quantity : 1) * unitPrice;
}
