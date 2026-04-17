// Privacy Policy Screen
// شاشة سياسة الخصوصية

import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import '../services/app_localizations.dart';

class PrivacyPolicyScreen extends StatefulWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  State<PrivacyPolicyScreen> createState() => _PrivacyPolicyScreenState();
}

class _PrivacyPolicyScreenState extends State<PrivacyPolicyScreen> {
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
    setState(() => _isLoading = true);
    try {
      final response = await _settingsService.getPrivacy(lang: languageCode);
      if (response.success && response.data is Map && mounted) {
        final data = Map<String, dynamic>.from(response.data as Map);
        setState(() {
          _content = (data['content'] ?? '').toString();
          _title = (data['title'] ?? '').toString();
          _isLoading = false;
        });
      } else if (mounted) {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: Colors.white,
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: Text(_title.isNotEmpty ? _title : context.tr('privacy_policy')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _title.isNotEmpty ? _title : context.tr('privacy_policy'),
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 24),
            Text(
              _content.isNotEmpty
                  ? _content
                  : context.tr('privacy_policy_updating'),
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
