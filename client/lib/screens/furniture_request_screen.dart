import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../services/app_localizations.dart';
import '../services/furniture_requests_service.dart';
import '../widgets/guest_guard.dart';
import 'location_picker_screen.dart';
import 'static_content_page_screen.dart';

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

  List<Map<String, dynamic>> _fields = [];
  List<Map<String, dynamic>> _services = [];
  List<int> _selectedServiceIds = [];

  Map<String, dynamic>? _pickupAddressData;
  Map<String, dynamic>? _dropoffAddressData;

  int? _selectedServiceId;
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;

  @override
  void initState() {
    super.initState();
    _selectedServiceIds = _normalizeServiceIds([
      ...widget.selectedServiceIds,
      ...widget.selectedServices.map((item) => item['id']),
    ]);
    if (_selectedServiceIds.isNotEmpty) {
      _selectedServiceId = _selectedServiceIds.first;
    }
    _loadConfig();
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

  @override
  void dispose() {
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

  Map<String, dynamic> _serviceById(int? serviceId) {
    if (serviceId == null || serviceId <= 0) {
      return <String, dynamic>{};
    }

    for (final item in _services) {
      if (_toInt(item['id']) == serviceId) {
        return item;
      }
    }

    for (final item in widget.selectedServices) {
      if (_toInt(item['id']) == serviceId) {
        return Map<String, dynamic>.from(item);
      }
    }

    return <String, dynamic>{};
  }

  List<Map<String, dynamic>> get _selectedServiceRows {
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

  Map<String, dynamic> get _selectedServiceRow {
    return _serviceById(_primarySelectedServiceId);
  }

  double get _estimatedWeightKg =>
      double.tryParse(_estimatedWeightController.text.trim()) ?? 0;

  double get _estimatedDistanceMeters =>
      double.tryParse(_estimatedDistanceController.text.trim()) ?? 0;

  double get _calculatedEstimate {
    final selectedRows = _selectedServiceRows;
    if (selectedRows.isEmpty) {
      final fallback = _selectedServiceRow;
      if (fallback.isEmpty) return 0;
      return _calculateServiceEstimate(fallback);
    }

    var total = 0.0;
    for (final service in selectedRows) {
      total += _calculateServiceEstimate(service);
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

  String _serviceName(Map<String, dynamic> item) {
    return _localized(item, 'name_ar', 'name_en');
  }

  double _calculateServiceEstimate(Map<String, dynamic> service) {
    final basePrice = _toDouble(service['base_price']);
    final perKg = _toDouble(service['price_per_kg']);
    final perMeter = _toDouble(service['price_per_meter']);
    final minimumCharge = _toDouble(service['minimum_charge']);

    var total =
        basePrice +
        (perKg * _estimatedWeightKg) +
        (perMeter * _estimatedDistanceMeters);
    if (minimumCharge > 0 && total < minimumCharge) {
      total = minimumCharge;
    }
    return total;
  }

  String _addressText(Map<String, dynamic>? data) {
    if (data == null) return '';
    return (data['address'] ?? '').toString().trim();
  }

  String _addressCity(Map<String, dynamic>? data) {
    if (data == null) return '';
    return (data['city_name'] ?? data['city'] ?? '').toString().trim();
  }

  Future<void> _pickAddress({required bool isPickup}) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const LocationPickerScreen(
          returnDataOnly: true,
          persistSelection: false,
        ),
      ),
    );

    if (result is! Map) return;

    final mapped = Map<String, dynamic>.from(
      result.map((key, value) => MapEntry(key.toString(), value)),
    );

    setState(() {
      if (isPickup) {
        _pickupAddressData = mapped;
      } else {
        _dropoffAddressData = mapped;
      }
    });
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

      final fieldsRaw = (data['fields'] as List? ?? []);
      final servicesRaw = (data['services'] as List? ?? []);

      final fields = <Map<String, dynamic>>[];
      final services = <Map<String, dynamic>>[];

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

      final matchedSelectedIds = _selectedServiceIds
          .where((id) => services.any((service) => _toInt(service['id']) == id))
          .toList();

      int? selectedServiceId;
      if (matchedSelectedIds.isNotEmpty) {
        selectedServiceId = matchedSelectedIds.first;
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
        _fields = fields;
        _services = services;
        _selectedServiceIds = matchedSelectedIds.isNotEmpty
            ? matchedSelectedIds
            : (selectedServiceId != null ? [selectedServiceId] : <int>[]);
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
    if (_services.isNotEmpty && _selectedServiceIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('furniture_service_type_required'))),
      );
      return false;
    }

    if (_addressText(_pickupAddressData).isEmpty ||
        _addressText(_dropoffAddressData).isEmpty) {
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

      final selectedServicesPayload = _selectedServiceRows.map((selected) {
        return {
          'id': _toInt(selected['id']),
          'name_ar': _toStr(selected['name_ar']),
          'name_en': _toStr(selected['name_en']),
          'base_price': _toDouble(selected['base_price']),
          'price_per_kg': _toDouble(selected['price_per_kg']),
          'price_per_meter': _toDouble(selected['price_per_meter']),
          'minimum_charge': _toDouble(selected['minimum_charge']),
        };
      }).toList();

      final response = await _service.createRequest(
        serviceId: _primarySelectedServiceId,
        pickupAddress: _addressText(_pickupAddressData),
        dropoffAddress: _addressText(_dropoffAddressData),
        pickupCity: _addressCity(_pickupAddressData),
        dropoffCity: _addressCity(_dropoffAddressData),
        moveDate: moveDate,
        preferredTime: preferredTime,
        notes: _notesController.text.trim(),
        details: details,
        estimatedWeightKg: _estimatedWeightKg > 0 ? _estimatedWeightKg : null,
        estimatedDistanceMeters: _estimatedDistanceMeters > 0
            ? _estimatedDistanceMeters
            : null,
        serviceIds: _selectedServiceIds.isNotEmpty ? _selectedServiceIds : null,
        selectedServices: selectedServicesPayload.isNotEmpty
            ? selectedServicesPayload
            : null,
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
          initialValue: (_fieldValues[key] as String?)?.isNotEmpty == true
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

  Widget _buildAddressTile({
    required String title,
    required Map<String, dynamic>? addressData,
    required VoidCallback onTap,
  }) {
    final address = _addressText(addressData);
    final city = _addressCity(addressData);

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.location_on_outlined),
      title: Text(
        address.isNotEmpty ? address : title,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        city.isNotEmpty
            ? city
            : context.tr('service_request_add_new_address_from_map'),
      ),
      trailing: const Icon(Icons.edit_location_alt_outlined),
      onTap: onTap,
    );
  }

  Widget _buildSelectedServicesCard() {
    final selectedRows = _selectedServiceRows;
    if (selectedRows.isEmpty && _services.isEmpty) {
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
                  children: selectedRows.map((service) {
                    return Chip(
                      label: Text(
                        _serviceName(service),
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
                labelText: context.tr('service_type_required_label'),
              ),
              items: _services
                  .map(
                    (service) => DropdownMenuItem<int>(
                      value: _toInt(service['id']),
                      child: Text(_serviceName(service)),
                    ),
                  )
                  .toList(),
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
    final selectedRows = _selectedServiceRows;

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
                            _selectedServiceRow.isNotEmpty) ...[
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
                        _buildAddressTile(
                          title: context.tr('pickup_address'),
                          addressData: _pickupAddressData,
                          onTap: () => _pickAddress(isPickup: true),
                        ),
                        const Divider(height: 1),
                        _buildAddressTile(
                          title: context.tr('dropoff_address'),
                          addressData: _dropoffAddressData,
                          onTap: () => _pickAddress(isPickup: false),
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
