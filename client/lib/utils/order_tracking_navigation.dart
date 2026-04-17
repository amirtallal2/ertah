import 'package:flutter/material.dart';

import '../models/service_category_model.dart';
import '../screens/order_tracking_screen.dart';

class OrderTrackingNavigation {
  const OrderTrackingNavigation._();

  static Future<void> open(
    BuildContext context, {
    required int orderId,
    String? categoryName,
    String? categoryIcon,
  }) {
    if (orderId <= 0) {
      return Future.value();
    }

    final service = ServiceCategoryModel(
      id: 0,
      nameAr: (categoryName ?? 'تفاصيل الطلب').trim(),
      nameEn: (categoryName ?? 'Order Details').trim(),
      icon: (categoryIcon ?? '🔧').trim(),
      createdAt: DateTime.now(),
    );

    return Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => OrderTrackingScreen(
          service: service,
          orderId: orderId.toString(),
          onBackToHome: () => Navigator.pop(context),
        ),
      ),
    );
  }
}
