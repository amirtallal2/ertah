// Service Rating Screen
// شاشة تقييم الخدمة

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/service_category_model.dart';
import '../services/app_localizations.dart';
import '../services/orders_service.dart';

class ServiceRatingScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  // Using simplified provider/order data for now
  final String providerName;
  final String orderNumber;
  final VoidCallback onSubmit;

  const ServiceRatingScreen({
    super.key,
    required this.service,
    required this.providerName,
    required this.orderNumber,
    required this.onSubmit,
  });

  @override
  State<ServiceRatingScreen> createState() => _ServiceRatingScreenState();
}

class _ServiceRatingScreenState extends State<ServiceRatingScreen> {
  int _overallRating = 0;
  int _qualityRating = 0;
  int _speedRating = 0;
  int _priceRating = 0;
  int _behaviorRating = 0;
  final TextEditingController _commentController = TextEditingController();
  final List<String> _selectedTags = [];

  final List<String> _suggestedTags = [
    "tag_professional",
    "tag_fast",
    "tag_excellent_prices",
    "tag_high_service",
    "tag_clean",
    "tag_punctual",
    "tag_communication",
    "tag_recommended",
  ];

  Future<void> _handleSubmit() async {
    if (_overallRating == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('select_overall_rating'))),
      );
      return;
    }

    try {
      final res = await OrdersService().rateOrder(
        orderId: int.parse(widget.orderNumber),
        rating: _overallRating,
        comment: _commentController.text,
      );

      if (!res.success) {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(res.message ?? 'Unknown error')));
        return;
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('rating_submit_failed'))),
      );
      return;
    }

    if (!mounted) return;

    // Show success dialog
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  color: Colors.green,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.check, color: Colors.white, size: 32),
              ).animate().scale(),
              const SizedBox(height: 16),
              Text(
                context.tr('thank_you'),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                context.tr('rating_sent'),
                style: const TextStyle(color: AppColors.gray600, fontSize: 13),
              ),
            ],
          ),
        ),
      ),
    );

    Future.delayed(const Duration(seconds: 1, milliseconds: 500), () {
      if (!mounted) return;
      Navigator.pop(context); // Close dialog
      widget.onSubmit();
    });
  }

  void _toggleTag(String tagKey) {
    setState(() {
      if (_selectedTags.contains(tagKey)) {
        _selectedTags.remove(tagKey);
      } else {
        _selectedTags.add(tagKey);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 100),
            child: Column(
              children: [
                _buildHeader(),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      // Service Summary
                      _buildServiceSummary(),
                      const SizedBox(height: 16),

                      // Overall Rating
                      _buildOverallRating(),
                      const SizedBox(height: 16),

                      // Detailed Ratings (Animated)
                      if (_overallRating > 0) ...[
                        _buildDetailedRatings().animate().fadeIn().slideY(
                          begin: 0.1,
                          end: 0,
                        ),
                        const SizedBox(height: 16),
                        _buildTagsSection().animate().fadeIn(delay: 100.ms),
                        const SizedBox(height: 16),
                        _buildCommentSection().animate().fadeIn(delay: 200.ms),
                        const SizedBox(height: 16),
                        // Tips
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.blue.withValues(alpha: 0.05),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: Colors.blue.withValues(alpha: 0.2),
                            ),
                          ),
                          child: Row(
                            children: [
                              const Text('💡', style: TextStyle(fontSize: 20)),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      context.tr('rating_important'),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 12,
                                        color: Colors.blue,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      context.tr('rating_help_improve'),
                                      style: TextStyle(
                                        fontSize: 11,
                                        color: Colors.blue.withValues(
                                          alpha: 0.8,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ).animate().fadeIn(delay: 300.ms),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Submit Button
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -5),
                  ),
                ],
              ),
              child: SafeArea(
                top: false,
                child: SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: ElevatedButton(
                    onPressed: _overallRating > 0 ? _handleSubmit : null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFBCC26),
                      foregroundColor: Colors.white,
                      disabledBackgroundColor: Colors.grey[300],
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      elevation: _overallRating > 0 ? 4 : 0,
                    ),
                    child: Text(
                      context.tr('submit_rating'),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 50, 16, 20),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(24),
          bottomRight: Radius.circular(24),
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
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('service_rating'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  '#${widget.orderNumber}',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildServiceSummary() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.sm,
      ),
      child: Row(
        children: [
          Container(
            width: 60,
            height: 60,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            // Simple check for if image is URL or asset if needed, usually image is URL here
            child:
                widget.service.image != null && widget.service.image!.isNotEmpty
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: CachedNetworkImage(
                      imageUrl: AppConfig.fixMediaUrl(widget.service.image),
                      width: 60,
                      height: 60,
                      fit: BoxFit.cover,
                      errorWidget: (_, __, ___) =>
                          const Icon(Icons.broken_image, color: Colors.white),
                    ),
                  )
                : Text(
                    widget.service.icon ?? '',
                    style: const TextStyle(fontSize: 28),
                  ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.service.nameAr,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${context.tr('provided_by')} ${widget.providerName}',
                  style: const TextStyle(
                    color: AppColors.gray500,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOverallRating() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Text(
            context.tr('how_was_experience'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          Text(
            context.tr('rate_experience'),
            style: const TextStyle(color: AppColors.gray500, fontSize: 12),
          ),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              final star = index + 1;
              return GestureDetector(
                    onTap: () => setState(() => _overallRating = star),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: Icon(
                        Icons.star_rounded,
                        size: 48,
                        color: star <= _overallRating
                            ? Colors.amber
                            : Colors.grey[200],
                      ),
                    ),
                  )
                  .animate(target: star <= _overallRating ? 1 : 0)
                  .scale(
                    begin: const Offset(1, 1),
                    end: const Offset(1.2, 1.2),
                  );
            }),
          ),
          if (_overallRating > 0)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Text(
                _getRatingLabel(_overallRating),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: AppColors.gray700,
                ),
              ).animate().fadeIn(),
            ),
        ],
      ),
    );
  }

  String _getRatingLabel(int rating) {
    switch (rating) {
      case 5:
        return context.tr('rating_excellent');
      case 4:
        return context.tr('rating_very_good');
      case 3:
        return context.tr('rating_good');
      case 2:
        return context.tr('rating_fair');
      case 1:
        return context.tr('rating_poor');
      default:
        return "";
    }
  }

  Widget _buildDetailedRatings() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('detailed_rating'),
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          _buildRatingRow(
            context.tr('quality_service'),
            Icons.thumb_up,
            Colors.blue,
            _qualityRating,
            (v) => setState(() => _qualityRating = v),
          ),
          const Divider(height: 24),
          _buildRatingRow(
            context.tr('speed_commitment'),
            Icons.access_time,
            Colors.green,
            _speedRating,
            (v) => setState(() => _speedRating = v),
          ),
          const Divider(height: 24),
          _buildRatingRow(
            context.tr('price_appropriateness'),
            Icons.attach_money,
            Colors.orange,
            _priceRating,
            (v) => setState(() => _priceRating = v),
          ),
          const Divider(height: 24),
          _buildRatingRow(
            context.tr('professionalism'),
            Icons.people,
            Colors.purple,
            _behaviorRating,
            (v) => setState(() => _behaviorRating = v),
          ),
        ],
      ),
    );
  }

  Widget _buildRatingRow(
    String label,
    IconData icon,
    Color color,
    int rating,
    Function(int) onRate,
  ) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: color, size: 16),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
          ),
        ),
        Row(
          children: List.generate(5, (index) {
            final star = index + 1;
            return GestureDetector(
              onTap: () => onRate(star),
              child: Icon(
                Icons.star_rounded,
                size: 20,
                color: star <= rating ? Colors.amber : Colors.grey[200],
              ),
            );
          }),
        ),
      ],
    );
  }

  Widget _buildTagsSection() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('add_quick_tags'),
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _suggestedTags.map((tagKey) {
              final isSelected = _selectedTags.contains(tagKey);
              return GestureDetector(
                onTap: () => _toggleTag(tagKey),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: isSelected
                        ? const Color(0xFFFBCC26)
                        : AppColors.gray100,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: isSelected
                        ? [
                            BoxShadow(
                              color: const Color(
                                0xFFFBCC26,
                              ).withValues(alpha: 0.4),
                              blurRadius: 4,
                            ),
                          ]
                        : [],
                  ),
                  child: Text(
                    context.tr(tagKey),
                    style: TextStyle(
                      fontSize: 12,
                      color: isSelected ? Colors.white : AppColors.gray600,
                      fontWeight: isSelected
                          ? FontWeight.bold
                          : FontWeight.normal,
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildCommentSection() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('additional_comment'),
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              color: AppColors.gray50,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.gray200),
            ),
            child: TextField(
              controller: _commentController,
              maxLines: 4,
              maxLength: 500,
              decoration: InputDecoration(
                hintText: context.tr('comment_hint'),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.all(16),
                hintStyle: const TextStyle(
                  fontSize: 12,
                  color: AppColors.gray400,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
