// Most Requested Services Screen
// شاشة الخدمات الأكثر طلباً

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
import '../utils/saudi_riyal_icon.dart';
import 'service_request_screen.dart';
import 'service_selection_screen.dart';

class MostRequestedServicesScreen extends StatefulWidget {
  const MostRequestedServicesScreen({super.key});

  @override
  State<MostRequestedServicesScreen> createState() =>
      _MostRequestedServicesScreenState();
}

class _MostRequestedServicesScreenState
    extends State<MostRequestedServicesScreen> {
  final HomeService _homeService = HomeService();
  static const List<Shadow> _headerTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];
  bool _isLoading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

  @override
  void initState() {
    super.initState();
    _fetchFeatured();
  }

  Future<void> _fetchFeatured() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _homeService.getFeaturedServices(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (!mounted) return;

      if (!response.success || response.data is! List) {
        setState(() {
          _isLoading = false;
          _error = response.message ?? context.tr('error_loading_data');
        });
        return;
      }

      final raw = response.data as List;
      final rows = raw.whereType<Map>().map((row) {
        return Map<String, dynamic>.from(
          row.map((key, value) => MapEntry(key.toString(), value)),
        );
      }).toList();

      setState(() {
        _items = rows;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = context.tr('connection_error');
      });
    }
  }

  String _localizedName(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['name_ar'] ?? '').toString();
    final en = (item['name_en'] ?? '').toString();
    if (lang == 'ar') return ar.isNotEmpty ? ar : en;
    return en.isNotEmpty ? en : ar;
  }

  String _localizedCategory(Map<String, dynamic> item) {
    final lang = Localizations.localeOf(context).languageCode;
    final ar = (item['category_name_ar'] ?? '').toString();
    final en = (item['category_name_en'] ?? '').toString();
    if (lang == 'ar') return ar.isNotEmpty ? ar : en;
    return en.isNotEmpty ? en : ar;
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  double _toDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _formatPrice(dynamic value) {
    final price = _toDouble(value);
    return price % 1 == 0 ? price.toInt().toString() : price.toStringAsFixed(1);
  }

  void _openService(Map<String, dynamic> item) {
    final categoryId = _toInt(item['category_id']);
    final serviceId = _toInt(item['id']);
    if (categoryId <= 0) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('error_loading_data'))));
      return;
    }

    final category = ServiceCategoryModel(
      id: categoryId,
      parentId: _toInt(item['category_parent_id']),
      nameAr: (item['category_name_ar'] ?? item['name_ar'] ?? '').toString(),
      nameEn: (item['category_name_en'] ?? item['name_en'] ?? '').toString(),
      icon: (item['category_icon'] ?? item['icon'] ?? item['image'] ?? '')
          .toString(),
      image: (item['category_image'] ?? item['image'] ?? '').toString(),
      specialModule: item['category_special_module']?.toString(),
      inspectionPricingMode:
          (item['category_inspection_pricing_mode'] ?? 'free').toString(),
      inspectionFee: _toDouble(item['category_inspection_fee']),
      inspectionDetailsAr: item['category_inspection_details_ar']?.toString(),
      inspectionDetailsEn: item['category_inspection_details_en']?.toString(),
      inspectionDetailsUr: item['category_inspection_details_ur']?.toString(),
      createdAt: DateTime.now(),
    );

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => serviceId > 0
            ? ServiceRequestScreen(
                service: category,
                subServices: <int>[serviceId],
                selectedServices: <Map<String, dynamic>>[
                  Map<String, dynamic>.from(item),
                ],
              )
            : ServiceSelectionScreen(service: category),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(16, 48, 16, 20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFBCC26), Color(0xFFE5B41F)],
              ),
              boxShadow: AppShadows.md,
            ),
            child: Row(
              children: [
                InkWell(
                  onTap: () => Navigator.pop(context),
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.arrow_back,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    context.tr('most_requested'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      shadows: _headerTextShadows,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? _buildError()
                : _items.isEmpty
                ? _buildEmpty()
                : RefreshIndicator(
                    onRefresh: _fetchFeatured,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final item = _items[index];

                        return GestureDetector(
                          onTap: () => _openService(item),
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              boxShadow: AppShadows.sm,
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 52,
                                  height: 52,
                                  decoration: BoxDecoration(
                                    color: AppColors.gray100,
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: CachedNetworkImage(
                                      imageUrl: AppConfig.fixMediaUrl(
                                        item['image']?.toString(),
                                      ),
                                      fit: BoxFit.cover,
                                      errorWidget: (_, __, ___) => const Icon(
                                        Icons.image_not_supported,
                                        color: Colors.grey,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _localizedName(item),
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 14,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        _localizedCategory(item),
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: AppColors.gray500,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 4),
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.star,
                                            color: Colors.amber,
                                            size: 14,
                                          ),
                                          const SizedBox(width: 4),
                                          Text(
                                            _toDouble(
                                              item['rating'],
                                            ).toStringAsFixed(1),
                                            style: const TextStyle(
                                              fontSize: 12,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          const SizedBox(width: 10),
                                          Text(
                                            '${_toInt(item['requests_count'])}+ ${context.tr('requests_count_suffix')}',
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: AppColors.gray500,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    SaudiRiyalText(
                                      text:
                                          '${context.tr('starts_from')} ${_formatPrice(item['price'])}',
                                      style: const TextStyle(
                                        color: Color(0xFF7466ED),
                                        fontWeight: FontWeight.bold,
                                        fontSize: 13,
                                      ),
                                      iconSize: 13,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ).animate().fadeIn(delay: (index * 100).ms).slideX(),
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 46, color: Colors.grey),
          const SizedBox(height: 12),
          Text(_error ?? context.tr('error_loading_data')),
          const SizedBox(height: 8),
          TextButton(
            onPressed: _fetchFeatured,
            child: Text(context.tr('try_again')),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Text(
        context.tr('no_data_available'),
        style: const TextStyle(color: AppColors.gray500),
      ),
    );
  }
}
