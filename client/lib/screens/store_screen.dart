// Store Screen
// شاشة المتجر (السوق أو متجر محدد)

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import '../services/app_localizations.dart';
import '../config/app_config.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../utils/saudi_riyal_icon.dart';
import 'all_stores_screen.dart';
import 'products_screen.dart';

class StoreScreen extends StatefulWidget {
  final int? storeId;
  final String? storeName;

  const StoreScreen({super.key, this.storeId, this.storeName});

  @override
  State<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends State<StoreScreen> {
  final StoresService _storesService = StoresService();

  bool _isLoading = true;
  String _searchQuery = '';
  List<dynamic> _categories = [];
  List<dynamic> _stores = [];
  List<dynamic> _products = [];

  int _selectedCategoryId = 0; // 0 for 'All'

  @override
  void initState() {
    super.initState();
    _fetchStoreData();
  }

  Future<void> _fetchStoreData() async {
    final allLabel = context.tr('all');
    setState(() => _isLoading = true);
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      // Fetch categories
      final catResponse = await _storesService.getProductCategories(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      List<dynamic> fetchedCategories = [];
      if (catResponse.success && catResponse.data is List) {
        fetchedCategories = catResponse.data as List;
      }

      // Add 'All' category locally
      fetchedCategories.insert(0, {'id': 0, 'name': allLabel});
      _categories = fetchedCategories;

      // Fetch stores (featured) only if we are in general market mode (no storeId)
      if (widget.storeId == null) {
        final storesResponse = await _storesService.getStores(
          lat: location.requestLat,
          lng: location.requestLng,
          countryCode: location.requestCountryCode,
          allowOutside: allowOutside,
        );
        if (storesResponse.success && storesResponse.data is List) {
          _stores = storesResponse.data as List;
        }
      }

      // Fetch initial products
      await _fetchProducts();

      setState(() => _isLoading = false);
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _fetchProducts() async {
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _storesService.getProducts(
        categoryId: _selectedCategoryId == 0 ? null : _selectedCategoryId,
        storeId: widget.storeId,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (response.success && response.data is List) {
        setState(() {
          _products = response.data as List;
        });
      }
    } catch (e) {
      // Handle error
    }
  }

  void _onCategorySelected(int id) {
    setState(() {
      _selectedCategoryId = id;
      _products = []; // Clear current list while loading
    });
    _fetchProducts();
  }

  bool _matchesSearch(dynamic product) {
    final query = _searchQuery.trim().toLowerCase();
    if (query.isEmpty) return true;
    final name = (product['name_ar'] ?? product['name'] ?? '')
        .toString()
        .toLowerCase();
    return name.contains(query);
  }

  Future<void> _openSearchDialog() async {
    final controller = TextEditingController(text: _searchQuery);
    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.tr('search_product_title')),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: InputDecoration(
            hintText: context.tr('search_product_hint'),
            border: OutlineInputBorder(),
          ),
          onSubmitted: (value) {
            Navigator.of(dialogContext).pop(value);
          },
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(_searchQuery),
            child: Text(context.tr('cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(''),
            child: Text(context.tr('clear')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(dialogContext).pop(controller.text),
            child: Text(context.tr('search')),
          ),
        ],
      ),
    );

    if (!mounted || result == null) return;
    setState(() => _searchQuery = result.trim());
  }

  void _openProductsScreen() {
    final title = widget.storeName ?? context.tr('store');
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (routeContext) => ProductsScreen(
          storeName: title,
          storeId: widget.storeId,
          onBack: () => Navigator.of(routeContext).pop(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.storeName ?? context.tr('store');
    final visibleProducts = _products.where(_matchesSearch).toList();

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(title),
        backgroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: _openSearchDialog,
          ),
          IconButton(
            icon: Stack(
              children: [
                const Icon(Icons.shopping_cart_outlined),
                // Badge logic implementation later
              ],
            ),
            onPressed: _openProductsScreen,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchStoreData,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Categories
                    SizedBox(
                      height: 50,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 8,
                        ),
                        itemCount: _categories.length,
                        itemBuilder: (context, index) {
                          final cat = _categories[index];
                          final isSelected = cat['id'] == _selectedCategoryId;
                          return GestureDetector(
                            onTap: () => _onCategorySelected(cat['id']),
                            child: Container(
                              margin: EdgeInsets.only(
                                left: index == _categories.length - 1 ? 0 : 8,
                              ),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 20,
                                vertical: 8,
                              ),
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? AppColors.primary
                                    : Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                  color: isSelected
                                      ? AppColors.primary
                                      : AppColors.gray200,
                                ),
                              ),
                              child: Center(
                                child: Text(
                                  cat['name'] ?? '',
                                  style: TextStyle(
                                    color: isSelected
                                        ? Colors.white
                                        : AppColors.gray600,
                                    fontWeight: isSelected
                                        ? FontWeight.w600
                                        : FontWeight.normal,
                                    fontSize: 13,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Stores Section (Only show if no specific store selected)
                    if (widget.storeId == null && _stores.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              context.tr('featured_stores'),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w600),
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => const AllStoresScreen(),
                                  ),
                                );
                              },
                              child: Text(context.tr('view_all')),
                            ),
                          ],
                        ),
                      ),

