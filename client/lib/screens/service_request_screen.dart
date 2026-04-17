// Service Request Screen
// شاشة طلب الخدمة

import 'package:flutter/material.dart';

import 'dart:io';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/models.dart';
import '../services/services.dart';
import '../widgets/guest_guard.dart';
import '../services/app_localizations.dart';
import '../utils/saudi_riyal_icon.dart';
import 'location_picker_screen.dart';

class _ProblemTypeOption {
  final int? id;
  final String title;

  const _ProblemTypeOption({this.id, required this.title});
}

class ServiceRequestScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  final VoidCallback? onBack;
  final Map<String, dynamic>? addressData;
  final List<int> subServices;
  final List<Map<String, dynamic>> selectedServices;
  final String? customServiceTitle;
  final List<String> customServiceImagePaths;

  const ServiceRequestScreen({
    super.key,
    required this.service,
    this.onBack,
    this.addressData,
    this.subServices = const [],
    this.selectedServices = const [],
    this.customServiceTitle,
    this.customServiceImagePaths = const [],
  });

  @override
  State<ServiceRequestScreen> createState() => _ServiceRequestScreenState();
}

class _ServiceRequestScreenState extends State<ServiceRequestScreen> {
  final OrdersService _ordersService = OrdersService();
  final HomeService _homeService = HomeService();
  final AddressesService _addressesService = AddressesService();
  bool _agreedToTerms = false;
  final TextEditingController _descriptionController = TextEditingController();
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;

  final ImagePicker _picker = ImagePicker();
  final List<XFile> _media = [];
  String? _selectedProblemType;
  String _customServiceTitle = '';
  List<String> _customServiceImagePaths = [];
  static const List<String> _fallbackProblemTitleKeys = [
    'service_request_problem_water_leak',
    'service_request_problem_blockage',
    'service_request_problem_installations',
    'service_request_problem_maintenance',
    'service_request_other_service',
  ];
  List<_ProblemTypeOption> _problemTypes = [];

  Map<String, dynamic>? _currentAddressData;
  List<Map<String, dynamic>> _savedAddresses = [];
  bool _isAddressesLoading = false;
  bool _isSparesLoading = false;
  List<Map<String, dynamic>> _availableSpares = [];
  final Map<String, Map<String, dynamic>> _selectedSpares = {};
  final Map<String, int> _selectedSpareQuantities = {};
  final Map<String, String> _selectedSparePricingModes = {};

  bool _isSubmitting = false;
  bool _didInitLocalizedState = false;

  bool _isOtherProblemType(String? value) {
    final normalized = (value ?? '').toLowerCase().trim();
    if (normalized.isEmpty) return false;
    final localizedOther = context
        .tr('service_request_other_service')
        .toLowerCase()
        .trim();
    return normalized == 'other' ||
        normalized.contains('other') ||
        normalized == localizedOther ||
        (localizedOther.isNotEmpty && normalized.contains(localizedOther)) ||
        normalized.contains('اخرى') ||
        normalized.contains('أخرى');
  }

  List<String> _fallbackProblemTitles() {
    return _fallbackProblemTitleKeys.map(context.tr).toList();
  }

  bool get _hasCustomServiceFromSelection =>
      _customServiceTitle.trim().isNotEmpty;

  bool get _isCustomServiceSelection =>
      _hasCustomServiceFromSelection ||
      _isOtherProblemType(_selectedProblemType);

  List<_ProblemTypeOption> _injectCustomServiceOption(
    List<_ProblemTypeOption> values,
  ) {
    final customTitle = _customServiceTitle.trim();
    if (customTitle.isEmpty) return values;

    final hasSameTitle = values.any(
      (item) => item.title.trim().toLowerCase() == customTitle.toLowerCase(),
    );
    if (hasSameTitle) return values;

    return <_ProblemTypeOption>[
      ...values,
      _ProblemTypeOption(title: customTitle),
    ];
  }

  void _seedCustomServicePrefill() {
    if (!_hasCustomServiceFromSelection) return;

    final customTitle = _customServiceTitle.trim();
    if (customTitle.isEmpty) return;

    _problemTypes = _injectCustomServiceOption(_problemTypes);
    _selectedProblemType = customTitle;

    if (_descriptionController.text.trim().isEmpty) {
      _descriptionController.text = context
          .tr('service_request_other_service_request')
          .replaceAll('{title}', customTitle);
    }
  }

  String get _localizedServiceName {
    final language = Localizations.localeOf(context).languageCode;
    final nameAr = widget.service.nameAr.trim();
    final nameEn = (widget.service.nameEn ?? '').trim();
    if (language == 'ar') {
      if (nameAr.isNotEmpty) return nameAr;
      if (nameEn.isNotEmpty) return nameEn;
      return context.tr('service');
    }
    if (nameEn.isNotEmpty) return nameEn;
    if (nameAr.isNotEmpty) return nameAr;
    return context.tr('service');
  }

