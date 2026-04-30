import 'package:flutter/material.dart';

import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../services/services.dart';

enum StaticContentPageKey { privacy, terms, refund }

extension StaticContentPageKeyX on StaticContentPageKey {
  String get action {
    switch (this) {
      case StaticContentPageKey.privacy:
        return 'privacy';
      case StaticContentPageKey.terms:
        return 'terms';
      case StaticContentPageKey.refund:
        return 'refund';
    }
  }

  String title(BuildContext context) {
    switch (this) {
      case StaticContentPageKey.privacy:
        return context.tr('privacy_policy');
      case StaticContentPageKey.terms:
        return context.tr('terms_and_conditions');
      case StaticContentPageKey.refund:
        return context.tr('refund_policy');
    }
  }

  String emptyMessage(BuildContext context) {
    final languageCode = Localizations.localeOf(context).languageCode;
    switch (this) {
      case StaticContentPageKey.privacy:
        if (languageCode == 'en') {
          return 'Privacy policy is being updated...';
        }
        if (languageCode == 'ur') {
          return 'پرائیویسی پالیسی اپ ڈیٹ ہو رہی ہے...';
        }
        return 'جاري تحديث سياسة الخصوصية...';
      case StaticContentPageKey.terms:
        if (languageCode == 'en') {
          return 'Terms and conditions are being updated...';
        }
        if (languageCode == 'ur') {
          return 'شرائط و ضوابط اپ ڈیٹ ہو رہے ہیں...';
        }
        return 'جاري تحديث الشروط والأحكام...';
      case StaticContentPageKey.refund:
        if (languageCode == 'en') {
          return 'Refund policy is being updated...';
        }
        if (languageCode == 'ur') {
          return 'واپسی کی پالیسی اپ ڈیٹ ہو رہی ہے...';
        }
        return 'جاري تحديث سياسة الاسترداد...';
    }
  }
}

class StaticContentPageScreen extends StatefulWidget {
  const StaticContentPageScreen({super.key, required this.page});

  final StaticContentPageKey page;

  @override
  State<StaticContentPageScreen> createState() =>
      _StaticContentPageScreenState();
}

class _StaticContentPageScreenState extends State<StaticContentPageScreen> {
  final SettingsService _settingsService = SettingsService();

  String _content = '';
  String _title = '';
  String _activeLanguage = '';
  bool _isLoading = true;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final languageCode = Localizations.localeOf(context).languageCode;
    if (languageCode != _activeLanguage) {
      _activeLanguage = languageCode;
      _fetchData(languageCode);
    }
  }

  Future<void> _fetchData(String languageCode) async {
    if (mounted) {
      setState(() => _isLoading = true);
    }

    try {
      final response = await _settingsService.getContentPage(
        widget.page.action,
        lang: languageCode,
      );
      if (!mounted) return;

      if (response.success && response.data is Map) {
        final data = Map<String, dynamic>.from(response.data as Map);
        setState(() {
          _content = (data['content'] ?? '').toString().trim();
          _title = (data['title'] ?? '').toString().trim();
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final fallbackTitle = widget.page.title(context);
    final pageTitle = _title.isNotEmpty ? _title : fallbackTitle;

    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: AppBar(
        title: Text(pageTitle),
        centerTitle: true,
        backgroundColor: AppColors.white,
        elevation: 0,
        leading: const BackButton(color: AppColors.gray800),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    pageTitle,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: AppColors.gray900,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    _content.isNotEmpty
                        ? _content
                        : widget.page.emptyMessage(context),
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.gray600,
                      height: 1.6,
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
