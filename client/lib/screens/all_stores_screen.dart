// All Stores Screen
// شاشة جميع المتاجر

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import 'store_screen.dart';
import '../services/services.dart';
import '../services/app_localizations.dart';
import '../models/models.dart';
import '../config/app_config.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';

class AllStoresScreen extends StatefulWidget {
  const AllStoresScreen({super.key});

  @override
  State<AllStoresScreen> createState() => _AllStoresScreenState();
}

class _AllStoresScreenState extends State<AllStoresScreen> {
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();
  final StoresService _storesService = StoresService();

  bool _isLoading = true;
  String? _error;
  List<StoreModel> _stores = [];

  @override
  void initState() {
    super.initState();
    _fetchStores();
  }

  Future<void> _fetchStores() async {
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _storesService.getStores(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (response.success) {
        final List<dynamic> data = response.data is List
            ? response.data as List
            : const <dynamic>[];
        setState(() {
          _stores = data
              .map(
                (json) => StoreModel(
                  id: int.tryParse(json['id'].toString()) ?? 0,
                  nameAr: json['name_ar'] ?? json['name'] ?? '',
                  nameEn: json['name_en'] ?? '',
                  description: json['description'],
                  logo:
                      json['logo'] ??
                      json['image'], // Handle both logo and image
                  isActive: true,
                  rating: json['rating'] != null
                      ? double.tryParse(json['rating'].toString())
                      : null,
                  createdAt: DateTime.now(),
                ),
              )
              .toList();
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.message;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = context.tr('stores_load_error');
          _isLoading = false;
        });
      }
    }
  }

  String _localizedStoreName(StoreModel store) {
    final lang = Localizations.localeOf(context).languageCode;
    if (lang == 'ar') return store.nameAr;
    final en = store.nameEn?.trim() ?? '';
    return en.isNotEmpty ? en : store.nameAr;
  }

  @override
  Widget build(BuildContext context) {
    // Filter stores based on search
    final filteredStores = _stores.where((store) {
      final query = _searchQuery.toLowerCase();
      return store.nameAr.toLowerCase().contains(query) ||
          (store.nameEn?.toLowerCase().contains(query) ?? false);
    }).toList();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: SafeArea(
        child: Column(
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.all(16),
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
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: AppShadows.sm,
                          ),
                          child: const Icon(
                            Icons.arrow_back,
                            color: AppColors.gray800,
                            size: 20,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        context.tr('all_stores_title'),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppColors.gray800,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.sm,
                    ),
                    child: TextField(
                      controller: _searchController,
                      onChanged: (val) => setState(() => _searchQuery = val),
                      decoration: InputDecoration(
                        hintText: context.tr('search_store_hint'),
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

            // Grid
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? Center(child: Text(_error!))
                  : filteredStores.isEmpty
                  ? _buildNoResults()
                  : GridView.builder(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 3,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                            childAspectRatio: 0.75,
                          ),
                      itemCount: filteredStores.length,
                      itemBuilder: (context, index) {
                        final store = filteredStores[index];
                        return GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (context) => StoreScreen(
                                  storeId: store.id,
                                  storeName: _localizedStoreName(store),
                                ),
                              ),
                            );
                          },
                          child:
                              Container(
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(20),
                                      boxShadow: AppShadows.sm,
                                    ),
                                    padding: const EdgeInsets.all(8),
                                    child: Column(
                                      mainAxisAlignment:
                                          MainAxisAlignment.center,
                                      children: [
                                        Container(
                                          width: 60,
                                          height: 60,
                                          decoration: BoxDecoration(
                                            color: Colors.grey[100],
                                            shape: BoxShape.circle,
                                            border: Border.all(
                                              color: AppColors.gray50,
                                            ),
                                          ),
                                          alignment: Alignment.center,
                                          child: store.image != null
                                              ? ClipOval(
                                                  child: CachedNetworkImage(
                                                    imageUrl:
                                                        AppConfig.fixMediaUrl(
                                                          store.image,
                                                        ),
                                                    width: 60,
                                                    height: 60,
                                                    fit: BoxFit.cover,
                                                    errorWidget: (_, __, ___) =>
                                                        const Icon(
                                                          Icons.store,
                                                          size: 30,
                                                          color:
                                                              AppColors.primary,
                                                        ),
                                                  ),
                                                )
                                              : const Icon(
                                                  Icons.store,
                                                  size: 30,
                                                  color: AppColors.primary,
                                                ),
                                        ),

                                        const SizedBox(height: 8),
                                        Text(
                                          _localizedStoreName(store),
                                          style: const TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.bold,
                                            color: AppColors.gray800,
                                          ),
                                          textAlign: TextAlign.center,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ),

                                        const SizedBox(height: 4),
                                        Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            const Icon(
                                              Icons.star,
                                              color: Colors.amber,
                                              size: 12,
                                            ),
                                            const SizedBox(width: 2),
                                            Text(
                                              store.rating?.toString() ?? '4.5',
                                              style: const TextStyle(
                                                fontSize: 10,
                                                color: AppColors.gray600,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  )
                                  .animate()
                                  .fadeIn(delay: (index * 50).ms)
                                  .scale(begin: const Offset(0.9, 0.9)),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildNoResults() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.storefront, size: 64, color: AppColors.gray300),
          const SizedBox(height: 16),
          Text(
            context.tr('no_stores_with_name'),
            style: const TextStyle(
              color: AppColors.gray500,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    ).animate().fadeIn();
  }
}
