// All Spares Screen
// شاشة جميع قطع الغيار

import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../providers/app_settings_provider.dart';
import '../services/app_localizations.dart';
import '../services/addresses_service.dart';
import '../services/home_service.dart';
import '../services/orders_service.dart';
import '../utils/order_tracking_navigation.dart';
import '../utils/saudi_riyal_icon.dart';
import '../widgets/guest_guard.dart';
import 'location_picker_screen.dart';
import 'payment_screen.dart';

class _SpareCheckoutData {
  final Map<String, dynamic> addressData;
  final DateTime date;
  final TimeOfDay time;
  final String notes;

  const _SpareCheckoutData({
    required this.addressData,
    required this.date,
    required this.time,
    required this.notes,
  });
}

class AllSparesScreen extends StatefulWidget {
  final VoidCallback onBack;
  final int? autoAddSpareId;

  const AllSparesScreen({super.key, required this.onBack, this.autoAddSpareId});

  @override
  State<AllSparesScreen> createState() => _AllSparesScreenState();
}

class _AllSparesScreenState extends State<AllSparesScreen> {
  static const String _allCategoryKey = 'all';
  String _selectedCategoryKey = _allCategoryKey;

  final Map<String, int> _cartQuantities = {};
  final Map<String, Map<String, dynamic>> _cartItems = {};
  final Map<String, String> _cartPricingModes = {};

