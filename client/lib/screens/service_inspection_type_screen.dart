// Service Inspection Type Screen
// شاشة اختيار نوع المعاينة

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import 'payment_screen.dart';
import 'service_selection_screen.dart';
import '../services/app_localizations.dart';
import '../utils/saudi_riyal_icon.dart';

class ServiceInspectionTypeScreen extends StatefulWidget {
  final ServiceCategoryModel service;

  const ServiceInspectionTypeScreen({super.key, required this.service});

  @override
  State<ServiceInspectionTypeScreen> createState() =>
      _ServiceInspectionTypeScreenState();
}

class _ServiceInspectionTypeScreenState
    extends State<ServiceInspectionTypeScreen> {
  String? _selectedType; // 'online' or 'home'
  bool _agreedToTerms = false;
  final TextEditingController _problemDescriptionController =
      TextEditingController();
  File? _uploadedImage;
  bool _showError = false;
  final double _onlinePrice = 30.0;
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImage() async {
    final XFile? image = await _picker.pickImage(source: ImageSource.gallery);
    if (image != null) {
      setState(() {
        _uploadedImage = File(image.path);
      });
    }
  }

  void _handleContinue() {
    if (_selectedType == 'online') {
      if (!_agreedToTerms ||
          _problemDescriptionController.text.isEmpty ||
          _uploadedImage == null) {
        setState(() => _showError = true);
        return;
      }
      // Navigate to Payment
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => PaymentScreen(
            amount: _onlinePrice,
            serviceName: widget.service.nameAr,
            onPaymentSuccess: () {
              // Handle success (e.g. go to tracking or success screen)
              Navigator.of(
                context,
              ).popUntil((route) => route.isFirst); // Quick hack for now
            },
          ),
        ),
      );
    } else if (_selectedType == 'home') {
      // Navigate to Service Selection Screen
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => ServiceSelectionScreen(service: widget.service),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: SafeArea(
        child: Column(
          children: [
            // Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
                ),
                boxShadow: AppShadows.md,
                borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(24),
                ),
              ),
              child: Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.pop(context),
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.arrow_back,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    context.tr('inspection_type'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Spacer(),
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Center(
                      child: (widget.service.icon ?? '').startsWith('http')
                          ? CachedNetworkImage(
                              imageUrl: AppConfig.fixMediaUrl(
                                widget.service.icon,
                              ),
                              width: 24,
                              height: 24,
                              color: Colors.white,
                              errorWidget: (_, __, ___) => const Text(
                                '🔧',
                                style: TextStyle(fontSize: 20),
                              ),
                            )
                          : Text(
                              (widget.service.icon ?? '').isNotEmpty
                                  ? widget.service.icon!
                                  : '🔧',
                              style: const TextStyle(fontSize: 20),
                            ),
                    ),
                  ),
                ],
              ),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const SizedBox(height: 8),
                    Text(
                      context.tr('choose_appropriate_inspection'),
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                        color: AppColors.gray800,
                      ),
                    ).animate().fadeIn(),
                    const SizedBox(height: 4),
                    Text(
                      context.tr('choose_best_way'),
                      style: TextStyle(fontSize: 12, color: AppColors.gray500),
                    ).animate().fadeIn(),

                    const SizedBox(height: 24),

                    // Options Grid
                    Row(
                      children: [
                        Expanded(
                          child: _buildOptionCard(
                            type: 'online',
                            title: context.tr('remote_inspection'),
                            subtitle: context.tr('consultation_via_images'),
                            priceWidget: SaudiRiyalText(
                              text: '$_onlinePrice',
                              style: const TextStyle(
                                color: Color(0xFF7466ED),
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                              iconSize: 10,
                            ),
                            image:
                                'https://images.pexels.com/photos/585419/pexels-photo-585419.jpeg?auto=compress&cs=tinysrgb?w=400',
                            icon: Icons.video_call,
                            color: const Color(0xFF7466ED),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _buildOptionCard(
                            type: 'home',
                            title: context.tr('home_visit'),
                            subtitle: context.tr('specialist_visits_home'),
                            price: context.tr('depending_on_service'),
                            image:
                                'https://images.pexels.com/photos/3862130/pexels-photo-3862130.jpeg?auto=compress&cs=tinysrgb?w=400',
                            icon: Icons.home_work,
                            color: const Color(0xFFFBCC26),
                          ),
                        ),
                      ],
                    ),

                    // Online Details
                    if (_selectedType == 'online') ...[
                      const SizedBox(height: 24),
                      _buildOnlineDetails().animate().fadeIn().slideY(
                        begin: 0.1,
                        end: 0,
                      ),
                    ],

                    const SizedBox(height: 32),

                    // Continue Button
                    if (_selectedType != null)
                      ElevatedButton(
                        onPressed: _handleContinue,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _selectedType == 'online'
                              ? const Color(0xFF7466ED)
                              : const Color(0xFFFBCC26),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                          elevation: 4,
                          minimumSize: const Size(double.infinity, 56),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              _selectedType == 'online'
                                  ? context.tr('continue_to_payment')
                                  : context.tr('choose_services'),
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Icon(Icons.arrow_forward),
                          ],
                        ),
                      ).animate().fadeIn().scale(),

                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOptionCard({
    required String type,
    required String title,
    required String subtitle,
    String? price,
    Widget? priceWidget,
    required String image,
    required IconData icon,
    required Color color,
  }) {
    final isSelected = _selectedType == type;
    return GestureDetector(
      onTap: () => setState(() => _selectedType = type),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? color : Colors.transparent,
            width: 2,
          ),
          boxShadow: isSelected
              ? [BoxShadow(color: color.withValues(alpha: 0.3), blurRadius: 10)]
              : AppShadows.sm,
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: [
            SizedBox(
              height: 100,
              width: double.infinity,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  CachedNetworkImage(imageUrl: image, fit: BoxFit.cover),
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.transparent,
                          color.withValues(alpha: 0.4),
                        ],
                      ),
                    ),
                  ),
                  if (isSelected)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: color,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.check,
                          color: Colors.white,
                          size: 14,
                        ),
                      ),
                    ),
                  Positioned(
                    bottom: 8,
                    left: 8,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(icon, color: color, size: 18),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: AppColors.gray800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 10,
                      color: AppColors.gray500,
                    ),
                    textAlign: TextAlign.center,
                    maxLines: 2,
                  ),
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child:
                        priceWidget ??
                        Text(
                          price ?? '',
                          style: TextStyle(
                            color: color,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOnlineDetails() {
    return Column(
      children: [
        // Terms
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFF7466ED).withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: const Color(0xFF7466ED).withValues(alpha: 0.2),
            ),
          ),
          child: Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.info_outline,
                    color: Color(0xFF7466ED),
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          context.tr('remote_inspection_terms_title'),
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                            color: AppColors.gray800,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          context.tr('remote_inspection_terms_body'),
                          style: TextStyle(
                            fontSize: 11,
                            color: AppColors.gray600,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 24),
              InkWell(
                onTap: () => setState(() => _agreedToTerms = !_agreedToTerms),
                child: Row(
                  children: [
                    Icon(
                      _agreedToTerms
                          ? Icons.check_box
                          : Icons.check_box_outline_blank,
                      color: _agreedToTerms
                          ? const Color(0xFF7466ED)
                          : AppColors.gray400,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      context.tr('agree_to_terms'),
                      style: TextStyle(fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        // Image Upload
        GestureDetector(
          onTap: _pickImage,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: _uploadedImage != null
                    ? Colors.green
                    : AppColors.gray200,
                style: _uploadedImage != null
                    ? BorderStyle.solid
                    : BorderStyle.solid,
                width: 2,
              ),
            ),
            child: _uploadedImage != null
                ? Column(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.file(
                          _uploadedImage!,
                          height: 150,
                          width: double.infinity,
                          fit: BoxFit.cover,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.check_circle,
                            color: Colors.green,
                            size: 16,
                          ),
                          SizedBox(width: 4),
                          Text(
                            context.tr('image_selected'),
                            style: TextStyle(color: Colors.green, fontSize: 12),
                          ),
                        ],
                      ),
                    ],
                  )
                : Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.orange.withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.cloud_upload,
                          color: Colors.orange,
                          size: 24,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        context.tr('tap_to_upload_image'),
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
          ),
        ),

        const SizedBox(height: 16),

        // Description
        Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: AppShadows.sm,
          ),
          child: TextField(
            controller: _problemDescriptionController,
            maxLines: 4,
            decoration: InputDecoration(
              hintText: context.tr('problem_description_hint'),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.all(12),
              hintStyle: const TextStyle(
                fontSize: 12,
                color: AppColors.gray400,
              ),
            ),
          ),
        ),

        if (_showError)
          Padding(
            padding: const EdgeInsets.only(top: 16),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
              ),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: Colors.red, size: 20),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      context.tr('fill_all_fields_error'),
                      style: TextStyle(color: Colors.red, fontSize: 11),
                    ),
                  ),
                ],
              ),
            ).animate().shake(),
          ),
      ],
    );
  }
}
