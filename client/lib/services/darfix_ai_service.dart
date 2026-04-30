import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import 'api_service.dart';
import 'home_service.dart';
import 'offers_service.dart';
import 'orders_service.dart';
import 'settings_service.dart';
import 'stores_service.dart';
import 'user_service.dart';

class DarfixAiConversationMessage {
  final String role;
  final String content;

  const DarfixAiConversationMessage({
    required this.role,
    required this.content,
  });

  Map<String, String> toJson() => {'role': role, 'content': content};
}

class DarfixAiReply {
  final String content;
  final Map<String, dynamic> liveSnapshot;

  const DarfixAiReply({required this.content, required this.liveSnapshot});
}

class DarfixAiService {
  final ApiService _api = ApiService();
  final HomeService _homeService = HomeService();
  final SettingsService _settingsService = SettingsService();
  final OffersService _offersService = OffersService();
  final StoresService _storesService = StoresService();
  final OrdersService _ordersService = OrdersService();
  final UserService _userService = UserService();

  Future<DarfixAiReply> sendMessage({
    required String userMessage,
    required List<DarfixAiConversationMessage> history,
    required String localeCode,
    required AuthProvider authProvider,
    required LocationProvider locationProvider,
  }) async {
    final liveSnapshot = await _buildLiveSnapshot(
      localeCode: localeCode,
      authProvider: authProvider,
      locationProvider: locationProvider,
    );

    final response = await _api.post(
      '/mobile/ai.php?action=chat',
      body: {
        'message': userMessage.trim(),
        'locale': localeCode,
        'history': history
            .where((message) => message.content.trim().isNotEmpty)
            .take(12)
            .map((message) => message.toJson())
            .toList(),
        'live_snapshot': liveSnapshot,
      },
    );

    if (!response.success) {
      throw Exception(
        (response.message ?? '').trim().isEmpty
            ? 'Darfix AI request failed'
            : response.message!.trim(),
      );
    }

    final payload = _mapFromDynamic(response.data) ?? <String, dynamic>{};
    final content = (payload['content'] ?? '').toString().trim();
    if (content.isEmpty) {
      throw Exception('Empty AI response');
    }

    return DarfixAiReply(content: content, liveSnapshot: liveSnapshot);
  }

  Future<Map<String, dynamic>> _buildLiveSnapshot({
    required String localeCode,
    required AuthProvider authProvider,
    required LocationProvider locationProvider,
  }) async {
    final lat = locationProvider.requestLat;
    final lng = locationProvider.requestLng;
    final countryCode = locationProvider.requestCountryCode;

    final homeFuture = _homeService.getHomeData(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
      allowOutside: authProvider.isGuest,
    );
    final appSettingsFuture = _settingsService.getAppSettings(lang: localeCode);
    final contactFuture = _settingsService.getContact();
    final offersFuture = _offersService.getOffers(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
    );
    final storesFuture = _storesService.getStores(
      lat: lat,
      lng: lng,
      countryCode: countryCode,
    );

    final results = await Future.wait([
      homeFuture,
      appSettingsFuture,
      contactFuture,
      offersFuture,
      storesFuture,
    ]);

    final homeData = _mapFromDynamic(results[0].data) ?? <String, dynamic>{};
    final appSettings = _mapFromDynamic(results[1].data) ?? <String, dynamic>{};
    final contact = _mapFromDynamic(results[2].data) ?? <String, dynamic>{};
    final offers = _mapList(results[3].data);
    final stores = _extractStores(results[4].data);

    final snapshot = <String, dynamic>{
      'generated_at': DateTime.now().toIso8601String(),
      'locale': localeCode,
      'location': {
        'address': locationProvider.currentAddress,
        'city': locationProvider.currentCityName,
        'country_code': locationProvider.currentCountryCode,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
      },
      'app_settings': {
        'app_name': appSettings['app_name'] ?? appSettings['name'],
        'support_phone': appSettings['support_phone'] ?? contact['phone'],
        'support_email': appSettings['support_email'] ?? contact['email'],
        'whatsapp': appSettings['whatsapp'] ?? contact['whatsapp'],
        'currency': appSettings['currency'] ?? appSettings['currency_code'],
        'about': _pickFirstNonEmpty([
          appSettings['about_ar'],
          appSettings['about_en'],
          appSettings['about'],
        ]),
      },
      'home': {
        'categories': _extractCategorySummary(homeData['categories']),
        'offers': _extractOfferSummary(homeData['offers'], fallback: offers),
        'stores': _extractStoreSummary(homeData['stores'], fallback: stores),
        'most_requested_services': _extractServiceSummary(
          homeData['most_requested_services'],
        ),
        'spare_parts': _extractProductSummary(homeData['spare_parts']),
        'service_availability': _mapFromDynamic(homeData['service_availability']),
      },
    };

    final userSnapshot = await _buildUserSnapshot(authProvider);
    if (userSnapshot.isNotEmpty) {
      snapshot['user'] = userSnapshot;
    }

    return snapshot;
  }

