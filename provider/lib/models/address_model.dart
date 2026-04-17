/// Address Model
/// موديل عناوين المستخدم

class AddressModel {
  final int id;
  final int userId;
  final AddressType type;
  final String? label;
  final String address;
  final String? details;
  final double? lat;
  final double? lng;
  final bool isDefault;
  final DateTime createdAt;

  AddressModel({
    required this.id,
    required this.userId,
    this.type = AddressType.home,
    this.label,
    required this.address,
    this.details,
    this.lat,
    this.lng,
    this.isDefault = false,
    required this.createdAt,
  });

  factory AddressModel.fromJson(Map<String, dynamic> json) {
    return AddressModel(
      id: json['id'] ?? 0,
      userId: json['user_id'] ?? 0,
      type: AddressType.fromString(json['type']),
      label: json['label'],
      address: json['address'] ?? '',
      details: json['details'],
      lat: double.tryParse(json['lat']?.toString() ?? ''),
      lng: double.tryParse(json['lng']?.toString() ?? ''),
      isDefault: json['is_default'] == 1 || json['is_default'] == true,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'type': type.value,
      'label': label,
      'address': address,
      'details': details,
      'lat': lat,
      'lng': lng,
      'is_default': isDefault,
    };
  }

  String get displayName => label ?? type.arabicName;
  String get icon => type.icon;

  static List<AddressModel> getSampleAddresses() {
    return [
      AddressModel(
        id: 1,
        userId: 1,
        type: AddressType.home,
        label: 'المنزل',
        address: 'الرياض، حي النخيل، شارع الملك فهد',
        details: 'فيلا رقم 25',
        isDefault: true,
        createdAt: DateTime.now(),
      ),
      AddressModel(
        id: 2,
        userId: 1,
        type: AddressType.work,
        label: 'العمل',
        address: 'الرياض، حي العليا، برج الفيصلية',
        details: 'الطابق 15، مكتب 1502',
        createdAt: DateTime.now(),
      ),
    ];
  }
}

enum AddressType {
  home,
  work,
  other;

  String get value {
    switch (this) {
      case AddressType.home:
        return 'home';
      case AddressType.work:
        return 'work';
      case AddressType.other:
        return 'other';
    }
  }

  String get arabicName {
    switch (this) {
      case AddressType.home:
        return 'المنزل';
      case AddressType.work:
        return 'العمل';
      case AddressType.other:
        return 'آخر';
    }
  }

  String get icon {
    switch (this) {
      case AddressType.home:
        return '🏠';
      case AddressType.work:
        return '🏢';
      case AddressType.other:
        return '📍';
    }
  }

  static AddressType fromString(String? type) {
    switch (type?.toLowerCase()) {
      case 'work':
        return AddressType.work;
      case 'other':
        return AddressType.other;
      default:
        return AddressType.home;
    }
  }
}
