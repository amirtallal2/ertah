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

  final TextEditingController _siteCityController = TextEditingController();
  final TextEditingController _purposeController = TextEditingController();
  final TextEditingController _estimatedWeightController =
      TextEditingController();
  final TextEditingController _estimatedDistanceController =
      TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  final List<XFile> _media = [];

  Map<String, dynamic>? _addressData;
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
    _initializeSelectedService();
  }

  @override
  void dispose() {
    _siteCityController.dispose();
    _purposeController.dispose();
    _estimatedWeightController.dispose();
    _estimatedDistanceController.dispose();
    _notesController.dispose();
    super.dispose();
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

  double get _estimatedWeightKg =>
      double.tryParse(_estimatedWeightController.text.trim()) ?? 0;

  double get _estimatedDistanceMeters =>
      double.tryParse(_estimatedDistanceController.text.trim()) ?? 0;

  double _calculateEstimatedPrice(Map<String, dynamic> selectedService) {
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

  String _localizedServiceName(Map<String, dynamic> row) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = _toStr(row['name_ar']).trim();
    final en = _toStr(row['name_en']).trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  void _initializeSelectedService() {
    if (widget.availableServices.isEmpty) return;

    final preselected = widget.preselectedServiceIds.isNotEmpty
        ? widget.preselectedServiceIds.first
        : null;

    if (preselected != null &&
        widget.availableServices.any(
          (item) => _toInt(item['id']) == preselected,
        )) {
      _selectedServiceId = preselected;
      return;
    }

    if (widget.availableServices.length == 1) {
      _selectedServiceId = _toInt(widget.availableServices.first['id']);
    }
  }

  Future<void> _pickAddress() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const LocationPickerScreen(returnDataOnly: true),
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

  Future<void> _pickDate({required bool isStart}) async {
    final now = DateTime.now();
    final initial = isStart
        ? (_startDate ?? now)
        : (_endDate ?? _startDate ?? now.add(const Duration(days: 1)));

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: now,
      lastDate: now.add(const Duration(days: 180)),
    );

    if (picked == null) return;

    setState(() {
      if (isStart) {
        _startDate = picked;
        if (_endDate != null && _endDate!.isBefore(picked)) {
          _endDate = picked;
        }
      } else {
        _endDate = picked;
      }
      if (_startDate != null && _endDate != null) {
        final days = _endDate!.difference(_startDate!).inDays + 1;
        _durationDays = days > 0 ? days : 1;
      }
    });
  }

  bool _validate() {
    if (widget.availableServices.isNotEmpty && _selectedServiceId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('container_type_required'))),
      );
      return false;
    }

    if ((_addressData?['address'] ?? '').toString().trim().isEmpty) {
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

    // Orders API requires media for custom service requests.
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

  Map<String, dynamic> _selectedServiceRow() {
    if (_selectedServiceId == null) return <String, dynamic>{};
    return widget.availableServices.firstWhere(
      (item) => _toInt(item['id']) == _selectedServiceId,
      orElse: () => <String, dynamic>{},
    );
  }

  Future<void> _submit() async {
    final canProceed = await checkGuestAndShowDialog(context);
    if (!mounted || !canProceed) return;

    if (!_validate()) return;

    setState(() => _isSubmitting = true);
    try {
      final selectedService = _selectedServiceRow();
      final selectedServiceName = selectedService.isNotEmpty
          ? _localizedServiceName(selectedService)
          : context.tr('container_service');
      final estimatedAmount = _calculateEstimatedPrice(selectedService);

      final address = (_addressData?['address'] ?? '').toString().trim();
      final lat = double.tryParse('${_addressData?['lat'] ?? ''}');
      final lng = double.tryParse('${_addressData?['lng'] ?? ''}');
      final countryCode = (_addressData?['country_code'] ?? '')
          .toString()
          .trim()
          .toUpperCase();

      final payload = <String, dynamic>{
        'type': 'container_rental',
        'module': 'container_rental',
        'user_desc': _notesController.text.trim(),
        'container_request': {
          'container_service_id': _selectedServiceId,
          'container_service_name': selectedServiceName,
          'container_size': _toStr(selectedService['container_size']),
          'capacity_ton': selectedService['capacity_ton'],
          'daily_price':
              selectedService['daily_price'] ?? selectedService['price'],
          'weekly_price': selectedService['weekly_price'],
          'monthly_price': selectedService['monthly_price'],
          'delivery_fee': selectedService['delivery_fee'],
          'price_per_kg': selectedService['price_per_kg'],
          'price_per_meter': selectedService['price_per_meter'],
          'minimum_charge': selectedService['minimum_charge'],
          'site_city': _siteCityController.text.trim(),
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
          'estimated_price': _calculateEstimatedPrice(
            selectedService,
          ).toStringAsFixed(2),
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
    } catch (e) {
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

  @override
  Widget build(BuildContext context) {
    final selectedService = _selectedServiceRow();
    final selectedServiceName = selectedService.isNotEmpty
        ? _localizedServiceName(selectedService)
        : context.tr('not_specified');

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
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            if (widget.availableServices.isNotEmpty)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: AppShadows.sm,
                ),
                child: DropdownButtonFormField<int>(
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
                  onChanged: (value) =>
                      setState(() => _selectedServiceId = value),
                ),
              ),
            if (selectedService.isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: AppShadows.sm,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      selectedServiceName,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${context.tr('container_size_label')}: ${_toStr(selectedService['container_size']).isEmpty ? '-' : _toStr(selectedService['container_size'])}',
                      style: const TextStyle(
                        color: AppColors.gray600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
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
                    controller: _siteCityController,
                    decoration: InputDecoration(
                      labelText: context.tr('city'),
                      border: InputBorder.none,
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.location_on_outlined),
                    title: Text(
                      (_addressData?['address'] ?? '').toString().trim().isEmpty
                          ? context.tr('container_install_address_label')
                          : (_addressData?['address']).toString(),
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
                  if (selectedService.isNotEmpty) ...[
                    const Divider(height: 16),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        context
                            .tr('initial_estimate_with_value')
                            .replaceAll(
                              '{value}',
                              _calculateEstimatedPrice(
                                selectedService,
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
                  const Divider(height: 1),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          context.tr('container_rental_duration_days'),
                        ),
                      ),
                      IconButton(
                        onPressed: _durationDays > 1
                            ? () => setState(() => _durationDays--)
                            : null,
                        icon: const Icon(Icons.remove_circle_outline),
                      ),
                      Text('$_durationDays'),
                      IconButton(
                        onPressed: () => setState(() => _durationDays++),
                        icon: const Icon(Icons.add_circle_outline),
                      ),
                    ],
                  ),
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
                Expanded(child: Text(context.tr('agree_service_terms'))),
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
