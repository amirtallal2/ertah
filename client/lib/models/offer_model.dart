// Offer Model
// موديل العروض

import '../config/currency_config.dart';

class OfferModel {
  final int id;
  final String titleAr;
  final String? titleEn;
  final String? descriptionAr;
  final String? descriptionEn;
  final String? image;
  final String discountType;
  final double discountValue;
  final double minOrderAmount;
  final double? maxDiscountAmount;
  final int? categoryId;
  final DateTime startDate;
  final DateTime endDate;
  final int? usageLimit;
  final int usedCount;
  final bool isActive;
  final DateTime createdAt;

  OfferModel({
    required this.id,
    required this.titleAr,
    this.titleEn,
    this.descriptionAr,
    this.descriptionEn,
    this.image,
    this.discountType = 'percentage',
    required this.discountValue,
    this.minOrderAmount = 0.0,
    this.maxDiscountAmount,
    this.categoryId,
    required this.startDate,
    required this.endDate,
    this.usageLimit,
    this.usedCount = 0,
    this.isActive = true,
    required this.createdAt,
  });

  factory OfferModel.fromJson(Map<String, dynamic> json) {
    return OfferModel(
      id: json['id'] ?? 0,
      titleAr: json['title_ar'] ?? '',
      titleEn: json['title_en'],
      descriptionAr: json['description_ar'],
      descriptionEn: json['description_en'],
      image: json['image'],
      discountType: json['discount_type'] ?? 'percentage',
      discountValue:
          double.tryParse(json['discount_value']?.toString() ?? '0') ?? 0.0,
      minOrderAmount:
          double.tryParse(json['min_order_amount']?.toString() ?? '0') ?? 0.0,
      maxDiscountAmount: double.tryParse(
        json['max_discount_amount']?.toString() ?? '',
      ),
      categoryId: json['category_id'],
      startDate: DateTime.parse(
        json['start_date'] ?? DateTime.now().toIso8601String(),
      ),
      endDate: DateTime.parse(
        json['end_date'] ?? DateTime.now().toIso8601String(),
      ),
      usageLimit: json['usage_limit'],
      usedCount: json['used_count'] ?? 0,
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  String get title => titleAr;
  String get description => descriptionAr ?? '';

  String get discountText {
    if (discountType == 'percentage') {
      return '${discountValue.toInt()}%';
    }
    return '${discountValue.toInt()} ${CurrencyConfig.symbol}';
  }

  bool get isExpired => DateTime.now().isAfter(endDate);
  bool get isNotStarted => DateTime.now().isBefore(startDate);
  bool get isValid => isActive && !isExpired && !isNotStarted;

  int get remainingDays {
    final diff = endDate.difference(DateTime.now());
    return diff.inDays;
  }

  static List<OfferModel> getSampleOffers() {
    return [
      OfferModel(
        id: 1,
        titleAr: 'خصم 30% على صيانة المكيفات',
        descriptionAr: 'استمتع بخصم خاص على جميع خدمات صيانة وتنظيف المكيفات',
        discountType: 'percentage',
        discountValue: 30,
        startDate: DateTime.now().subtract(const Duration(days: 5)),
        endDate: DateTime.now().add(const Duration(days: 10)),
        createdAt: DateTime.now(),
      ),
      OfferModel(
        id: 2,
        titleAr: 'خصم 50 ${CurrencyConfig.symbol} على طلبك الأول',
        descriptionAr: 'خصم خاص للمستخدمين الجدد على أول طلب',
        discountType: 'fixed',
        discountValue: 50,
        startDate: DateTime.now().subtract(const Duration(days: 30)),
        endDate: DateTime.now().add(const Duration(days: 30)),
        createdAt: DateTime.now(),
      ),
      OfferModel(
        id: 3,
        titleAr: 'خصم 20% على خدمات التنظيف',
        descriptionAr: 'تنظيف شامل للمنزل بخصم مميز',
        discountType: 'percentage',
        discountValue: 20,
        categoryId: 4,
        startDate: DateTime.now().subtract(const Duration(days: 2)),
        endDate: DateTime.now().add(const Duration(days: 7)),
        createdAt: DateTime.now(),
      ),
    ];
  }
}
