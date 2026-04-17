/// User Model
/// موديل المستخدم

class UserModel {
  final int id;
  final String fullName;
  final String phone;
  final String? email;
  final String? avatar;
  final double walletBalance;
  final int points;
  final String membershipLevel;
  final String? referralCode;
  final int? referredBy;
  final bool isActive;
  final bool isVerified;
  final String? deviceToken;
  final DateTime? lastLogin;
  final DateTime createdAt;
  final DateTime updatedAt;

  UserModel({
    required this.id,
    required this.fullName,
    required this.phone,
    this.email,
    this.avatar,
    this.walletBalance = 0.0,
    this.points = 0,
    this.membershipLevel = 'silver',
    this.referralCode,
    this.referredBy,
    this.isActive = true,
    this.isVerified = false,
    this.deviceToken,
    this.lastLogin,
    required this.createdAt,
    required this.updatedAt,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] ?? 0,
      fullName: json['full_name'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'],
      avatar: json['avatar'],
      walletBalance:
          double.tryParse(json['wallet_balance']?.toString() ?? '0') ?? 0.0,
      points: json['points'] ?? 0,
      membershipLevel: json['membership_level'] ?? 'silver',
      referralCode: json['referral_code'],
      referredBy: json['referred_by'],
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      isVerified: json['is_verified'] == 1 || json['is_verified'] == true,
      deviceToken: json['device_token'],
      lastLogin: json['last_login'] != null
          ? DateTime.tryParse(json['last_login'])
          : null,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
      updatedAt: DateTime.parse(
        json['updated_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'full_name': fullName,
      'phone': phone,
      'email': email,
      'avatar': avatar,
      'wallet_balance': walletBalance,
      'points': points,
      'membership_level': membershipLevel,
      'referral_code': referralCode,
      'referred_by': referredBy,
      'is_active': isActive,
      'is_verified': isVerified,
      'device_token': deviceToken,
    };
  }

  UserModel copyWith({
    int? id,
    String? fullName,
    String? phone,
    String? email,
    String? avatar,
    double? walletBalance,
    int? points,
    String? membershipLevel,
    String? referralCode,
    int? referredBy,
    bool? isActive,
    bool? isVerified,
    String? deviceToken,
    DateTime? lastLogin,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return UserModel(
      id: id ?? this.id,
      fullName: fullName ?? this.fullName,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      avatar: avatar ?? this.avatar,
      walletBalance: walletBalance ?? this.walletBalance,
      points: points ?? this.points,
      membershipLevel: membershipLevel ?? this.membershipLevel,
      referralCode: referralCode ?? this.referralCode,
      referredBy: referredBy ?? this.referredBy,
      isActive: isActive ?? this.isActive,
      isVerified: isVerified ?? this.isVerified,
      deviceToken: deviceToken ?? this.deviceToken,
      lastLogin: lastLogin ?? this.lastLogin,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  String get displayName => fullName.isNotEmpty ? fullName : 'مستخدم';

  String get membershipLevelAr {
    switch (membershipLevel) {
      case 'gold':
        return 'ذهبي';
      case 'platinum':
        return 'بلاتيني';
      default:
        return 'فضي';
    }
  }

  String get membershipIcon {
    switch (membershipLevel) {
      case 'gold':
        return '🥇';
      case 'platinum':
        return '💎';
      default:
        return '🥈';
    }
  }
}
