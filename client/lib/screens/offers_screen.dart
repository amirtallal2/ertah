// Offers Screen
// شاشة العروض

import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import '../config/app_config.dart';
import '../providers/location_provider.dart';
import '../utils/saudi_riyal_icon.dart';

import '../services/app_localizations.dart';

class OffersScreen extends StatefulWidget {
  final VoidCallback? onViewAllOffers;

  const OffersScreen({super.key, this.onViewAllOffers});

  @override
  State<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends State<OffersScreen> {
  final OffersService _offersService = OffersService();
  static const List<Shadow> _headerTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];
  bool _isLoading = true;
  String? _error;
  List<dynamic> _offers = [];

  @override
  void initState() {
    super.initState();
    _fetchOffers();
  }

  Future<void> _fetchOffers() async {
    try {
      final location = context.read<LocationProvider>();
      final response = await _offersService.getOffers(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
      );
      if (response.success) {
        setState(() {
          _offers = response.data;
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.message;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = context.tr('offers_load_failed');
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: AppColors.gray50,
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null) {
      return Scaffold(
        backgroundColor: AppColors.gray50,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.orange),
              const SizedBox(height: 16),
              Text(_error!),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () {
                  setState(() {
                    _isLoading = true;
                    _error = null;
                  });
                  _fetchOffers();
                },
                child: Text(context.tr('try_again')),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _fetchOffers,
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 100, top: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Hero Banner
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Container(
                    height: 128,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.md,
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Stack(
                      children: [
                        Positioned.fill(
                          child: Container(
                            color: Colors.black.withValues(alpha: 0.1),
                          ),
                        ),

                        // Decorative circles
                        Positioned(
                          top: -32,
                          left: -32,
                          child: Container(
                            width: 128,
                            height: 128,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                        Positioned(
                          bottom: -32,
                          right: -32,
                          child: Container(
                            width: 160,
                            height: 160,
                            decoration: BoxDecoration(
                              color: Colors.yellow[300]!.withValues(alpha: 0.2),
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),

                        // Content
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  context.tr('special_offers'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 10,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                context.tr('save_up_to_50'),
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                  shadows: _headerTextShadows,
                                ),
                              ),
                              Text(
                                context.tr('on_all_services'),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                  shadows: _headerTextShadows,
                                ),
                              ),
                            ],
                          ),
                        ).animate().fadeIn().slideX(begin: 0.1, end: 0),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 24),

                // Offers List Header
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        context.tr('exclusive_offers'),
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppColors.gray800,
                        ),
                      ),
                      TextButton(
                        onPressed: widget.onViewAllOffers,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: Text(
                          context.tr('view_all'),
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF7466ED),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 12),

                // Offers List
                _offers.isEmpty
                    ? Center(child: Text(context.tr('no_offers_currently')))
                    : ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        physics: const NeverScrollableScrollPhysics(),
                        shrinkWrap: true,
                        itemCount: _offers.length,
                        separatorBuilder: (context, index) =>
                            const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final offer = _offers[index];
                          final isPercentage =
                              offer['discount_type'] == 'percentage';
                          final discountValue = isPercentage
                              ? '${offer['discount_value']}%'
                              : '${offer['discount_value']}';
                          final locale = Localizations.localeOf(
                            context,
                          ).languageCode;
                          final titleAr = (offer['title_ar'] ?? '')
                              .toString()
                              .trim();
                          final titleEn = (offer['title_en'] ?? '')
                              .toString()
                              .trim();
                          final titleUr = (offer['title_ur'] ?? '')
                              .toString()
                              .trim();
                          final descriptionAr = (offer['description_ar'] ?? '')
                              .toString()
                              .trim();
                          final descriptionEn = (offer['description_en'] ?? '')
                              .toString()
                              .trim();
                          final descriptionUr = (offer['description_ur'] ?? '')
                              .toString()
                              .trim();
                          final promoCode =
                              (offer['promo_code'] ?? offer['code'] ?? '')
                                  .toString()
                                  .trim()
                                  .toUpperCase();
                          final localizedTitle = locale == 'ar'
                              ? (titleAr.isNotEmpty
                                    ? titleAr
                                    : (titleEn.isNotEmpty ? titleEn : titleUr))
                              : locale == 'ur'
                              ? (titleUr.isNotEmpty
                                    ? titleUr
                                    : (titleEn.isNotEmpty ? titleEn : titleAr))
                              : (titleEn.isNotEmpty
                                    ? titleEn
                                    : (titleAr.isNotEmpty ? titleAr : titleUr));
                          final localizedDescription = locale == 'ar'
                              ? (descriptionAr.isNotEmpty
                                    ? descriptionAr
                                    : (descriptionEn.isNotEmpty
                                          ? descriptionEn
                                          : descriptionUr))
                              : locale == 'ur'
                              ? (descriptionUr.isNotEmpty
                                    ? descriptionUr
                                    : (descriptionEn.isNotEmpty
                                          ? descriptionEn
                                          : descriptionAr))
                              : (descriptionEn.isNotEmpty
                                    ? descriptionEn
                                    : (descriptionAr.isNotEmpty
                                          ? descriptionAr
                                          : descriptionUr));

                          return Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: AppShadows.sm,
                            ),
                            clipBehavior: Clip.antiAlias,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Image
                                Stack(
                                  children: [
                                    SizedBox(
                                      height: 112,
                                      width: double.infinity,
                                      child: offer['image'] != null
                                          ? CachedNetworkImage(
                                              imageUrl: AppConfig.fixMediaUrl(
                                                offer['image'],
                                              ),
                                              fit: BoxFit.cover,
                                              placeholder: (_, __) => Container(
                                                color: Colors.grey[200],
                                              ),
                                            )
                                          : Container(
                                              color: Colors.grey[200],
                                              child: const Icon(
                                                Icons.local_offer,
                                              ),
                                            ),
                                    ),
                                    // Discount Badge
                                    Positioned(
                                      top: 8,
                                      right: 8,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 4,
                                        ),
                                        decoration: BoxDecoration(
                                          color: Colors.red,
                                          borderRadius: BorderRadius.circular(
                                            12,
                                          ),
                                          boxShadow: const [
                                            BoxShadow(
                                              color: Colors.black26,
                                              blurRadius: 4,
                                            ),
                                          ],
                                        ),
                                        child: isPercentage
                                            ? Text(
                                                '${context.tr('discount')} $discountValue',
                                                style: const TextStyle(
                                                  color: Colors.white,
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.bold,
                                                ),
                                              )
                                            : SaudiRiyalText(
                                                text:
                                                    '${context.tr('discount')} $discountValue',
                                                style: const TextStyle(
                                                  color: Colors.white,
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.bold,
                                                ),
                                                iconSize: 10,
                                              ),
                                      ),
                                    ),
                                    // Gradient
                                    Positioned(
                                      bottom: 0,
                                      left: 0,
                                      right: 0,
                                      height: 56,
                                      child: Container(
                                        decoration: BoxDecoration(
                                          gradient: LinearGradient(
                                            begin: Alignment.bottomCenter,
                                            end: Alignment.topCenter,
                                            colors: [
                                              Colors.black.withValues(
                                                alpha: 0.4,
                                              ),
                                              Colors.transparent,
                                            ],
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),

                                // Content
                                Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        localizedTitle.isNotEmpty
                                            ? localizedTitle
                                            : context.tr('special_offer'),
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 13,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        localizedDescription,
                                        style: const TextStyle(
                                          color: AppColors.gray500,
                                          fontSize: 11,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 12),
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: [
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                children: [
                                                  const Icon(
                                                    Icons.access_time,
                                                    size: 12,
                                                    color: AppColors.gray400,
                                                  ),
                                                  const SizedBox(width: 4),
                                                  Text(
                                                    '${context.tr('valid_until')} ${offer['end_date']}',
                                                    style: const TextStyle(
                                                      fontSize: 10,
                                                      color: AppColors.gray400,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ),
                                          ElevatedButton.icon(
                                            onPressed: promoCode.isEmpty
                                                ? null
                                                : () {
                                                    Clipboard.setData(
                                                      ClipboardData(
                                                        text: promoCode,
                                                      ),
                                                    );
                                                    if (!mounted) return;
                                                    ScaffoldMessenger.of(
                                                      context,
                                                    ).showSnackBar(
                                                      SnackBar(
                                                        content: Text(
                                                          context.tr(
                                                            'promo_code_copied',
                                                          ),
                                                        ),
                                                      ),
                                                    );
                                                  },
                                            icon: const Icon(
                                              Icons.copy,
                                              size: 14,
                                            ),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: const Color(
                                                0xFF7466ED,
                                              ),
                                              foregroundColor: Colors.white,
                                              elevation: 2,
                                              shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(20),
                                              ),
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    horizontal: 14,
                                                    vertical: 0,
                                                  ),
                                              minimumSize: const Size(0, 32),
                                            ),
                                            label: Text(
                                              promoCode.isNotEmpty
                                                  ? promoCode
                                                  : context.tr(
                                                      'promo_code_not_available',
                                                    ),
                                              style: const TextStyle(
                                                fontSize: 12,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ).animate().fadeIn(delay: (100 * index).ms).slideY(begin: 0.1, end: 0);
                        },
                      ),

                const SizedBox(height: 24),

                // Special Note
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFBCC26).withValues(alpha: 0.05),
                      border: Border.all(
                        color: const Color(0xFFFBCC26).withValues(alpha: 0.4),
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      context.tr('limited_offers_note'),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.gray800,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
