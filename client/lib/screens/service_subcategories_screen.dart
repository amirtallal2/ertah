import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/home_service.dart';
import 'service_selection_screen.dart';

class ServiceSubcategoriesScreen extends StatefulWidget {
  const ServiceSubcategoriesScreen({super.key, required this.rootCategory});

  final ServiceCategoryModel rootCategory;

  @override
  State<ServiceSubcategoriesScreen> createState() =>
      _ServiceSubcategoriesScreenState();
}

class _ServiceSubcategoriesScreenState
    extends State<ServiceSubcategoriesScreen> {
  final HomeService _homeService = HomeService();
  final TextEditingController _searchController = TextEditingController();

  bool _isLoading = true;
  String _searchQuery = '';
  List<Map<String, dynamic>> _subCategories = [];

  @override
  void initState() {
    super.initState();
    _fetchSubCategories();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
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

  String _localizedName(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['name_ar'] ?? '').toString().trim();
    final en = (item['name_en'] ?? '').toString().trim();
    if (lang == 'ar') {
      return ar.isNotEmpty ? ar : en;
    }
    return en.isNotEmpty ? en : ar;
  }

  String _chooseSubCategoryText() {
    return context.tr('choose_right_subcategory');
  }

  String _openServicesHintText() {
    return context.tr('view_services_in_subcategory');
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

  Future<void> _fetchSubCategories() async {
    setState(() => _isLoading = true);
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _homeService.getCategoryDetail(
        widget.rootCategory.id,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );

      if (!mounted) return;
      if (!response.success || response.data is! Map) {
        setState(() => _isLoading = false);
        return;
      }

      final data = Map<String, dynamic>.from(
        (response.data as Map).map(
          (key, value) => MapEntry(key.toString(), value),
        ),
      );
      final subCategories = _asMapList(data['sub_categories']);

      setState(() {
        _subCategories = subCategories;
        _isLoading = false;
      });

      if (_subCategories.isEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) =>
                  ServiceSelectionScreen(service: widget.rootCategory),
            ),
          );
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  List<Map<String, dynamic>> _filteredSubCategories() {
    final query = _searchQuery.trim().toLowerCase();
    if (query.isEmpty) return _subCategories;
    return _subCategories.where((item) {
      return _localizedName(item).toLowerCase().contains(query);
    }).toList();
  }

  Widget _buildCategoryIcon(Map<String, dynamic> category) {
    final icon = (category['icon'] ?? '').toString().trim();
    final image = (category['image'] ?? '').toString().trim();

    String? mediaUrl;
    if (icon.isNotEmpty && (icon.contains('/') || icon.startsWith('http'))) {
      mediaUrl = AppConfig.fixMediaUrl(icon);
    } else if (image.isNotEmpty) {
      mediaUrl = AppConfig.fixMediaUrl(image);
    }

    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
      ),
      child: mediaUrl == null
          ? Center(
              child: icon.isNotEmpty
                  ? Text(icon, style: const TextStyle(fontSize: 20))
                  : const Icon(
                      Icons.category_outlined,
                      color: AppColors.primary,
                      size: 24,
                    ),
            )
          : ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: CachedNetworkImage(
                imageUrl: mediaUrl,
                fit: BoxFit.cover,
                errorWidget: (_, __, ___) => Center(
                  child: icon.isNotEmpty
                      ? Text(icon, style: const TextStyle(fontSize: 20))
                      : const Icon(
                          Icons.category_outlined,
                          color: AppColors.primary,
                          size: 24,
                        ),
                ),
              ),
            ),
    );
  }

  void _openSubCategory(Map<String, dynamic> category) {
    final subCategory = _mapCategoryToModel(category);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ServiceSelectionScreen(service: subCategory),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final title = Localizations.localeOf(context).languageCode == 'ar'
        ? widget.rootCategory.nameAr
        : (widget.rootCategory.nameEn ?? widget.rootCategory.nameAr);
    final filtered = _filteredSubCategories();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(title),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Container(
                  margin: const EdgeInsets.all(16),
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
                            Icons.account_tree_outlined,
                            color: AppColors.primary,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _chooseSubCategoryText(),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: AppColors.gray800,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Container(
                        decoration: BoxDecoration(
                          color: AppColors.gray50,
                          borderRadius: BorderRadius.circular(30),
                          border: Border.all(color: AppColors.gray200),
                        ),
                        child: TextField(
                          controller: _searchController,
                          onChanged: (value) =>
                              setState(() => _searchQuery = value),
                          decoration: InputDecoration(
                            hintText: context.tr('search_service_hint'),
                            hintStyle: const TextStyle(
                              color: AppColors.gray400,
                              fontSize: 13,
                            ),
                            prefixIcon: const Icon(
                              Icons.search,
                              color: AppColors.gray400,
                              size: 20,
                            ),
                            border: InputBorder.none,
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 12,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: filtered.isEmpty
                      ? Center(
                          child: Text(
                            context.tr('no_search_results'),
                            style: const TextStyle(color: AppColors.gray500),
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: filtered.length,
                          itemBuilder: (context, index) {
                            final category = filtered[index];
                            return Container(
                                  margin: const EdgeInsets.only(bottom: 12),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(18),
                                    boxShadow: AppShadows.sm,
                                  ),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(18),
                                    onTap: () => _openSubCategory(category),
                                    child: Padding(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 14,
                                        vertical: 13,
                                      ),
                                      child: Row(
                                        children: [
                                          _buildCategoryIcon(category),
                                          const SizedBox(width: 14),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  _localizedName(category),
                                                  style: const TextStyle(
                                                    fontSize: 16,
                                                    fontWeight: FontWeight.w800,
                                                    color: AppColors.gray800,
                                                    height: 1.15,
                                                  ),
                                                ),
                                                const SizedBox(height: 8),
                                                Text(
                                                  _openServicesHintText(),
                                                  style: const TextStyle(
                                                    fontSize: 12,
                                                    color: AppColors.gray500,
                                                    height: 1.35,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                          const SizedBox(width: 10),
                                          const Icon(
                                            Icons.arrow_forward_ios_rounded,
                                            size: 16,
                                            color: AppColors.gray400,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                )
                                .animate()
                                .fadeIn(delay: (index * 40).ms)
                                .slideY(begin: 0.08, end: 0);
                          },
                        ),
                ),
              ],
            ),
    );
  }
}