  int? get _selectedProblemTypeId {
    final selected = (_selectedProblemType ?? '').trim();
    if (selected.isEmpty) return null;
    for (final option in _problemTypes) {
      if (option.title == selected) {
        return option.id;
      }
    }
    return null;
  }

  List<_ProblemTypeOption> _ensureOtherOption(List<_ProblemTypeOption> values) {
    final unique = <String>{};
    final items = <_ProblemTypeOption>[];

    for (final option in values) {
      final title = option.title.trim();
      if (title.isEmpty) continue;
      final key = title.toLowerCase();
      if (!unique.add(key)) continue;
      items.add(_ProblemTypeOption(id: option.id, title: title));
    }

    if (!items.any((item) => _isOtherProblemType(item.title))) {
      items.add(
        _ProblemTypeOption(title: context.tr('service_request_other_service')),
      );
    }
    return items;
  }

  @override
  void initState() {
    super.initState();
    _currentAddressData = widget.addressData;
    _customServiceTitle = (widget.customServiceTitle ?? '').trim();
    _customServiceImagePaths = widget.customServiceImagePaths
        .map((path) => path.trim())
        .where((path) => path.isNotEmpty)
        .toList();
    _loadSavedAddresses();
    _loadSpares();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didInitLocalizedState) return;
    _didInitLocalizedState = true;

