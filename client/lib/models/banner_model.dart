/// Banner Model
/// موديل البانرات

class BannerModel {
  final int id;
  final String? title;
  final String image;
  final String? link;
  final BannerLinkType linkType;
  final int? linkId;
  final BannerPosition position;
  final bool isActive;
  final int sortOrder;
  final DateTime? startDate;
  final DateTime? endDate;
  final DateTime createdAt;

  BannerModel({
    required this.id,
    this.title,
    required this.image,
    this.link,
    this.linkType = BannerLinkType.none,
    this.linkId,
    this.position = BannerPosition.homeSlider,
    this.isActive = true,
    this.sortOrder = 0,
    this.startDate,
    this.endDate,
    required this.createdAt,
  });

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: json['id'] ?? 0,
      title: json['title'],
      image: json['image'] ?? '',
      link: json['link'],
      linkType: BannerLinkType.fromString(json['link_type']),
      linkId: json['link_id'],
      position: BannerPosition.fromString(json['position']),
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      sortOrder: json['sort_order'] ?? 0,
      startDate: json['start_date'] != null
          ? DateTime.tryParse(json['start_date'])
          : null,
      endDate: json['end_date'] != null
          ? DateTime.tryParse(json['end_date'])
          : null,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  bool get isExpired => endDate != null && DateTime.now().isAfter(endDate!);
  bool get isNotStarted =>
      startDate != null && DateTime.now().isBefore(startDate!);
  bool get isValid => isActive && !isExpired && !isNotStarted;

  static List<BannerModel> getSampleBanners() {
    return [
      BannerModel(
        id: 1,
        title: 'خدمات التنظيف',
        image: 'https://j.top4top.io/p_3621g6yx21.jpeg',
        position: BannerPosition.homeSlider,
        createdAt: DateTime.now(),
      ),
      BannerModel(
        id: 2,
        title: 'خدمات السباكة',
        image: 'https://i.top4top.io/p_3621bdx2z1.jpeg',
        position: BannerPosition.homeSlider,
        createdAt: DateTime.now(),
      ),
      BannerModel(
        id: 3,
        title: 'صيانة منزلية',
        image: 'https://l.top4top.io/p_3621gov4g3.jpeg',
        position: BannerPosition.homeSlider,
        createdAt: DateTime.now(),
      ),
    ];
  }
}

enum BannerLinkType {
  category,
  offer,
  product,
  external,
  none;

  String get value {
    switch (this) {
      case BannerLinkType.category:
        return 'category';
      case BannerLinkType.offer:
        return 'offer';
      case BannerLinkType.product:
        return 'product';
      case BannerLinkType.external:
        return 'external';
      case BannerLinkType.none:
        return 'none';
    }
  }

  static BannerLinkType fromString(String? type) {
    switch (type?.toLowerCase()) {
      case 'category':
        return BannerLinkType.category;
      case 'offer':
        return BannerLinkType.offer;
      case 'product':
        return BannerLinkType.product;
      case 'external':
        return BannerLinkType.external;
      default:
        return BannerLinkType.none;
    }
  }
}

enum BannerPosition {
  homeSlider,
  homePopup,
  category,
  offer;

  String get value {
    switch (this) {
      case BannerPosition.homeSlider:
        return 'home_slider';
      case BannerPosition.homePopup:
        return 'home_popup';
      case BannerPosition.category:
        return 'category';
      case BannerPosition.offer:
        return 'offer';
    }
  }

  static BannerPosition fromString(String? position) {
    switch (position?.toLowerCase()) {
      case 'home_popup':
        return BannerPosition.homePopup;
      case 'category':
        return BannerPosition.category;
      case 'offer':
        return BannerPosition.offer;
      default:
        return BannerPosition.homeSlider;
    }
  }
}
