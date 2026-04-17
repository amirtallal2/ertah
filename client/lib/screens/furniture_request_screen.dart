import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../services/furniture_requests_service.dart';
import '../widgets/guest_guard.dart';
import '../services/app_localizations.dart';

class FurnitureRequestScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  final List<int> selectedServiceIds;
  final List<Map<String, dynamic>> selectedServices;

  const FurnitureRequestScreen({
    super.key,
    required this.service,
    this.selectedServiceIds = const [],
    this.selectedServices = const [],
  });

  @override
  State<FurnitureRequestScreen> createState() => _FurnitureRequestScreenState();
}

class _FurnitureRequestScreenState extends State<FurnitureRequestScreen> {
  final FurnitureRequestsService _service = FurnitureRequestsService();

  final TextEditingController _pickupAddressController =
      TextEditingController();
  final TextEditingController _dropoffAddressController =
      TextEditingController();
  final TextEditingController _pickupCityController = TextEditingController();
  final TextEditingController _dropoffCityController = TextEditingController();
  final TextEditingController _estimatedWeightController =
      TextEditingController();
  final TextEditingController _estimatedDistanceController =
      TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  final Map<String, TextEditingController> _textFieldControllers = {};
  final Map<String, dynamic> _fieldValues = {};

  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _agreedToTerms = false;

  List<Map<String, dynamic>> _areas = [];
  List<Map<String, dynamic>> _fields = [];
  List<Map<String, dynamic>> _services = [];

  int? _selectedAreaId;
  int? _selectedServiceId;
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;

  @override
  void initState() {
    super.initState();
    _loadConfig();
  }

