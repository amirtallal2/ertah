// Special Offer Screen
// شاشة العرض الخاص

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/offers_service.dart';
import '../utils/saudi_riyal_icon.dart';

class SpecialOfferScreen extends StatefulWidget {
  final VoidCallback onBack;
  final int? offerId;

  const SpecialOfferScreen({super.key, required this.onBack, this.offerId});

  @override
  State<SpecialOfferScreen> createState() => _SpecialOfferScreenState();
}

class _SpecialOfferScreenState extends State<SpecialOfferScreen> {
  final OffersService _offersService = OffersService();
  bool _isLoading = true;
  String? _error;
  Map<String, dynamic>? _offer;

  @override
  void initState() {
    super.initState();
    _loadOffer();
  }

  Future<void> _loadOffer() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final location = context.read<LocationProvider>();
      if (widget.offerId != null && widget.offerId! > 0) {
        final response = await _offersService.getOfferDetail(
          widget.offerId!,
          lat: location.requestLat,
          lng: location.requestLng,
          countryCode: location.requestCountryCode,
        );
        if (!mounted) return;
        if (response.success && response.data is Map) {
          setState(() {
            _offer = Map<String, dynamic>.from(
              (response.data as Map).map(
                (key, value) => MapEntry(key.toString(), value),
              ),
            );
            _isLoading = false;
          });
          return;
        }
      } else {
        final response = await _offersService.getOffers(
          lat: location.requestLat,
          lng: location.requestLng,
          countryCode: location.requestCountryCode,
        );
        if (!mounted) return;
        if (response.success && response.data is List) {
          final rows = (response.data as List).whereType<Map>().toList();
          if (rows.isNotEmpty) {
            setState(() {
              _offer = Map<String, dynamic>.from(
                rows.first.map((k, v) => MapEntry(k.toString(), v)),
              );
              _isLoading = false;
            });
            return;
          }
        }
      }

      setState(() {
        _isLoading = false;
        _error = context.tr('no_offers_currently');
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = context.tr('offers_load_failed');
      });
    }
  }

  String _localizedText(String arKey, String enKey, [String? urKey]) {
    final ar = (_offer?[arKey] ?? '').toString().trim();
    final en = (_offer?[enKey] ?? '').toString().trim();
    final ur = urKey == null ? '' : (_offer?[urKey] ?? '').toString().trim();
    final lang = Localizations.localeOf(context).languageCode;
    if (lang == 'ar') return ar.isNotEmpty ? ar : en;
    if (lang == 'ur') return ur.isNotEmpty ? ur : (en.isNotEmpty ? en : ar);
    return en.isNotEmpty ? en : (ar.isNotEmpty ? ar : ur);
  }

  double _toDouble(dynamic value) {
    if (value is double) return value;
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _discountText() {
    final type = (_offer?['discount_type'] ?? 'percentage').toString();
    final value = _toDouble(_offer?['discount_value']);
    if (type == 'percentage') {
      return '${value.toInt()}%';
    }
    return '${value.toInt()}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(
              top: MediaQuery.of(context).padding.top + 12,
              left: 12,
              right: 12,
              bottom: 12,
            ),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: AppShadows.sm,
            ),
            child: Row(
              children: [
                InkWell(
                  onTap: widget.onBack,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: AppColors.gray100,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.arrow_forward,
                      color: AppColors.gray700,
                      size: 18,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  context.tr('special_offer'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                ? _buildError()
                : _buildOffer(),
          ),
        ],
      ),
    );
  }

  Widget _buildOffer() {
    final title = _localizedText('title_ar', 'title_en', 'title_ur');
    final description = _localizedText(
      'description_ar',
      'description_en',
      'description_ur',
    );
    final image = AppConfig.fixMediaUrl(_offer?['image']?.toString());
    final endDate = (_offer?['end_date'] ?? '').toString();
    final minAmount = _toDouble(_offer?['min_order_amount']);

    return SingleChildScrollView(
      child: Column(
        children: [
          Stack(
            children: [
              CachedNetworkImage(
                imageUrl: image,
                height: 220,
                width: double.infinity,
                fit: BoxFit.cover,
                errorWidget: (_, __, ___) => Container(
                  height: 220,
                  color: AppColors.gray200,
                  alignment: Alignment.center,
                  child: const Icon(Icons.image_not_supported),
                ),
              ),
              Container(
                height: 220,
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [Colors.black54, Colors.transparent],
                  ),
                ),
              ),
              Positioned(
                top: 16,
                left: 16,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.red,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child:
                      (_offer?['discount_type'] ?? 'percentage') == 'percentage'
                      ? Text(
                          '${context.tr('discount')} ${_discountText()}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        )
                      : SaudiRiyalText(
                          text: '${context.tr('discount')} ${_discountText()}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                          iconSize: 13,
                        ),
                ).animate().scale(curve: Curves.easeOutBack),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  description,
                  style: const TextStyle(
                    color: AppColors.gray700,
                    height: 1.5,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 16),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: AppShadows.sm,
                  ),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          const Icon(
                            Icons.calendar_today,
                            size: 16,
                            color: Colors.orange,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              endDate.isEmpty
                                  ? context.tr('limited_offers_note')
                                  : '${context.tr('expires_on')}: $endDate',
                              style: const TextStyle(fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                      if (minAmount > 0) ...[
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            const Icon(
                              Icons.shopping_basket,
                              size: 16,
                              color: Colors.indigo,
                            ),
                            const SizedBox(width: 8),
                            SaudiRiyalText(
                              text:
                                  '${context.tr('minimum_order_amount')}: ${minAmount.toInt()}',
                              style: const TextStyle(fontSize: 13),
                              iconSize: 13,
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ).animate().fadeIn(delay: 120.ms).slideY(begin: 0.05),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.local_offer_outlined, size: 52, color: Colors.grey),
          const SizedBox(height: 12),
          Text(_error ?? context.tr('connection_error')),
          const SizedBox(height: 8),
          TextButton(
            onPressed: _loadOffer,
            child: Text(context.tr('try_again')),
          ),
        ],
      ),
    );
  }
}
