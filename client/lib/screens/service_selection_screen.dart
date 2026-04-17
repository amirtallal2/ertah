// Service Selection Screen
// شاشة اختيار الأقسام الفرعية والخدمات

import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/home_service.dart';
import '../utils/saudi_riyal_icon.dart';
import 'all_spares_screen.dart';
import 'container_request_screen.dart';
import 'furniture_request_screen.dart';
import 'service_request_screen.dart';

class ServiceSelectionScreen extends StatefulWidget {
  final ServiceCategoryModel service;

  const ServiceSelectionScreen({super.key, required this.service});

  @override
  State<ServiceSelectionScreen> createState() => _ServiceSelectionScreenState();
}

class _ServiceSelectionScreenState extends State<ServiceSelectionScreen> {
  final HomeService _homeService = HomeService();
  final ImagePicker _picker = ImagePicker();
  final TextEditingController _otherServiceNameController =
      TextEditingController();
  final Set<int> _selectedServices = {};
  String? _otherServiceImagePath;

  bool _isLoading = true;
  bool _isLoadingSpareParts = false;
  String? _specialModule;
  List<Map<String, dynamic>> _subCategories = [];
  List<Map<String, dynamic>> _subServices = [];
  List<Map<String, dynamic>> _spareParts = [];

  @override
  void initState() {
    super.initState();
    _fetchCategoryDetail();
  }

  @override
  void dispose() {
    _otherServiceNameController.dispose();
    super.dispose();
  }

  String _localizedName(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['name_ar'] ?? '').toString().trim();
    final en = (item['name_en'] ?? '').toString().trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  String _localizedDescription(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['description_ar'] ?? item['description'] ?? '')
        .toString()
        .trim();
    final en = (item['description_en'] ?? item['description'] ?? '')
        .toString()
        .trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
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

  String? _resolveItemMediaUrl(
    Map<String, dynamic> item, {
    bool preferImage = true,
  }) {
    final image = (item['image'] ?? '').toString().trim();
    final icon = (item['icon'] ?? '').toString().trim();
    final candidates = preferImage
        ? <String>[image, icon]
        : <String>[icon, image];

    for (final candidate in candidates) {
      if (candidate.isEmpty) continue;
      if (candidate.startsWith('http') || candidate.contains('/')) {
        return AppConfig.fixMediaUrl(candidate);
      }
    }

    return null;
  }

  String _fallbackSymbol(Map<String, dynamic> item, String fallback) {
    final icon = (item['icon'] ?? '').toString().trim();
    if (icon.isNotEmpty && !icon.startsWith('http') && !icon.contains('/')) {
      return icon;
    }
    return fallback;
  }