  List<Map<String, dynamic>> _categories = const [
    {'key': _allCategoryKey, 'name': '', 'icon': '🔧'},
  ];

  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _isAddressesLoading = false;
  List<Map<String, dynamic>> _allSpares = [];
  List<Map<String, dynamic>> _savedAddresses = [];
  final HomeService _homeService = HomeService();
  final OrdersService _ordersService = OrdersService();
  final AddressesService _addressesService = AddressesService();
  bool _autoAddHandled = false;
  Timer? _topNoticeTimer;
  String? _topNoticeMessage;
  static const List<Shadow> _headerTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];

  @override
  void initState() {
    super.initState();
    _fetchSpares();
    _loadSavedAddresses(showLoading: false);
  }

  Future<void> _fetchSpares() async {
    try {
      final locationProvider = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest ||
          !locationProvider.hasSelectedLocation;
      final response = await _homeService.getSpareParts(
        lat: locationProvider.requestLat,
        lng: locationProvider.requestLng,
        countryCode: locationProvider.requestCountryCode,
        allowOutside: allowOutside,
      );

      if (!mounted) return;

      if (response.success && response.data != null) {
        final loadedSpares = List<Map<String, dynamic>>.from(response.data);
        final categories = _buildCategoriesFromSpares(loadedSpares);
        final selectedCategoryStillExists = categories.any(
          (item) =>
              (item['key'] ?? _allCategoryKey).toString() ==
              _selectedCategoryKey,
        );
        setState(() {
          _allSpares = loadedSpares;
          _categories = categories;
          if (!selectedCategoryStillExists) {
            _selectedCategoryKey = _allCategoryKey;
          }
          _isLoading = false;
        });
        _maybeHandleAutoAddSpare(loadedSpares);
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Error fetching spares: $e');
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  void _maybeHandleAutoAddSpare(List<Map<String, dynamic>> loadedSpares) {
    if (_autoAddHandled) return;
    final targetId = widget.autoAddSpareId;
    if (targetId == null || targetId <= 0) return;

    Map<String, dynamic>? targetSpare;
    for (final spare in loadedSpares) {
      final spareId = int.tryParse('${spare['id']}') ?? 0;
      if (spareId == targetId) {
        targetSpare = spare;
        break;
      }
    }

    if (targetSpare == null) return;
    _autoAddHandled = true;
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      await _addSpareToCart(targetSpare!);
    });
  }

  @override
  void dispose() {
    _topNoticeTimer?.cancel();
    super.dispose();
  }

  String _spareCategoryLabel(Map<String, dynamic> spare) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (spare['category_name_ar'] ?? '').toString().trim();
    final en = (spare['category_name_en'] ?? '').toString().trim();
    final fallback = (spare['category'] ?? '').toString().trim();

    if (lang == 'ar') {
      if (ar.isNotEmpty) return ar;
      if (en.isNotEmpty) return en;
      return fallback;
    }
    if (en.isNotEmpty) return en;
    if (ar.isNotEmpty) return ar;
    return fallback;
  }

  String _spareServiceLabel(Map<String, dynamic> service) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (service['name_ar'] ?? '').toString().trim();
    final en = (service['name_en'] ?? '').toString().trim();

    if (lang == 'ar') {
      if (ar.isNotEmpty) return ar;
      if (en.isNotEmpty) return en;
      return '';
    }
    if (en.isNotEmpty) return en;
    if (ar.isNotEmpty) return ar;
    return '';
  }

  String _normalizedCategoryToken(String value) {
    return value.trim().toLowerCase().replaceAll(RegExp(r'\s+'), '_');
  }

  List<Map<String, String>> _spareFilterEntries(Map<String, dynamic> spare) {
    final entries = <Map<String, String>>[];
    final seen = <String>{};
    void addEntry(String key, String name) {
      if (key.isEmpty || seen.contains(key)) return;
      seen.add(key);
      entries.add({'key': key, 'name': name});
    }

    final linkedServicesRaw = spare['linked_services'];
    if (linkedServicesRaw is List) {
      for (final serviceRaw in linkedServicesRaw) {
        if (serviceRaw is! Map) continue;
        final service = Map<String, dynamic>.from(
          serviceRaw.map((key, value) => MapEntry(key.toString(), value)),
        );
        final serviceId = _toInt(service['id']);
        if (serviceId <= 0) continue;
        final label = _spareServiceLabel(service);
        addEntry('service:$serviceId', label);
      }
    }
    if (entries.isNotEmpty) {
      return entries;
    }

    final categoryId = _toInt(spare['category_id']);
    final categoryLabel = _spareCategoryLabel(spare);
    if (categoryId > 0) {
      addEntry('category:$categoryId', categoryLabel);
      return entries;
    }

    final token = _normalizedCategoryToken(categoryLabel);
    if (token.isNotEmpty) {
      addEntry('category_name:$token', categoryLabel);
    }
    return entries;
  }

  List<Map<String, dynamic>> _buildCategoriesFromSpares(
    List<Map<String, dynamic>> spares,
  ) {
    final categories = <Map<String, dynamic>>[
      {'key': _allCategoryKey, 'name': '', 'icon': '🔧'},
    ];
    final seen = <String>{_allCategoryKey};

    for (final spare in spares) {
      for (final filter in _spareFilterEntries(spare)) {
        final key = (filter['key'] ?? '').trim();
        if (key.isEmpty || seen.contains(key)) continue;
        seen.add(key);
        categories.add({
          'key': key,
          'name': filter['name'] ?? '',
          'icon': '🧰',
        });
      }
    }

    return categories;
  }

  List<Map<String, dynamic>> get _filteredSpares {
    if (_selectedCategoryKey == _allCategoryKey) return _allSpares;
    return _allSpares.where((spare) {
      final filters = _spareFilterEntries(spare);
      for (final filter in filters) {
        if ((filter['key'] ?? '') == _selectedCategoryKey) {
          return true;
        }
      }
      return false;
    }).toList();
  }

  String _spareKey(Map<String, dynamic> spare) {
    final id = spare['id']?.toString().trim() ?? '';
    if (id.isNotEmpty) return id;
    return (spare['name'] ?? DateTime.now().microsecondsSinceEpoch).toString();
  }

  String _withInstallationLabel() {
    return context.tr('order_tracking_with_installation');
  }

  String _withoutInstallationLabel() {
    return context.tr('order_tracking_without_installation');
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _textValue(dynamic value) {
    return (value ?? '').toString().trim();
  }

  double _priceWithInstallation(Map<String, dynamic> spare) {
    final fallback = _toDouble(spare['price']);
    final withInstallation = _toDouble(
      spare['price_with_installation'] ?? spare['priceWithInstallation'],
    );
    return withInstallation > 0 ? withInstallation : fallback;
  }

  double _priceWithoutInstallation(Map<String, dynamic> spare) {
    final withInstallation = _priceWithInstallation(spare);
    final withoutInstallation = _toDouble(
      spare['price_without_installation'] ?? spare['priceWithoutInstallation'],
    );
    return withoutInstallation > 0 ? withoutInstallation : withInstallation;
  }

  double _unitPriceByMode(Map<String, dynamic> spare, String pricingMode) {
    return pricingMode == 'without_installation'
        ? _priceWithoutInstallation(spare)
        : _priceWithInstallation(spare);
  }

  double? _oldPriceWithInstallation(Map<String, dynamic> spare) {
    final oldWith = _toDouble(
      spare['old_price_with_installation'] ?? spare['oldPriceWithInstallation'],
    );
    if (oldWith > 0) return oldWith;
    final oldFallback = _toDouble(spare['oldPrice'] ?? spare['old_price']);
    return oldFallback > 0 ? oldFallback : null;
  }

  double? _oldPriceWithoutInstallation(Map<String, dynamic> spare) {
    final oldWithout = _toDouble(
      spare['old_price_without_installation'] ??
          spare['oldPriceWithoutInstallation'],
    );
    if (oldWithout > 0) return oldWithout;
    return _oldPriceWithInstallation(spare);
  }

  String _spareWarrantyDuration(Map<String, dynamic> spare) {
    final explicit = _textValue(spare['warranty_duration']);
    if (explicit.isNotEmpty) return explicit;

    final legacy = _textValue(spare['warranty']);
    if (legacy.isNotEmpty) return legacy;

    return '';
  }

  String _spareWarrantyTerms(Map<String, dynamic> spare) {
    return _textValue(spare['warranty_terms']);
  }

  List<Map<String, String>> _cartWarrantyEntries() {
    final entries = <Map<String, String>>[];
    final signatures = <String>{};

    for (final entry in _cartItems.entries) {
      final spare = entry.value;
      final duration = _spareWarrantyDuration(spare);
      final terms = _spareWarrantyTerms(spare);
      if (duration.isEmpty && terms.isEmpty) continue;

      final name = _textValue(spare['name']).isNotEmpty
          ? _textValue(spare['name'])
          : context.tr('spare_part');
      final signature = '$name|$duration|$terms';
      if (!signatures.add(signature)) continue;

      entries.add({'name': name, 'duration': duration, 'terms': terms});
    }

    return entries;
  }

  int get _cartItemsCount {
    return _cartQuantities.values.fold(0, (sum, qty) => sum + qty);
  }

  double get _cartTotal {
    double total = 0;
    _cartQuantities.forEach((key, qty) {
      final spare = _cartItems[key];
      if (spare == null || qty <= 0) return;
      final mode = _cartPricingModes[key] ?? 'with_installation';
      total += _unitPriceByMode(spare, mode) * qty;
    });
    return total;
  }

  double get _cartTotalWithInstallation {
    double total = 0;
    _cartQuantities.forEach((key, qty) {
      final spare = _cartItems[key];
      if (spare == null || qty <= 0) return;
      final mode = _cartPricingModes[key] ?? 'with_installation';
      if (mode == 'without_installation') return;
      total += _unitPriceByMode(spare, mode) * qty;
    });
    return total;
  }

  bool get _hasInstallationItems {
    for (final entry in _cartQuantities.entries) {
      if (entry.value <= 0) continue;
      final mode = _cartPricingModes[entry.key] ?? 'with_installation';
      if (mode != 'without_installation') {
        return true;
      }
    }
    return false;
  }

  String _spareModeLabel(String mode) {
    return mode == 'without_installation'
        ? _withoutInstallationLabel()
        : _withInstallationLabel();
  }

  Future<String?> _pickPricingModeForSpare(Map<String, dynamic> spare) async {
    final withPrice = _priceWithInstallation(spare);
    final withoutPrice = _priceWithoutInstallation(spare);

    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                (spare['name'] ?? context.tr('spare_part')).toString(),
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 14),
              ListTile(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: AppColors.gray200),
                ),
                title: Text(_withInstallationLabel()),
                subtitle: Text(
                  context.tr('spares_mode_with_installation_subtitle'),
                ),
                trailing: SaudiRiyalText(
                  text: withPrice.toStringAsFixed(2),
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.bold,
                  ),
                  iconSize: 14,
                ),
                onTap: () => Navigator.pop(context, 'with_installation'),
              ),
              const SizedBox(height: 10),
              ListTile(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: AppColors.gray200),
                ),
                title: Text(_withoutInstallationLabel()),
                subtitle: Text(
                  context.tr('spares_mode_without_installation_subtitle'),
                ),
                trailing: SaudiRiyalText(
                  text: withoutPrice.toStringAsFixed(2),
                  style: const TextStyle(
                    color: AppColors.gray700,
                    fontWeight: FontWeight.bold,
                  ),
                  iconSize: 14,
                ),
                onTap: () => Navigator.pop(context, 'without_installation'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _addSpareToCart(Map<String, dynamic> spare) async {
    final mode = await _pickPricingModeForSpare(spare);
    if (mode == null || mode.isEmpty) return;
    if (!mounted) return;

    final key = _spareKey(spare);
    final current = _cartQuantities[key] ?? 0;

    setState(() {
      _cartItems[key] = spare;
      _cartPricingModes[key] = mode;
      _cartQuantities[key] = current + 1;
    });

    final name = (spare['name'] ?? context.tr('spare_part')).toString();
    final message = context
        .tr('spares_added_with_mode')
        .replaceAll('{name}', name)
        .replaceAll('{mode}', _spareModeLabel(mode));
    _showTopNotice(message);
  }

  void _showTopNotice(String message) {
    _topNoticeTimer?.cancel();
    if (!mounted) return;

    setState(() {
      _topNoticeMessage = message;
    });

    _topNoticeTimer = Timer(const Duration(milliseconds: 1600), () {
      if (!mounted) return;
      setState(() => _topNoticeMessage = null);
    });
  }

  Widget _buildTopNoticeOverlay() {
    return IgnorePointer(
      ignoring: true,
      child: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 220),
            switchInCurve: Curves.easeOut,
            switchOutCurve: Curves.easeIn,
            transitionBuilder: (child, animation) {
              final offset = Tween<Offset>(
                begin: const Offset(0, -0.18),
                end: Offset.zero,
              ).animate(animation);
              return FadeTransition(
                opacity: animation,
                child: SlideTransition(position: offset, child: child),
              );
            },
            child: _topNoticeMessage == null
                ? const SizedBox.shrink()
                : Container(
                    key: ValueKey<String>(_topNoticeMessage!),
                    margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFF111827),
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.18),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.check_circle_rounded,
                          color: Color(0xFF34D399),
                          size: 18,
                        ),
                        const SizedBox(width: 10),
                        Flexible(
                          child: Text(
                            _topNoticeMessage!,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              height: 1.35,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
        ),
      ),
    );
  }

  Widget _buildQuantityControl({
    required String key,
    required int quantity,
    bool compact = false,
  }) {
    final buttonSize = compact ? 28.0 : 34.0;
    final iconSize = compact ? 16.0 : 18.0;

    Widget actionButton({
      required IconData icon,
      required VoidCallback onTap,
      required Color backgroundColor,
      required Color iconColor,
    }) {
      return Material(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: SizedBox(
            width: buttonSize,
            height: buttonSize,
            child: Icon(icon, color: iconColor, size: iconSize),
          ),
        ),
      );
    }

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 6 : 8,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          actionButton(
            icon: Icons.remove_rounded,
            onTap: () => _changeCartQuantity(key, -1),
            backgroundColor: const Color(0xFFFFF1F2),
            iconColor: const Color(0xFFE11D48),
          ),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 10),
            child: Text(
              '$quantity',
              style: TextStyle(
                fontSize: compact ? 12 : 14,
                fontWeight: FontWeight.w800,
                color: AppColors.gray900,
              ),
            ),
          ),
          actionButton(
            icon: Icons.add_rounded,
            onTap: () => _changeCartQuantity(key, 1),
            backgroundColor: const Color(0xFFFFF9E7),
            iconColor: AppColors.primaryDark,
          ),
        ],
      ),
    );
  }

  Widget _buildCartPriceStat({
    required String label,
    required double amount,
    bool emphasized = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 10,
            color: AppColors.gray500,
            fontWeight: emphasized ? FontWeight.w700 : FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        SaudiRiyalText(
          text: amount.toStringAsFixed(2),
          style: TextStyle(
            fontSize: emphasized ? 14 : 12,
            fontWeight: FontWeight.w800,
            color: emphasized ? AppColors.primary : AppColors.gray800,
          ),
          iconSize: emphasized ? 13 : 12,
        ),
      ],
    );
  }

  void _changeCartQuantity(String key, int delta) {
    final current = _cartQuantities[key] ?? 0;
    final next = current + delta;

    setState(() {
      if (next <= 0) {
        _cartQuantities.remove(key);
        _cartItems.remove(key);
        _cartPricingModes.remove(key);
      } else {
        _cartQuantities[key] = next;
      }
    });
  }

  bool _isDefaultAddress(Map<String, dynamic> item) {
    final value = item['is_default'];
    if (value is bool) return value;
    if (value is num) return value.toInt() == 1;
    return value?.toString() == '1';
  }

  Map<String, dynamic>? _addressFromRow(Map<String, dynamic> row) {
    final address = (row['address'] ?? '').toString().trim();
    if (address.isEmpty) return null;

    return {
      if (row['id'] != null) 'id': row['id'],
      'title': (row['title'] ?? '').toString().trim(),
      'address': address,
      'lat': _toDouble(row['lat']),
      'lng': _toDouble(row['lng']),
      'country_code': (row['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase(),
      'city_name': (row['city_name'] ?? '').toString().trim(),
      'village_name': (row['village_name'] ?? '').toString().trim(),
      'notes': (row['notes'] ?? '').toString().trim(),
      'is_default': _isDefaultAddress(row),
    };
  }

  Map<String, dynamic>? _pickPreferredAddress(
    List<Map<String, dynamic>> addresses,
  ) {
    if (addresses.isEmpty) return null;
    final preferred = addresses.firstWhere(
      _isDefaultAddress,
      orElse: () => addresses.first,
    );
    return _addressFromRow(preferred);
  }

  Future<List<Map<String, dynamic>>> _fetchSavedAddressesSnapshot() async {
    final response = await _addressesService.getAddresses();
    final addresses = <Map<String, dynamic>>[];

    if (response.success && response.data is List) {
      for (final raw in (response.data as List)) {
        if (raw is! Map) continue;
        final row = Map<String, dynamic>.from(
          raw.map((key, value) => MapEntry(key.toString(), value)),
        );
        addresses.add(row);
      }
    }

    return addresses;
  }

  Future<void> _loadSavedAddresses({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() => _isAddressesLoading = true);
    }

    try {
      final addresses = await _fetchSavedAddressesSnapshot();
      if (!mounted) return;
      setState(() {
        _savedAddresses = addresses;
        _isAddressesLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isAddressesLoading = false);
    }
  }

  Future<Map<String, dynamic>?> _pickAddressFromMap() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const LocationPickerScreen(returnDataOnly: true),
      ),
    );

    if (!mounted || result is! Map) return null;
    final row = Map<String, dynamic>.from(
      result.map((key, value) => MapEntry(key.toString(), value)),
    );

    await _loadSavedAddresses(showLoading: false);
    return _addressFromRow(row) ??
        {
          'address': (row['address'] ?? '').toString().trim(),
          'lat': _toDouble(row['lat']),
          'lng': _toDouble(row['lng']),
          'country_code': (row['country_code'] ?? '')
              .toString()
              .trim()
              .toUpperCase(),
          'city_name': (row['city_name'] ?? '').toString().trim(),
          'village_name': (row['village_name'] ?? '').toString().trim(),
        };
  }

  Future<DateTime?> _pickDate({DateTime? initialDate}) async {
    final now = DateTime.now();
    return showDatePicker(
      context: context,
      initialDate: initialDate ?? now,
      firstDate: now,
      lastDate: now.add(const Duration(days: 30)),
    );
  }

  Future<TimeOfDay?> _pickTime({TimeOfDay? initialTime}) async {
    return showTimePicker(
      context: context,
      initialTime: initialTime ?? TimeOfDay.now(),
    );
  }

  Future<_SpareCheckoutData?> _showCheckoutSheet() async {
    final savedLocation = context.read<LocationProvider>();
    final fallbackAddressData = {
      'address': savedLocation.currentAddress,
      'lat': savedLocation.requestLat,
      'lng': savedLocation.requestLng,
      'country_code': savedLocation.requestCountryCode ?? '',
      'city_name': savedLocation.currentCityName,
      'village_name': savedLocation.currentVillageName,
    };
    List<Map<String, dynamic>> savedAddresses = List<Map<String, dynamic>>.from(
      _savedAddresses,
    );
    bool addressesLoading = _isAddressesLoading;
    Map<String, dynamic>? addressData =
        _pickPreferredAddress(savedAddresses) ??
        ((fallbackAddressData['address'] ?? '').toString().trim().isNotEmpty
            ? fallbackAddressData
            : null);
    DateTime? selectedDate;
    TimeOfDay? selectedTime;
    final notesController = TextEditingController();

    Future<void> refreshSavedAddresses(
      StateSetter setSheetState, {
      bool showLoader = true,
    }) async {
      if (showLoader) {
        setSheetState(() => addressesLoading = true);
      }

      try {
        final latest = await _fetchSavedAddressesSnapshot();
        if (!mounted) return;

        setState(() {
          _savedAddresses = latest;
          _isAddressesLoading = false;
        });

        setSheetState(() {
          savedAddresses = latest;
          addressesLoading = false;
          final hasSelectedAddress = (addressData?['address'] ?? '')
              .toString()
              .trim()
              .isNotEmpty;
          if (!hasSelectedAddress) {
            addressData = _pickPreferredAddress(latest) ?? addressData;
          }
        });
      } catch (_) {
        if (!mounted) return;
        setState(() => _isAddressesLoading = false);
        setSheetState(() => addressesLoading = false);
      }
    }

    final result = await showModalBottomSheet<_SpareCheckoutData>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            final hasAddress = (addressData?['address'] ?? '')
                .toString()
                .trim()
                .isNotEmpty;
            final warrantyEntries = _cartWarrantyEntries();

            bool isAddressSelected(Map<String, dynamic> candidate) {
              final selected = addressData;
              if (selected == null) return false;
              final selectedId = (selected['id'] ?? '').toString().trim();
              final candidateId = (candidate['id'] ?? '').toString().trim();
              if (selectedId.isNotEmpty && candidateId.isNotEmpty) {
                return selectedId == candidateId;
              }
              final selectedAddress = (selected['address'] ?? '')
                  .toString()
                  .trim();
              final candidateAddress = (candidate['address'] ?? '')
                  .toString()
                  .trim();
              return selectedAddress.isNotEmpty &&
                  selectedAddress == candidateAddress;
            }

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 16,
                  bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 16,
                ),
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Center(
                        child: SizedBox(
                          width: 44,
                          child: Divider(thickness: 4),
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        context.tr('spares_checkout_title'),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        context.tr('spares_checkout_subtitle'),
                        style: TextStyle(
                          color: AppColors.gray600,
                          fontSize: 12,
                        ),
                      ),
                      if (warrantyEntries.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppColors.gray200),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    width: 34,
                                    height: 34,
                                    decoration: BoxDecoration(
                                      color: AppColors.primary.withValues(
                                        alpha: 0.10,
                                      ),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: const Icon(
                                      Icons.shield_outlined,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      context.tr('spares_warranty_quality'),
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              for (
                                var i = 0;
                                i < warrantyEntries.length;
                                i++
                              ) ...[
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                      color: AppColors.gray200,
                                    ),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        warrantyEntries[i]['name'] ?? '',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900,
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      RichText(
                                        text: TextSpan(
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: AppColors.gray700,
                                            height: 1.5,
                                          ),
                                          children: [
                                            TextSpan(
                                              text:
                                                  '${context.tr('spares_warranty_duration')}: ',
                                              style: const TextStyle(
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                            TextSpan(
                                              text:
                                                  (warrantyEntries[i]['duration'] ??
                                                          '')
                                                      .isNotEmpty
                                                  ? warrantyEntries[i]['duration']
                                                  : context.tr('not_specified'),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if ((warrantyEntries[i]['terms'] ?? '')
                                          .isNotEmpty) ...[
                                        const SizedBox(height: 4),
                                        RichText(
                                          text: TextSpan(
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: AppColors.gray700,
                                              height: 1.5,
                                            ),
                                            children: [
                                              TextSpan(
                                                text:
                                                    '${context.tr('spares_warranty_terms')}: ',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                              TextSpan(
                                                text:
                                                    warrantyEntries[i]['terms'],
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                if (i != warrantyEntries.length - 1)
                                  const SizedBox(height: 10),
                              ],
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 16),
                      Text(
                        context.tr('spares_service_address'),
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (addressesLoading)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 14),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      else if (savedAddresses.isEmpty)
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9FAFB),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.gray200),
                          ),
                          child: Text(
                            context.tr('service_request_no_saved_addresses'),
                            style: const TextStyle(color: AppColors.gray700),
                          ),
                        )
                      else
                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: savedAddresses.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final item = savedAddresses[index];
                            final mapped = _addressFromRow(item);
                            if (mapped == null) return const SizedBox.shrink();

                            final title = (item['title'] ?? '')
                                .toString()
                                .trim();
                            final address = (item['address'] ?? '')
                                .toString()
                                .trim();
                            final selected = isAddressSelected(mapped);

                            return InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: () =>
                                  setSheetState(() => addressData = mapped),
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: selected
                                      ? AppColors.primary.withValues(
                                          alpha: 0.08,
                                        )
                                      : const Color(0xFFF9FAFB),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: selected
                                        ? AppColors.primary
                                        : AppColors.gray200,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      _isDefaultAddress(item)
                                          ? Icons.home_filled
                                          : Icons.location_on_outlined,
                                      color: AppColors.primary,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            title.isEmpty
                                                ? context.tr(
                                                    'service_request_saved_address',
                                                  )
                                                : title,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            address,
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: AppColors.gray700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (_isDefaultAddress(item))
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 4,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.primary.withValues(
                                            alpha: 0.12,
                                          ),
                                          borderRadius: BorderRadius.circular(
                                            8,
                                          ),
                                        ),
                                        child: Text(
                                          context.tr('service_request_default'),
                                          style: const TextStyle(
                                            fontSize: 10,
                                            fontWeight: FontWeight.bold,
                                            color: AppColors.primary,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      const SizedBox(height: 10),
                      OutlinedButton.icon(
                        onPressed: () async {
                          final added = await _pickAddressFromMap();
                          if (added == null || !mounted) return;

                          setSheetState(() => addressData = added);
                          await refreshSavedAddresses(
                            setSheetState,
                            showLoader: false,
                          );
                        },
                        icon: const Icon(Icons.add_location_alt_outlined),
                        label: Text(
                          context.tr(
                            'service_request_add_new_address_from_map',
                          ),
                        ),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(44),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Container(
                        decoration: BoxDecoration(
                          color: const Color(0xFFF9FAFB),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: AppColors.gray200),
                        ),
                        child: Column(
                          children: [
                            ListTile(
                              leading: const Icon(
                                Icons.calendar_month_outlined,
                              ),
                              title: Text(context.tr('spares_visit_date')),
                              subtitle: Text(
                                selectedDate == null
                                    ? context.tr('choose_date')
                                    : DateFormat(
                                        'yyyy-MM-dd',
                                      ).format(selectedDate!),
                              ),
                              trailing: const Icon(
                                Icons.edit_calendar_outlined,
                              ),
                              onTap: () async {
                                final date = await _pickDate(
                                  initialDate: selectedDate,
                                );
                                if (date == null) return;
                                setSheetState(() => selectedDate = date);
                              },
                            ),
                            const Divider(height: 1),
                            ListTile(
                              leading: const Icon(Icons.access_time_outlined),
                              title: Text(context.tr('spares_visit_time')),
                              subtitle: Text(
                                selectedTime == null
                                    ? context.tr('choose_time')
                                    : selectedTime!.format(sheetContext),
                              ),
                              trailing: const Icon(Icons.timer_outlined),
                              onTap: () async {
                                final time = await _pickTime(
                                  initialTime: selectedTime,
                                );
                                if (time == null) return;
                                setSheetState(() => selectedTime = time);
                              },
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: notesController,
                        maxLines: 3,
                        decoration: InputDecoration(
                          labelText: context.tr(
                            'spares_additional_notes_optional',
                          ),
                          hintText: context.tr('spares_additional_notes_hint'),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: _isSubmitting
                              ? null
                              : () {
                                  if (!hasAddress) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                          context.tr(
                                            'spares_select_address_required',
                                          ),
                                        ),
                                      ),
                                    );
                                    return;
                                  }
                                  if (selectedDate == null ||
                                      selectedTime == null) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                          context.tr(
                                            'spares_select_date_time_required',
                                          ),
                                        ),
                                      ),
                                    );
                                    return;
                                  }

                                  Navigator.pop(
                                    sheetContext,
                                    _SpareCheckoutData(
                                      addressData: addressData!,
                                      date: selectedDate!,
                                      time: selectedTime!,
                                      notes: notesController.text.trim(),
                                    ),
                                  );
                                },
                          icon: const Icon(Icons.send_outlined),
                          label: Text(context.tr('spares_send_request')),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );

    notesController.dispose();
    return result;
  }

  Future<void> _checkoutCart() async {
    if (_cartItems.isEmpty) return;

    final canProceed = await checkGuestAndShowDialog(context);
    if (!mounted || !canProceed) return;

    final minOrder = context
        .read<AppSettingsProvider>()
        .sparePartsMinOrderWithInstallation;
    if (_hasInstallationItems && minOrder > 0) {
      final installTotal = _cartTotalWithInstallation;
      if (installTotal < minOrder) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'الحد الأدنى لطلب قطع الغيار مع التركيب هو ${minOrder.toStringAsFixed(2)} ⃁',
            ),
          ),
        );
        return;
      }
    }

    await _loadSavedAddresses(showLoading: false);
    if (!mounted) return;

    final checkoutData = await _showCheckoutSheet();
    if (!mounted || checkoutData == null) return;

    setState(() => _isSubmitting = true);

    try {
      final estimatedAmount = _cartTotal;
      final requestedParts = <Map<String, dynamic>>[];
      _cartQuantities.forEach((key, quantity) {
        final spare = _cartItems[key];
        if (spare == null || quantity <= 0) return;

        final pricingMode = _cartPricingModes[key] == 'without_installation'
            ? 'without_installation'
            : 'with_installation';
        final requiresInstallation = pricingMode != 'without_installation';
        final unitPrice = _unitPriceByMode(spare, pricingMode);

        requestedParts.add({
          'spare_part_id': _toInt(spare['id']),
          'name': (spare['name'] ?? context.tr('spares_default_part_name'))
              .toString(),
          'quantity': quantity,
          'pricing_mode': pricingMode,
          'requires_installation': requiresInstallation,
          'unit_price': unitPrice,
          if (checkoutData.notes.isNotEmpty) 'notes': checkoutData.notes,
        });
      });

      if (requestedParts.isEmpty) {
        throw Exception(context.tr('spares_no_valid_items_in_cart'));
      }

      final dateStr = DateFormat('yyyy-MM-dd').format(checkoutData.date);
      final timeStr =
          '${checkoutData.time.hour.toString().padLeft(2, '0')}:${checkoutData.time.minute.toString().padLeft(2, '0')}';

      final address = (checkoutData.addressData['address'] ?? '')
          .toString()
          .trim();
      final lat = _toDouble(checkoutData.addressData['lat']);
      final lng = _toDouble(checkoutData.addressData['lng']);
      final countryCode = (checkoutData.addressData['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase();

      final response = await _ordersService.createOrder(
        categoryId: 0,
        address: address,
        lat: lat,
        lng: lng,
        countryCode: countryCode,
        notes: checkoutData.notes,
        scheduledDate: dateStr,
        scheduledTime: timeStr,
        isCustomService: true,
        customServiceTitle: context.tr('spares_custom_service_title'),
        customServiceDescription: checkoutData.notes,
        problemDetails: {
          'type': 'spare_parts_order',
          'user_desc': checkoutData.notes,
          'spare_parts': requestedParts,
          'requires_installation': requestedParts.any(
            (item) => item['requires_installation'] == true,
          ),
        },
      );

      if (!mounted) return;

      if (!response.success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.message ?? context.tr('request_send_failed'),
            ),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      final createdOrder = response.data is Map
          ? Map<String, dynamic>.from(
              (response.data as Map).map(
                (key, value) => MapEntry(key.toString(), value),
              ),
            )
          : <String, dynamic>{};

      final orderId = _toInt(createdOrder['id']);
      final createdAmount = _toDouble(createdOrder['total_amount']);
      final payableAmount = createdAmount > 0 ? createdAmount : estimatedAmount;

      if (orderId <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.tr('request_send_failed')),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      setState(() {
        _cartQuantities.clear();
        _cartItems.clear();
        _cartPricingModes.clear();
      });

      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => PaymentScreen(
            amount: payableAmount,
            serviceName: context.tr('spares_custom_service_title'),
            orderId: orderId,
            onPaymentSuccess: () {
              if (!mounted) return;
              Navigator.of(context).pop();
              Future.microtask(() {
                if (!mounted) return;
                OrderTrackingNavigation.open(
                  context,
                  orderId: orderId,
                  categoryName:
                      (createdOrder['category_name'] ??
                              context.tr('spares_custom_service_title'))
                          .toString(),
                  categoryIcon: (createdOrder['category_icon'] ?? '')
                      .toString(),
                  categoryImage: (createdOrder['category_image'] ?? '')
                      .toString(),
                );
              });
            },
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context
                .tr('spares_request_sent_failed_with_reason')
                .replaceAll('{error}', e.toString()),
          ),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: AppColors.gray50,
        body: Stack(
          children: [
            Column(
              children: [
                _buildHeader(),
                const Expanded(
                  child: Center(child: CircularProgressIndicator()),
                ),
              ],
            ),
            _buildTopNoticeOverlay(),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Stack(
        children: [
          Column(
            children: [
              _buildHeader(),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: _fetchSpares,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(
                      16,
                      16,
                      16,
                      _cartItems.isEmpty ? 80 : 140,
                    ),
                    child: Column(
                      children: [
                        _buildInfoBanner().animate().fadeIn().slideY(
                          begin: 0.1,
                        ),
                        const SizedBox(height: 16),
                        ..._filteredSpares.asMap().entries.map((entry) {
                          return _buildSpareCard(entry.value)
                              .animate()
                              .fadeIn(delay: (entry.key * 100).ms)
                              .slideY(begin: 0.1);
                        }),
                        if (_filteredSpares.isEmpty) _buildEmptyState(),
                      ],
                    ),
                  ),
                ),
              ),
              if (_cartItems.isNotEmpty) _buildCartBar(),
            ],
          ),
          _buildTopNoticeOverlay(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 16,
        left: 16,
        right: 16,
        bottom: 16,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.primary, AppColors.primaryDark],
        ),
        boxShadow: AppShadows.lg,
      ),
      child: Column(
        children: [
          Row(
            children: [
              InkWell(
                onTap: widget.onBack,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.arrow_forward,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('spare_parts_with_installation'),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        shadows: _headerTextShadows,
                      ),
                    ),
                    Text(
                      context.tr('spares_original_best_price'),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        shadows: _headerTextShadows,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.inventory_2,
                  color: Colors.white,
                  size: 20,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 40,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: _categories.length,
              itemBuilder: (context, index) {
                final category = _categories[index];
                final categoryKey = (category['key'] ?? _allCategoryKey)
                    .toString();
                final isSelected = _selectedCategoryKey == categoryKey;
                final isAll = categoryKey == _allCategoryKey;
                final categoryName = isAll
                    ? context.tr('all')
                    : (category['name'] ?? '').toString().trim();
                return GestureDetector(
                  onTap: () =>
                      setState(() => _selectedCategoryKey = categoryKey),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? Colors.white
                          : Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        Text(
                          category['icon'],
                          style: const TextStyle(fontSize: 14),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          categoryName.isNotEmpty
                              ? categoryName
                              : context.tr('not_specified'),
                          style: TextStyle(
                            color: isSelected
                                ? AppColors.primary
                                : Colors.white,
                            fontSize: 12,
                            fontWeight: isAll
                                ? FontWeight.w600
                                : FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoBanner() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFEFF6FF), Color(0xFFE0E7FF)],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFBFDBFE), width: 2),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: Colors.blue,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.shield, color: Colors.white, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('spares_warranty_quality'),
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  context.tr('spares_quality_description'),
                  style: const TextStyle(
                    color: AppColors.gray600,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSpareCard(Map<String, dynamic> spare) {
    final withInstallationPrice = _priceWithInstallation(spare);
    final withoutInstallationPrice = _priceWithoutInstallation(spare);
    final oldWithInstallationPrice = _oldPriceWithInstallation(spare);
    final oldWithoutInstallationPrice = _oldPriceWithoutInstallation(spare);
    final key = _spareKey(spare);
    final cartQty = _cartQuantities[key] ?? 0;
    final selectedMode = _cartPricingModes[key] ?? '';
    final selectedUnitPrice = selectedMode.isNotEmpty
        ? _unitPriceByMode(spare, selectedMode)
        : 0.0;
    final selectedLineTotal = selectedUnitPrice * cartQty;

    final storeName = (spare['store_name'] ?? '').toString().trim();
    final distanceRaw = spare['distance_km'];
    final distance = distanceRaw == null
        ? null
        : double.tryParse(distanceRaw.toString());
    final name = (spare['name'] ?? context.tr('spare_part')).toString();
    final warrantyDuration = _spareWarrantyDuration(spare);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.md,
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                          color: AppColors.gray900,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFFBEB),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(
                                  Icons.star,
                                  color: Colors.amber,
                                  size: 13,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '${spare['rating']} (${spare['reviews']})',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppColors.gray700,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFECFDF3),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(
                                  Icons.shield_outlined,
                                  color: Colors.green,
                                  size: 13,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  warrantyDuration.isNotEmpty
                                      ? '${context.tr('warranty')} $warrantyDuration'
                                      : context.tr('warranty'),
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppColors.gray700,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (storeName.isNotEmpty)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.gray100,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                distance == null
                                    ? context
                                          .tr('spares_store_with_name')
                                          .replaceAll('{name}', storeName)
                                    : context
                                          .tr('spares_store_with_distance_km')
                                          .replaceAll('{name}', storeName)
                                          .replaceAll(
                                            '{distance}',
                                            distance.toStringAsFixed(2),
                                          ),
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: AppColors.gray600,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Container(
                  width: 112,
                  height: 112,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    color: AppColors.gray100,
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      CachedNetworkImage(
                        imageUrl: AppConfig.fixMediaUrl(spare['image']),
                        fit: BoxFit.cover,
                        placeholder: (context, url) =>
                            Container(color: AppColors.gray100),
                        errorWidget: (context, url, error) =>
                            const Icon(Icons.image_not_supported),
                      ),
                      if (spare['discount'] != null)
                        Positioned(
                          top: 6,
                          left: 6,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.red,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '-${spare['discount']}%',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.gray200),
              ),
              child: Column(
                children: [
                  _buildPriceModeRow(
                    label: _withInstallationLabel(),
                    price: withInstallationPrice,
                    oldPrice: oldWithInstallationPrice,
                    emphasized: true,
                  ),
                  const SizedBox(height: 8),
                  const Divider(height: 1),
                  const SizedBox(height: 8),
                  _buildPriceModeRow(
                    label: _withoutInstallationLabel(),
                    price: withoutInstallationPrice,
                    oldPrice: oldWithoutInstallationPrice,
                  ),
                  if (cartQty > 0 && selectedMode.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text(
                        context
                            .tr('spares_selected_mode_in_cart')
                            .replaceAll(
                              '{mode}',
                              _spareModeLabel(selectedMode),
                            ),
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.gray600,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 10),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              child: cartQty <= 0
                  ? SizedBox(
                      key: ValueKey<String>('add_$key'),
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _isSubmitting
                            ? null
                            : () => _addSpareToCart(spare),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          minimumSize: const Size(double.infinity, 48),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        icon: const Icon(
                          Icons.shopping_cart_outlined,
                          size: 18,
                        ),
                        label: Text(context.tr('add_to_cart')),
                      ),
                    )
                  : Container(
                      key: ValueKey<String>('selected_$key'),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFFBF0),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFF3E1A4)),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _spareModeLabel(selectedMode),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray800,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    Expanded(
                                      child: _buildCartPriceStat(
                                        label: context.tr(
                                          'spares_unit_price_label',
                                        ),
                                        amount: selectedUnitPrice,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: _buildCartPriceStat(
                                        label: context.tr(
                                          'spares_line_total_label',
                                        ),
                                        amount: selectedLineTotal,
                                        emphasized: true,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          _buildQuantityControl(key: key, quantity: cartQty),
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCartBar() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 18,
            offset: const Offset(0, -6),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              height: 82,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _cartItems.keys.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final key = _cartItems.keys.elementAt(index);
                  final spare = _cartItems[key]!;
                  final qty = _cartQuantities[key] ?? 0;
                  final mode = _cartPricingModes[key] ?? 'with_installation';
                  final unit = _unitPriceByMode(spare, mode);

                  return Container(
                    width: 252,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.gray200),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                (spare['name'] ??
                                        context.tr('spares_default_part_name'))
                                    .toString(),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                _spareModeLabel(mode),
                                style: const TextStyle(
                                  fontSize: 10,
                                  color: AppColors.gray600,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Expanded(
                                    child: _buildCartPriceStat(
                                      label: context.tr(
                                        'spares_unit_price_label',
                                      ),
                                      amount: unit,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: _buildCartPriceStat(
                                      label: context.tr(
                                        'spares_line_total_label',
                                      ),
                                      amount: unit * qty,
                                      emphasized: true,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        _buildQuantityControl(
                          key: key,
                          quantity: qty,
                          compact: true,
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context
                            .tr('spares_items_count_in_cart')
                            .replaceAll('{count}', _cartItemsCount.toString()),
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.gray600,
                        ),
                      ),
                      const SizedBox(height: 2),
                      SaudiRiyalText(
                        text: _cartTotal.toStringAsFixed(2),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppColors.gray900,
                        ),
                        iconSize: 18,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _isSubmitting ? null : _checkoutCart,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      minimumSize: const Size(double.infinity, 48),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: _isSubmitting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.shopping_bag_outlined),
                    label: Text(
                      _isSubmitting
                          ? context.tr('spares_submitting_request')
                          : context.tr('spares_complete_order'),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 48),
      child: Column(
        children: [
          Container(
            width: 80,
            height: 80,
            decoration: const BoxDecoration(
              color: AppColors.gray100,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.inventory_2,
              color: AppColors.gray400,
              size: 40,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            context.tr('no_spares_in_category'),
            style: const TextStyle(color: AppColors.gray500),
          ),
        ],
      ),
    ).animate().fadeIn();
  }

  Widget _buildPriceModeRow({
    required String label,
    required double price,
    double? oldPrice,
    bool emphasized = false,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: AppColors.gray500,
              fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Align(
            alignment: AlignmentDirectional.centerEnd,
            child: Wrap(
              spacing: 6,
              runSpacing: 4,
              alignment: WrapAlignment.end,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                SaudiRiyalText(
                  text: price.toStringAsFixed(2),
                  style: TextStyle(
                    color: emphasized ? AppColors.primary : AppColors.gray700,
                    fontWeight: FontWeight.bold,
                    fontSize: emphasized ? 16 : 14,
                  ),
                  iconSize: emphasized ? 15 : 13,
                ),
                if (oldPrice != null && oldPrice > price)
                  SaudiRiyalText(
                    text: oldPrice.toStringAsFixed(2),
                    style: const TextStyle(
                      color: AppColors.gray400,
                      fontSize: 11,
                      decoration: TextDecoration.lineThrough,
                    ),
                    iconSize: 11,
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