  @override
  void dispose() {
    _pickupAddressController.dispose();
    _dropoffAddressController.dispose();
    _pickupCityController.dispose();
    _dropoffCityController.dispose();
    _estimatedWeightController.dispose();
    _estimatedDistanceController.dispose();
    _notesController.dispose();
    for (final controller in _textFieldControllers.values) {
      controller.dispose();
    }
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

  String _toStr(dynamic value) {
    return value?.toString() ?? '';
  }

  Map<String, dynamic> get _selectedServiceRow {
    if (_selectedServiceId == null) return <String, dynamic>{};
    return _services.firstWhere(
      (item) => _toInt(item['id']) == _selectedServiceId,
      orElse: () => <String, dynamic>{},
    );
  }

  double get _estimatedWeightKg =>
      double.tryParse(_estimatedWeightController.text.trim()) ?? 0;

  double get _estimatedDistanceMeters =>
      double.tryParse(_estimatedDistanceController.text.trim()) ?? 0;

  double get _calculatedEstimate {
    final service = _selectedServiceRow;
    if (service.isEmpty) return 0;

    final basePrice = _toDouble(service['base_price']);
    final perKg = _toDouble(service['price_per_kg']);
    final perMeter = _toDouble(service['price_per_meter']);
    final minimumCharge = _toDouble(service['minimum_charge']);

    var total =
        basePrice +
        (perKg * _estimatedWeightKg) +
        (perMeter * _estimatedDistanceMeters);
    if (minimumCharge > 0) {
      total = total < minimumCharge ? minimumCharge : total;
    }
    return total;
  }

  String _localized(Map<String, dynamic> item, String arKey, String enKey) {
    final isArabic = Localizations.localeOf(context).languageCode == 'ar';
    final ar = _toStr(item[arKey]).trim();
    final en = _toStr(item[enKey]).trim();
    if (isArabic) {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  Future<void> _loadConfig() async {
    setState(() => _isLoading = true);
    try {
      final response = await _service.getConfig();
      if (!mounted) return;

      if (!response.success || response.data is! Map) {
        setState(() => _isLoading = false);
        return;
      }

      final data = Map<String, dynamic>.from(response.data as Map);

      final areasRaw = (data['areas'] as List? ?? []);
      final fieldsRaw = (data['fields'] as List? ?? []);
      final servicesRaw = (data['services'] as List? ?? []);

      final areas = <Map<String, dynamic>>[];
      final fields = <Map<String, dynamic>>[];
      final services = <Map<String, dynamic>>[];

      for (final item in areasRaw) {
        if (item is Map) {
          areas.add(Map<String, dynamic>.from(item));
        }
      }

      for (final item in fieldsRaw) {
        if (item is Map) {
          fields.add(Map<String, dynamic>.from(item));
        }
      }

      for (final item in servicesRaw) {
        if (item is Map) {
          services.add(Map<String, dynamic>.from(item));
        }
      }

      final selectedFromPrevious = widget.selectedServiceIds.isNotEmpty
          ? widget.selectedServiceIds.first
          : null;

      int? selectedServiceId;
      if (selectedFromPrevious != null &&
          services.any((s) => _toInt(s['id']) == selectedFromPrevious)) {
        selectedServiceId = selectedFromPrevious;
      } else if (services.length == 1) {
        selectedServiceId = _toInt(services.first['id']);
      }

      for (final field in fields) {
        final key = _toStr(field['field_key']);
        if (key.isEmpty) continue;
        final type = _toStr(field['field_type']).toLowerCase();

        if (type == 'checkbox') {
          _fieldValues[key] = false;
          continue;
        }

        _fieldValues[key] = '';
        _textFieldControllers[key] = TextEditingController();
      }

      setState(() {
        _areas = areas;
        _fields = fields;
        _services = services;
        _selectedServiceId = selectedServiceId;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: now,
      lastDate: now.add(const Duration(days: 60)),
    );

    if (picked != null) {
      setState(() => _selectedDate = picked);
    }
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );

    if (picked != null) {
      setState(() => _selectedTime = picked);
    }
  }

  String _fieldLabel(Map<String, dynamic> field) {
    return _localized(field, 'label_ar', 'label_en');
  }

  String _fieldPlaceholder(Map<String, dynamic> field) {
    return _localized(field, 'placeholder_ar', 'placeholder_en');
  }

  bool _isFieldRequired(Map<String, dynamic> field) {
    return field['is_required'] == true || field['is_required'] == 1;
  }

  bool _validateFields() {
    if (_selectedAreaId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('furniture_area_required'))),
      );
      return false;
    }

    if (_services.isNotEmpty && _selectedServiceId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('furniture_service_type_required'))),
      );
      return false;
    }

    if (_pickupAddressController.text.trim().isEmpty ||
        _dropoffAddressController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('furniture_addresses_required'))),
      );
      return false;
    }

    for (final field in _fields) {
      if (!_isFieldRequired(field)) {
        continue;
      }
      final key = _toStr(field['field_key']);
      if (key.isEmpty) {
        continue;
      }

      final type = _toStr(field['field_type']).toLowerCase();
      final label = _fieldLabel(field);
      final value = _fieldValues[key];

      if (type == 'checkbox') {
        if (value != true) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('${context.tr('field_required')}: $label')),
          );
          return false;
        }
        continue;
      }

      final text = _toStr(value).trim();
      if (text.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${context.tr('field_required')}: $label')),
        );
        return false;
      }
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

    if (!_validateFields()) {
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final details = <String, dynamic>{};
      for (final field in _fields) {
        final key = _toStr(field['field_key']);
        if (key.isEmpty) continue;
        final value = _fieldValues[key];
        if (value == null) continue;
        if (value is String && value.trim().isEmpty) continue;
        details[key] = value;
      }

      if (_estimatedWeightKg > 0) {
        details['estimated_weight_kg'] = _estimatedWeightKg;
      }
      if (_estimatedDistanceMeters > 0) {
        details['estimated_distance_meters'] = _estimatedDistanceMeters;
      }

      final moveDate = _selectedDate != null
          ? DateFormat('yyyy-MM-dd').format(_selectedDate!)
          : null;

      final preferredTime = _selectedTime != null
          ? '${_selectedTime!.hour.toString().padLeft(2, '0')}:${_selectedTime!.minute.toString().padLeft(2, '0')}'
          : null;

      final selectedService = _services.firstWhere(
        (item) => _toInt(item['id']) == _selectedServiceId,
        orElse: () => <String, dynamic>{},
      );

      final selectedServicesPayload = selectedService.isEmpty
          ? <Map<String, dynamic>>[]
          : [
              {
                'id': _toInt(selectedService['id']),
                'name_ar': _toStr(selectedService['name_ar']),
                'name_en': _toStr(selectedService['name_en']),
                'base_price': _toDouble(selectedService['base_price']),
                'price_per_kg': _toDouble(selectedService['price_per_kg']),
                'price_per_meter': _toDouble(
                  selectedService['price_per_meter'],
                ),
                'minimum_charge': _toDouble(selectedService['minimum_charge']),
              },
            ];

      final response = await _service.createRequest(
        serviceId: _selectedServiceId,
        areaId: _selectedAreaId!,
        pickupAddress: _pickupAddressController.text.trim(),
        dropoffAddress: _dropoffAddressController.text.trim(),
        pickupCity: _pickupCityController.text.trim(),
        dropoffCity: _dropoffCityController.text.trim(),
        moveDate: moveDate,
        preferredTime: preferredTime,
        notes: _notesController.text.trim(),
        details: details,
        estimatedWeightKg: _estimatedWeightKg > 0 ? _estimatedWeightKg : null,
        estimatedDistanceMeters: _estimatedDistanceMeters > 0
            ? _estimatedDistanceMeters
            : null,
        serviceIds: _selectedServiceId != null ? [_selectedServiceId!] : null,
        selectedServices: selectedServicesPayload,
      );

      if (!mounted) return;

      if (response.success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.tr('furniture_request_sent_success')),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.of(context).popUntil((route) => route.isFirst);
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

  Widget _buildDynamicField(Map<String, dynamic> field) {
    final key = _toStr(field['field_key']);
    if (key.isEmpty) return const SizedBox.shrink();

    final type = _toStr(field['field_type']).toLowerCase();
    final label = _fieldLabel(field);
    final placeholder = _fieldPlaceholder(field);
    final required = _isFieldRequired(field);

    if (type == 'checkbox') {
      return Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: AppShadows.sm,
        ),
        child: CheckboxListTile(
          value: _fieldValues[key] == true,
          onChanged: (value) {
            setState(() {
              _fieldValues[key] = value == true;
            });
          },
          title: Text(required ? '$label *' : label),
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: AppColors.primary,
        ),
      );
    }

    if (type == 'select') {
      final optionsRaw = (field['options'] as List? ?? []);
      final options = <Map<String, dynamic>>[];
      for (final item in optionsRaw) {
        if (item is Map) {
          options.add(Map<String, dynamic>.from(item));
        }
      }

      return Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: AppShadows.sm,
        ),
        child: DropdownButtonFormField<String>(
          value: (_fieldValues[key] as String?)?.isNotEmpty == true
              ? _fieldValues[key] as String
              : null,
          decoration: InputDecoration(
            border: InputBorder.none,
            labelText: required ? '$label *' : label,
          ),
          items: options
              .map(
                (option) => DropdownMenuItem<String>(
                  value: _toStr(option['value']),
                  child: Text(_localized(option, 'label_ar', 'label_en')),
                ),
              )
              .toList(),
          onChanged: (value) {
            setState(() {
              _fieldValues[key] = value ?? '';
            });
          },
        ),
      );
    }

    final controller = _textFieldControllers[key]!;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: AppShadows.sm,
      ),
      child: TextField(
        controller: controller,
        keyboardType: type == 'number'
            ? TextInputType.number
            : TextInputType.text,
        maxLines: type == 'textarea' ? 3 : 1,
        decoration: InputDecoration(
          border: InputBorder.none,
          labelText: required ? '$label *' : label,
          hintText: placeholder.isNotEmpty ? placeholder : null,
        ),
        onChanged: (value) {
          _fieldValues[key] = value;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: Text(
          _localized(
            {
              'name_ar': widget.service.nameAr,
              'name_en': widget.service.nameEn,
            },
            'name_ar',
            'name_en',
          ),
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.tr('furniture_request_data_title'),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
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
                    child: DropdownButtonFormField<int>(
                      value: _selectedAreaId,
                      decoration: InputDecoration(
                        border: InputBorder.none,
                        labelText: context.tr('area_required_label'),
                      ),
                      items: _areas
                          .map(
                            (area) => DropdownMenuItem<int>(
                              value: _toInt(area['id']),
                              child: Text(
                                _localized(area, 'name_ar', 'name_en'),
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (value) =>
                          setState(() => _selectedAreaId = value),
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (_services.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: AppShadows.sm,
                      ),
                      child: DropdownButtonFormField<int>(
                        value: _selectedServiceId,
                        decoration: InputDecoration(
                          border: InputBorder.none,
                          labelText: context.tr('service_type_required_label'),
                        ),
                        items: _services
                            .map(
                              (service) => DropdownMenuItem<int>(
                                value: _toInt(service['id']),
                                child: Text(
                                  _localized(service, 'name_ar', 'name_en'),
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setState(() => _selectedServiceId = value),
                      ),
                    ),
                  if (_services.isNotEmpty) ...[
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
                          if (_selectedServiceRow.isNotEmpty) ...[
                            const Divider(height: 16),
                            Align(
                              alignment: Alignment.centerRight,
                              child: Text(
                                context
                                    .tr('initial_estimate_with_value')
                                    .replaceAll(
                                      '{value}',
                                      _calculatedEstimate.toStringAsFixed(2),
                                    )
                                    .replaceAll(
                                      '{currency}',
                                      context.tr('sar'),
                                    ),
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
                          controller: _pickupCityController,
                          decoration: InputDecoration(
                            labelText: context.tr('pickup_city'),
                            border: InputBorder.none,
                          ),
                        ),
                        const Divider(height: 1),
                        TextField(
                          controller: _pickupAddressController,
                          decoration: InputDecoration(
                            labelText: context.tr('pickup_address'),
                            border: InputBorder.none,
                          ),
                        ),
                        const Divider(height: 1),
                        TextField(
                          controller: _dropoffCityController,
                          decoration: InputDecoration(
                            labelText: context.tr('dropoff_city'),
                            border: InputBorder.none,
                          ),
                        ),
                        const Divider(height: 1),
                        TextField(
                          controller: _dropoffAddressController,
                          decoration: InputDecoration(
                            labelText: context.tr('dropoff_address'),
                            border: InputBorder.none,
                          ),
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
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.calendar_today_outlined),
                          title: Text(
                            _selectedDate == null
                                ? context.tr('move_date_optional')
                                : DateFormat(
                                    'yyyy-MM-dd',
                                  ).format(_selectedDate!),
                          ),
                          onTap: _pickDate,
                        ),
                        const Divider(height: 1),
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.access_time_outlined),
                          title: Text(
                            _selectedTime == null
                                ? context.tr('preferred_time_optional')
                                : _selectedTime!.format(context),
                          ),
                          onTap: _pickTime,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (_fields.isNotEmpty) ...[
                    Text(
                      context.tr('additional_details'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 10),
                    ..._fields.map(_buildDynamicField),
                  ],
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
                        labelText: context.tr('additional_notes'),
                      ),
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
                      ),
                      child: _isSubmitting
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.black,
                              ),
                            )
                          : Text(context.tr('submit_request')),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
