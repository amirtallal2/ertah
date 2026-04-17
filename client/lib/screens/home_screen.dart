// Home Screen
// الشاشة الرئيسية

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:async';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../models/models.dart';
import '../services/services.dart';
import '../providers/auth_provider.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';
import '../services/popup_banner_policy_service.dart';
import '../providers/location_provider.dart';
import '../utils/saudi_riyal_icon.dart';
import '../widgets/app_logo.dart';
import 'location_picker_screen.dart';
import 'best_offers_screen.dart';
import 'service_selection_screen.dart';
import 'service_request_screen.dart';
import 'all_spares_screen.dart';

class HomeScreen extends StatefulWidget {
  final Function(ServiceCategoryModel)? onServiceClick;
  final Function(StoreModel)?
  onStoreClick; // Using StoreModel for spare parts too for now, or just ID
  final VoidCallback? onWalletClick;
  final VoidCallback? onViewAllServices;
  final VoidCallback? onViewAllStores;
  final VoidCallback? onBannerClick;
  final VoidCallback? onViewMostRequested;
  final VoidCallback? onProfileClick;
  final VoidCallback? onViewAllSpares;

  const HomeScreen({
    super.key,
    this.onServiceClick,
    this.onStoreClick,
    this.onWalletClick,
    this.onViewAllServices,
    this.onViewAllStores,
    this.onBannerClick,
    this.onViewMostRequested,
    this.onProfileClick,
    this.onViewAllSpares,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final PageController _bannerController = PageController();
  final HomeService _homeService = HomeService();
  final PopupBannerPolicyService _popupBannerPolicy =
      PopupBannerPolicyService();
  LocationProvider? _locationProvider;

  int _currentBanner = 0;
  Timer? _bannerTimer;
  bool _showPopupBanner = false;
  bool _isLoading = true;
  String? _error;

  Map<String, dynamic>? _homeData;
  static const List<Shadow> _topBarTextShadows = <Shadow>[
    Shadow(color: Color(0x66000000), offset: Offset(0, 1), blurRadius: 2),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _fetchHomeData();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final provider = context.read<LocationProvider>();
    if (_locationProvider == provider) {
      return;
    }
    _locationProvider?.removeListener(_onLocationChanged);
    _locationProvider = provider;
    _locationProvider?.addListener(_onLocationChanged);
  }

  Map<String, dynamic>? _mapFromDynamic(dynamic raw) {
    if (raw is! Map) return null;
    return Map<String, dynamic>.from(
      raw.map((key, value) => MapEntry(key.toString(), value)),
    );
  }

  List<Map<String, dynamic>> _mapList(dynamic raw) {
    if (raw is! List) return <Map<String, dynamic>>[];
    return raw
        .map((item) => _mapFromDynamic(item))
        .whereType<Map<String, dynamic>>()
        .toList();
  }

  Future<List<Map<String, dynamic>>> _appendMissingSpecialCategories(
    List<Map<String, dynamic>> categories, {
    required bool allowOutside,
  }) async {
    final modules = categories
        .map((item) => (item['special_module'] ?? '').toString().trim())
        .where((item) => item.isNotEmpty)
        .toSet();
    final result = <Map<String, dynamic>>[...categories];

    Future<void> addIfMissing(int id, String moduleKey) async {
      if (modules.contains(moduleKey) ||
          result.any((item) => _toInt(item['id']) == id)) {
        return;
      }

      final location = context.read<LocationProvider>();
      final detail = await _homeService.getCategoryDetail(
        id,
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (!detail.success) return;

      final data = _mapFromDynamic(detail.data);
      if (data == null) return;

      final module = (data['special_module'] ?? '').toString().trim();
      if (module != moduleKey) return;

      result.add(data);
      modules.add(moduleKey);
    }

    await addIfMissing(-101, 'furniture_moving');
    await addIfMissing(-102, 'container_rental');

    return result;
  }

  Future<void> _fetchHomeData() async {
    try {
      final location = context.read<LocationProvider>();
      final allowOutside =
          context.read<AuthProvider>().isGuest || !location.hasSelectedLocation;
      final response = await _homeService.getHomeData(
        lat: location.requestLat,
        lng: location.requestLng,
        countryCode: location.requestCountryCode,
        allowOutside: allowOutside,
      );
      if (!mounted) return;

      if (response.success && response.data != null) {
        final data = _mapFromDynamic(response.data) ?? <String, dynamic>{};
        final categoriesResponse = await _homeService.getCategories(
          lat: location.requestLat,
          lng: location.requestLng,
          countryCode: location.requestCountryCode,
          allowOutside: allowOutside,
        );
        final treeCategories = _mapList(categoriesResponse.data);

        if (categoriesResponse.success && treeCategories.isNotEmpty) {
          data['categories'] = treeCategories;
        } else {
          final categories = _mapList(data['categories']);
          if (categories.isNotEmpty) {
            data['categories'] = await _appendMissingSpecialCategories(
              categories,
              allowOutside: allowOutside,
            );
          }
        }

        if (!mounted) return;
        setState(() {
          _homeData = data;
          _currentBanner = 0;
          _showPopupBanner = false;
          _isLoading = false;
        });
        _startBannerTimer();
        await _evaluatePopupBannerVisibility();
      } else {
        setState(() {
          _error = response.message;
          _showPopupBanner = false;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = context.tr('error_loading_data');
          _showPopupBanner = false;
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _evaluatePopupBannerVisibility() async {
    final banner = _mapFromDynamic(_homeData?['popup_banner']);
    if (banner == null || banner.isEmpty) {
      if (!mounted) return;
      setState(() => _showPopupBanner = false);
      return;
    }

    final shouldShow = await _popupBannerPolicy.shouldShowBanner(banner);
    if (!mounted) return;

    if (shouldShow) {
      await _popupBannerPolicy.markBannerShown(banner);
    }

    if (!mounted) return;
    setState(() {
      _showPopupBanner = shouldShow;
    });
  }

  Future<void> _closePopupBanner(Map<String, dynamic> banner) async {
    await _popupBannerPolicy.markBannerDismissed(banner);
    if (!mounted) return;
    setState(() => _showPopupBanner = false);
  }

  String _getLoc(Map<String, dynamic> item, String key) {
    final lang = Localizations.localeOf(context).languageCode;
    if (lang == 'ar') {
      return item['${key}_ar']?.toString() ?? '';
    }
    return item['${key}_en']?.toString() ?? item['${key}_ar']?.toString() ?? '';
  }

  void _startBannerTimer() {
    _bannerTimer?.cancel();
    if (_homeData == null) return;
    final banners = _homeData!['banners'] as List? ?? [];
    if (banners.length <= 1) return;

    if (_bannerController.hasClients) {
      _bannerController.jumpToPage(0);
    }
    _bannerTimer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (_bannerController.hasClients && _homeData != null) {
        final banners = _homeData!['banners'] as List;
        if (banners.isNotEmpty) {
          final nextPage = (_currentBanner + 1) % banners.length;
          _bannerController.animateToPage(
            nextPage,
            duration: const Duration(milliseconds: 500),
            curve: Curves.easeInOut,
          );
        }
      }
    });
  }

  @override
  void dispose() {
    _locationProvider?.removeListener(_onLocationChanged);
    _bannerTimer?.cancel();
    _bannerController.dispose();
    super.dispose();
  }

  void _onLocationChanged() {
    if (!mounted) return;
    _fetchHomeData();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_error != null) {
      return Scaffold(
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
                  _fetchHomeData();
                },
                child: Text(context.tr('retry')),
              ),
            ],
          ),
        ),
      );
    }

    return Stack(
      children: [
        Scaffold(
          backgroundColor: AppColors.gray50,
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              onRefresh: _fetchHomeData,
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 100),
                child: Column(
                  children: [
                    // Top Section (Header + Banners) with dynamic background
                    _buildTopSection(),
                    _buildServiceAvailabilityNotice(),
                    ..._buildOrderedSections(),
                  ],
                ),
              ),
            ),
          ),
        ),

        // Popup Banner Overlay
        if (_showPopupBanner && _homeData?['popup_banner'] != null)
          _buildPopupBanner(),
      ],
    );
  }

  List<Widget> _buildOrderedSections() {
    final sectionDefinitions = <Map<String, dynamic>>[
      {
        'key': 'how_it_works',
        'defaultOrder': 1,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildHowItWorks(),
        ),
      },
      {
        'key': 'services',
        'defaultOrder': 2,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildServicesGrid(),
        ),
      },
      {
        'key': 'most_requested_services',
        'defaultOrder': 3,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildMostRequestedServices(),
        ),
      },
      {
        'key': 'ad_banner',
        'defaultOrder': 4,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: _buildAdBanner(),
        ),
      },
      {
        'key': 'spare_parts',
        'defaultOrder': 5,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildSpareParts(),
        ),
      },
      {
        'key': 'stores',
        'defaultOrder': 6,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildStores(),
        ),
      },
      {
        'key': 'offers',
        'defaultOrder': 7,
        'widget': Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: _buildOffers(),
        ),
      },
    ];

    final visibleSections = <Map<String, dynamic>>[];
    for (final definition in sectionDefinitions) {
      final key = definition['key'] as String;
      final defaultOrder = definition['defaultOrder'] as int;
      if (!_isSectionVisible(key)) {
        continue;
      }
      visibleSections.add({
        'order': _getSectionOrder(key, defaultOrder),
        'widget': definition['widget'],
      });
    }

    visibleSections.sort(
      (a, b) => (a['order'] as int).compareTo(b['order'] as int),
    );

    final widgets = <Widget>[const SizedBox(height: 12)];
    for (final section in visibleSections) {
      widgets.add(section['widget'] as Widget);
      widgets.add(const SizedBox(height: 24));
    }
    return widgets;
  }

  bool _isSectionVisible(String key) {
    final config = _getSectionConfig(key);
    if (config == null) return true;
    return _parseBool(config['visible'], defaultValue: true);
  }

  int _getSectionOrder(String key, int defaultOrder) {
    final config = _getSectionConfig(key);
    if (config == null) return defaultOrder;

    final raw = config['order'];
    if (raw is num) {
      final order = raw.toInt();
      return order > 0 ? order : defaultOrder;
    }

    final parsed = int.tryParse(raw?.toString() ?? '');
    if (parsed == null || parsed <= 0) {
      return defaultOrder;
    }
    return parsed;
  }

  Map<String, dynamic>? _getSectionConfig(String key) {
    final sections = _homeData?['home_sections'];
    if (sections is! Map || sections[key] is! Map) {
      return null;
    }

    final raw = sections[key] as Map;
    return Map<String, dynamic>.from(
      raw.map((entryKey, value) => MapEntry(entryKey.toString(), value)),
    );
  }

  bool _parseBool(dynamic value, {required bool defaultValue}) {
    if (value is bool) return value;
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    if (normalized.isEmpty) return defaultValue;
    if (['1', 'true', 'yes', 'on'].contains(normalized)) return true;
    if (['0', 'false', 'no', 'off'].contains(normalized)) return false;
    return defaultValue;
  }

  Color? _parseColor(String? hexColor) {
    if (hexColor == null || hexColor.isEmpty) return null;
    try {
      hexColor = hexColor.replaceAll('#', '');
      if (hexColor.length == 6) {
        hexColor = 'FF$hexColor';
      }
      return Color(int.parse(hexColor, radix: 16));
    } catch (e) {
      return null;
    }
  }

  int? _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String _normalizeLink(dynamic value) {
    final link = value?.toString().trim() ?? '';
    if (link.isEmpty || link.toLowerCase() == 'null') {
      return '';
    }
    return link;
  }

  bool _bannerHasAction(Map<String, dynamic> banner) {
    final linkType = (banner['link_type'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    final link = _normalizeLink(banner['link']);
    final linkId = _toInt(banner['link_id']);

    if (linkType == 'none') {
      return false;
    }

    if (linkType == 'external') {
      return link.isNotEmpty;
    }

    if (linkType.isEmpty) {
      return link.isNotEmpty || linkId != null;
    }

    if (linkType == 'stores' ||
        linkType == 'store' ||
        linkType == 'offers' ||
        linkType == 'offer' ||
        linkType == 'spare_parts' ||
        linkType == 'spareparts' ||
        linkType == 'parts' ||
        linkType == 'most_requested' ||
        linkType == 'most_requested_services') {
      return true;
    }

    if (linkType == 'category' ||
        linkType == 'service' ||
        linkType == 'services' ||
        linkType == 'product' ||
        linkType == 'products') {
      return linkId != null || link.isNotEmpty;
    }

    return link.isNotEmpty || linkId != null;
  }

  String _bannerActionLabel(Map<String, dynamic> banner) {
    final customActionText =
        (banner['button_text'] ??
                banner['cta_text'] ??
                banner['action_text'] ??
                '')
            .toString()
            .trim();
    if (customActionText.isNotEmpty) {
      return customActionText;
    }

    return context.tr('view_more');
  }

  Color _getBannerTextColor(Color background) {
    return background.computeLuminance() > 0.45
        ? AppColors.gray900
        : Colors.white;
  }

  Future<bool> _openExternalLink(String rawLink) async {
    final normalized = rawLink.trim();
    if (normalized.isEmpty) {
      return false;
    }

    final withScheme =
        normalized.startsWith('http://') || normalized.startsWith('https://')
        ? normalized
        : 'https://$normalized';

    final uri = Uri.tryParse(withScheme);
    if (uri == null) {
      return false;
    }

    return await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Widget _buildServiceAvailabilityNotice() {
    final serviceAvailability = _homeData?['service_availability'];
    if (serviceAvailability is! Map) {
      return const SizedBox.shrink();
    }

    final categories = _homeData?['categories'];
    if (categories is List && categories.isNotEmpty) {
      return const SizedBox.shrink();
    }

    final availability = Map<String, dynamic>.from(
      serviceAvailability.map((key, value) => MapEntry(key.toString(), value)),
    );
    final isSupported = _parseBool(
      availability['is_supported'],
      defaultValue: true,
    );
    if (isSupported) {
      return const SizedBox.shrink();
    }

    final lang = Localizations.localeOf(context).languageCode.toLowerCase();
    final messageAr = (availability['message_ar'] ?? '').toString().trim();
    final messageEn = (availability['message_en'] ?? '').toString().trim();
    final message = lang == 'en'
        ? (messageEn.isNotEmpty ? messageEn : messageAr)
        : (messageAr.isNotEmpty ? messageAr : messageEn);

    if (message.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF7ED),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFFED7AA)),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.location_off_outlined,
              color: Color(0xFFEA580C),
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                  fontSize: 13,
                  color: Color(0xFF9A3412),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String? _getSectionIconUrl(String key) {
    final icons = _homeData?['section_icons'];
    if (icons is! Map || icons[key] == null) {
      return null;
    }
    final value = icons[key]?.toString().trim() ?? '';
    if (value.isEmpty) {
      return null;
    }
    return AppConfig.fixMediaUrl(value);
  }

  Widget _buildSectionIcon(String key, String fallbackEmoji) {
    final iconUrl = _getSectionIconUrl(key);
    if (iconUrl == null) {
      return Text(fallbackEmoji, style: const TextStyle(fontSize: 16));
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(6),
      child: CachedNetworkImage(
        imageUrl: iconUrl,
        width: 18,
        height: 18,
        fit: BoxFit.cover,
        errorWidget: (_, __, ___) =>
            Text(fallbackEmoji, style: const TextStyle(fontSize: 16)),
      ),
    );
  }

  ServiceCategoryModel _mapCategoryModelFromMap(Map<String, dynamic> category) {
    final subCategoriesRaw = category['sub_categories'];
    final subCategories = <ServiceCategoryModel>[];

    if (subCategoriesRaw is List) {
      for (final item in subCategoriesRaw) {
        if (item is! Map) continue;
        subCategories.add(
          _mapCategoryModelFromMap(
            Map<String, dynamic>.from(
              item.map((key, value) => MapEntry(key.toString(), value)),
            ),
          ),
        );
      }
    }

    return ServiceCategoryModel(
      id: _toInt(category['id']) ?? 0,
      parentId: category['parent_id'] == null
          ? null
          : int.tryParse('${category['parent_id']}'),
      nameAr: category['name_ar']?.toString() ?? '',
      nameEn: category['name_en']?.toString(),
      icon: category['icon']?.toString(),
      image: category['image']?.toString(),
      specialModule: category['special_module']?.toString(),
      subCategories: subCategories,
      isActive: category['is_active'] == 1 || category['is_active'] == true,
      sortOrder: _toInt(category['sort_order']) ?? 0,
      createdAt: DateTime.now(),
    );
  }

  void _openCategoryFromMap(Map<String, dynamic> category) {
    final service = _mapCategoryModelFromMap(category);

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ServiceSelectionScreen(service: service),
      ),
    );
  }

  void _bookMostRequestedService(Map<String, dynamic> item) {
    final categoryId = _toInt(item['category_id']) ?? 0;
    final serviceId = _toInt(item['id']) ?? 0;

    if (categoryId <= 0) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('error_loading_data'))));
      return;
    }

    final category = ServiceCategoryModel(
      id: categoryId,
      parentId: _toInt(item['category_parent_id']),
      nameAr: (item['category_name_ar'] ?? item['name_ar'] ?? '').toString(),
      nameEn: (item['category_name_en'] ?? item['name_en'] ?? '').toString(),
      icon: (item['category_icon'] ?? item['icon'] ?? item['image'] ?? '')
          .toString(),
      image: (item['category_image'] ?? item['image'] ?? '').toString(),
      specialModule: item['category_special_module']?.toString(),
      createdAt: DateTime.now(),
    );

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ServiceRequestScreen(
          service: category,
          subServices: serviceId > 0 ? <int>[serviceId] : const <int>[],
        ),
      ),
    );
  }

  void _openAllSpares({int? autoAddSpareId}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => AllSparesScreen(
          onBack: () => Navigator.pop(context),
          autoAddSpareId: autoAddSpareId,
        ),
      ),
    );
  }

  void _handleOfferTap(Map<String, dynamic> offer) {
    final linkType = (offer['link_type'] ?? '').toString().trim().toLowerCase();
    final linkId = _toInt(offer['link_id']);
    final link = _normalizeLink(offer['link']);

    if (linkType.isNotEmpty || link.isNotEmpty || linkId != null) {
      _handleBannerTap({
        'link_type': linkType,
        'link_id': linkId,
        'link': link,
      });
      return;
    }

    final categoryId = _toInt(offer['category_id']) ?? 0;
    if (categoryId > 0) {
      final service = ServiceCategoryModel(
        id: categoryId,
        parentId: null,
        nameAr:
            (offer['category_name_ar'] ?? offer['category_name'] ?? '')
                .toString()
                .trim()
                .isNotEmpty
            ? (offer['category_name_ar'] ?? offer['category_name']).toString()
            : context.tr('offer'),
        nameEn: (offer['category_name_en'] ?? offer['category_name'] ?? 'Offer')
            .toString(),
        icon: '',
        image: null,
        specialModule: null,
        createdAt: DateTime.now(),
      );

      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => ServiceSelectionScreen(service: service),
        ),
      );
      return;
    }

    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const BestOffersScreen()),
    );
  }

  Future<void> _handleBannerTap(Map<String, dynamic> banner) async {
    final linkType = (banner['link_type'] ?? '')
        .toString()
        .trim()
        .toLowerCase();
    final linkId = _toInt(banner['link_id']);
    final link = _normalizeLink(banner['link']);

    if (!_bannerHasAction(banner)) {
      return;
    }

    if ((linkType == 'external' || linkType.isEmpty) && link.isNotEmpty) {
      final opened = await _openExternalLink(link);
      if (opened) {
        return;
      }
    }

    if ((linkType == 'category' ||
            linkType == 'service' ||
            linkType == 'services') &&
        linkId != null) {
      final categories = _homeData?['categories'] as List? ?? [];
      for (final item in categories) {
        if (item is Map && _toInt(item['id']) == linkId) {
          _openCategoryFromMap(
            Map<String, dynamic>.from(
              item.map((key, value) => MapEntry(key.toString(), value)),
            ),
          );
          return;
        }
      }
      widget.onViewAllServices?.call();
      return;
    }

    if (linkType == 'stores' || linkType == 'store') {
      widget.onViewAllStores?.call();
      return;
    }

    if (linkType == 'offers' || linkType == 'offer') {
      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(builder: (context) => const BestOffersScreen()),
      );
      return;
    }

    if (linkType == 'spare_parts' ||
        linkType == 'spareparts' ||
        linkType == 'parts') {
      widget.onViewAllSpares?.call();
      return;
    }

    if (linkType == 'most_requested' || linkType == 'most_requested_services') {
      widget.onViewMostRequested?.call();
      return;
    }

    if (linkType == 'none') {
      return;
    }

    if (link.isNotEmpty) {
      await _openExternalLink(link);
      return;
    }

    widget.onBannerClick?.call();
  }

  Widget _buildTopSection() {
    Color bgStartColor = AppColors.gray50;
    Color? bgEndColor;

    if (_homeData != null) {
      final banners = _homeData!['banners'] as List? ?? [];
      if (banners.isNotEmpty && _currentBanner < banners.length) {
        final currentBannerData = banners[_currentBanner];
        final colorHex = currentBannerData['background_color'];
        final parsedColor = _parseColor(colorHex);
        if (parsedColor != null) {
          bgStartColor = parsedColor;
        }
        final parsedEndColor = _parseColor(
          currentBannerData['background_color_end']?.toString(),
        );
        if (parsedEndColor != null && parsedEndColor != bgStartColor) {
          bgEndColor = parsedEndColor;
        }
      }
    }

    return AnimatedContainer(
      duration: const Duration(milliseconds: 500),
      decoration: BoxDecoration(
        color: bgEndColor == null ? bgStartColor : null,
        gradient: bgEndColor == null
            ? null
            : LinearGradient(
                colors: [bgStartColor, bgEndColor],
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
              ),
      ),
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: _buildHeader(),
          ),
          _buildBanners(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final user = auth.user;
        final isGuest = auth.isGuest;
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // User Profile (Right)
            GestureDetector(
              onTap: isGuest ? () => auth.logout() : widget.onProfileClick,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        isGuest
                            ? '${context.tr('welcome_guest')} \u{1F44B}'
                            : '${context.tr('welcome_back')}, ${user?.fullName ?? context.tr('user')}',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                          shadows: _topBarTextShadows,
                        ),
                      ),
                      if (isGuest) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.22),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.5),
                            ),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            context.tr('login'),
                            style: const TextStyle(
                              fontSize: 11,
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              shadows: _topBarTextShadows,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const LocationPickerScreen(),
                        ),
                      ).then((_) {
                        if (!mounted) return;
                        _fetchHomeData();
                      });
                    },
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.location_on,
                          size: 14,
                          color: Colors.white,
                        ),
                        const SizedBox(width: 4),
                        Consumer<LocationProvider>(
                          builder: (context, provider, child) {
                            return ConstrainedBox(
                              constraints: const BoxConstraints(maxWidth: 250),
                              child: Text(
                                provider.currentAddress.isNotEmpty
                                    ? provider.currentAddress
                                    : context.tr('select_location'),
                                style: const TextStyle(
                                  fontSize: 13,
                                  color: Colors.white,
                                  shadows: _topBarTextShadows,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          },
                        ),
                        const Icon(
                          Icons.keyboard_arrow_down,
                          size: 14,
                          color: Colors.white,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // App Actions + Logo (Left)
            Row(
              children: [
                if (widget.onWalletClick != null) ...[
                  _buildHeaderActionButton(
                    icon: Icons.account_balance_wallet_outlined,
                    onTap: widget.onWalletClick!,
                  ),
                  const SizedBox(width: 8),
                ],
                const SizedBox(width: 8),
                SizedBox(
                  width: 44,
                  height: 44,
                  child: const AppLogo(fit: BoxFit.contain),
                ),
              ],
            ),
          ],
        );
      },
    );
  }

  Widget _buildHeaderActionButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.9),
          borderRadius: BorderRadius.circular(12),
          boxShadow: AppShadows.sm,
        ),
        child: Icon(icon, size: 18, color: AppColors.gray700),
      ),
    );
  }

  Widget _buildBanners() {
    final banners = _homeData?['banners'] as List? ?? [];

    if (banners.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      children: [
        SizedBox(
          height: 192,
          child: PageView.builder(
            controller: _bannerController,
            onPageChanged: (index) => setState(() => _currentBanner = index),
            itemCount: banners.length,
            itemBuilder: (context, index) {
              final banner = banners[index];
              final bannerMap = banner is Map
                  ? Map<String, dynamic>.from(
                      banner.map(
                        (key, value) => MapEntry(key.toString(), value),
                      ),
                    )
                  : <String, dynamic>{};
              final title = (bannerMap['title'] ?? '').toString().trim();
              final subtitle = (bannerMap['subtitle'] ?? '').toString().trim();
              final startColor =
                  _parseColor(bannerMap['background_color']?.toString()) ??
                  const Color(0xFFFBCC26);
              final endColor = _parseColor(
                bannerMap['background_color_end']?.toString(),
              );
              final textColor = _getBannerTextColor(startColor);
              final secondaryTextColor = textColor == Colors.white
                  ? Colors.white70
                  : AppColors.gray700;
              final hasAction = _bannerHasAction(bannerMap);

              return Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  color: endColor == null ? startColor : null,
                  gradient: endColor == null
                      ? null
                      : LinearGradient(
                          colors: [startColor, endColor],
                          begin: Alignment.topRight,
                          end: Alignment.bottomLeft,
                        ),
                  boxShadow: [
                    BoxShadow(
                      color: startColor.withValues(alpha: 0.3),
                      blurRadius: 16,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(24),
                    onTap: hasAction ? () => _handleBannerTap(bannerMap) : null,
                    child: Padding(
                      padding: const EdgeInsetsDirectional.fromSTEB(
                        18,
                        12,
                        12,
                        12,
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  title.isNotEmpty
                                      ? title
                                      : context.tr('special_offers'),
                                  style: TextStyle(
                                    fontSize: 20,
                                    height: 1.1,
                                    fontWeight: FontWeight.w800,
                                    color: textColor,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                if (subtitle.isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    subtitle,
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w500,
                                      color: secondaryTextColor,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                                if (hasAction) ...[
                                  const SizedBox(height: 12),
                                  DecoratedBox(
                                    decoration: BoxDecoration(
                                      color: textColor == Colors.white
                                          ? Colors.white.withValues(alpha: 0.18)
                                          : Colors.black.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(999),
                                      border: Border.all(
                                        color: textColor.withValues(alpha: 0.2),
                                      ),
                                    ),
                                    child: Padding(
                                      padding:
                                          const EdgeInsetsDirectional.fromSTEB(
                                            12,
                                            6,
                                            10,
                                            6,
                                          ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            _bannerActionLabel(bannerMap),
                                            style: TextStyle(
                                              color: textColor,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          const SizedBox(width: 6),
                                          Icon(
                                            Icons.arrow_forward_ios_rounded,
                                            size: 12,
                                            color: textColor,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          SizedBox(
                            width: 132,
                            child: CachedNetworkImage(
                              imageUrl: AppConfig.fixMediaUrl(
                                bannerMap['image'],
                              ),
                              fit: BoxFit.contain,
                              placeholder: (context, url) => Center(
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: textColor.withValues(alpha: 0.5),
                                ),
                              ),
                              errorWidget: (context, url, error) => Icon(
                                Icons.image_not_supported_outlined,
                                color: textColor.withValues(alpha: 0.7),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildHowItWorks() {
    String localizeStepValue(Map<String, dynamic> row, String field) {
      final lang = Localizations.localeOf(context).languageCode.toLowerCase();
      final ar = (row['${field}_ar'] ?? '').toString().trim();
      final en = (row['${field}_en'] ?? '').toString().trim();
      final ur = (row['${field}_ur'] ?? '').toString().trim();

      if (lang == 'en') {
        if (en.isNotEmpty) return en;
        if (ar.isNotEmpty) return ar;
        return ur;
      }
      if (lang == 'ur') {
        if (ur.isNotEmpty) return ur;
        if (ar.isNotEmpty) return ar;
        return en;
      }

      if (ar.isNotEmpty) return ar;
      if (en.isNotEmpty) return en;
      return ur;
    }

    final dynamicStepsRaw = _homeData?['how_it_works_steps'];
    final List<Map<String, dynamic>> dynamicSteps =
        (dynamicStepsRaw is List ? dynamicStepsRaw : const [])
            .map((item) {
              if (item is! Map) return null;
              final row = Map<String, dynamic>.from(
                item.map((key, value) => MapEntry(key.toString(), value)),
              );

              final title = localizeStepValue(row, 'title');
              final subtitle = localizeStepValue(row, 'subtitle');
              final image = AppConfig.fixMediaUrl(row['image']);

              if (title.isEmpty || subtitle.isEmpty || image.isEmpty) {
                return null;
              }

              return {
                'id': int.tryParse(row['id']?.toString() ?? ''),
                'title': title,
                'subtitle': subtitle,
                'image': image,
              };
            })
            .whereType<Map<String, dynamic>>()
            .toList();

    final fallbackSteps = [
      {
        'id': 1,
        'title': context.tr('book_service'),
        'subtitle': context.tr('that_you_need'),
        'image': 'https://iili.io/fxvIxea.png',
      },
      {
        'id': 2,
        'title': context.tr('receive_offers'),
        'subtitle': context.tr('from_service_providers'),
        'image': 'https://iili.io/fxvg2TP.png',
      },
      {
        'id': 3,
        'title': context.tr('choose_best'),
        'subtitle': context.tr('price_and_rating'),
        'image': 'https://iili.io/fxv4PvR.png',
      },
      {
        'id': 4,
        'title': context.tr('execute_service'),
        'subtitle': context.tr('high_quality'),
        'image': 'https://iili.io/fxWOm92.png',
      },
    ];

    final steps = dynamicSteps.isNotEmpty ? dynamicSteps : fallbackSteps;
    const stepColors = <Color>[
      Color(0xFFFBCC26),
      Color(0xFF7466ED),
      Color(0xFF14B8A6),
      Color(0xFFA855F7),
    ];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        children: [
          Text(
            context.tr('how_ertah_works'),
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppColors.gray800,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: steps.asMap().entries.map((entry) {
              final index = entry.key;
              final step = entry.value;
              return Expanded(
                child: Column(
                  children: [
                    Stack(
                      children: [
                        SizedBox(
                          width: 64,
                          height: 64,
                          child: CachedNetworkImage(
                            imageUrl: AppConfig.fixMediaUrl(
                              step['image'] as String,
                            ),
                          ),
                        ),
                        Positioned(
                          top: 0,
                          right: 0,
                          child: Container(
                            width: 20,
                            height: 20,
                            decoration: BoxDecoration(
                              color: stepColors[index % stepColors.length],
                              shape: BoxShape.circle,
                              boxShadow: const [
                                BoxShadow(color: Colors.black12, blurRadius: 4),
                              ],
                            ),
                            alignment: Alignment.center,
                            child: Text(
                              '${step['id'] ?? (index + 1)}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      step['title'] as String,
                      style: const TextStyle(
                        fontSize: 11,
                        height: 1.25,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      step['subtitle'] as String,
                      style: const TextStyle(
                        fontSize: 9,
                        height: 1.35,
                        color: AppColors.gray500,
                      ),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildServicesGrid() {
    final categories = _homeData?['categories'] as List? ?? [];

    if (categories.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                _buildSectionIcon('services', '🛠️'),
                const SizedBox(width: 6),
                Text(
                  context.tr('services'),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            TextButton(
              onPressed: widget.onViewAllServices,
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF7466ED),
              ),
              child: Text(
                context.tr('view_all'),
                style: const TextStyle(fontSize: 12),
              ),
            ),
          ],
        ),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth;
            const gridSpacing = 12.0;
            const targetTileWidth = 140.0;
            final estimatedCount = (width / targetTileWidth).floor();
            final crossAxisCount = estimatedCount.clamp(3, 8).toInt();
            final childAspectRatio = 1.0;

            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                childAspectRatio: childAspectRatio,
                crossAxisSpacing: gridSpacing,
                mainAxisSpacing: gridSpacing,
              ),
              itemCount: categories.length,
              itemBuilder: (context, index) {
                final categoryRaw = categories[index];
                final category = categoryRaw is Map
                    ? Map<String, dynamic>.from(
                        categoryRaw.map(
                          (key, value) => MapEntry(key.toString(), value),
                        ),
                      )
                    : <String, dynamic>{};
                return GestureDetector(
                  onTap: () => _openCategoryFromMap(category),
                  child: Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.gray100),
                      boxShadow: AppShadows.sm,
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 2,
                        vertical: 3,
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SizedBox(
                            width: 78,
                            height: 78,
                            child: Builder(
                              builder: (context) {
                                final icon = category['icon']?.toString();
                                final image = category['image'];

                                // 1. Try Icon as Image (Path)
                                if (icon != null && icon.contains('/')) {
                                  return CachedNetworkImage(
                                    imageUrl: AppConfig.fixMediaUrl(icon),
                                    fit: BoxFit.contain,
                                    placeholder: (_, __) => const Center(
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    ),
                                    errorWidget: (_, __, ___) => const Center(
                                      child: Icon(Icons.error, size: 20),
                                    ),
                                  );
                                }

                                // 2. Backup: Cover Image
                                if (image != null) {
                                  return CachedNetworkImage(
                                    imageUrl: AppConfig.fixMediaUrl(image),
                                    fit: BoxFit.contain,
                                  );
                                }

                                // 3. Fallback: Icon as Emoji/Text (or Default)
                                return Center(
                                  child: Text(
                                    icon ?? '🔧',
                                    style: const TextStyle(fontSize: 30),
                                  ),
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _getLoc(category, 'name'),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: AppColors.gray700,
                              height: 1.25,
                            ),
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),

        // Category Banner
        _buildCategoryBanner(),
      ],
    );
  }

  Widget _buildMostRequestedServices() {
    final services = _homeData?['most_requested_services'] as List? ?? [];

    if (services.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                _buildSectionIcon('most_requested', '⭐'),
                const SizedBox(width: 8),
                Text(
                  context.tr('most_requested'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            if (widget.onViewMostRequested != null)
              TextButton(
                onPressed: widget.onViewMostRequested,
                style: TextButton.styleFrom(
                  foregroundColor: const Color(0xFF7466ED),
                ),
                child: Text(
                  context.tr('view_all'),
                  style: const TextStyle(fontSize: 14),
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: services.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final serviceRaw = services[index];
            final service = serviceRaw is Map
                ? Map<String, dynamic>.from(
                    serviceRaw.map(
                      (key, value) => MapEntry(key.toString(), value),
                    ),
                  )
                : <String, dynamic>{};

            Color iconBgColor;

            switch (index % 4) {
              case 0:
                iconBgColor = const Color(0xFFE3F2FD);
                break;
              case 1:
                iconBgColor = const Color(0xFFEDE7F6);
                break;
              case 2:
                iconBgColor = const Color(0xFFE0F2F1);
                break;
              default:
                iconBgColor = const Color(0xFFF3E5F5);
            }

            return InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => _bookMostRequestedService(service),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppShadows.sm,
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      // Icon/Image
                      Container(
                        width: 50,
                        height: 50,
                        decoration: BoxDecoration(
                          color: iconBgColor,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: CachedNetworkImage(
                            imageUrl: AppConfig.fixMediaUrl(service['image']),
                            fit: BoxFit.cover,
                            placeholder: (_, __) =>
                                const Icon(Icons.image, color: Colors.grey),
                            errorWidget: (_, __, ___) => const Icon(
                              Icons.broken_image,
                              color: Colors.grey,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),

                      // Content
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _getLoc(service, 'name'),
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                                color: AppColors.gray800,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                const Icon(
                                  Icons.star,
                                  size: 14,
                                  color: Color(0xFFFFC107),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '${service['rating'] ?? 5.0}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray600,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                const Icon(
                                  Icons.bar_chart,
                                  size: 14,
                                  color: AppColors.gray500,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '${service['requests_count'] ?? 0}+ ${context.tr('requests_count_suffix')}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray500,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),

                      // Price
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          SaudiRiyalText(
                            text: '${context.tr('from')} ${service['price']}',
                            style: const TextStyle(
                              fontSize: 13,
                              color: Color(0xFF7466ED),
                              fontWeight: FontWeight.bold,
                            ),
                            iconSize: 13,
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 30,
                            child: ElevatedButton(
                              onPressed: () =>
                                  _bookMostRequestedService(service),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF7466ED),
                                foregroundColor: Colors.white,
                                elevation: 0,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              child: Text(
                                context.tr('book_now'),
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildAdBanner() {
    final ad = _homeData?['ad_banner'];
    if (ad == null) return const SizedBox.shrink();
    final adMap = ad is Map
        ? Map<String, dynamic>.from(
            ad.map((key, value) => MapEntry(key.toString(), value)),
          )
        : <String, dynamic>{};

    return GestureDetector(
      onTap: () => _handleBannerTap(adMap),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: CachedNetworkImage(
          imageUrl: AppConfig.fixMediaUrl(adMap['image']),
          height: 100,
          width: double.infinity,
          fit: BoxFit.cover,
        ),
      ),
    );
  }

  Widget _buildCategoryBanner() {
    final banner = _homeData?['category_banner'];
    if (banner == null) return const SizedBox.shrink();
    final bannerMap = banner is Map
        ? Map<String, dynamic>.from(
            banner.map((key, value) => MapEntry(key.toString(), value)),
          )
        : <String, dynamic>{};

    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: GestureDetector(
        onTap: () => _handleBannerTap(bannerMap),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: CachedNetworkImage(
            imageUrl: AppConfig.fixMediaUrl(bannerMap['image']),
            height: 100,
            width: double.infinity,
            fit: BoxFit.cover,
            placeholder: (context, url) => Container(
              height: 100,
              color: AppColors.gray100,
              child: const Center(child: CircularProgressIndicator()),
            ),
            errorWidget: (context, url, error) => const SizedBox.shrink(),
          ),
        ),
      ),
    );
  }

  Widget _buildSpareParts() {
    final parts = _homeData?['spare_parts'] as List? ?? [];
    if (parts.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                _buildSectionIcon('spare_parts', '🔧'),
                const SizedBox(width: 8),
                Text(
                  context.tr('spare_parts_with_installation'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            TextButton(
              onPressed: () {
                if (widget.onViewAllSpares != null) {
                  widget.onViewAllSpares!();
                  return;
                }
                _openAllSpares();
              },
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF7466ED),
              ),
              child: Text(
                context.tr('view_all'),
                style: const TextStyle(fontSize: 14),
              ),
            ),
          ],
        ),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: parts.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final part = parts[index];
            final spareId = _toInt(part['id']);
            return InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => _openAllSpares(autoAddSpareId: spareId),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppShadows.sm,
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          // Image
                          Container(
                            width: 80,
                            height: 80,
                            decoration: BoxDecoration(
                              color: AppColors.gray50,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: CachedNetworkImage(
                                imageUrl: AppConfig.fixMediaUrl(part['image']),
                                fit: BoxFit.cover,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),

                          // Content
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _getLoc(part, 'name'),
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.gray800,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _getLoc(part, 'description').isEmpty
                                      ? context.tr('installation_included')
                                      : _getLoc(part, 'description'),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray500,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    const Icon(
                                      Icons.star,
                                      size: 14,
                                      color: Color(0xFFFFC107),
                                    ),
                                    const SizedBox(width: 4),
                                    const Text(
                                      '4.8',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: AppColors.gray600,
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    const Icon(
                                      Icons.check_circle,
                                      size: 14,
                                      color: AppColors.success,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      context.tr('warranty'),
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: AppColors.gray500,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),

                          // Price
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              SaudiRiyalText(
                                text: '${context.tr('from')} ${part['price']}',
                                style: const TextStyle(
                                  fontSize: 14,
                                  color: Color(0xFF7466ED),
                                  fontWeight: FontWeight.bold,
                                ),
                                iconSize: 14,
                              ),
                              if (part['old_price'] != null)
                                SaudiRiyalText(
                                  text: '${part['old_price']}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray400,
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                  iconSize: 12,
                                ),
                              const SizedBox(height: 8),
                              Material(
                                color: const Color(0xFFFBCC26),
                                borderRadius: BorderRadius.circular(10),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(10),
                                  onTap: () =>
                                      _openAllSpares(autoAddSpareId: spareId),
                                  child: const SizedBox(
                                    width: 36,
                                    height: 36,
                                    child: Icon(
                                      Icons.shopping_cart_outlined,
                                      size: 18,
                                      color: AppColors.gray900,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildStores() {
    final stores = _homeData?['stores'] as List? ?? [];

    if (stores.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                const Text('🏪', style: TextStyle(fontSize: 18)),
                const SizedBox(width: 8),
                Text(
                  context.tr('trusted_stores'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            TextButton(
              onPressed: widget.onViewAllStores,
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF7466ED),
              ),
              child: Text(
                context.tr('view_all'),
                style: const TextStyle(fontSize: 14),
              ),
            ),
          ],
        ),
        SizedBox(
          height: 160,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: stores.length,
            separatorBuilder: (_, __) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final store = stores[index];
              return GestureDetector(
                onTap: () {
                  if (widget.onStoreClick != null) {
                    widget.onStoreClick!(
                      StoreModel(
                        id: int.parse(store['id'].toString()),
                        nameAr: store['name_ar'],
                        nameEn:
                            store['name_en'] ??
                            store['name_ar'], // Should try to get name_en if exists
                        isActive: true,
                        createdAt: DateTime.now(),
                      ),
                    );
                  }
                },
                child: Container(
                  width: 140,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: AppShadows.sm,
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 70,
                        height: 70,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFF9FAFB), Color(0xFFF3F4F6)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Center(
                          child: Text(
                            '🏪',
                            style: const TextStyle(fontSize: 32),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        _getLoc(store, 'name'),
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.star, size: 12, color: Color(0xFFFFC107)),
                          SizedBox(width: 2),
                          Text(
                            '4.8',
                            style: TextStyle(
                              fontSize: 10,
                              color: AppColors.gray600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildOffers() {
    final offers = _homeData?['offers'] as List? ?? [];

    if (offers.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                _buildSectionIcon('latest_offers', '🔥'),
                const SizedBox(width: 6),
                Text(
                  context.tr('latest_offers'),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            TextButton(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const BestOffersScreen(),
                  ),
                );
              },
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF7466ED),
                padding: EdgeInsets.zero,
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: Text(
                context.tr('view_all'),
                style: const TextStyle(fontSize: 14),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: offers.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final offer = offers[index];
            final offerMap = offer is Map
                ? Map<String, dynamic>.from(
                    offer.map((key, value) => MapEntry(key.toString(), value)),
                  )
                : <String, dynamic>{};

            return InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => _handleOfferTap(offerMap),
              child: Container(
                height: 100,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: AppShadows.sm,
                ),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: const BorderRadius.only(
                        topRight: Radius.circular(16),
                        bottomRight: Radius.circular(16),
                      ),
                      child: offerMap['image'] != null
                          ? CachedNetworkImage(
                              imageUrl: AppConfig.fixMediaUrl(
                                offerMap['image'],
                              ),
                              width: 100,
                              height: 100,
                              fit: BoxFit.cover,
                            )
                          : Container(
                              width: 100,
                              height: 100,
                              color: Colors.grey[200],
                              child: const Icon(Icons.local_offer),
                            ),
                    ),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              _getLoc(offerMap, 'title'),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _getLoc(offerMap, 'description'),
                              style: TextStyle(
                                color: AppColors.gray500,
                                fontSize: 11,
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.red[50],
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: offerMap['discount_type'] == 'percentage'
                                  ? Text(
                                      '${context.tr('discount')} ${offerMap['discount_value']}%',
                                      style: const TextStyle(
                                        color: Colors.red,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    )
                                  : SaudiRiyalText(
                                      text:
                                          '${context.tr('discount')} ${offerMap['discount_value']}',
                                      style: const TextStyle(
                                        color: Colors.red,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                      iconSize: 10,
                                    ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildPopupBanner() {
    final rawBanner = _homeData!['popup_banner'];
    final banner = rawBanner is Map
        ? Map<String, dynamic>.from(
            rawBanner.map((key, value) => MapEntry(key.toString(), value)),
          )
        : <String, dynamic>{};

    return Container(
      color: Colors.black.withValues(alpha: 0.6),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                children: [
                  GestureDetector(
                    onTap: () async {
                      await _closePopupBanner(banner);
                      if (!mounted) return;
                      await _handleBannerTap(banner);
                    },
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(24),
                      child: CachedNetworkImage(
                        imageUrl: AppConfig.fixMediaUrl(banner['image']),
                        height: 400,
                        fit: BoxFit.cover,
                      ),
                    ),
                  ),
                  Positioned(
                    top: 12,
                    left: 12,
                    child: GestureDetector(
                      onTap: () => _closePopupBanner(banner),
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.9),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.close, size: 20),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ).animate().scale(curve: Curves.elasticOut, duration: 400.ms),
        ),
      ),
    );
  }
}