  Future<Map<String, dynamic>> _buildUserSnapshot(AuthProvider authProvider) async {
    if (!authProvider.isLoggedIn || authProvider.isGuest) {
      return const <String, dynamic>{};
    }

    final results = await Future.wait([
      _userService.getProfile(),
      _userService.getWallet(),
      _ordersService.getOrders(page: 1, perPage: 5),
    ]);

    final profile = _mapFromDynamic(results[0].data) ?? authProvider.user?.toJson();
    final wallet = _mapFromDynamic(results[1].data) ?? <String, dynamic>{};
    final orders = _extractOrders(results[2].data);

    if (profile == null && wallet.isEmpty && orders.isEmpty) {
      return const <String, dynamic>{};
    }

    return {
      if (profile != null) 'profile': _extractProfileSummary(profile),
      if (wallet.isNotEmpty)
        'wallet': {
          'balance': wallet['balance'] ?? wallet['wallet_balance'],
          'points': wallet['points'],
          'currency': wallet['currency'] ?? wallet['currency_code'],
        },
      'recent_orders': orders,
    };
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
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(
            item.map((key, value) => MapEntry(key.toString(), value)),
          ),
        )
        .toList();
  }

  List<Map<String, dynamic>> _extractStores(dynamic raw) {
    if (raw is List) return _mapList(raw);
    final map = _mapFromDynamic(raw);
    if (map == null) return <Map<String, dynamic>>[];
    return _mapList(map['stores'] ?? map['data']);
  }

  List<Map<String, dynamic>> _extractOrders(dynamic raw) {
    if (raw is List) {
      return _mapList(raw).take(5).map(_extractOrderFields).toList();
    }
    final map = _mapFromDynamic(raw);
    if (map == null) return <Map<String, dynamic>>[];
    final source = map['orders'] ?? map['data'];
    return _mapList(source).take(5).map(_extractOrderFields).toList();
  }

  Map<String, dynamic> _extractOrderFields(Map<String, dynamic> order) {
    return {
      'id': order['id'],
      'order_number': order['order_number'],
      'status': order['status'],
      'category_name': _pickFirstNonEmpty([
        order['category_name_ar'],
        order['category_name'],
        order['category_name_en'],
      ]),
      'scheduled_date': order['scheduled_date'],
      'scheduled_time': order['scheduled_time'],
      'total_amount': order['total_amount'],
      'payment_status': order['payment_status'],
      'created_at': order['created_at'],
    };
  }

  Map<String, dynamic> _extractProfileSummary(Map<String, dynamic> profile) {
    return {
      'id': profile['id'],
      'full_name': profile['full_name'],
      'phone': profile['phone'],
      'email': profile['email'],
      'wallet_balance': profile['wallet_balance'],
      'points': profile['points'],
      'membership_level': profile['membership_level'],
      'city': profile['city'],
      'country': profile['country'],
    };
  }

  List<Map<String, dynamic>> _extractCategorySummary(dynamic raw) {
    return _mapList(raw)
        .take(8)
        .map(
          (item) => {
            'id': item['id'],
            'name': _pickFirstNonEmpty([
              item['name_ar'],
              item['category_name_ar'],
              item['name_en'],
              item['category_name_en'],
              item['name'],
            ]),
            'special_module': item['special_module'],
            'requests_count': item['requests_count'],
          },
        )
        .toList();
  }

  List<Map<String, dynamic>> _extractOfferSummary(
    dynamic raw, {
    List<Map<String, dynamic>> fallback = const [],
  }) {
    final source = _mapList(raw).isNotEmpty ? _mapList(raw) : fallback;
    return source
        .take(6)
        .map(
          (item) => {
            'id': item['id'],
            'title': _pickFirstNonEmpty([
              item['title_ar'],
              item['title'],
              item['name_ar'],
              item['name'],
            ]),
            'discount_type': item['discount_type'],
            'discount_value': item['discount_value'],
            'code': item['code'],
            'expires_at': item['expires_at'],
          },
        )
        .toList();
  }

  List<Map<String, dynamic>> _extractStoreSummary(
    dynamic raw, {
    List<Map<String, dynamic>> fallback = const [],
  }) {
    final source = _mapList(raw).isNotEmpty ? _mapList(raw) : fallback;
    return source
        .take(6)
        .map(
          (item) => {
            'id': item['id'],
            'name': _pickFirstNonEmpty([
              item['name_ar'],
              item['store_name_ar'],
              item['name_en'],
              item['store_name_en'],
              item['name'],
            ]),
            'rating': item['rating'],
            'city': item['city'],
            'delivery_fee': item['delivery_fee'],
          },
        )
        .toList();
  }

  List<Map<String, dynamic>> _extractServiceSummary(dynamic raw) {
    return _mapList(raw)
        .take(6)
        .map(
          (item) => {
            'id': item['id'],
            'name': _pickFirstNonEmpty([
              item['name_ar'],
              item['category_name_ar'],
              item['name_en'],
              item['category_name_en'],
              item['name'],
            ]),
            'requests_count': item['requests_count'],
            'rating': item['rating'],
          },
        )
        .toList();
  }

  List<Map<String, dynamic>> _extractProductSummary(dynamic raw) {
    return _mapList(raw)
        .take(6)
        .map(
          (item) => {
            'id': item['id'],
            'name': _pickFirstNonEmpty([
              item['name_ar'],
              item['product_name_ar'],
              item['name_en'],
              item['product_name_en'],
              item['name'],
            ]),
            'price': item['price'],
            'old_price': item['old_price'],
            'store_name': _pickFirstNonEmpty([
              item['store_name_ar'],
              item['store_name_en'],
              item['store_name'],
            ]),
          },
        )
        .toList();
  }

  String _pickFirstNonEmpty(List<dynamic> values) {
    for (final value in values) {
      final text = (value ?? '').toString().trim();
      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }
    return '';
  }
}
