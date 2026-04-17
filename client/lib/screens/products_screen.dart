// Products Screen
// شاشة المنتجات

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/stores_service.dart';
import '../utils/saudi_riyal_icon.dart';
import 'payment_screen.dart';

class ProductsScreen extends StatefulWidget {
  final String storeName;
  final VoidCallback onBack;
  final int? storeId;

  const ProductsScreen({
    super.key,
    required this.storeName,
    required this.onBack,
    this.storeId,
  });

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final StoresService _storesService = StoresService();

  bool _isLoading = true;
  String? _error;
  String _searchQuery = '';
  int _activeCategoryId = 0;

  final Map<int, int> _cartItems = <int, int>{};
  final Set<int> _favorites = <int>{};
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _products = [];

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final catResponse = await _storesService.getProductCategories(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      final productsResponse = await _storesService.getProducts(
        storeId: widget.storeId,
        perPage: 200,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );

      if (!mounted) return;

      final categories = <Map<String, dynamic>>[
        {'id': 0, 'name_ar': context.tr('all'), 'name': context.tr('all')},
      ];
      if (catResponse.success && catResponse.data is List) {
        categories.addAll(
          (catResponse.data as List).whereType<Map>().map(
            (row) => Map<String, dynamic>.from(
              row.map((key, value) => MapEntry(key.toString(), value)),
            ),
          ),
        );
      }

      final products = <Map<String, dynamic>>[];
      if (productsResponse.success && productsResponse.data is List) {
        products.addAll(
          (productsResponse.data as List).whereType<Map>().map(
            (row) => Map<String, dynamic>.from(
              row.map((key, value) => MapEntry(key.toString(), value)),
            ),
          ),
        );
      }

      setState(() {
        _categories = categories;
        _products = products;
        _isLoading = false;
        if (_products.isEmpty) {
          _error = null;
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = context.tr('error_loading_data');
      });
    }
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  double _toDouble(dynamic value) {
    if (value is double) return value;
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _nameOf(Map<String, dynamic> product) {
    return (product['name_ar'] ?? product['name'] ?? '').toString();
  }

  double _priceOf(Map<String, dynamic> product) {
    return _toDouble(product['price']);
  }

  double? _oldPriceOf(Map<String, dynamic> product) {
    final old = _toDouble(product['old_price'] ?? product['original_price']);
    return old > 0 ? old : null;
  }

  bool _inStock(Map<String, dynamic> product) {
    final inStock = product['in_stock'];
    if (inStock is bool) return inStock;
    if (inStock is num) return inStock == 1;
    return _toInt(product['stock_quantity']) > 0;
  }

  List<Map<String, dynamic>> get _filteredProducts {
    return _products.where((product) {
      final categoryId = _toInt(product['category_id']);
      final matchesCategory =
          _activeCategoryId == 0 || categoryId == _activeCategoryId;
      final name = _nameOf(product).toLowerCase();
      final matchesSearch = name.contains(_searchQuery.toLowerCase());
      return matchesCategory && matchesSearch;
    }).toList();
  }

  void _addToCart(int productId) {
    setState(() {
      _cartItems[productId] = (_cartItems[productId] ?? 0) + 1;
    });
  }

  void _removeFromCart(int productId) {
    setState(() {
      if ((_cartItems[productId] ?? 0) > 1) {
        _cartItems[productId] = _cartItems[productId]! - 1;
      } else {
        _cartItems.remove(productId);
      }
    });
  }

  void _toggleFavorite(int productId) {
    setState(() {
      if (_favorites.contains(productId)) {
        _favorites.remove(productId);
      } else {
        _favorites.add(productId);
      }
    });
  }

  int get _totalItems => _cartItems.values.fold(0, (sum, count) => sum + count);

  double get _totalPrice {
    double total = 0;
    _cartItems.forEach((id, count) {
      final product = _products.firstWhere(
        (p) => _toInt(p['id']) == id,
        orElse: () => <String, dynamic>{},
      );
      total += _priceOf(product) * count;
    });
    return total;
  }

  String _formatPrice(double value) {
    return value % 1 == 0 ? value.toInt().toString() : value.toStringAsFixed(1);
  }

  void _checkoutCart() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PaymentScreen(
          amount: _totalPrice,
          serviceName: widget.storeName,
          onPaymentSuccess: () {
            if (!mounted) return;
            setState(() => _cartItems.clear());
            Navigator.pop(context);
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(context.tr('order_sent_successfully'))),
            );
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Stack(
        children: [
          Column(
            children: [
              _buildHeader(),
              _buildCategoriesFilter(),
              Expanded(
                child: _isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                    ? _buildErrorState()
                    : _filteredProducts.isEmpty
                    ? _buildEmptyState()
                    : LayoutBuilder(
                        builder: (context, constraints) {
                          final width = constraints.maxWidth;
                          final crossAxisCount = width >= 1100
                              ? 4
                              : width >= 760
                              ? 3
                              : 2;

                          return GridView.builder(
                            padding: const EdgeInsets.all(16),
                            gridDelegate:
                                SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: crossAxisCount,
                                  childAspectRatio: width < 380 ? 0.58 : 0.65,
                                  crossAxisSpacing: 12,
                                  mainAxisSpacing: 12,
                                ),
                            itemCount: _filteredProducts.length,
                            itemBuilder: (context, index) {
                              return _buildProductCard(
                                _filteredProducts[index],
                                index,
                              );
                            },
                          );
                        },
                      ),
              ),
            ],
          ),
          if (_totalItems > 0) _buildCartSummary(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 16,
        left: 16,
        right: 16,
        bottom: 16,
      ),
      decoration: BoxDecoration(
        color: AppColors.primary,
        boxShadow: AppShadows.lg,
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              InkWell(
                onTap: widget.onBack,
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.arrow_forward,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  widget.storeName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ),
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.shopping_cart,
                  color: Colors.white,
                  size: 20,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.9),
              borderRadius: BorderRadius.circular(16),
            ),
            child: TextField(
              onChanged: (value) => setState(() => _searchQuery = value),
              decoration: InputDecoration(
                hintText: context.tr('search_products_hint'),
                hintStyle: TextStyle(color: AppColors.gray400),
                prefixIcon: const Icon(Icons.search, color: AppColors.gray400),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoriesFilter() {
    return SizedBox(
      height: 62,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        scrollDirection: Axis.horizontal,
        itemCount: _categories.length,
        itemBuilder: (context, index) {
          final category = _categories[index];
          final categoryId = _toInt(category['id']);
          final isActive = _activeCategoryId == categoryId;
          final label = (category['name_ar'] ?? category['name'] ?? '')
              .toString();

          return GestureDetector(
            onTap: () => setState(() => _activeCategoryId = categoryId),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              margin: EdgeInsets.only(
                left: index < _categories.length - 1 ? 8 : 0,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: isActive ? AppColors.primary : Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isActive ? AppColors.primary : AppColors.gray200,
                ),
              ),
              child: Center(
                child: Text(
                  label,
                  style: TextStyle(
                    color: isActive ? Colors.white : AppColors.gray600,
                    fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product, int index) {
    final id = _toInt(product['id']);
    final name = _nameOf(product);
    final price = _priceOf(product);
    final oldPrice = _oldPriceOf(product);
    final inStock = _inStock(product);
    final rating = _toDouble(product['rating']);
    final reviews = _toInt(product['reviews_count']);
    final imageUrl = AppConfig.fixMediaUrl(product['image']?.toString());
    final quantity = _cartItems[id] ?? 0;
    final isFav = _favorites.contains(id);

    final discountPercent = _toInt(
      product['discount_percent'] ?? product['discount_percentage'],
    );

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(16),
                ),
                child: SizedBox(
                  height: 110,
                  width: double.infinity,
                  child: CachedNetworkImage(
                    imageUrl: imageUrl,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => Container(
                      color: AppColors.gray100,
                      alignment: Alignment.center,
                      child: const Icon(Icons.image_not_supported),
                    ),
                  ),
                ),
              ),
              if (discountPercent > 0)
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '-$discountPercent%',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              Positioned(
                top: 8,
                left: 8,
                child: InkWell(
                  onTap: () => _toggleFavorite(id),
                  child: Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.9),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      isFav ? Icons.favorite : Icons.favorite_border,
                      size: 16,
                      color: isFav ? Colors.red : AppColors.gray500,
                    ),
                  ),
                ),
              ),
            ],
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.star, size: 12, color: Colors.amber),
                      const SizedBox(width: 3),
                      Text(
                        rating.toStringAsFixed(1),
                        style: const TextStyle(fontSize: 10),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '($reviews)',
                        style: const TextStyle(
                          fontSize: 10,
                          color: AppColors.gray500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  if (oldPrice != null && oldPrice > price)
                    SaudiRiyalText(
                      text: _formatPrice(oldPrice),
                      style: const TextStyle(
                        fontSize: 10,
                        color: AppColors.gray400,
                        decoration: TextDecoration.lineThrough,
                      ),
                      iconSize: 10,
                    ),
                  SaudiRiyalText(
                    text: _formatPrice(price),
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF7466ED),
                    ),
                    iconSize: 13,
                  ),
                  const Spacer(),
                  if (!inStock)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: Colors.red.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        context.tr('out_of_stock'),
                        style: const TextStyle(color: Colors.red, fontSize: 11),
                      ),
                    )
                  else if (quantity == 0)
                    SizedBox(
                      width: double.infinity,
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () => _addToCart(id),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: Text(
                          context.tr('add'),
                          style: const TextStyle(fontSize: 11),
                        ),
                      ),
                    )
                  else
                    Container(
                      height: 32,
                      decoration: BoxDecoration(
                        color: AppColors.gray100,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            onPressed: () => _removeFromCart(id),
                            icon: const Icon(Icons.remove, size: 14),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                          Expanded(
                            child: Text(
                              '$quantity',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ),
                          IconButton(
                            onPressed: () => _addToCart(id),
                            icon: const Icon(Icons.add, size: 14),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    ).animate().fadeIn(delay: (index * 80).ms).slideY(begin: 0.08);
  }

  Widget _buildCartSummary() {
    return Positioned(
      left: 16,
      right: 16,
      bottom: 16,
      child: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF7466ED), Color(0xFF5F52D8)],
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: AppShadows.lg,
          ),
          child: Row(
            children: [
              const Icon(Icons.shopping_bag_outlined, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(
                child: SaudiRiyalText(
                  text:
                      '$_totalItems ${context.tr('product_label')} - ${_formatPrice(_totalPrice)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                  iconSize: 14,
                ),
              ),
              TextButton(
                onPressed: _checkoutCart,
                child: Text(
                  context.tr('complete_order'),
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          Text(_error ?? context.tr('connection_error')),
          const SizedBox(height: 8),
          TextButton(onPressed: _loadData, child: Text(context.tr('retry'))),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Text(
        context.tr('no_products'),
        style: const TextStyle(color: AppColors.gray500),
      ),
    );
  }
}
