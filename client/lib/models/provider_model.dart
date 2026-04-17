/// Provider Model
/// موديل مقدم الخدمة

class ProviderModel {
  final int id;
  final String fullName;
  final String phone;
  final String? email;
  final String? avatar;
  final ProviderStatus status;
  final bool isAvailable;
  final double rating;
  final int reviewsCount;
  final double walletBalance;
  final List<int>? categoryIds;
  final String? bio;
  final int? yearsExperience;
  final double? completionRate;
  final DateTime createdAt;

  ProviderModel({
    required this.id,
    required this.fullName,
    required this.phone,
    this.email,
    this.avatar,
    this.status = ProviderStatus.pending,
    this.isAvailable = true,
    this.rating = 0.0,
    this.reviewsCount = 0,
    this.walletBalance = 0.0,
    this.categoryIds,
    this.bio,
    this.yearsExperience,
    this.completionRate,
    required this.createdAt,
  });

  factory ProviderModel.fromJson(Map<String, dynamic> json) {
    return ProviderModel(
      id: json['id'] ?? 0,
      fullName: json['full_name'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'],
      avatar: json['avatar'],
      status: ProviderStatus.fromString(json['status']),
      isAvailable: json['is_available'] == 1 || json['is_available'] == true,
      rating: double.tryParse(json['rating']?.toString() ?? '0') ?? 0.0,
      reviewsCount: json['reviews_count'] ?? 0,
      walletBalance:
          double.tryParse(json['wallet_balance']?.toString() ?? '0') ?? 0.0,
      categoryIds: _parseCategoryIds(json['category_ids']),
      bio: json['bio'],
      yearsExperience: _parseNullableInt(
        json['experience_years'] ?? json['years_experience'],
      ),
      completionRate: double.tryParse(
        json['completion_rate']?.toString() ?? '0',
      ),
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  static List<int>? _parseCategoryIds(dynamic raw) {
    if (raw == null) {
      return null;
    }

    if (raw is List) {
      final ids = raw
          .map((item) => int.tryParse(item.toString()) ?? 0)
          .where((id) => id != 0)
          .toList();
      return ids;
    }

    final normalized = raw.toString().trim();
    if (normalized.isEmpty) {
      return const <int>[];
    }

    return normalized
        .split(',')
        .map((item) => int.tryParse(item.trim()) ?? 0)
        .where((id) => id != 0)
        .toList();
  }

  static int? _parseNullableInt(dynamic raw) {
    if (raw == null) {
      return null;
    }
    if (raw is int) {
      return raw;
    }
    if (raw is num) {
      return raw.toInt();
    }
    return int.tryParse(raw.toString().trim());
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'full_name': fullName,
      'phone': phone,
      'email': email,
      'avatar': avatar,
      'status': status.value,
      'is_available': isAvailable,
      'rating': rating,
      'wallet_balance': walletBalance,
      'category_ids': categoryIds,
      'bio': bio,
      'years_experience': yearsExperience,
    };
  }

  String get displayName => fullName.isNotEmpty ? fullName : 'مقدم خدمة';

  String get ratingText => rating.toStringAsFixed(1);

  String get statusAr => status.arabicName;

  bool get isApproved => status == ProviderStatus.approved;

  // For demo purposes
  static List<ProviderModel> getSampleProviders() {
    return [
      ProviderModel(
        id: 1,
        fullName: 'محمد أحمد',
        phone: '+966500000001',
        rating: 4.9,
        reviewsCount: 128,
        yearsExperience: 5,
        bio: 'فني سباكة محترف مع خبرة 5 سنوات في جميع أعمال السباكة',
        status: ProviderStatus.approved,
        completionRate: 98.5,
        createdAt: DateTime.now(),
      ),
      ProviderModel(
        id: 2,
        fullName: 'خالد محمد',
        phone: '+966500000002',
        rating: 4.8,
        reviewsCount: 95,
        yearsExperience: 3,
        bio: 'متخصص في صيانة المكيفات والتكييف المركزي',
        status: ProviderStatus.approved,
        completionRate: 97.2,
        createdAt: DateTime.now(),
      ),
      ProviderModel(
        id: 3,
        fullName: 'أحمد سعيد',
        phone: '+966500000003',
        rating: 4.7,
        reviewsCount: 64,
        yearsExperience: 4,
        bio: 'كهربائي محترف للمنازل والمباني التجارية',
        status: ProviderStatus.approved,
        completionRate: 96.8,
        createdAt: DateTime.now(),
      ),
    ];
  }
}

enum ProviderStatus {
  pending,
  approved,
  rejected,
  suspended;

  String get value {
    switch (this) {
      case ProviderStatus.pending:
        return 'pending';
      case ProviderStatus.approved:
        return 'approved';
      case ProviderStatus.rejected:
        return 'rejected';
      case ProviderStatus.suspended:
        return 'suspended';
    }
  }

  String get arabicName {
    switch (this) {
      case ProviderStatus.pending:
        return 'قيد المراجعة';
      case ProviderStatus.approved:
        return 'مفعّل';
      case ProviderStatus.rejected:
        return 'مرفوض';
      case ProviderStatus.suspended:
        return 'موقوف';
    }
  }

  static ProviderStatus fromString(String? status) {
    switch (status?.toLowerCase()) {
      case 'approved':
        return ProviderStatus.approved;
      case 'rejected':
        return ProviderStatus.rejected;
      case 'suspended':
        return ProviderStatus.suspended;
      default:
        return ProviderStatus.pending;
    }
  }
}
