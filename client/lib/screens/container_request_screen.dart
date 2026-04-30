import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../services/app_localizations.dart';
import '../services/orders_service.dart';
import '../utils/order_tracking_navigation.dart';
import '../widgets/guest_guard.dart';
import 'location_picker_screen.dart';
import 'payment_screen.dart';
import 'static_content_page_screen.dart';

class ContainerRequestScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  final List<Map<String, dynamic>> availableServices;
  final List<int> preselectedServiceIds;

  const ContainerRequestScreen({
    super.key,
    required this.service,
    this.availableServices = const [],
    this.preselectedServiceIds = const [],
  });

  @override
  State<ContainerRequestScreen> createState() => _ContainerRequestScreenState();
}

class _ContainerRequestScreenState extends State<ContainerRequestScreen> {
  final OrdersService _ordersService = OrdersService();
  final ImagePicker _picker = ImagePicker();

  final TextEditingController _purposeController = TextEditingController();
  final TextEditingController _estimatedWeightController =
      TextEditingController();
  final TextEditingController _estimatedDistanceController =
      TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  final List<XFile> _media = [];

  Map<String, dynamic>? _addressData;
  List<int> _selectedServiceIds = [];
  int? _selectedServiceId;
  DateTime? _startDate;
  DateTime? _endDate;
  int _quantity = 1;
  int _durationDays = 1;
  bool _needsLoadingHelp = false;
  bool _needsOperator = false;
  bool _agreedToTerms = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _initializeSelectedServices();
  }

  @override
  void dispose() {
    _purposeController.dispose();
    _estimatedWeightController.dispose();
    _estimatedDistanceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  void _openTermsPage() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            const StaticContentPageScreen(page: StaticContentPageKey.terms),
      ),
    );
  }

  int? _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _toStr(dynamic value) => value?.toString() ?? '';

  List<int> _normalizeServiceIds(Iterable<dynamic> values) {
    final ids = <int>[];
    for (final value in values) {
      final id = _toInt(value);
      if (id != null && id > 0 && !ids.contains(id)) {
        ids.add(id);
      }
    }
    return ids;
  }

  double get _estimatedWeightKg =>
      double.tryParse(_estimatedWeightController.text.trim()) ?? 0;

  double get _estimatedDistanceMeters =>
      double.tryParse(_estimatedDistanceController.text.trim()) ?? 0;

  void _initializeSelectedServices() {
    final matchedIds = _normalizeServiceIds(widget.preselectedServiceIds)
        .where(
          (id) =>
              widget.availableServices.any((item) => _toInt(item['id']) == id),
        )
        .toList();

    if (matchedIds.isNotEmpty) {
      _selectedServiceIds = matchedIds;
      _selectedServiceId = matchedIds.first;
      return;
    }

    if (widget.availableServices.length == 1) {
      final onlyId = _toInt(widget.availableServices.first['id']);
      if (onlyId != null) {
        _selectedServiceIds = [onlyId];
        _selectedServiceId = onlyId;
      }
    }
  }

  Map<String, dynamic> _serviceById(int? serviceId) {
    if (serviceId == null || serviceId <= 0) {
      return <String, dynamic>{};
    }

    return widget.availableServices.firstWhere(
      (item) => _toInt(item['id']) == serviceId,
      orElse: () => <String, dynamic>{},
    );
  }

  List<Map<String, dynamic>> _selectedServiceRows() {
    final rows = <Map<String, dynamic>>[];
    for (final serviceId in _selectedServiceIds) {
      final row = _serviceById(serviceId);
      if (row.isNotEmpty) {
        rows.add(row);
      }
    }
    return rows;
  }

  int? get _primarySelectedServiceId {
    if (_selectedServiceIds.isNotEmpty) {
      return _selectedServiceIds.first;
    }
    return _selectedServiceId;
  }

  Map<String, dynamic> _selectedServiceRow() {
    return _serviceById(_primarySelectedServiceId);
  }

  double _calculateServiceEstimatedPrice(Map<String, dynamic> selectedService) {
    final dailyPrice = _toDouble(selectedService['daily_price']);
    final weeklyPrice = _toDouble(selectedService['weekly_price']);
    final monthlyPrice = _toDouble(selectedService['monthly_price']);
    final deliveryFee = _toDouble(selectedService['delivery_fee']);
    final perKg = _toDouble(selectedService['price_per_kg']);
    final perMeter = _toDouble(selectedService['price_per_meter']);
    final minimumCharge = _toDouble(selectedService['minimum_charge']);

    double baseRental;
    if (_durationDays >= 30 && monthlyPrice > 0) {
      baseRental = (monthlyPrice / 30) * _durationDays;
    } else if (_durationDays >= 7 && weeklyPrice > 0) {
      baseRental = (weeklyPrice / 7) * _durationDays;
    } else {
      baseRental = dailyPrice * _durationDays;
    }

    var total =
        (baseRental * _quantity) +
        deliveryFee +
        (perKg * _estimatedWeightKg) +
        (perMeter * _estimatedDistanceMeters);
    if (minimumCharge > 0 && total < minimumCharge) {
      total = minimumCharge;
    }

    return total;
  }

  double _calculateEstimatedTotal(List<Map<String, dynamic>> selectedRows) {
    if (selectedRows.isEmpty) {
      final fallback = _selectedServiceRow();
      if (fallback.isEmpty) return 0;
      return _calculateServiceEstimatedPrice(fallback);
    }

    var total = 0.0;
    for (final service in selectedRows) {
      total += _calculateServiceEstimatedPrice(service);
    }
    return total;
  }

  String _localizedServiceName(Map<String, dynamic> row) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = _toStr(row['name_ar']).trim();
    final en = _toStr(row['name_en']).trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  String _selectedServicesTitle(List<Map<String, dynamic>> selectedRows) {
    if (selectedRows.isEmpty) {
      final fallback = _selectedServiceRow();
      if (fallback.isEmpty) return context.tr('container_service');
      return _localizedServiceName(fallback);
    }

    return selectedRows.map(_localizedServiceName).join('، ');
  }

  String _serviceChipLabel(Map<String, dynamic> row) {
    final name = _localizedServiceName(row);
    final size = _toStr(row['container_size']).trim();
    if (size.isEmpty) return name;
    return '$name ($size)';
  }

  String _addressText(Map<String, dynamic>? data) {
    if (data == null) return '';
    return (data['address'] ?? '').toString().trim();
  }

  String _addressCity(Map<String, dynamic>? data) {
    if (data == null) return '';
    return (data['city_name'] ?? data['city'] ?? '').toString().trim();
  }

  Future<void> _pickAddress() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const LocationPickerScreen(
          returnDataOnly: true,
          persistSelection: false,
        ),
      ),
    );

    if (result is Map<String, dynamic>) {
      setState(() => _addressData = result);
    }
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
                final image = await _picker.pickImage(
                  source: ImageSource.camera,
                );
                if (image != null && mounted) {
                  setState(() => _media.add(image));
                }
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: Text(context.tr('gallery_photo')),
              onTap: () async {
                Navigator.pop(ctx);
                final image = await _picker.pickImage(
                  source: ImageSource.gallery,
                );
                if (image != null && mounted) {
                  setState(() => _media.add(image));
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  DateTime _dateOnly(DateTime value) {
    return DateTime(value.year, value.month, value.day);
  }

  DateTime _minimumEndDateFor(DateTime startDate) {
    return _dateOnly(startDate).add(const Duration(days: 1));
  }

  void _syncRentalDurationFromDates() {
    if (_startDate == null || _endDate == null) return;

    final start = _dateOnly(_startDate!);
    var end = _dateOnly(_endDate!);
    if (!end.isAfter(start)) {
      end = _minimumEndDateFor(start);
      _endDate = end;
    }

    _startDate = start;
    _durationDays = end.difference(start).inDays.clamp(1, 365).toInt();
  }

  Future<void> _pickDate({required bool isStart}) async {
    final today = _dateOnly(DateTime.now());
    final firstDate = isStart
        ? today
        : (_startDate == null
              ? today.add(const Duration(days: 1))
              : _minimumEndDateFor(_startDate!));
    final lastDate = today.add(Duration(days: isStart ? 180 : 365));
    var initial = isStart
        ? (_startDate ?? today)
        : (_endDate ??
              (_startDate == null
                  ? today.add(const Duration(days: 1))
                  : _minimumEndDateFor(_startDate!)));
    initial = _dateOnly(initial);
    if (initial.isBefore(firstDate)) {
      initial = firstDate;
    }
    if (initial.isAfter(lastDate)) {
      initial = lastDate;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: firstDate,
      lastDate: lastDate,
    );

    if (picked == null) return;

    setState(() {
      if (isStart) {
        final previousDuration = _durationDays.clamp(1, 365).toInt();
        final start = _dateOnly(picked);
        _startDate = start;
        if (_endDate == null || !_dateOnly(_endDate!).isAfter(start)) {
          _endDate = start.add(Duration(days: previousDuration));
        }
      } else {
        _endDate = _dateOnly(picked);
      }
      _syncRentalDurationFromDates();
    });
  }

  bool _validate() {
    if (widget.availableServices.isNotEmpty && _selectedServiceIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('container_type_required'))),
      );
      return false;
    }

    if (_addressText(_addressData).isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('select_address_error'))),
      );
      return false;
    }

    if (_notesController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('container_notes_required'))),
      );
      return false;
    }

    if (_media.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('container_media_required'))),
      );
      return false;
    }

    if (!_agreedToTerms) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('agree_terms_error'))));
      return false;
    }

    return true;
  }

  Future<void> _submit() async {
    final canProceed = await checkGuestAndShowDialog(context);
    if (!mounted || !canProceed) return;

    if (!_validate()) return;

    setState(() => _isSubmitting = true);
    try {
      final selectedRows = _selectedServiceRows();
      final primaryService = _selectedServiceRow();
      final selectedServiceName = _selectedServicesTitle(selectedRows);
      final estimatedAmount = _calculateEstimatedTotal(selectedRows);

      final address = _addressText(_addressData);
      final cityName = _addressCity(_addressData);
      final lat = double.tryParse('${_addressData?['lat'] ?? ''}');
      final lng = double.tryParse('${_addressData?['lng'] ?? ''}');
      final countryCode = (_addressData?['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase();

      final selectedServicesPayload = selectedRows.map((row) {
        return {
          'id': _toInt(row['id']),
          'name_ar': _toStr(row['name_ar']),
          'name_en': _toStr(row['name_en']),
          'container_size': _toStr(row['container_size']),
          'daily_price': _toDouble(row['daily_price']),
          'weekly_price': _toDouble(row['weekly_price']),
          'monthly_price': _toDouble(row['monthly_price']),
          'delivery_fee': _toDouble(row['delivery_fee']),
          'price_per_kg': _toDouble(row['price_per_kg']),
          'price_per_meter': _toDouble(row['price_per_meter']),
          'minimum_charge': _toDouble(row['minimum_charge']),
        };
      }).toList();

      final payload = <String, dynamic>{
        'type': 'container_rental',
        'module': 'container_rental',
        'selected_services': selectedServicesPayload,
        'service_type_ids': _selectedServiceIds,
        'sub_services': _selectedServiceIds,
        'user_desc': _notesController.text.trim(),
        'container_request': {
          'container_service_id': _primarySelectedServiceId,
          'selected_service_ids': _selectedServiceIds,
          'selected_services': selectedServicesPayload,
          'container_service_name': selectedServiceName,
          'container_size': _toStr(primaryService['container_size']),
          'capacity_ton': primaryService['capacity_ton'],
          'daily_price':
              primaryService['daily_price'] ?? primaryService['price'],
          'weekly_price': primaryService['weekly_price'],
          'monthly_price': primaryService['monthly_price'],
          'delivery_fee': primaryService['delivery_fee'],
          'price_per_kg': primaryService['price_per_kg'],
          'price_per_meter': primaryService['price_per_meter'],
          'minimum_charge': primaryService['minimum_charge'],
          'site_city': cityName,
          'site_address': address,
          'start_date': _startDate != null
              ? DateFormat('yyyy-MM-dd').format(_startDate!)
              : null,
          'end_date': _endDate != null
              ? DateFormat('yyyy-MM-dd').format(_endDate!)
              : null,
          'duration_days': _durationDays,
          'quantity': _quantity,
          'estimated_weight_kg': _estimatedWeightKg > 0
              ? _estimatedWeightKg
              : null,
          'estimated_distance_meters': _estimatedDistanceMeters > 0
              ? _estimatedDistanceMeters
              : null,
          'estimated_price': estimatedAmount.toStringAsFixed(2),
          'needs_loading_help': _needsLoadingHelp,
          'needs_operator': _needsOperator,
          'purpose': _purposeController.text.trim(),
          'notes': _notesController.text.trim(),
        },
      };

      final response = await _ordersService.createOrder(
        categoryId: 0,
        address: address,
        lat: lat,
        lng: lng,
        countryCode: countryCode,
        notes: _notesController.text.trim(),
        scheduledDate: _startDate != null
            ? DateFormat('yyyy-MM-dd').format(_startDate!)
            : null,
        mediaFiles: _media.map((item) => item.path).toList(),
        problemDetails: payload,
        serviceIds: _selectedServiceIds.isNotEmpty ? _selectedServiceIds : null,
        isCustomService: true,
        customServiceTitle:
            '${context.tr('container_request_title')} - $selectedServiceName',
        customServiceDescription: _notesController.text.trim(),
      );

      if (!mounted) return;

      if (response.success) {
        final createdOrder = response.data is Map
            ? Map<String, dynamic>.from(
                (response.data as Map).map(
                  (key, value) => MapEntry(key.toString(), value),
                ),
              )
            : <String, dynamic>{};

        final orderId = _toInt(createdOrder['id']) ?? 0;
        final createdAmount = _toDouble(createdOrder['total_amount']);
        final payableAmount = createdAmount > 0
            ? createdAmount
            : estimatedAmount;

        if (orderId <= 0 || payableAmount <= 0) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('request_send_failed')),
              backgroundColor: Colors.red,
            ),
          );
          return;
        }

        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PaymentScreen(
              amount: payableAmount,
              serviceName: context.tr('container_request_title'),
              orderId: orderId,
              autoStartCardPayment: false,
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
                                context.tr('container_request_title'))
                            .toString(),
                    categoryIcon:
                        (createdOrder['category_icon'] ?? widget.service.icon)
                            .toString(),
                    categoryImage:
                        (createdOrder['category_image'] ?? widget.service.image)
                            .toString(),
                  );
                });
              },
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.message ?? context.tr('request_send_failed'),
            ),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('request_send_failed')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  Widget _buildSelectedServicesCard() {
    final selectedRows = _selectedServiceRows();
    if (selectedRows.isEmpty && widget.availableServices.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: AppShadows.sm,
      ),
      child: selectedRows.isNotEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('service_request_selected_services'),
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: selectedRows.map((row) {
                    return Chip(
                      label: Text(
                        _serviceChipLabel(row),
                        style: const TextStyle(color: Colors.black),
                      ),
                      backgroundColor: AppColors.primary.withValues(
                        alpha: 0.12,
                      ),
                      side: BorderSide.none,
                    );
                  }).toList(),
                ),
              ],
            )
          : DropdownButtonFormField<int>(
              initialValue: _selectedServiceId,
              decoration: InputDecoration(
                border: InputBorder.none,
                labelText: context.tr('container_type_label'),
              ),
              items: widget.availableServices.map((row) {
                final serviceId = _toInt(row['id']);
                return DropdownMenuItem<int>(
                  value: serviceId,
                  child: Text(_localizedServiceName(row)),
                );
              }).toList(),
              onChanged: (value) {
                setState(() {
                  _selectedServiceId = value;
                  _selectedServiceIds = value != null ? [value] : <int>[];
                });
              },
            ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final selectedRows = _selectedServiceRows();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('container_request_title')),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.tr('container_request_details'),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            _buildSelectedServicesCard(),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.location_on_outlined),
                    title: Text(
                      _addressText(_addressData).isEmpty
                          ? context.tr('container_install_address_label')
                          : _addressText(_addressData),
                    ),
                    subtitle: Text(
                      _addressCity(_addressData).isNotEmpty
                          ? _addressCity(_addressData)
                          : context.tr(
                              'service_request_add_new_address_from_map',
                            ),
                    ),
                    trailing: const Icon(Icons.edit_location_alt_outlined),
                    onTap: _pickAddress,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: Column(
                children: [
                  TextField(
                    controller: _estimatedWeightController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      labelText: context.tr('estimated_weight_kg'),
                      hintText: context.tr('optional'),
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                  const Divider(height: 1),
                  TextField(
                    controller: _estimatedDistanceController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      labelText: context.tr('estimated_distance_meter'),
                      hintText: context.tr('optional'),
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                  if (selectedRows.isNotEmpty ||
                      _selectedServiceRow().isNotEmpty) ...[
                    const Divider(height: 16),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        context
                            .tr('initial_estimate_with_value')
                            .replaceAll(
                              '{value}',
                              _calculateEstimatedTotal(
                                selectedRows,
                              ).toStringAsFixed(2),
                            )
                            .replaceAll('{currency}', context.tr('sar')),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: AppColors.gray800,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.calendar_today_outlined),
                    title: Text(
                      _startDate == null
                          ? context.tr('container_start_date')
                          : '${context.tr('from')} ${DateFormat('yyyy-MM-dd').format(_startDate!)}',
                    ),
                    onTap: () => _pickDate(isStart: true),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.event_available_outlined),
                    title: Text(
                      _endDate == null
                          ? context.tr('container_end_date')
                          : '${context.tr('to')} ${DateFormat('yyyy-MM-dd').format(_endDate!)}',
                    ),
                    onTap: () => _pickDate(isStart: false),
                  ),
                  if (_startDate != null && _endDate != null) ...[
                    const Divider(height: 1),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.timelapse_outlined),
                      title: Text(context.tr('container_rental_duration_days')),
                      trailing: Text(
                        '$_durationDays',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                  const Divider(height: 1),
                  Row(
                    children: [
                      Expanded(child: Text(context.tr('quantity'))),
                      IconButton(
                        onPressed: _quantity > 1
                            ? () => setState(() => _quantity--)
                            : null,
                        icon: const Icon(Icons.remove_circle_outline),
                      ),
                      Text('$_quantity'),
                      IconButton(
                        onPressed: () => setState(() => _quantity++),
                        icon: const Icon(Icons.add_circle_outline),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: Column(
                children: [
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _needsLoadingHelp,
                    onChanged: (value) =>
                        setState(() => _needsLoadingHelp = value),
                    title: Text(context.tr('container_needs_loading_help')),
                  ),
                  const Divider(height: 1),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _needsOperator,
                    onChanged: (value) =>
                        setState(() => _needsOperator = value),
                    title: Text(context.tr('container_needs_operator')),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: TextField(
                controller: _purposeController,
                decoration: InputDecoration(
                  border: InputBorder.none,
                  labelText: context.tr('container_request_purpose'),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: TextField(
                controller: _notesController,
                maxLines: 3,
                decoration: InputDecoration(
                  border: InputBorder.none,
                  labelText: context.tr('container_request_notes'),
                  hintText: context.tr('container_request_notes_hint'),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: AppShadows.sm,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          context.tr('container_request_images'),
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                      TextButton.icon(
                        onPressed: _pickMedia,
                        icon: const Icon(Icons.add_a_photo_outlined),
                        label: Text(context.tr('add')),
                      ),
                    ],
                  ),
                  if (_media.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 84,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _media.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (context, index) {
                          return Stack(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: Image.file(
                                  File(_media[index].path),
                                  width: 84,
                                  height: 84,
                                  fit: BoxFit.cover,
                                ),
                              ),
                              Positioned(
                                top: 2,
                                right: 2,
                                child: InkWell(
                                  onTap: () =>
                                      setState(() => _media.removeAt(index)),
                                  child: Container(
                                    padding: const EdgeInsets.all(3),
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
                          );
                        },
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Checkbox(
                  value: _agreedToTerms,
                  onChanged: (value) =>
                      setState(() => _agreedToTerms = value ?? false),
                  activeColor: AppColors.primary,
                ),
                Expanded(
                  child: InkWell(
                    onTap: _openTermsPage,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Text(
                        context.tr('agree_service_terms'),
                        style: const TextStyle(
                          color: AppColors.gray700,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.black,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: _isSubmitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(
                        context.tr('submit_request'),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