    _problemTypes = _fallbackProblemTitles()
        .map((title) => _ProblemTypeOption(title: title))
        .toList();
    _seedCustomServicePrefill();
    _loadProblemTypes();
  }

  Future<void> _loadProblemTypes() async {
    try {
      final lat = _toNullableDouble(_currentAddressData?['lat']);
      final lng = _toNullableDouble(_currentAddressData?['lng']);
      final response = await _homeService.getProblemTypes(
        categoryId: widget.service.id,
        serviceIds: widget.subServices,
        lat: lat,
        lng: lng,
        countryCode: (_currentAddressData?['country_code'] ?? '')
            .toString()
            .trim()
            .toUpperCase(),
        allowOutside: lat == null || lng == null,
      );

      if (!mounted) return;

      if (response.success && response.data is List) {
        final options = (response.data as List)
            .map((item) {
              if (item is! Map) return null;
              final row = Map<String, dynamic>.from(
                item.map((key, value) => MapEntry(key.toString(), value)),
              );
              final localized = _localizedProblemTitle(row);
              if (localized.isEmpty) return null;

              final id = int.tryParse((row['id'] ?? '').toString());
              return _ProblemTypeOption(
                id: (id != null && id > 0) ? id : null,
                title: localized,
              );
            })
            .whereType<_ProblemTypeOption>()
            .toList();

        if (options.isNotEmpty) {
          final normalizedOptions = _injectCustomServiceOption(
            _ensureOtherOption(options),
          );
          final validTitles = normalizedOptions
              .map((item) => item.title)
              .toSet();

          setState(() {
            _problemTypes = normalizedOptions;
            if (_hasCustomServiceFromSelection &&
                (_selectedProblemType ?? '').trim().isEmpty) {
              _selectedProblemType = _customServiceTitle.trim();
            }
            if (_selectedProblemType != null &&
                !validTitles.contains(_selectedProblemType)) {
              _selectedProblemType = null;
            }
          });
          return;
        }
      }
    } catch (_) {
      // Keep fallback list on API/network issues.
    }

    if (!mounted) return;
    setState(() {
      _problemTypes = _injectCustomServiceOption(
        _ensureOtherOption(
          _fallbackProblemTitles()
              .map((title) => _ProblemTypeOption(title: title))
              .toList(),
        ),
      );

      final validTitles = _problemTypes.map((item) => item.title).toSet();
      if (_hasCustomServiceFromSelection &&
          (_selectedProblemType ?? '').trim().isEmpty) {
        _selectedProblemType = _customServiceTitle.trim();
      }
      if (_selectedProblemType != null &&
          !validTitles.contains(_selectedProblemType)) {
        _selectedProblemType = null;
      }
    });
  }

  String _localizedProblemTitle(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['title_ar'] ?? '').toString().trim();
    final en = (item['title_en'] ?? '').toString().trim();

    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }

    return en.isNotEmpty ? en : ar;
  }

  String _localizedSelectedServiceName(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar =
        (item['name_ar'] ?? item['service_name_ar'] ?? item['name'] ?? '')
            .toString()
            .trim();
    final en =
        (item['name_en'] ?? item['service_name_en'] ?? item['name'] ?? '')
            .toString()
            .trim();

    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  List<Map<String, dynamic>> get _selectedServiceRows {
    final seen = <int>{};
    final rows = <Map<String, dynamic>>[];

    for (final item in widget.selectedServices) {
      final id = _toInt(item['id']);
      if (id <= 0 || !seen.add(id)) continue;
      rows.add(item);
    }

    return rows;
  }

  bool _isDefaultAddress(Map<String, dynamic> item) {
    final value = item['is_default'];
    if (value is bool) return value;
    if (value is num) return value.toInt() == 1;
    return value?.toString() == '1';
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse((value ?? '').toString()) ?? 0.0;
  }

  double? _toNullableDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    final normalized = value.toString().trim();
    if (normalized.isEmpty) return null;
    return double.tryParse(normalized);
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  String _spareKey(Map<String, dynamic> spare) {
    final id = (spare['id'] ?? '').toString().trim();
    if (id.isNotEmpty) return id;
    return (spare['name'] ?? DateTime.now().microsecondsSinceEpoch).toString();
  }

  String _withInstallationLabel() {
    return context.tr('order_tracking_with_installation');
  }

  String _withoutInstallationLabel() {
    return context.tr('order_tracking_without_installation');
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

  String _spareModeLabel(String mode) {
    return mode == 'without_installation'
        ? _withoutInstallationLabel()
        : _withInstallationLabel();
  }

  Widget _spareFromPriceSubtitle(double price) {
    final template = context.tr('service_request_from_price');
    final amount = price.toStringAsFixed(2);
    final textWithoutCurrency = template
        .replaceAll('{price}', amount)
        .replaceAll('{currency}', '')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();

    return Text.rich(
      TextSpan(
        style: const TextStyle(fontSize: 12),
        children: [
          TextSpan(text: '$textWithoutCurrency '),
          const WidgetSpan(
            alignment: PlaceholderAlignment.middle,
            child: SaudiRiyalIcon(size: 12),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _spareModeAndPriceLine(String mode, double unitPrice) {
    const style = TextStyle(fontSize: 12, color: AppColors.gray600);
    return Text.rich(
      TextSpan(
        style: style,
        children: [
          TextSpan(
            text: '${_spareModeLabel(mode)} • ${unitPrice.toStringAsFixed(2)} ',
          ),
          const WidgetSpan(
            alignment: PlaceholderAlignment.middle,
            child: SaudiRiyalIcon(size: 12),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }

  Future<void> _loadSpares() async {
    if (!mounted) return;
    setState(() => _isSparesLoading = true);

    try {
      final lat = _toNullableDouble(_currentAddressData?['lat']);
      final lng = _toNullableDouble(_currentAddressData?['lng']);
      final response = await _homeService.getSpareParts(
        lat: lat,
        lng: lng,
        countryCode: (_currentAddressData?['country_code'] ?? '')
            .toString()
            .trim()
            .toUpperCase(),
        categoryId: widget.service.id,
        serviceIds: widget.subServices,
        allowOutside: lat == null || lng == null,
      );
      final next = <Map<String, dynamic>>[];

      if (response.success && response.data is List) {
        for (final raw in (response.data as List)) {
          if (raw is! Map) continue;
          next.add(
            Map<String, dynamic>.from(
              raw.map((key, value) => MapEntry(key.toString(), value)),
            ),
          );
        }
      }

      if (!mounted) return;
      setState(() {
        _availableSpares = next;
        _isSparesLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isSparesLoading = false);
    }
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
                  context.tr('service_request_spare_price_with_installation'),
                ),
                trailing: SaudiRiyalText(
                  text: withPrice.toStringAsFixed(2),
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.bold,
                  ),
                  iconSize: 13,
                ),
                onTap: () => Navigator.pop(context, 'with_installation'),
              ),
              const SizedBox(height: 8),
              ListTile(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: AppColors.gray200),
                ),
                title: Text(_withoutInstallationLabel()),
                subtitle: Text(
                  context.tr(
                    'service_request_spare_price_without_installation',
                  ),
                ),
                trailing: SaudiRiyalText(
                  text: withoutPrice.toStringAsFixed(2),
                  style: const TextStyle(
                    color: AppColors.gray700,
                    fontWeight: FontWeight.bold,
                  ),
                  iconSize: 13,
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

  Future<void> _addSpareToRequest(Map<String, dynamic> spare) async {
    final mode = await _pickPricingModeForSpare(spare);
    if (!mounted || mode == null || mode.isEmpty) return;

    final key = _spareKey(spare);
    final current = _selectedSpareQuantities[key] ?? 0;

    setState(() {
      _selectedSpares[key] = spare;
      _selectedSparePricingModes[key] = mode;
      _selectedSpareQuantities[key] = current + 1;
    });
  }

  void _changeSpareQuantity(String key, int delta) {
    final current = _selectedSpareQuantities[key] ?? 0;
    final next = current + delta;

    setState(() {
      if (next <= 0) {
        _selectedSpareQuantities.remove(key);
        _selectedSparePricingModes.remove(key);
        _selectedSpares.remove(key);
      } else {
        _selectedSpareQuantities[key] = next;
      }
    });
  }

  Future<void> _showSparesPicker() async {
    if (_isSparesLoading) return;
    if (_availableSpares.isEmpty) {
      await _loadSpares();
    }
    if (!mounted) return;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(
                  child: SizedBox(width: 48, child: Divider(thickness: 4)),
                ),
                const SizedBox(height: 12),
                Text(
                  context.tr('service_request_add_spares_title'),
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 6),
                Text(
                  context.tr('service_request_add_spares_subtitle'),
                  style: TextStyle(fontSize: 12, color: AppColors.gray600),
                ),
                const SizedBox(height: 14),
                if (_isSparesLoading)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_availableSpares.isEmpty)
                  Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Center(
                      child: Text(
                        context.tr('service_request_no_spares_available'),
                        style: TextStyle(color: AppColors.gray600),
                      ),
                    ),
                  )
                else
                  Flexible(
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: _availableSpares.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final spare = _availableSpares[index];
                        final name = (spare['name'] ?? context.tr('spare_part'))
                            .toString();
                        final imageUrl = AppConfig.fixMediaUrl(
                          spare['image']?.toString(),
                        );
                        final withPrice = _priceWithInstallation(spare);

                        return ListTile(
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 4,
                            vertical: 2,
                          ),
                          leading: ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: imageUrl.isNotEmpty
                                ? CachedNetworkImage(
                                    imageUrl: imageUrl,
                                    width: 44,
                                    height: 44,
                                    fit: BoxFit.cover,
                                    errorWidget: (_, __, ___) => Container(
                                      width: 44,
                                      height: 44,
                                      color: AppColors.gray100,
                                      alignment: Alignment.center,
                                      child: const Icon(
                                        Icons.image_not_supported,
                                        size: 18,
                                      ),
                                    ),
                                  )
                                : Container(
                                    width: 44,
                                    height: 44,
                                    color: AppColors.gray100,
                                    alignment: Alignment.center,
                                    child: const Icon(Icons.build_outlined),
                                  ),
                          ),
                          title: Text(
                            name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          subtitle: _spareFromPriceSubtitle(withPrice),
                          trailing: ElevatedButton(
                            onPressed: () {
                              _addSpareToRequest(spare).then((_) {
                                if (!mounted) return;
                                final message = this.context
                                    .tr('service_request_spare_added')
                                    .replaceAll('{name}', name);
                                ScaffoldMessenger.of(this.context).showSnackBar(
                                  SnackBar(content: Text(message)),
                                );
                              });
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 8,
                              ),
                            ),
                            child: Text(context.tr('add')),
                          ),
                        );
                      },
                    ),
                  ),
                const SizedBox(height: 10),
              ],
            ),
          ),
        );
      },
    );
  }

  Map<String, dynamic>? _addressFromRow(Map<String, dynamic> row) {
    final address = (row['address'] ?? '').toString().trim();
    if (address.isEmpty) return null;
    return {
      if (row['id'] != null) 'id': row['id'],
      'title': (row['title'] ?? '').toString(),
      'address': address,
      'lat': _toDouble(row['lat']),
      'lng': _toDouble(row['lng']),
      'country_code': (row['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase(),
      'city_name': (row['city_name'] ?? '').toString().trim(),
      'village_name': (row['village_name'] ?? '').toString().trim(),
      'notes': (row['notes'] ?? '').toString(),
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

  Future<void> _loadSavedAddresses({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() => _isAddressesLoading = true);
    }

    try {
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

      if (!mounted) return;
      setState(() {
        _savedAddresses = addresses;
        _isAddressesLoading = false;

        final hasCurrentAddress = (_currentAddressData?['address'] ?? '')
            .toString()
            .trim()
            .isNotEmpty;
        if (!hasCurrentAddress) {
          _currentAddressData = _pickPreferredAddress(addresses);
        }
      });
      _loadSpares();
      _loadProblemTypes();
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

    final addressData =
        _addressFromRow(row) ??
        {
          'address': (row['address'] ?? '').toString(),
          'lat': _toDouble(row['lat']),
          'lng': _toDouble(row['lng']),
          'country_code': (row['country_code'] ?? '')
              .toString()
              .trim()
              .toUpperCase(),
          'city_name': (row['city_name'] ?? '').toString().trim(),
          'village_name': (row['village_name'] ?? '').toString().trim(),
        };

    await _loadSavedAddresses(showLoading: false);
    return addressData;
  }

  Future<Map<String, dynamic>?> _showAddressSelector() async {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: EdgeInsets.only(
              left: 16,
              right: 16,
              top: 16,
              bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 16,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(
                  child: SizedBox(width: 48, child: Divider(thickness: 4)),
                ),
                const SizedBox(height: 12),
                Text(
                  context.tr('service_request_select_address_title'),
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 6),
                Text(
                  context.tr('service_request_select_address_subtitle'),
                  style: TextStyle(fontSize: 12, color: AppColors.gray600),
                ),
                const SizedBox(height: 16),
                if (_isAddressesLoading)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_savedAddresses.isEmpty)
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.gray200),
                    ),
                    child: Text(
                      context.tr('service_request_no_saved_addresses'),
                      style: TextStyle(color: AppColors.gray700),
                    ),
                  )
                else
                  Flexible(
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: _savedAddresses.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final item = _savedAddresses[index];
                        final mapped = _addressFromRow(item);
                        final title = (item['title'] ?? '').toString().trim();
                        final address = (item['address'] ?? '')
                            .toString()
                            .trim();

                        if (mapped == null) return const SizedBox.shrink();

                        return InkWell(
                          borderRadius: BorderRadius.circular(12),
                          onTap: () => Navigator.pop(sheetContext, mapped),
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF9FAFB),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.gray200),
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
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      context.tr('service_request_default'),
                                      style: TextStyle(
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
                  ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () => Navigator.pop(
                    sheetContext,
                    <String, dynamic>{'__action': 'add_new'},
                  ),
                  icon: const Icon(Icons.add_location_alt_outlined),
                  label: Text(
                    context.tr('service_request_add_new_address_from_map'),
                  ),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(46),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _pickAddress() async {
    if (_savedAddresses.isEmpty && !_isAddressesLoading) {
      await _loadSavedAddresses();
    }

    final result = await _showAddressSelector();
    if (!mounted || result == null) return;

    if ((result['__action'] ?? '') == 'add_new') {
      final added = await _pickAddressFromMap();
      if (!mounted || added == null) return;
      setState(() => _currentAddressData = added);
      _loadSpares();
      _loadProblemTypes();
      return;
    }

    setState(() => _currentAddressData = result);
    _loadSpares();
    _loadProblemTypes();
  }

  Future<void> _pickMedia() async {
    showModalBottomSheet(
      context: context,
      builder: (ctx) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: Text(context.tr('take_photo')),
              onTap: () async {
                Navigator.pop(ctx);
                final img = await _picker.pickImage(source: ImageSource.camera);
                if (img != null) {
                  setState(() => _media.add(img));
                }
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: Text(context.tr('gallery_photo')),
              onTap: () async {
                Navigator.pop(ctx);
                final img = await _picker.pickImage(
                  source: ImageSource.gallery,
                );
                if (img != null) {
                  setState(() => _media.add(img));
                }
              },
            ),
            ListTile(
              leading: const Icon(Icons.videocam),
              title: Text(context.tr('video')),
              onTap: () async {
                Navigator.pop(ctx);
                // Note: video might be large, maybe compress or limit
                final vid = await _picker.pickVideo(source: ImageSource.camera);
                if (vid != null) {
                  setState(() => _media.add(vid));
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _handleRequest() async {
    // Check if user is guest
    final canProceed = await checkGuestAndShowDialog(context);
    if (!mounted) return;
    if (!canProceed) return;

    if (!_agreedToTerms) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('agree_terms_error'))));
      return;
    }

    // Description is now optional if type is selected, or handled differently?
    // Notion doc: "وصف المشكلة (نص) “أختياري ” , صور “أختياري ”"
    // But later says "صور (أهم نقطة لتقليل الخلافات)".
    // Let's keep it optional but encourage it.

    if (_selectedDate == null || _selectedTime == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('select_visit_date_error'))),
      );
      return;
    }

    final userDescription = _descriptionController.text.trim();
    final customFromSelection = _hasCustomServiceFromSelection;
    final customFromProblemType =
        _isOtherProblemType(_selectedProblemType) && !customFromSelection;

    if (customFromProblemType && userDescription.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.tr('service_request_other_service_description_required'),
          ),
        ),
      );
      return;
    }

    final mergedMediaFiles = <String>[
      ..._customServiceImagePaths.where((path) => path.trim().isNotEmpty),
      ..._media.map((e) => e.path).where((path) => path.trim().isNotEmpty),
    ];

    if (_isCustomServiceSelection && mergedMediaFiles.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.tr('service_request_other_service_image_required'),
          ),
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate!);
      final timeStr =
          '${_selectedTime!.hour.toString().padLeft(2, '0')}:${_selectedTime!.minute.toString().padLeft(2, '0')}';

      final address = _currentAddressData?['address'] ?? '';
      final lat = _toDouble(_currentAddressData?['lat']);
      final lng = _toDouble(_currentAddressData?['lng']);
      final countryCode = (_currentAddressData?['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase();

      if (address.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('select_address_error'))),
        );
        setState(() => _isSubmitting = false);
        return;
      }

      final requestedParts = <Map<String, dynamic>>[];
      _selectedSpareQuantities.forEach((key, quantity) {
        final spare = _selectedSpares[key];
        if (spare == null || quantity <= 0) return;

        final pricingMode =
            _selectedSparePricingModes[key] == 'without_installation'
            ? 'without_installation'
            : 'with_installation';
        final requiresInstallation = pricingMode != 'without_installation';
        final unitPrice = _unitPriceByMode(spare, pricingMode);

        requestedParts.add({
          'spare_part_id': _toInt(spare['id']),
          'name': (spare['name'] ?? context.tr('spare_part')).toString(),
          'quantity': quantity,
          'pricing_mode': pricingMode,
          'requires_installation': requiresInstallation,
          'unit_price': unitPrice,
        });
      });

      final effectiveCustomTitle = _hasCustomServiceFromSelection
          ? _customServiceTitle.trim()
          : (_selectedProblemType?.trim().isNotEmpty == true
                ? _selectedProblemType!.trim()
                : context.tr('service_request_other_service'));
      final effectiveNotes = userDescription.isNotEmpty
          ? userDescription
          : (_isCustomServiceSelection
                ? context
                      .tr('service_request_other_service_request')
                      .replaceAll('{title}', effectiveCustomTitle)
                : '');

      final response = await _ordersService.createOrder(
        categoryId: widget.service.id,
        address: address,
        lat: lat,
        lng: lng,
        countryCode: countryCode,
        notes: effectiveNotes,
        scheduledDate: dateStr,
        scheduledTime: timeStr,
        mediaFiles: mergedMediaFiles,
        serviceIds: widget.subServices.isEmpty ? null : widget.subServices,
        isCustomService: _isCustomServiceSelection,
        customServiceTitle: _isCustomServiceSelection
            ? effectiveCustomTitle
            : null,
        customServiceDescription: _isCustomServiceSelection
            ? effectiveNotes
            : null,
        problemDetails: {
          'type':
              _selectedProblemType ??
              (_hasCustomServiceFromSelection
                  ? 'custom_service_request'
                  : 'Other'),
          if (_selectedProblemTypeId != null)
            'type_option_id': _selectedProblemTypeId,
          'user_desc': effectiveNotes,
          'category_id': widget.service.id,
          if (widget.subServices.length == 1)
            'service_type_id': widget.subServices.first,
          if (widget.subServices.isNotEmpty)
            'service_type_ids': widget.subServices,
          if (requestedParts.isNotEmpty) 'spare_parts': requestedParts,
          if (requestedParts.isNotEmpty)
            'requires_installation': requestedParts.any(
              (part) => part['requires_installation'] == true,
            ),
          if (_isCustomServiceSelection)
            'custom_service': {
              'title': effectiveCustomTitle,
              'description': effectiveNotes,
            },
          if (_isCustomServiceSelection) 'is_custom_service': true,
        },
      );

      if (response.success) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('request_sent_success')),
              backgroundColor: Colors.green,
            ),
          );
          // Navigate to Orders Screen or Home
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                response.message ?? context.tr('request_send_failed'),
              ),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${context.tr('connection_error')}: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: now,
      lastDate: now.add(const Duration(days: 30)),
    );
    if (date != null) {
      setState(() => _selectedDate = date);
    }
  }

  Future<void> _pickTime() async {
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (time != null) {
      setState(() => _selectedTime = time);
    }
  }

  Widget _buildSelectedServicesCard() {
    final selectedRows = _selectedServiceRows;
    final count = selectedRows.isNotEmpty
        ? selectedRows.length
        : widget.subServices.length;
    if (count <= 1) {
      return const SizedBox.shrink();
    }

    final names = selectedRows.isNotEmpty
        ? selectedRows
        : widget.subServices.map((id) => <String, dynamic>{'id': id}).toList();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.sm,
        border: Border.all(color: AppColors.gray100),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.playlist_add_check, color: AppColors.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  context.tr('service_request_selected_services'),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Text(
                '$count',
                style: TextStyle(
                  color: AppColors.gray600,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: names
                .map(
                  (item) => Chip(
                    label: Text(
                      item.containsKey('name_ar')
                          ? _localizedSelectedServiceName(item)
                          : '${context.tr('service')} #${_toInt(item['id'])}',
                      overflow: TextOverflow.ellipsis,
                    ),
                    backgroundColor: const Color(0xFFF8FAFC),
                    side: BorderSide(color: AppColors.gray200),
                    labelStyle: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AppColors.gray900,
                    ),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA), // Softer background
      body: SafeArea(
        child: Column(
          children: [
            // 1. Premium Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  InkWell(
                    onTap: widget.onBack ?? () => Navigator.pop(context),
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade100,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.arrow_back,
                        color: Colors.black87,
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Text(
                    context
                        .tr('service_request_title')
                        .replaceAll('{service}', _localizedServiceName),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87,
                      fontFamily: 'Cairo',
                    ),
                  ),
                ],
              ),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // 2. The "Free Inspection" Promise (Trust Builder)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [Colors.green.shade50, Colors.white],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        border: Border.all(color: Colors.green.shade200),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.green.withValues(alpha: 0.1),
                            blurRadius: 8,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: Colors.green.withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.verified_user,
                              color: Colors.green,
                              size: 24,
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  context.tr(
                                    'service_request_free_inspection_title',
                                  ),
                                  style: const TextStyle(
                                    color: Colors.green,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  context.tr(
                                    'service_request_free_inspection_subtitle',
                                  ),
                                  style: TextStyle(
                                    color: Colors.green.shade900,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    _buildSelectedServicesCard(),

                    if (_selectedServiceRows.isNotEmpty ||
                        widget.subServices.length > 1)
                      const SizedBox(height: 20),

                    // 3. Problem Diagnosis (The "Input" Phase)
                    Text(
                      context.tr('service_request_problem_details'),
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Problem Type Dropdown
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: AppShadows.sm,
                      ),
                      child: DropdownButtonFormField<String>(
                        initialValue: _selectedProblemType,
                        isExpanded: true,
                        decoration: InputDecoration(
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 12,
                          ),
                          border: InputBorder.none,
                          hintText: context.tr(
                            'service_request_problem_type_hint',
                          ),
                          hintStyle: TextStyle(
                            color: Colors.grey.shade400,
                            fontSize: 13,
                          ),
                          prefixIcon: const Icon(
                            Icons.build_circle_outlined,
                            color: AppColors.primary,
                          ),
                        ),
                        selectedItemBuilder: (context) {
                          return _problemTypes
                              .map(
                                (item) => Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: Text(
                                    item.title,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList();
                        },
                        items: _problemTypes
                            .map(
                              (item) => DropdownMenuItem(
                                value: item.title,
                                child: Text(
                                  item.title,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: (v) =>
                            setState(() => _selectedProblemType = v),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Description & Photos (Grouped)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: AppShadows.sm,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          TextField(
                            controller: _descriptionController,
                            maxLines: 3,
                            decoration: InputDecoration(
                              hintText: _isCustomServiceSelection
                                  ? context.tr(
                                      'service_request_problem_description_required_for_other',
                                    )
                                  : context.tr(
                                      'service_request_problem_description_optional',
                                    ),
                              border: InputBorder.none,
                            ),
                          ),
                          if (_isCustomServiceSelection)
                            Padding(
                              padding: EdgeInsets.only(top: 8),
                              child: Text(
                                context.tr(
                                  'service_request_other_service_note',
                                ),
                                style: TextStyle(
                                  color: Colors.redAccent,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          const Divider(height: 24),

                          // Photos Section with specific label from prompt
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  context.tr('service_request_problem_images'),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.black87,
                                  ),
                                ),
                              ),
                              InkWell(
                                onTap: _pickMedia,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 6,
                                  ),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary.withValues(
                                      alpha: 0.1,
                                    ),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        Icons.add_a_photo,
                                        size: 16,
                                        color: AppColors.primary,
                                      ),
                                      SizedBox(width: 4),
                                      Text(
                                        context.tr('add'),
                                        style: TextStyle(
                                          color: AppColors.primary,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 12,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),

                          if (_media.isNotEmpty) ...[
                            const SizedBox(height: 16),
                            SizedBox(
                              height: 90,
                              child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                itemCount: _media.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(width: 8),
                                itemBuilder: (ctx, i) => Stack(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(12),
                                      child: Image.file(
                                        File(_media[i].path),
                                        width: 90,
                                        height: 90,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                    Positioned(
                                      top: 4,
                                      right: 4,
                                      child: InkWell(
                                        onTap: () =>
                                            setState(() => _media.removeAt(i)),
                                        child: Container(
                                          padding: const EdgeInsets.all(4),
                                          decoration: const BoxDecoration(
                                            color: Colors.black54,
                                            shape: BoxShape.circle,
                                          ),
                                          child: const Icon(
                                            Icons.close,
                                            size: 12,
                                            color: Colors.white,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    if (_hasCustomServiceFromSelection) ...[
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF9FAFB),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: AppColors.gray200),
                        ),
                        child: Row(
                          children: [
                            if (_customServiceImagePaths.isNotEmpty &&
                                File(
                                  _customServiceImagePaths.first,
                                ).existsSync())
                              ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: Image.file(
                                  File(_customServiceImagePaths.first),
                                  width: 56,
                                  height: 56,
                                  fit: BoxFit.cover,
                                ),
                              ),
                            if (_customServiceImagePaths.isNotEmpty &&
                                File(
                                  _customServiceImagePaths.first,
                                ).existsSync())
                              const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    context.tr(
                                      'service_request_custom_service_added',
                                    ),
                                    style: const TextStyle(
                                      color: AppColors.gray600,
                                      fontSize: 12,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    _customServiceTitle,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.gray900,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 24),
                    ],

                    Text(
                      context.tr('service_request_requested_spares_optional'),
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: AppShadows.sm,
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              const Icon(
                                Icons.precision_manufacturing_outlined,
                                color: AppColors.gray500,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  _selectedSpares.isEmpty
                                      ? context.tr(
                                          'service_request_no_spares_added',
                                        )
                                      : context
                                            .tr(
                                              'service_request_spares_added_count',
                                            )
                                            .replaceAll(
                                              '{count}',
                                              _selectedSpares.length.toString(),
                                            ),
                                  style: const TextStyle(
                                    color: AppColors.gray700,
                                    fontSize: 13,
                                  ),
                                ),
                              ),
                              TextButton.icon(
                                onPressed: _showSparesPicker,
                                icon: const Icon(Icons.add_circle_outline),
                                label: Text(context.tr('add')),
                              ),
                            ],
                          ),
                          if (_selectedSpares.isNotEmpty) ...[
                            const Divider(height: 20),
                            ..._selectedSpares.entries.map((entry) {
                              final key = entry.key;
                              final spare = entry.value;
                              final qty = _selectedSpareQuantities[key] ?? 1;
                              final mode =
                                  _selectedSparePricingModes[key] ??
                                  'with_installation';
                              final unitPrice = _unitPriceByMode(spare, mode);
                              final name =
                                  (spare['name'] ?? context.tr('spare_part'))
                                      .toString();

                              return Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF9FAFB),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppColors.gray200),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            name,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          const SizedBox(height: 2),
                                          _spareModeAndPriceLine(
                                            mode,
                                            unitPrice,
                                          ),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      onPressed: () =>
                                          _changeSpareQuantity(key, -1),
                                      icon: const Icon(Icons.remove_circle),
                                    ),
                                    Text(
                                      '$qty',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    IconButton(
                                      onPressed: () =>
                                          _changeSpareQuantity(key, 1),
                                      icon: const Icon(Icons.add_circle),
                                    ),
                                  ],
                                ),
                              );
                            }),
                          ],
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    // 4. Scheduling (The "Logistics" Phase)
                    Text(
                      context.tr('service_request_schedule_location'),
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: AppShadows.sm,
                      ),
                      child: Column(
                        children: [
                          // Address
                          Row(
                            children: [
                              const Icon(
                                Icons.location_on_outlined,
                                color: Colors.grey,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  _currentAddressData?['address'] ??
                                      context.tr(
                                        'service_request_select_service_location',
                                      ),
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: _currentAddressData == null
                                        ? Colors.red
                                        : Colors.black,
                                  ),
                                ),
                              ),
                              TextButton(
                                onPressed: _pickAddress,
                                child: Text(
                                  context.tr('service_request_change'),
                                  style: TextStyle(fontSize: 12),
                                ),
                              ),
                            ],
                          ),
                          const Divider(),
                          // Date
                          InkWell(
                            onTap: _pickDate,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                vertical: 8.0,
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.calendar_today_outlined,
                                    color: Colors.grey,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Text(
                                      _selectedDate == null
                                          ? context.tr(
                                              'service_request_select_day',
                                            )
                                          : DateFormat(
                                              'EEEE, d MMMM yyyy',
                                              Localizations.localeOf(
                                                context,
                                              ).languageCode,
                                            ).format(_selectedDate!),
                                      style: TextStyle(
                                        color: _selectedDate == null
                                            ? Colors.grey
                                            : Colors.black,
                                        fontWeight: _selectedDate == null
                                            ? FontWeight.normal
                                            : FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const Divider(),
                          // Time
                          InkWell(
                            onTap: _pickTime,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                vertical: 8.0,
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.access_time_outlined,
                                    color: Colors.grey,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Text(
                                      _selectedTime == null
                                          ? context.tr(
                                              'service_request_select_time',
                                            )
                                          : _selectedTime!.format(context),
                                      style: TextStyle(
                                        color: _selectedTime == null
                                            ? Colors.grey
                                            : Colors.black,
                                        fontWeight: _selectedTime == null
                                            ? FontWeight.normal
                                            : FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 32),

                    // 5. Action (Confirmation)
                    Row(
                      children: [
                        Checkbox(
                          value: _agreedToTerms,
                          onChanged: (v) =>
                              setState(() => _agreedToTerms = v ?? false),
                          activeColor: AppColors.primary,
                        ),
                        Expanded(
                          child: Text(
                            context.tr('service_request_agree_terms'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: _isSubmitting ? null : _handleRequest,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors
                            .black, // Dark text on yellow primary usually looks better for contrast
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: _isSubmitting
                          ? const SizedBox(
                              height: 24,
                              width: 24,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(
                              context.tr('service_request_confirm_order'),
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
