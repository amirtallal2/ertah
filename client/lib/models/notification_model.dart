// Notification Model
// موديل الإشعارات

import '../config/currency_config.dart';

class NotificationModel {
  final int id;
  final int? userId;
  final int? providerId;
  final String title;
  final String body;
  final NotificationType type;
  final Map<String, dynamic>? data;
  final bool isRead;
  final DateTime createdAt;

  NotificationModel({
    required this.id,
    this.userId,
    this.providerId,
    required this.title,
    required this.body,
    this.type = NotificationType.system,
    this.data,
    this.isRead = false,
    required this.createdAt,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] ?? 0,
      userId: json['user_id'],
      providerId: json['provider_id'],
      title: json['title'] ?? '',
      body: json['body'] ?? '',
      type: NotificationType.fromString(json['type']),
      data: json['data'] != null
          ? (json['data'] is String ? {} : json['data'])
          : null,
      isRead: json['is_read'] == 1 || json['is_read'] == true,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  String get icon => type.icon;

  String get timeAgo {
    final diff = DateTime.now().difference(createdAt);
    if (diff.inMinutes < 1) return 'الآن';
    if (diff.inMinutes < 60) return 'منذ ${diff.inMinutes} دقيقة';
    if (diff.inHours < 24) return 'منذ ${diff.inHours} ساعة';
    if (diff.inDays < 7) return 'منذ ${diff.inDays} يوم';
    return 'منذ ${(diff.inDays / 7).floor()} أسبوع';
  }

  static List<NotificationModel> getSampleNotifications() {
    return [
      NotificationModel(
        id: 1,
        title: 'تم قبول طلبك',
        body: 'مقدم الخدمة محمد أحمد في طريقه إليك',
        type: NotificationType.order,
        createdAt: DateTime.now().subtract(const Duration(minutes: 5)),
      ),
      NotificationModel(
        id: 2,
        title: 'عرض خاص!',
        body: 'احصل على خصم 30% على خدمات التنظيف',
        type: NotificationType.promotion,
        createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      ),
      NotificationModel(
        id: 3,
        title: 'تم إضافة رصيد',
        body: 'تم إضافة 50 ${CurrencyConfig.symbol} إلى محفظتك',
        type: NotificationType.wallet,
        createdAt: DateTime.now().subtract(const Duration(days: 1)),
      ),
    ];
  }
}

enum NotificationType {
  order,
  promotion,
  system,
  wallet,
  review;

  String get value {
    switch (this) {
      case NotificationType.order:
        return 'order';
      case NotificationType.promotion:
        return 'promotion';
      case NotificationType.system:
        return 'system';
      case NotificationType.wallet:
        return 'wallet';
      case NotificationType.review:
        return 'review';
    }
  }

  String get icon {
    switch (this) {
      case NotificationType.order:
        return '📦';
      case NotificationType.promotion:
        return '🎁';
      case NotificationType.system:
        return '🔔';
      case NotificationType.wallet:
        return '💰';
      case NotificationType.review:
        return '⭐';
    }
  }

  static NotificationType fromString(String? type) {
    switch (type?.toLowerCase()) {
      case 'order':
        return NotificationType.order;
      case 'promotion':
        return NotificationType.promotion;
      case 'wallet':
      case 'wallet_update':
        return NotificationType.wallet;
      case 'review':
        return NotificationType.review;
      case 'system':
      case 'info_update':
        return NotificationType.system;
      default:
        return NotificationType.system;
    }
  }
}
