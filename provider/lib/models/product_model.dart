// Product Model
// موديل المنتج

import '../config/currency_config.dart';

class ProductModel {
  final int id;
  final String nameAr;
  final String? nameEn;
  final String? description;
  final double price;
  final double? oldPrice;
  final String? image;
  final List<String>? images;
  final int? storeId;
  final int? categoryId;
  final int stockQuantity;
  final bool isActive;
  final double? rating;
  final int reviewsCount;
  final DateTime createdAt;

  // Related data
  final String? storeName;
  final String? categoryName;

  ProductModel({
    required this.id,
    required this.nameAr,
    this.nameEn,
    this.description,
    required this.price,
    this.oldPrice,
    this.image,
    this.images,
    this.storeId,
    this.categoryId,
    this.stockQuantity = 0,
    this.isActive = true,
    this.rating,
    this.reviewsCount = 0,
    required this.createdAt,
    this.storeName,
    this.categoryName,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'] ?? 0,
      nameAr: json['name_ar'] ?? json['name'] ?? '',
      nameEn: json['name_en'],
      description: json['description'],
      price: double.tryParse(json['price']?.toString() ?? '0') ?? 0.0,
      oldPrice: double.tryParse(json['old_price']?.toString() ?? ''),
      image: json['image'],
      images: json['images'] != null ? List<String>.from(json['images']) : null,
      storeId: json['store_id'],
      categoryId: json['category_id'],
      stockQuantity: json['stock_quantity'] ?? 0,
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      rating: double.tryParse(json['rating']?.toString() ?? ''),
      reviewsCount: json['reviews_count'] ?? 0,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
      storeName: json['store_name'],
      categoryName: json['category_name'],
    );
  }

  String get name => nameAr;

  bool get hasDiscount => oldPrice != null && oldPrice! > price;

  double get discountPercentage {
    if (!hasDiscount) return 0;
    return ((oldPrice! - price) / oldPrice! * 100);
  }

  String get priceText =>
      '${price.toStringAsFixed(0)} ${CurrencyConfig.symbol}';
  String get oldPriceText => oldPrice != null
      ? '${oldPrice!.toStringAsFixed(0)} ${CurrencyConfig.symbol}'
      : '';

  bool get inStock => stockQuantity > 0;

  static List<ProductModel> getSampleProducts() {
    return [
      ProductModel(
        id: 1,
        nameAr: 'مفتاح كهربائي ذكي',
        description: 'مفتاح كهربائي ذكي يتصل بالواي فاي ويتحكم به عن بعد',
        price: 75,
        oldPrice: 100,
        rating: 4.8,
        reviewsCount: 45,
        stockQuantity: 50,
        createdAt: DateTime.now(),
      ),
      ProductModel(
        id: 2,
        nameAr: 'دهان أبيض مائي 20 لتر',
        description: 'دهان داخلي عالي الجودة',
        price: 250,
        rating: 4.6,
        reviewsCount: 28,
        stockQuantity: 30,
        createdAt: DateTime.now(),
      ),
      ProductModel(
        id: 3,
        nameAr: 'مجموعة أدوات صيانة',
        description: 'طقم أدوات منزلية متكامل',
        price: 180,
        oldPrice: 220,
        rating: 4.9,
        reviewsCount: 67,
        stockQuantity: 25,
        createdAt: DateTime.now(),
      ),
    ];
  }
}
