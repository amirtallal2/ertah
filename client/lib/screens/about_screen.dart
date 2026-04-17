// About Screen
// شاشة عن التطبيق

import 'package:flutter/material.dart';

import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../config/app_config.dart';
import '../services/services.dart';

import '../services/app_localizations.dart';
import '../widgets/app_logo.dart';

class AboutScreen extends StatefulWidget {
  final VoidCallback? onBack;

  const AboutScreen({super.key, this.onBack});

  @override
  State<AboutScreen> createState() => _AboutScreenState();
}

class _AboutScreenState extends State<AboutScreen> {
  final SettingsService _settingsService = SettingsService();
  String _aboutContent = '';
  String _aboutTitle = '';
  String _appVersion = AppConfig.appVersion;
  String _supportPhone = '+966 50 123 4567';
  String _supportEmail = 'support@darfix.org';
  String _supportAddress = '';
  String _statHappyClients = '50,000+';
  String _statServiceProviders = '2,500+';
  String _statCompletedOrders = '100,000+';
  String _whyTitle = '';
  List<Map<String, String>> _dynamicFeatures = [];
  String _activeLanguage = '';
  bool _isLoading = true;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final languageCode = Localizations.localeOf(context).languageCode;
    if (languageCode != _activeLanguage) {
      _activeLanguage = languageCode;
      _fetchAbout(languageCode);
    }
  }

  Future<void> _fetchAbout(String languageCode) async {
    setState(() => _isLoading = true);
    try {
      final responses = await Future.wait([
        _settingsService.getAbout(lang: languageCode),
        _settingsService.getAppSettings(lang: languageCode),
        _settingsService.getContact(),
      ]);

      final aboutResponse = responses[0];
      final appSettingsResponse = responses[1];
      final contactResponse = responses[2];

      var aboutContent = _aboutContent;
      var aboutTitle = _aboutTitle;
      var appVersion = _appVersion;
      var supportPhone = _supportPhone;
      var supportEmail = _supportEmail;
      var supportAddress = _supportAddress;
      var statHappyClients = _statHappyClients;
      var statServiceProviders = _statServiceProviders;
      var statCompletedOrders = _statCompletedOrders;
      var whyTitle = _whyTitle;
      var dynamicFeatures = List<Map<String, String>>.from(_dynamicFeatures);

      if (aboutResponse.success && aboutResponse.data is Map) {
        final data = Map<String, dynamic>.from(aboutResponse.data as Map);
        aboutContent = _firstNonEmpty([data['content']], aboutContent);
        aboutTitle = _firstNonEmpty([data['title']], aboutTitle);
      }

      if (appSettingsResponse.success && appSettingsResponse.data is Map) {
        final data = Map<String, dynamic>.from(appSettingsResponse.data as Map);
        appVersion = _firstNonEmpty([data['app_version']], appVersion);
        supportPhone = _firstNonEmpty([data['support_phone']], supportPhone);
        supportEmail = _firstNonEmpty([data['support_email']], supportEmail);
        supportAddress = _firstNonEmpty([
          data['support_address'],
          data['address'],
        ], supportAddress);
        statHappyClients = _firstNonEmpty([
          data['about_stat_happy_clients'],
        ], statHappyClients);
        statServiceProviders = _firstNonEmpty([
          data['about_stat_service_providers'],
        ], statServiceProviders);
        statCompletedOrders = _firstNonEmpty([
          data['about_stat_completed_orders'],
        ], statCompletedOrders);

        if (data['about_meta'] is Map) {
          final aboutMeta = Map<String, dynamic>.from(
            data['about_meta'] as Map,
          );
          if (aboutMeta['stats'] is Map) {
            final statsMeta = Map<String, dynamic>.from(
              aboutMeta['stats'] as Map,
            );
            statHappyClients = _firstNonEmpty([
              statsMeta['happy_clients'],
            ], statHappyClients);
            statServiceProviders = _firstNonEmpty([
              statsMeta['service_providers'],
            ], statServiceProviders);
            statCompletedOrders = _firstNonEmpty([
              statsMeta['completed_orders'],
            ], statCompletedOrders);
          }

          whyTitle = _firstNonEmpty([aboutMeta['why_title']], whyTitle);
          dynamicFeatures = _parseFeatures(aboutMeta['features']);
        }
      }

      if (contactResponse.success && contactResponse.data is Map) {
        final data = Map<String, dynamic>.from(contactResponse.data as Map);
        supportPhone = _firstNonEmpty([
          data['support_phone'],
          data['phone'],
        ], supportPhone);
        supportEmail = _firstNonEmpty([
          data['support_email'],
          data['email'],
        ], supportEmail);
        supportAddress = _firstNonEmpty([data['address']], supportAddress);
      }

      if (mounted) {
        setState(() {
          _aboutContent = aboutContent;
          _aboutTitle = aboutTitle;
          _appVersion = appVersion;
          _supportPhone = supportPhone;
          _supportEmail = supportEmail;
          _supportAddress = supportAddress;
          _statHappyClients = statHappyClients;
          _statServiceProviders = statServiceProviders;
          _statCompletedOrders = statCompletedOrders;
          _whyTitle = whyTitle;
          _dynamicFeatures = dynamicFeatures;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _firstNonEmpty(List<dynamic> values, String fallback) {
    for (final value in values) {
      final text = value?.toString().trim() ?? '';
      if (text.isNotEmpty) return text;
    }
    return fallback;
  }

  List<Map<String, String>> _parseFeatures(dynamic raw) {
    if (raw is! List) return [];

    final items = <Map<String, String>>[];
    for (final item in raw) {
      if (item is! Map) continue;
      final map = Map<String, dynamic>.from(item);
      final title = (map['title'] ?? '').toString().trim();
      final description = (map['description'] ?? '').toString().trim();
      final icon = (map['icon'] ?? '⭐').toString().trim();

      if (title.isEmpty && description.isEmpty) continue;
      items.add({
        'title': title,
        'description': description,
        'icon': icon.isEmpty ? '⭐' : icon,
      });
    }

    return items;
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: AppColors.gray50,
        body: Center(child: CircularProgressIndicator()),
      );
    }

    final stats = [
      {
        'icon': Icons.people,
        'label': context.tr('happy_client'),
        'value': _statHappyClients,
        'colors': [AppColors.primary, AppColors.primaryDark],
      },
      {
        'icon': Icons.workspace_premium,
        'label': context.tr('service_provider_count'),
        'value': _statServiceProviders,
        'colors': [const Color(0xFF7466ED), const Color(0xFF6858E0)],
      },
      {
        'icon': Icons.check_circle,
        'label': context.tr('completed_orders_count'),
        'value': _statCompletedOrders,
        'colors': [Colors.green, Colors.green.shade600],
      },
    ];

    final features = _dynamicFeatures.isNotEmpty
        ? _dynamicFeatures
        : [
            {
              'title': context.tr('high_quality_title'),
              'description': context.tr('high_quality_desc'),
              'icon': '⭐',
            },
            {
              'title': context.tr('competitive_prices_title'),
              'description': context.tr('competitive_prices_desc'),
              'icon': '💰',
            },
            {
              'title': context.tr('fast_service_title'),
              'description': context.tr('fast_service_desc'),
              'icon': '⚡',
            },
            {
              'title': context.tr('support_24_7_title'),
              'description': context.tr('support_24_7_desc'),
              'icon': '🎧',
            },
            {
              'title': context.tr('comprehensive_warranty_title'),
              'description': context.tr('comprehensive_warranty_desc'),
              'icon': '🛡️',
            },
          ];

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Header
            _buildHeader(context),

            // Stats
            Transform.translate(
              offset: const Offset(0, -64),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: List.generate(stats.length, (index) {
                    final stat = stats[index];
                    final colors = stat['colors'] as List<Color>;
                    return Expanded(
                      child:
                          Container(
                                margin: EdgeInsets.only(
                                  left: index < stats.length - 1 ? 12 : 0,
                                ),
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  boxShadow: AppShadows.lg,
                                ),
                                child: Column(
                                  children: [
                                    Container(
                                      width: 40,
                                      height: 40,
                                      decoration: BoxDecoration(
                                        gradient: LinearGradient(
                                          colors: colors,
                                        ),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Icon(
                                        stat['icon'] as IconData,
                                        color: Colors.white,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      stat['value'] as String,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 16,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      stat['label'] as String,
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        color: AppColors.gray500,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ),
                              )
                              .animate()
                              .fadeIn(delay: (500 + index * 100).ms)
                              .slideY(begin: 0.1),
                    );
                  }),
                ),
              ),
            ),

            // Content
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                children: [
                  // About Section
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.md,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.business,
                              color: Color(0xFF7466ED),
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              _aboutTitle.isNotEmpty
                                  ? _aboutTitle
                                  : context.tr('about_app'),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          _aboutContent.isNotEmpty
                              ? _aboutContent
                              : context.tr('about_ertah_desc'),
                          style: const TextStyle(
                            color: AppColors.gray600,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ).animate().fadeIn(delay: 800.ms).slideY(begin: 0.1),

                  const SizedBox(height: 24),

                  // Features
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.md,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _whyTitle.isNotEmpty
                              ? _whyTitle
                              : context.tr('why_ertah'),
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 16),
                        ...features.asMap().entries.map((entry) {
                          final feature = entry.value;
                          return Container(
                                margin: const EdgeInsets.only(bottom: 12),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [AppColors.gray50, Colors.white],
                                  ),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      feature['icon'] as String,
                                      style: const TextStyle(fontSize: 24),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            feature['title'] as String,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            feature['description'] as String,
                                            style: const TextStyle(
                                              color: AppColors.gray600,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              )
                              .animate()
                              .fadeIn(delay: (1000 + entry.key * 100).ms)
                              .slideX(begin: -0.1);
                        }),
                      ],
                    ),
                  ).animate().fadeIn(delay: 900.ms).slideY(begin: 0.1),

                  const SizedBox(height: 24),

                  // Contact Section
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppColors.primary, AppColors.primaryDark],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.lg,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.favorite,
                              color: Colors.white,
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              context.tr('contact_us'),
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        _buildContactItem(
                          Icons.phone,
                          context.tr('phone'),
                          _supportPhone,
                        ),
                        const SizedBox(height: 12),
                        _buildContactItem(
                          Icons.email,
                          context.tr('email'),
                          _supportEmail,
                        ),
                        const SizedBox(height: 12),
                        _buildContactItem(
                          Icons.location_on,
                          context.tr('address'),
                          _supportAddress.isNotEmpty
                              ? _supportAddress
                              : context.tr('address_value'),
                        ),
                      ],
                    ),
                  ).animate().fadeIn(delay: 1400.ms).slideY(begin: 0.1),

                  const SizedBox(height: 24),

                  // Version
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: AppShadows.sm,
                    ),
                    child: Column(
                      children: [
                        Text(
                          context.tr('copyright'),
                          style: const TextStyle(
                            color: AppColors.gray500,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${context.tr('version_label')} $_appVersion',
                          style: const TextStyle(
                            color: AppColors.gray400,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ).animate().fadeIn(delay: 1600.ms),

                  const SizedBox(height: 80),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 16,
        left: 16,
        right: 16,
        bottom: 96,
      ),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, AppColors.primaryDark, Color(0xFFE5B41F)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -50,
            left: -50,
            child: Container(
              width: 160,
              height: 160,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: 0,
            right: -30,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: Colors.teal.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Column(
            children: [
              // Top Bar
              Row(
                children: [
                  InkWell(
                    onTap: widget.onBack ?? () => Navigator.pop(context),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.arrow_forward,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    context.tr('about_title'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              // Logo
              Container(
                width: 96,
                height: 96,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: const AppLogo(fit: BoxFit.contain),
                ),
              ).animate().scale(delay: 200.ms, curve: Curves.elasticOut),
              const SizedBox(height: 16),
              const Text(
                'Darfix', // App Name usually stays same
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                ),
              ).animate().fadeIn(delay: 300.ms).slideY(begin: 0.1),
              const SizedBox(height: 8),
              Text(
                context.tr('app_slogan'),
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.9),
                  fontSize: 14,
                ),
              ).animate().fadeIn(delay: 400.ms),
            ],
          ),
        ],
      ),
    ).animate().slideY(begin: -0.5, end: 0, duration: 400.ms);
  }

  Widget _buildContactItem(IconData icon, String label, String value) {
    return Row(
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.2),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: Colors.white, size: 20),
        ),
        const SizedBox(width: 12),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.8),
                fontSize: 12,
              ),
            ),
            Text(
              value,
              style: const TextStyle(color: Colors.white, fontSize: 14),
            ),
          ],
        ),
      ],
    );
  }
}
