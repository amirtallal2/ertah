import 'dart:convert';

import 'package:http/http.dart' as http;

import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
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
  static const String _chatEndpoint =
      'https://api.us-west-2.modal.direct/v1/chat/completions';
  static const String _model = String.fromEnvironment(
    'DARFIX_AI_MODEL',
    defaultValue: 'zai-org/GLM-5-FP8',
  );
  static const String _apiKey = String.fromEnvironment(
    'DARFIX_AI_API_KEY',
    defaultValue:
        'modalresearch_yjGu-_89u70CljD8gI2xuUP7gDQIa-Y63uojEtC9Tso',
  );

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
    if (_apiKey.trim().isEmpty) {
      throw Exception('DARFIX_AI_API_KEY is empty');
    }

    final liveSnapshot = await _buildLiveSnapshot(
      localeCode: localeCode,
      authProvider: authProvider,
      locationProvider: locationProvider,
    );

    final messages = <Map<String, String>>[
      {
        'role': 'system',
        'content': _systemPrompt(
          localeCode: localeCode,
          liveSnapshotJson: const JsonEncoder.withIndent('  ').convert(
            liveSnapshot,
          ),
        ),
      },
      ...history
          .where((message) => message.content.trim().isNotEmpty)
          .take(12)
          .map((message) => message.toJson()),
      {'role': 'user', 'content': userMessage.trim()},
    ];

    final response = await http.post(
      Uri.parse(_chatEndpoint),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $_apiKey',
      },
      body: jsonEncode({
        'model': _model,
        'messages': messages,
        'max_tokens': 500,
      }),
    );

    final decodedBody = utf8.decode(response.bodyBytes, allowMalformed: true);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(decodedBody.trim().isEmpty ? response.statusCode : decodedBody.trim());
    }

    final payload = jsonDecode(decodedBody);
    final content = _extractAssistantMessage(payload);
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

  String _extractAssistantMessage(dynamic payload) {
    if (payload is! Map) return '';
    final choices = payload['choices'];
    if (choices is! List || choices.isEmpty) return '';
    final first = choices.first;
    if (first is! Map) return '';
    final message = first['message'];
    if (message is! Map) return '';
    final content = message['content'];
    if (content is String) {
      return content.trim();
    }
    if (content is List) {
      return content
          .whereType<Map>()
          .map((item) => item['text']?.toString() ?? '')
          .where((text) => text.trim().isNotEmpty)
          .join('\n')
          .trim();
    }
    return '';
  }

  String _systemPrompt({
    required String localeCode,
    required String liveSnapshotJson,
  }) {
    return '''
You are Darfix AI inside the Darfix customer app.
Use the live application snapshot below as your primary source of truth.
Do not invent prices, offers, stores, services, orders, balances, or support details.
If a requested fact is not present in the live snapshot, say clearly that the current live data available to you does not include it.
Prefer concise, practical answers.
Reply in the same language as the latest user message unless they explicitly ask for another language.
If the user asks about their account, recent orders, wallet, or profile, use the authenticated user data when available.
Current app locale: $localeCode

Live app snapshot:
$liveSnapshotJson
''';
  }
}