  Widget _buildItemThumbnail(
    Map<String, dynamic> item, {
    required double size,
    required String fallback,
    bool preferImage = true,
    BorderRadius? borderRadius,
  }) {
    final radius = borderRadius ?? BorderRadius.circular(16);
    final mediaUrl = _resolveItemMediaUrl(item, preferImage: preferImage);
    final symbol = _fallbackSymbol(item, fallback);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.08),
        borderRadius: radius,
      ),
      child: mediaUrl == null
          ? Center(child: Text(symbol, style: const TextStyle(fontSize: 20)))
          : ClipRRect(
              borderRadius: radius,
              child: CachedNetworkImage(
                imageUrl: mediaUrl,
                fit: BoxFit.cover,
                placeholder: (_, __) => Container(
                  color: AppColors.gray100,
                  alignment: Alignment.center,
                  child: const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
                errorWidget: (_, __, ___) => Center(
                  child: Text(symbol, style: const TextStyle(fontSize: 20)),
                ),
              ),
            ),
    );
  }

  Future<void> _fetchCategoryDetail() async {
    setState(() {
      _isLoading = true;
      _isLoadingSpareParts = true;
    });
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _homeService.getCategoryDetail(
        widget.service.id,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      final sparePartsResponse = await _homeService.getSpareParts(
        categoryId: widget.service.id,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );

      if (!mounted) return;

      final spareParts = <Map<String, dynamic>>[];
      if (sparePartsResponse.success && sparePartsResponse.data is List) {
        for (final raw in (sparePartsResponse.data as List)) {
          if (raw is! Map) continue;
          spareParts.add(
            Map<String, dynamic>.from(
              raw.map((key, value) => MapEntry(key.toString(), value)),
            ),
          );
        }
      }

      if (response.success && response.data is Map) {
        final data = Map<String, dynamic>.from(
          (response.data as Map).map(
            (key, value) => MapEntry(key.toString(), value),
          ),
        );

        final subCategories = _asMapList(data['sub_categories']);
        final subServices = _asMapList(data['sub_services']);

        setState(() {
          _specialModule =
              (data['special_module'] ?? widget.service.specialModule)
                  ?.toString()
                  .trim();
          _subCategories = subCategories;
          _subServices = subServices;
          _spareParts = spareParts;
          _selectedServices.clear();
          _isLoading = false;
          _isLoadingSpareParts = false;
        });
      } else {
        setState(() {
          _spareParts = spareParts;
          _isLoading = false;
          _isLoadingSpareParts = false;
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _isLoadingSpareParts = false;
      });
    }
  }

  void _toggleService(int serviceId) {
    setState(() {
      if (_selectedServices.contains(serviceId)) {
        _selectedServices.remove(serviceId);
      } else {
        _selectedServices.add(serviceId);
      }
    });
  }

  ServiceCategoryModel _mapCategoryToModel(Map<String, dynamic> category) {
    return ServiceCategoryModel(
      id: _toInt(category['id']),
      parentId: category['parent_id'] == null
          ? null
          : int.tryParse('${category['parent_id']}'),
      nameAr: (category['name_ar'] ?? '').toString(),
      nameEn: category['name_en']?.toString(),
      icon: category['icon']?.toString(),
      image: category['image']?.toString(),
      specialModule: category['special_module']?.toString(),
      createdAt: DateTime.now(),
    );
  }

  Future<void> _openSubCategory(Map<String, dynamic> category) async {
    final subCategory = _mapCategoryToModel(category);
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ServiceSelectionScreen(service: subCategory),
      ),
    );

    if (mounted) {
      _fetchCategoryDetail();
    }
  }

  Future<void> _openAllSpares({int? autoAddSpareId}) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => AllSparesScreen(
          onBack: () => Navigator.pop(context),
          autoAddSpareId: autoAddSpareId,
        ),
      ),
    );
  }

  List<Map<String, dynamic>> get _selectedServiceRows {
    return _subServices
        .where((row) => _selectedServices.contains(_toInt(row['id'])))
        .toList();
  }

  double get _selectedTotalPrice {
    return _selectedServiceRows.fold(0.0, (sum, row) {
      final price = double.tryParse('${row['price'] ?? 0}') ?? 0.0;
      return sum + price;
    });
  }

  bool get _canContinueWithoutSelection {
    if (_subServices.isNotEmpty) return false;
    return _specialModule == 'furniture_moving' ||
        _specialModule == 'container_rental';
  }

  bool get _hasOtherCustomService {
    return _otherServiceNameController.text.trim().isNotEmpty &&
        (_otherServiceImagePath?.trim().isNotEmpty ?? false);
  }

  List<int> _spareServiceIds(Map<String, dynamic> spare) {
    final raw = spare['service_ids'];
    if (raw is List) {
      return raw
          .map((item) => _toInt(item))
          .where((id) => id > 0)
          .toSet()
          .toList();
    }

    final text = raw?.toString().trim() ?? '';
    if (text.isEmpty) {
      return const <int>[];
    }

    return text
        .split(',')
        .map((item) => _toInt(item.trim()))
        .where((id) => id > 0)
        .toSet()
        .toList();
  }

  List<Map<String, dynamic>> get _visibleSpareParts {
    if (_selectedServices.isEmpty) {
      return _spareParts;
    }

    return _spareParts.where((spare) {
      final ids = _spareServiceIds(spare);
      if (ids.isEmpty) {
        return true;
      }
      return ids.any(_selectedServices.contains);
    }).toList();
  }

  String _localizedSpareName(Map<String, dynamic> spare) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (spare['name_ar'] ?? spare['name'] ?? '').toString().trim();
    final en = (spare['name_en'] ?? spare['name'] ?? '').toString().trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  double _sparePrice(Map<String, dynamic> spare) {
    final withInstallation =
        double.tryParse('${spare['price_with_installation'] ?? ''}') ?? 0;
    if (withInstallation > 0) {
      return withInstallation;
    }
    return double.tryParse('${spare['price'] ?? 0}') ?? 0;
  }

  bool _spareInStock(Map<String, dynamic> spare) {
    final inStock = spare['inStock'];
    if (inStock is bool) {
      return inStock;
    }
    return _toInt(spare['stock_quantity']) > 0;
  }

  Widget _buildSparePartsSection() {
    if (_isLoadingSpareParts) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 10),
          Text(
            context.tr('featured_products'),
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          const Center(child: CircularProgressIndicator()),
        ],
      );
    }

    final parts = _visibleSpareParts;
    if (parts.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_subCategories.isNotEmpty || _subServices.isNotEmpty)
          const SizedBox(height: 10),
        Text(
          context.tr('featured_products'),
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        ...parts.asMap().entries.map((entry) {
          final index = entry.key;
          final spare = entry.value;
          final name = _localizedSpareName(spare);
          final imageUrl = AppConfig.fixMediaUrl(spare['image']?.toString());
          final inStock = _spareInStock(spare);

          final spareId = _toInt(spare['id']);
          return InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: spareId > 0
                    ? () => _openAllSpares(autoAddSpareId: spareId)
                    : null,
                child: Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: AppShadows.sm,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: CachedNetworkImage(
                            imageUrl: imageUrl,
                            width: 60,
                            height: 60,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) => Container(
                              width: 60,
                              height: 60,
                              color: AppColors.gray100,
                              alignment: Alignment.center,
                              child: const Icon(Icons.inventory_2_outlined),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                name,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.gray800,
                                ),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                inStock
                                    ? context.tr('available')
                                    : context.tr('out_of_stock'),
                                style: TextStyle(
                                  fontSize: 12,
                                  color: inStock
                                      ? const Color(0xFF15803D)
                                      : AppColors.gray500,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            SaudiRiyalText(
                              text: _sparePrice(spare).toStringAsFixed(1),
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primary,
                              ),
                              iconSize: 15,
                            ),
                            if (spareId > 0) ...[
                              const SizedBox(height: 6),
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    context.tr('view_all'),
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: AppColors.primary,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(
                                    Icons.arrow_forward_ios,
                                    size: 11,
                                    color: AppColors.primary,
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              )
              .animate()
              .fadeIn(delay: (index * 40).ms)
              .slideY(begin: 0.06, end: 0);
        }),
      ],
    );
  }

  Future<void> _pickOtherServiceImage(ImageSource source) async {
    final picked = await _picker.pickImage(
      source: source,
      imageQuality: 85,
      maxWidth: 1600,
    );
    if (!mounted || picked == null) return;
    setState(() => _otherServiceImagePath = picked.path);
  }

  Future<void> _showOtherServiceSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            final hasImage =
                (_otherServiceImagePath?.trim().isNotEmpty ?? false);

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 16,
                  bottom: MediaQuery.of(context).viewInsets.bottom + 16,
                ),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Center(
                        child: SizedBox(
                          width: 48,
                          child: Divider(thickness: 4),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        context.tr('service_selection_add_other_service'),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        context.tr('service_selection_other_service_help'),
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.gray600,
                        ),
                      ),
                      const SizedBox(height: 16),
                      TextField(
                        controller: _otherServiceNameController,
                        textInputAction: TextInputAction.done,
                        decoration: InputDecoration(
                          labelText: context.tr(
                            'service_selection_other_service_name_label',
                          ),
                          hintText: context.tr(
                            'service_selection_other_service_name_hint',
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        onChanged: (_) => setSheetState(() {}),
                      ),
                      const SizedBox(height: 12),
                      if (hasImage)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            File(_otherServiceImagePath!),
                            height: 140,
                            fit: BoxFit.cover,
                          ),
                        )
                      else
                        Container(
                          height: 100,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9FAFB),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.gray200),
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            context.tr('service_selection_no_image_selected'),
                            style: TextStyle(color: AppColors.gray600),
                          ),
                        ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                await _pickOtherServiceImage(
                                  ImageSource.camera,
                                );
                                if (!mounted) return;
                                setSheetState(() {});
                              },
                              icon: const Icon(Icons.camera_alt_outlined),
                              label: Text(context.tr('camera_photo')),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                await _pickOtherServiceImage(
                                  ImageSource.gallery,
                                );
                                if (!mounted) return;
                                setSheetState(() {});
                              },
                              icon: const Icon(Icons.photo_library_outlined),
                              label: Text(context.tr('gallery_photo')),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      ElevatedButton(
                        onPressed: () {
                          final name = _otherServiceNameController.text.trim();
                          final image = _otherServiceImagePath?.trim() ?? '';

                          if (name.isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  context.tr(
                                    'service_selection_other_service_name_required',
                                  ),
                                ),
                              ),
                            );
                            return;
                          }

                          if (image.isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  context.tr(
                                    'service_selection_other_service_image_required',
                                  ),
                                ),
                              ),
                            );
                            return;
                          }

                          Navigator.pop(context);
                          setState(() {});
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          minimumSize: const Size.fromHeight(46),
                        ),
                        child: Text(
                          context.tr(
                            'service_selection_confirm_add_other_service',
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
  }

  void _clearOtherService() {
    setState(() {
      _otherServiceNameController.clear();
      _otherServiceImagePath = null;
    });
  }

  Future<void> _handleContinue() async {
    if (_selectedServices.isEmpty &&
        !_canContinueWithoutSelection &&
        !_hasOtherCustomService) {
      return;
    }

    final selectedIds = _selectedServices.toList();
    final selectedRows = _selectedServiceRows;

    if (_specialModule == 'furniture_moving') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => FurnitureRequestScreen(
            service: widget.service,
            selectedServiceIds: selectedIds,
            selectedServices: selectedRows,
          ),
        ),
      );
      return;
    }

    if (_specialModule == 'container_rental') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => ContainerRequestScreen(
            service: widget.service,
            availableServices: _subServices,
            preselectedServiceIds: selectedIds,
          ),
        ),
      );
      return;
    }

    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ServiceRequestScreen(
          service: widget.service,
          subServices: selectedIds,
          selectedServices: selectedRows,
          customServiceTitle: _hasOtherCustomService
              ? _otherServiceNameController.text.trim()
              : null,
          customServiceImagePaths:
              _hasOtherCustomService &&
                  (_otherServiceImagePath?.trim().isNotEmpty ?? false)
              ? <String>[_otherServiceImagePath!.trim()]
              : const <String>[],
        ),
      ),
    );
  }

  Widget _buildSubCategoriesSection() {
    if (_subCategories.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.tr('services'),
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        ..._subCategories.asMap().entries.map((entry) {
          final index = entry.key;
          final category = entry.value;
          return Container(
                margin: const EdgeInsets.only(bottom: 10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: AppShadows.sm,
                ),
                child: ListTile(
                  leading: _buildItemThumbnail(
                    category,
                    size: 58,
                    fallback: '📁',
                    preferImage: true,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  title: Text(
                    _localizedName(category),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    context.tr('view_all'),
                    style: const TextStyle(fontSize: 12),
                  ),
                  onTap: () => _openSubCategory(category),
                ),
              )
              .animate()
              .fadeIn(delay: (index * 40).ms)
              .slideY(begin: 0.08, end: 0);
        }),
      ],
    );
  }

  Widget _buildSubServicesSection() {
    if (_subServices.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_subCategories.isNotEmpty) const SizedBox(height: 10),
        Text(
          context.tr('most_requested'),
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        ..._subServices.asMap().entries.map((entry) {
          final index = entry.key;
          final service = entry.value;
          final serviceId = _toInt(service['id']);
          final selected = _selectedServices.contains(serviceId);
          final description = _localizedDescription(service);

          return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.only(bottom: 10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: selected
                      ? Border.all(color: AppColors.primary, width: 1.8)
                      : null,
                  boxShadow: AppShadows.sm,
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () => _toggleService(serviceId),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            _buildItemThumbnail(
                              service,
                              size: 66,
                              fallback: '🔧',
                              preferImage: true,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            PositionedDirectional(
                              end: 4,
                              top: 4,
                              child: Container(
                                width: 24,
                                height: 24,
                                decoration: BoxDecoration(
                                  color: selected
                                      ? AppColors.primary
                                      : Colors.white,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: selected
                                        ? AppColors.primary
                                        : AppColors.gray300,
                                  ),
                                  boxShadow: AppShadows.sm,
                                ),
                                child: Icon(
                                  selected ? Icons.check : Icons.add,
                                  size: 16,
                                  color: selected
                                      ? Colors.white
                                      : AppColors.primary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _localizedName(service),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.gray800,
                                ),
                              ),
                              if (description.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  description,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray500,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                              const SizedBox(height: 10),
                              SaudiRiyalText(
                                text: '${service['price'] ?? 0}',
                                style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.primary,
                                ),
                                iconSize: 15,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              )
              .animate()
              .fadeIn(delay: (index * 45).ms)
              .slideX(begin: 0.08, end: 0);
        }),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _showOtherServiceSheet,
          icon: const Icon(Icons.add_circle_outline),
          label: Text(context.tr('service_selection_add_other_service')),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(44),
            foregroundColor: AppColors.primary,
            side: BorderSide(color: AppColors.primary.withValues(alpha: 0.5)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
        if (_hasOtherCustomService) ...[
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.gray200),
            ),
            child: Row(
              children: [
                if ((_otherServiceImagePath?.trim().isNotEmpty ?? false))
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(
                      File(_otherServiceImagePath!),
                      width: 56,
                      height: 56,
                      fit: BoxFit.cover,
                    ),
                  ),
                if ((_otherServiceImagePath?.trim().isNotEmpty ?? false))
                  const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('service_selection_other_service_added'),
                        style: TextStyle(
                          color: AppColors.gray600,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _otherServiceNameController.text.trim(),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: AppColors.gray900,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: _clearOtherService,
                  icon: const Icon(Icons.close),
                  tooltip: context.tr('service_selection_remove_tooltip'),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final title = Localizations.localeOf(context).languageCode == 'ar'
        ? widget.service.nameAr
        : (widget.service.nameEn ?? widget.service.nameAr);

    final hasAnyItems =
        _subCategories.isNotEmpty ||
        _subServices.isNotEmpty ||
        _visibleSpareParts.isNotEmpty ||
        _isLoadingSpareParts;
    final showBottomAction =
        _selectedServices.isNotEmpty ||
        _canContinueWithoutSelection ||
        _hasOtherCustomService;

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('services_of').replaceAll('{}', title)),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : !hasAnyItems
          ? _buildEmptyState()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildSubCategoriesSection(),
                _buildSubServicesSection(),
                _buildSparePartsSection(),
              ],
            ),
      bottomNavigationBar: !showBottomAction
          ? null
          : Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.1),
                    blurRadius: 12,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                child: _selectedServices.isEmpty
                    ? SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _handleContinue,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                          child: Text(context.tr('continue_request')),
                        ),
                      )
                    : Row(
                        children: [
                          Expanded(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  context
                                      .tr('services_selected_count')
                                      .replaceAll(
                                        '{}',
                                        _selectedServices.length.toString(),
                                      ),
                                  style: const TextStyle(
                                    color: AppColors.gray500,
                                    fontSize: 12,
                                  ),
                                ),
                                SaudiRiyalText(
                                  text: _selectedTotalPrice.toStringAsFixed(1),
                                  style: const TextStyle(
                                    color: AppColors.primary,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16,
                                  ),
                                  iconSize: 15,
                                ),
                              ],
                            ),
                          ),
                          ElevatedButton(
                            onPressed: _handleContinue,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 24,
                                vertical: 12,
                              ),
                            ),
                            child: Text(context.tr('continue_request')),
                          ),
                        ],
                      ),
              ),
            ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.tune, size: 48, color: AppColors.gray300),
          const SizedBox(height: 14),
          Text(
            context.tr('no_services_available'),
            style: const TextStyle(color: AppColors.gray500),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: _showOtherServiceSheet,
            icon: const Icon(Icons.add_circle_outline),
            label: Text(context.tr('service_selection_add_other_service')),
          ),
        ],
      ),
    );
  }
}
