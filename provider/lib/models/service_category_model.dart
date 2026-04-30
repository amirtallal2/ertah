// Service Category Model
// موديل فئات الخدمات

import 'package:flutter/material.dart';
import '../config/app_theme.dart';

class ServiceCategoryModel {
  final int id;
  final String nameAr;
  final String? nameEn;
  final String? icon;
  final String? image;
  final String inspectionPricingMode;
  final double inspectionFee;
  final String? inspectionDetailsAr;
  final String? inspectionDetailsEn;
  final String? inspectionDetailsUr;
  final bool isActive;
  final int sortOrder;
  final DateTime createdAt;

  ServiceCategoryModel({
    required this.id,
    required this.nameAr,
    this.nameEn,
    this.icon,
    this.image,
    this.inspectionPricingMode = 'free',
    this.inspectionFee = 0,
    this.inspectionDetailsAr,
    this.inspectionDetailsEn,
    this.inspectionDetailsUr,
    this.isActive = true,
    this.sortOrder = 0,
    required this.createdAt,
  });

  factory ServiceCategoryModel.fromJson(Map<String, dynamic> json) {
    return ServiceCategoryModel(
      id: json['id'] ?? 0,
      nameAr: json['name_ar'] ?? '',
      nameEn: json['name_en'],
      icon: json['icon'],
      image: json['image'],
      inspectionPricingMode: (json['inspection_pricing_mode'] ?? 'free')
          .toString(),
      inspectionFee: _toDouble(json['inspection_fee']),
      inspectionDetailsAr: json['inspection_details_ar']?.toString(),
      inspectionDetailsEn: json['inspection_details_en']?.toString(),
      inspectionDetailsUr: json['inspection_details_ur']?.toString(),
      isActive: json['is_active'] == 1 || json['is_active'] == true,
      sortOrder: json['sort_order'] ?? 0,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name_ar': nameAr,
      'name_en': nameEn,
      'icon': icon,
      'image': image,
      'inspection_pricing_mode': inspectionPricingMode,
      'inspection_fee': inspectionFee,
      'inspection_details_ar': inspectionDetailsAr,
      'inspection_details_en': inspectionDetailsEn,
      'inspection_details_ur': inspectionDetailsUr,
      'is_active': isActive,
      'sort_order': sortOrder,
    };
  }

  String get name => nameAr;

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0.0;
  }

  String get displayIcon => icon ?? '';

  Color get color {
    switch (nameAr) {
      case 'سباكة':
        return AppColors.plumbing;
      case 'كهرباء':
        return AppColors.electrical;
      case 'تكييف':
        return AppColors.ac;
      case 'تنظيف':
        return AppColors.cleaning;
      case 'دهان':
        return AppColors.painting;
      case 'نجارة':
        return AppColors.carpentry;
      case 'أجهزة منزلية':
        return AppColors.appliances;
      case 'تبليط':
        return AppColors.tiling;
      case 'أمن وسلامة':
        return AppColors.security;
      default:
        return AppColors.primary;
    }
  }

  LinearGradient get gradient {
    return LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [color, color.withValues(alpha: 0.8)],
    );
  }

  // Static factory method for sample data
  static List<ServiceCategoryModel> getSampleCategories() {
    return [
      ServiceCategoryModel(
        id: 1,
        nameAr: 'سباكة',
        nameEn: 'Plumbing',
        icon: '🔧',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 2,
        nameAr: 'كهرباء',
        nameEn: 'Electrical',
        icon: '⚡',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 3,
        nameAr: 'تكييف',
        nameEn: 'AC',
        icon: '❄️',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 4,
        nameAr: 'تنظيف',
        nameEn: 'Cleaning',
        icon: '🧹',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 5,
        nameAr: 'نجارة',
        nameEn: 'Carpentry',
        icon: '🔨',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 6,
        nameAr: 'أجهزة منزلية',
        nameEn: 'Home Appliances',
        icon: '🌀',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 7,
        nameAr: 'دهان',
        nameEn: 'Painting',
        icon: '🎨',
        createdAt: DateTime.now(),
      ),
      ServiceCategoryModel(
        id: 8,
        nameAr: 'تبليط',
        nameEn: 'Tiling',
        icon: '🔲',
        createdAt: DateTime.now(),
      ),
    ];
  }
}
