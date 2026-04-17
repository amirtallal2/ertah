// Best Offers Screen
// شاشة أفضل العروض

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import '../models/offer_model.dart';
import '../services/offers_service.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';
import '../providers/location_provider.dart';
import '../utils/saudi_riyal_icon.dart';

class BestOffersScreen extends StatefulWidget {
  const BestOffersScreen({super.key});

  @override
  State<BestOffersScreen> createState() => _BestOffersScreenState();
}

class _BestOffersScreenState extends State<BestOffersScreen> {
  final OffersService _offersService = OffersService();
  static const List<Shadow> _headerTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];
  bool _isLoading = true;
  List<OfferModel> _offers = [];
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchOffers();
  }

  String _getOfferTitle(OfferModel offer) {
    if (Localizations.localeOf(context).languageCode == 'ar') {
      return offer.titleAr;
    }
    return offer.titleEn ?? offer.titleAr;
  }

  String _getOfferDesc(OfferModel offer) {
    if (Localizations.localeOf(context).languageCode == 'ar') {
      return offer.descriptionAr ?? '';
    }
    return offer.descriptionEn ?? offer.descriptionAr ?? '';
  }

  String _getDiscountText(OfferModel offer) {
    if (offer.discountType == 'percentage') {
      return '${offer.discountValue.toInt()}%';
    }
    return '${offer.discountValue.toInt()}';
  }

  Future<void> _fetchOffers() async {
    try {
      final location = context.read<LocationProvider>();
      final response = await _offersService.getOffers(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
      );
      if (mounted) {
        if (response.success && response.data != null) {
          final List<dynamic> data = response.data;
          setState(() {
            _offers = data.map((e) => OfferModel.fromJson(e)).toList();
            _isLoading = false;
          });
        } else {
          setState(() {
            _error = response.message ?? context.tr('offers_load_failed');
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = context.tr('connection_error');
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.fromLTRB(16, 48, 16, 20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
              ),
              boxShadow: AppShadows.md,
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
                        context.tr('best_offers_for_you'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          shadows: _headerTextShadows,
                        ),
                      ),
                      Text(
                        context.tr('exclusive_discounts'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          shadows: _headerTextShadows,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Content
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.error_outline,
                          size: 48,
                          color: Colors.grey,
                        ),
                        const SizedBox(height: 16),
                        Text(_error!),
                        TextButton(
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
                  )
                : _offers.isEmpty
                ? Center(child: Text(context.tr('no_offers_currently')))
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _offers.length,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 16),
                    itemBuilder: (context, index) {
                      final offer = _offers[index];
                      return Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: AppShadows.sm,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Image
                                ClipRRect(
                                  borderRadius: const BorderRadius.vertical(
                                    top: Radius.circular(16),
                                  ),
                                  child: SizedBox(
                                    height: 150,
                                    width: double.infinity,
                                    child: offer.image != null
                                        ? CachedNetworkImage(
                                            imageUrl: AppConfig.fixMediaUrl(
                                              offer.image,
                                            ),
                                            fit: BoxFit.cover,
                                            placeholder: (context, url) =>
                                                Container(
                                                  color: Colors.grey[200],
                                                ),
                                            errorWidget:
                                                (
                                                  context,
                                                  url,
                                                  error,
                                                ) => Container(
                                                  color: Colors.grey[200],
                                                  child: const Icon(
                                                    Icons.image_not_supported,
                                                  ),
                                                ),
                                          )
                                        : Container(
                                            color: Colors.grey[200],
                                            child: const Icon(
                                              Icons.image,
                                              size: 50,
                                              color: Colors.grey,
                                            ),
                                          ),
                                  ),
                                ),

                                // Details
                                Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Text(
                                              _getOfferTitle(offer),
                                              style: const TextStyle(
                                                fontSize: 16,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 8,
                                              vertical: 4,
                                            ),
                                            decoration: BoxDecoration(
                                              color: const Color(
                                                0xFFFBCC26,
                                              ).withValues(alpha: 0.1),
                                              borderRadius:
                                                  BorderRadius.circular(8),
                                            ),
                                            child:
                                                offer.discountType ==
                                                    'percentage'
                                                ? Text(
                                                    '${context.tr('discount')} ${_getDiscountText(offer)}',
                                                    style: const TextStyle(
                                                      color: Color(0xFFFBCC26),
                                                      fontWeight:
                                                          FontWeight.bold,
                                                      fontSize: 12,
                                                    ),
                                                  )
                                                : SaudiRiyalText(
                                                    text:
                                                        '${context.tr('discount')} ${_getDiscountText(offer)}',
                                                    style: const TextStyle(
                                                      color: Color(0xFFFBCC26),
                                                      fontWeight:
                                                          FontWeight.bold,
                                                      fontSize: 12,
                                                    ),
                                                    iconSize: 12,
                                                  ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        _getOfferDesc(offer),
                                        style: const TextStyle(
                                          fontSize: 14,
                                          color: Colors.grey,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 12),
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.calendar_today,
                                            size: 14,
                                            color: Colors.grey,
                                          ),
                                          const SizedBox(width: 4),
                                          Text(
                                            '${context.tr('expires_on')}: ${offer.endDate.toLocal().toString().split(' ')[0]}',
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: Colors.grey,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          )
                          .animate()
                          .fadeIn(delay: (100 * index).ms)
                          .slideY(begin: 0.1, end: 0);
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
