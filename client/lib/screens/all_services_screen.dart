// All Services Screen
// شاشة جميع الأقسام الرئيسية

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

class AllServicesScreen extends StatefulWidget {
  const AllServicesScreen({super.key});

  @override
  State<AllServicesScreen> createState() => _AllServicesScreenState();
}

class _AllServicesScreenState extends State<AllServicesScreen> {
  final TextEditingController _searchController = TextEditingController();
  final HomeService _homeService = HomeService();
  static const List<Shadow> _headerTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];

  String _searchQuery = '';
  bool _isLoading = true;
  int _totalCategoriesCount = 0;
  List<ServiceCategoryModel> _rootCategories = [];

  @override
  void initState() {
    super.initState();
    _fetchServices();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  String _localizedName(ServiceCategoryModel category) {
    final isAr = Localizations.localeOf(context).languageCode == 'ar';
    final ar = category.nameAr.trim();
    final en = (category.nameEn ?? '').trim();
    if (isAr) return ar.isNotEmpty ? ar : en;
    return en.isNotEmpty ? en : ar;
  }

  int _countNodes(List<ServiceCategoryModel> list) {
    var total = 0;
    for (final item in list) {
      total += 1;
      if (item.subCategories.isNotEmpty) {
        total += _countNodes(item.subCategories);
      }
    }
    return total;
  }

  ServiceCategoryModel _cloneWithSubCategories(
    ServiceCategoryModel item,
    List<ServiceCategoryModel> subCategories,
  ) {
    return ServiceCategoryModel(
      id: item.id,
      parentId: item.parentId,
      nameAr: item.nameAr,
      nameEn: item.nameEn,
      icon: item.icon,
      image: item.image,
      specialModule: item.specialModule,
      subCategories: subCategories,
      isActive: item.isActive,
      sortOrder: item.sortOrder,
      createdAt: item.createdAt,
    );
  }

  List<ServiceCategoryModel> _normalizeCategories(
    List<ServiceCategoryModel> all,
  ) {
    final roots =
        all
            .where((item) => item.parentId == null || item.parentId == 0)
            .toList()
          ..sort((a, b) {
            if (a.sortOrder == b.sortOrder) return a.id.compareTo(b.id);
            return a.sortOrder.compareTo(b.sortOrder);
          });

    final childrenByParent = <int, List<ServiceCategoryModel>>{};
    for (final item in all) {
      final parentId = item.parentId;
      if (parentId == null || parentId == 0) continue;
      childrenByParent.putIfAbsent(parentId, () => []).add(item);
    }

    return roots.map((root) {
      final merged = <int, ServiceCategoryModel>{};
      for (final child in root.subCategories) {
        merged[child.id] = child;
      }
      for (final child
          in childrenByParent[root.id] ?? const <ServiceCategoryModel>[]) {
        merged[child.id] = child;
      }
      final children = merged.values.toList()
        ..sort((a, b) {
          if (a.sortOrder == b.sortOrder) return a.id.compareTo(b.id);
          return a.sortOrder.compareTo(b.sortOrder);
        });
      return _cloneWithSubCategories(root, children);
    }).toList();
  }

  List<ServiceCategoryModel> _filteredCategories() {
    final query = _searchQuery.trim().toLowerCase();
    if (query.isEmpty) return _rootCategories;

    final filtered = <ServiceCategoryModel>[];
    for (final root in _rootCategories) {
      final rootMatches = _localizedName(root).toLowerCase().contains(query);
      if (rootMatches) {
        filtered.add(root);
        continue;
      }

      final matchingChildren = root.subCategories.where((child) {
        return _localizedName(child).toLowerCase().contains(query);
      }).toList();

      if (matchingChildren.isNotEmpty) {
        filtered.add(_cloneWithSubCategories(root, matchingChildren));
      }
    }
    return filtered;
  }

  Map<String, dynamic>? _mapFromDynamic(dynamic raw) {
    if (raw is! Map) return null;
    return Map<String, dynamic>.from(
      raw.map((key, value) => MapEntry(key.toString(), value)),
    );
  }

  Future<List<ServiceCategoryModel>> _appendMissingSpecialCategories(
    List<ServiceCategoryModel> categories, {
    required bool allowOutside,
  }) async {
    final modules = categories
        .map((item) => (item.specialModule ?? '').trim())
        .where((item) => item.isNotEmpty)
        .toSet();

    final result = <ServiceCategoryModel>[...categories];

    Future<void> addIfMissing(int id, String moduleKey) async {
      if (modules.contains(moduleKey) || result.any((item) => item.id == id)) {
        return;
      }

      final location = context.read<LocationProvider>();
      final response = await _homeService.getCategoryDetail(
        id,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (!response.success) return;

      final data = _mapFromDynamic(response.data);
      if (data == null) return;

      final parsed = ServiceCategoryModel.fromJson(data);
      if ((parsed.specialModule ?? '').trim() != moduleKey) return;

      result.add(parsed);
      modules.add(moduleKey);
    }

    await addIfMissing(-101, 'furniture_moving');
    await addIfMissing(-102, 'container_rental');

    return result;
  }

  Future<void> _fetchServices() async {
    setState(() => _isLoading = true);

    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _homeService.getCategories(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (!mounted) return;

      if (!response.success || response.data is! List) {
        setState(() => _isLoading = false);
        return;
      }

      final data = response.data as List;
      final parsed = data
          .whereType<Map>()
          .map(
            (json) => ServiceCategoryModel.fromJson(
              Map<String, dynamic>.from(
                json.map((key, value) => MapEntry(key.toString(), value)),
              ),
            ),
          )
          .toList();

      final merged = await _appendMissingSpecialCategories(
        parsed,
        allowOutside: allowOutside,
      );
      final normalized = _normalizeCategories(merged);
      setState(() {
        _rootCategories = normalized;
        _totalCategoriesCount = _countNodes(normalized);
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  void _openCategory(ServiceCategoryModel category) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ServiceSelectionScreen(service: category),
      ),
    );
  }

  Widget _buildCategoryIcon(ServiceCategoryModel category) {
    final icon = (category.icon ?? '').trim();
    final image = (category.image ?? '').trim();

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
      child: mediaUrl != null
          ? ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: CachedNetworkImage(
                imageUrl: mediaUrl,
                fit: BoxFit.cover,
                errorWidget: (_, __, ___) => Center(
                  child: icon.isNotEmpty
                      ? Text(icon, style: const TextStyle(fontSize: 18))
                      : const Icon(
                          Icons.category_outlined,
                          color: AppColors.primary,
                          size: 22,
                        ),
                ),
              ),
            )
          : Center(
              child: icon.isNotEmpty
                  ? Text(icon, style: const TextStyle(fontSize: 18))
                  : const Icon(
                      Icons.category_outlined,
                      color: AppColors.primary,
                      size: 22,
                    ),
            ),
    );
  }

  String _categoryHint(ServiceCategoryModel category) {
    final hasSubCategories = category.subCategories.isNotEmpty;
    if (hasSubCategories) {
      return context.tr('open_subcategories_then_choose_service');
    }
    return context.tr('open_services_in_category');
  }

  Widget _buildCategoryCard(ServiceCategoryModel category, int index) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: AppShadows.sm,
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _openCategory(category),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _buildCategoryIcon(category),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
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
                      _categoryHint(category),
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
              Column(
                children: [
                  if (category.subCategories.isNotEmpty)
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
                        '${category.subCategories.length}',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  const SizedBox(height: 8),
                  const Icon(
                    Icons.arrow_forward_ios_rounded,
                    size: 16,
                    color: AppColors.gray400,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    ).animate().fadeIn(delay: (index * 45).ms).slideY(begin: 0.08, end: 0);
  }

  @override
  Widget build(BuildContext context) {
    final filteredCategories = _filteredCategories();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.only(
              top: 48,
              bottom: 20,
              left: 16,
              right: 16,
            ),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFBCC26), Color(0xFFE5B41F)],
              ),
              boxShadow: AppShadows.md,
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(
                          Icons.arrow_back,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      context.tr('all_services'),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        shadows: _headerTextShadows,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: AppShadows.sm,
                  ),
                  child: TextField(
                    controller: _searchController,
                    onChanged: (value) => setState(() => _searchQuery = value),
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
                        horizontal: 20,
                        vertical: 14,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : filteredCategories.isEmpty
                ? _buildNoResults()
                : RefreshIndicator(
                    onRefresh: _fetchServices,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: filteredCategories.length,
                      itemBuilder: (context, index) {
                        return _buildCategoryCard(
                          filteredCategories[index],
                          index,
                        );
                      },
                    ),
                  ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppColors.gray100)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text('🛠️', style: TextStyle(fontSize: 16)),
                const SizedBox(width: 8),
                Text(
                  context
                      .tr('available_services_count')
                      .replaceAll('{}', _totalCategoriesCount.toString()),
                  style: const TextStyle(
                    color: AppColors.gray500,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNoResults() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.search_off, size: 64, color: AppColors.gray300),
          const SizedBox(height: 16),
          Text(
            context.tr('no_search_results'),
            style: const TextStyle(
              color: AppColors.gray500,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            context.tr('try_another_search_term'),
            style: const TextStyle(color: AppColors.gray400, fontSize: 12),
          ),
        ],
      ),
    ).animate().fadeIn();
  }
}
