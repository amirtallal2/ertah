/// Store Model
/// موديل المتاجر

class StoreModel {
  final int id;
  final String nameAr;
  final String? nameEn;
  final String? logo; // Using logo as primary image source
  final String? coverImage;
  final String? description;
  final String? phone;
  final String? address;
  final double? rating;
  final int productsCount;
  final bool isActive;
  final DateTime createdAt;

  StoreModel({
    required this.id,
    required this.nameAr,
    this.nameEn,
    this.logo,
    this.coverImage,
    this.description,
    this.phone,
    this.address,
    this.rating,
    this.productsCount = 0,
    this.isActive = true,
    required this.createdAt,
  });

  factory StoreModel.fromJson(Map<String, dynamic> json) {
    return StoreModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      nameAr: json['name_ar'] ?? json['name'] ?? '',
      nameEn: json['name_en'],
      logo: json['logo'] ?? json['image'], // Accept both
      coverImage: json['cover_image'],
      description: json['description'],
      phone: json['phone'],
      address: json['address'],
      rating: double.tryParse(json['rating']?.toString() ?? ''),
      productsCount:
          int.tryParse(json['products_count']?.toString() ?? '0') ?? 0,
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  String get name => nameAr;
  String? get image => logo; // Getter for compatibility

  static List<StoreModel> getSampleStores() {
    return [
      StoreModel(
        id: 1,
        nameAr: 'WiFi Adapters',
        description: 'متجر متخصص في الإلكترونيات',
        rating: 4.8,
        productsCount: 45,
        createdAt: DateTime.now(),
      ),
      StoreModel(
        id: 2,
        nameAr: 'دهانات نكو',
        description: 'جميع أنواع الدهانات والأصباغ',
        rating: 4.6,
        productsCount: 89,
        createdAt: DateTime.now(),
      ),
    ];
  }
}