                      SizedBox(
                        height: 120,
                        child: ListView.builder(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: _stores.length,
                          itemBuilder: (context, index) {
                            final store = _stores[index];
                            return InkWell(
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => StoreScreen(
                                      storeId: int.tryParse(
                                        store['id'].toString(),
                                      ),
                                      storeName:
                                          store['name_ar'] ?? store['name'],
                                    ),
                                  ),
                                );
                              },
                              child:
                                  Container(
                                        width: 100,
                                        margin: EdgeInsets.only(
                                          left: index == _stores.length - 1
                                              ? 0
                                              : 12,
                                        ),
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.circular(
                                            16,
                                          ),
                                          boxShadow: AppShadows.sm,
                                        ),
                                        child: Column(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            Container(
                                              width: 50,
                                              height: 50,
                                              decoration: BoxDecoration(
                                                color: AppColors.primaryLight,
                                                borderRadius:
                                                    BorderRadius.circular(12),
                                              ),
                                              child: store['logo'] != null
                                                  ? ClipRRect(
                                                      borderRadius:
                                                          BorderRadius.circular(
                                                            12,
                                                          ),
                                                      child: CachedNetworkImage(
                                                        imageUrl:
                                                            AppConfig.fixMediaUrl(
                                                              store['logo'],
                                                            ),
                                                        fit: BoxFit.cover,
                                                        errorWidget:
                                                            (_, __, ___) =>
                                                                const Center(
                                                                  child: Icon(
                                                                    Icons.store,
                                                                  ),
                                                                ),
                                                      ),
                                                    )
                                                  : const Center(
                                                      child: Text(
                                                        '🏪',
                                                        style: TextStyle(
                                                          fontSize: 28,
                                                        ),
                                                      ),
                                                    ),
                                            ),
                                            const SizedBox(height: 8),
                                            Text(
                                              store['name_ar'] ??
                                                  store['name'] ??
                                                  context.tr('store'),
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .labelSmall
                                                  ?.copyWith(
                                                    fontWeight: FontWeight.w500,
                                                  ),
                                              textAlign: TextAlign.center,
                                              maxLines: 2,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ),
                                      )
                                      .animate()
                                      .fadeIn(delay: (100 * index).ms)
                                      .slideX(begin: 0.2, end: 0),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 24),
                    ],

                    // Products Section
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text(
                        widget.storeId == null
                            ? context.tr('featured_products')
                            : context.tr('store_products'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Products Grid
                    visibleProducts.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(32.0),
                              child: Text(context.tr('no_products')),
                            ),
                          )
                        : Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            child: GridView.builder(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              gridDelegate:
                                  const SliverGridDelegateWithFixedCrossAxisCount(
                                    crossAxisCount: 2,
                                    childAspectRatio: 0.75,
                                    crossAxisSpacing: 12,
                                    mainAxisSpacing: 12,
                                  ),
                              itemCount: visibleProducts.length,
                              itemBuilder: (context, index) {
                                final product = visibleProducts[index];
                                return _buildProductCard(product, index);
                              },
                            ),
                          ),

                    const SizedBox(height: 100),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product, int index) {
    final hasDiscount =
        product['discount_percentage'] != null &&
        (product['discount_percentage'] as num) > 0;

    return Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: AppShadows.sm,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Image
              Expanded(
                child: Stack(
                  children: [
                    Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: AppColors.gray100,
                        borderRadius: const BorderRadius.vertical(
                          top: Radius.circular(16),
                        ),
                      ),
                      child: product['image'] != null
                          ? CachedNetworkImage(
                              imageUrl: AppConfig.fixMediaUrl(product['image']),
                              fit: BoxFit.cover,
                              errorWidget: (context, url, error) =>
                                  const Center(child: Icon(Icons.error)),
                            )
                          : const Center(
                              child: Text('📦', style: TextStyle(fontSize: 40)),
                            ),
                    ),
                    if (hasDiscount)
                      Positioned(
                        top: 8,
                        right: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.error,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            '-${product['discount_percentage']}%',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),

              // Content
              Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product['name_ar'] ?? product['name'] ?? '',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        SaudiRiyalText(
                          text: '${product['price']}',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(
                                color: AppColors.secondary,
                                fontWeight: FontWeight.bold,
                              ),
                          iconSize: 14,
                        ),
                        if (hasDiscount) ...[
                          const SizedBox(width: 6),
                          SaudiRiyalText(
                            text: '${product['old_price']}',
                            style: Theme.of(context).textTheme.labelSmall
                                ?.copyWith(
                                  color: AppColors.gray400,
                                  decoration: TextDecoration.lineThrough,
                                ),
                            iconSize: 11,
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        )
        .animate()
        .fadeIn(delay: (100 + index * 50).ms)
        .scale(begin: const Offset(0.95, 0.95), end: const Offset(1, 1));
  }
}
