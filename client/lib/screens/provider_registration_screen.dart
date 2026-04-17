// Provider Registration Screen
// شاشة تسجيل مزود خدمة جديد

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';
import '../services/services.dart';
import 'package:url_launcher/url_launcher.dart';

class ProviderRegistrationScreen extends StatefulWidget {
  final VoidCallback? onBack;

  const ProviderRegistrationScreen({super.key, this.onBack});

  @override
  State<ProviderRegistrationScreen> createState() =>
      _ProviderRegistrationScreenState();
}

class _ProviderRegistrationScreenState
    extends State<ProviderRegistrationScreen> {
  final ProvidersService _providersService = ProvidersService();

  String _selectedService = '';
  String _selectedExperience = '';
  bool _agreedToTerms = false;
  bool _isLoading = false;

  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();

  List<Map<String, String>> _services = [];
  bool _isLoadingServices = true;

  final List<Map<String, String>> _fallbackServices = [
    {
      'id': '1',
      'label': 'cleaning',
      'icon': '🧹',
    }, // IDs should match backend category IDs
    {'id': '2', 'label': 'plumbing', 'icon': '🔧'},
    {'id': '3', 'label': 'electricity', 'icon': '⚡'},
    {'id': '4', 'label': 'ac_repair', 'icon': '❄️'},
    {'id': '5', 'label': 'carpentry', 'icon': '🪚'},
    {'id': '6', 'label': 'painting', 'icon': '🎨'},
  ];

  @override
  void initState() {
    super.initState();
    _loadAvailableServices();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _loadAvailableServices() async {
    setState(() => _isLoadingServices = true);

    try {
      final response = await _providersService.getCategories();
      if (!mounted) return;

      if (response.success && response.data is List) {
        final rows = (response.data as List)
            .whereType<Map>()
            .map(
              (row) => Map<String, dynamic>.from(
                row.map((key, value) => MapEntry(key.toString(), value)),
              ),
            )
            .toList();

        final mapped = <Map<String, String>>[];
        final lang = Localizations.localeOf(context).languageCode.toLowerCase();
        for (final row in rows) {
          final id = (row['id'] ?? '').toString();
          if (id.isEmpty) continue;
          final nameAr = (row['name_ar'] ?? '').toString().trim();
          final nameEn = (row['name_en'] ?? row['name'] ?? '')
              .toString()
              .trim();
          final nameUr = (row['name_ur'] ?? '').toString().trim();
          final label = lang == 'ar'
              ? (nameAr.isNotEmpty ? nameAr : nameEn)
              : lang == 'ur'
              ? (nameUr.isNotEmpty
                    ? nameUr
                    : (nameAr.isNotEmpty ? nameAr : nameEn))
              : (nameEn.isNotEmpty ? nameEn : nameAr);
          if (label.isEmpty) continue;
          mapped.add({'id': id, 'label': label, 'icon': '🛠️'});
        }

        setState(() {
          _services = mapped.isNotEmpty ? mapped : List.of(_fallbackServices);
          _isLoadingServices = false;
        });
        return;
      }
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _services = List.of(_fallbackServices);
      _isLoadingServices = false;
    });
  }

  Future<void> _submitRegistration() async {
    if (!_validateInputs()) return;

    setState(() => _isLoading = true);

    try {
      final response = await _providersService.register(
        fullName: _nameController.text.trim(),
        phone: _phoneController.text.trim(),
        email: _emailController.text.trim().isNotEmpty
            ? _emailController.text.trim()
            : null,
        address: _addressController.text.trim(),
        serviceId: int.tryParse(
          _selectedService,
        ), // Assuming API expects integer ID
        experience: _selectedExperience,
        description: _descriptionController.text.trim(),
      );

      if (response.success) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('provider_registration_success')),
              backgroundColor: Colors.green,
            ),
          );
          Navigator.pop(context);
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                response.message ?? context.tr('provider_registration_failed'),
              ),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.tr('connection_error'))));
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  bool _validateInputs() {
    if (_nameController.text.trim().isEmpty) {
      _showError(context.tr('full_name_required'));
      return false;
    }
    if (_phoneController.text.trim().isEmpty) {
      _showError(context.tr('provider_phone_required'));
      return false;
    }
    if (_addressController.text.trim().isEmpty) {
      _showError(context.tr('provider_address_required'));
      return false;
    }
    if (_selectedService.isEmpty) {
      _showError(context.tr('provider_service_required'));
      return false;
    }
    if (_selectedExperience.isEmpty) {
      _showError(context.tr('provider_experience_required'));
      return false;
    }
    if (!_agreedToTerms) {
      _showError(context.tr('agree_terms_error'));
      return false;
    }
    return true;
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  Future<void> _contactSupportForDocuments() async {
    final phone = AppConfig.supportPhone.replaceAll(RegExp(r'[^\d+]'), '');
    final opened = await launchUrl(Uri.parse('tel:$phone'));
    if (!opened && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.tr('open_dialer_failed'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          // Header
          _buildHeader(),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Personal Information
                  _buildPersonalInfoCard()
                      .animate()
                      .fadeIn(delay: 200.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Service Selection
                  _buildServiceSelectionCard()
                      .animate()
                      .fadeIn(delay: 300.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Experience and Documents
                  _buildExperienceCard()
                      .animate()
                      .fadeIn(delay: 400.ms)
                      .slideY(begin: 0.1),

                  const SizedBox(height: 16),

                  // Terms
                  _buildTermsCard().animate().fadeIn(delay: 500.ms),

                  const SizedBox(height: 100),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _buildSubmitButton(),
    );
  }

  // Reuse existing build methods (unchanged UI components, simplified for brevity here)
  Widget _buildHeader() {
    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top + 16,
        left: 16,
        right: 16,
        bottom: 24,
      ),
      decoration: BoxDecoration(
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
            right: -50,
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
            bottom: -30,
            left: -30,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: Colors.purple.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
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
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('provider_register_title'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        context.tr('provider_register_subtitle'),
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Icon(
                      Icons.person_add,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    ).animate().slideY(begin: -0.5, end: 0, duration: 400.ms);
  }

  Widget _buildPersonalInfoCard() {
    return Container(
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
            context.tr('personal_information'),
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 16),
          _buildInputField(
            controller: _nameController,
            label: context.tr('full_name'),
            icon: Icons.person,
            hint: context.tr('provider_full_name_hint'),
          ),
          const SizedBox(height: 16),
          _buildInputField(
            controller: _phoneController,
            label: context.tr('phone_number'),
            icon: Icons.phone,
            hint: context.tr('provider_phone_hint'),
            isRtl: false,
          ),
          const SizedBox(height: 16),
          _buildInputField(
            controller: _emailController,
            label: context.tr('email'),
            icon: Icons.email,
            hint: context.tr('provider_email_hint'),
            isRtl: false,
          ),
          const SizedBox(height: 16),
          _buildInputField(
            controller: _addressController,
            label: context.tr('address'),
            icon: Icons.location_on,
            hint: context.tr('provider_address_hint'),
          ),
        ],
      ),
    );
  }

  Widget _buildInputField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    required String hint,
    bool isRtl = true,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: Colors.indigo, size: 16),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(color: AppColors.gray600, fontSize: 13),
            ),
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          textDirection: isRtl ? TextDirection.rtl : TextDirection.ltr,
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: AppColors.gray400),
            filled: true,
            fillColor: AppColors.gray50,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(color: AppColors.gray200),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(color: AppColors.gray200),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(color: Colors.indigo, width: 2),
            ),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 14,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildServiceSelectionCard() {
    return Container(
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
              Icon(Icons.work, color: Colors.indigo, size: 20),
              const SizedBox(width: 8),
              Text(
                context.tr('service_type'),
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_isLoadingServices)
            const Center(child: CircularProgressIndicator())
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _services.map((service) {
                final isSelected = _selectedService == service['id'];
                return GestureDetector(
                  onTap: () =>
                      setState(() => _selectedService = service['id']!),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? Colors.indigo.shade50
                          : AppColors.gray50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isSelected ? Colors.indigo : AppColors.gray200,
                        width: 2,
                      ),
                    ),
                    child: Column(
                      children: [
                        Text(
                          service['icon']!,
                          style: const TextStyle(fontSize: 24),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          context.tr(service['label']!),
                          style: TextStyle(
                            color: isSelected
                                ? Colors.indigo
                                : AppColors.gray600,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
        ],
      ),
    );
  }

  Widget _buildExperienceCard() {
    return Container(
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
            context.tr('provider_experience_documents'),
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 16),
          Text(
            context.tr('provider_experience_years'),
            style: TextStyle(color: AppColors.gray600, fontSize: 13),
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              color: AppColors.gray50,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.gray200),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                isExpanded: true,
                hint: Text(context.tr('provider_experience_select')),
                value: _selectedExperience.isEmpty ? null : _selectedExperience,
                items: [
                  DropdownMenuItem(
                    value: '1-2',
                    child: Text(context.tr('provider_experience_1_2')),
                  ),
                  DropdownMenuItem(
                    value: '3-5',
                    child: Text(context.tr('provider_experience_3_5')),
                  ),
                  DropdownMenuItem(
                    value: '5+',
                    child: Text(context.tr('provider_experience_5_plus')),
                  ),
                ],
                onChanged: (value) =>
                    setState(() => _selectedExperience = value ?? ''),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Icon(Icons.description, color: Colors.indigo, size: 16),
              const SizedBox(width: 8),
              Text(
                context.tr('provider_about_you'),
                style: TextStyle(color: AppColors.gray600, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _descriptionController,
            maxLines: 4,
            decoration: InputDecoration(
              hintText: context.tr('provider_about_you_hint'),
              hintStyle: TextStyle(color: AppColors.gray400),
              filled: true,
              fillColor: AppColors.gray50,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: AppColors.gray200),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: AppColors.gray200),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: Colors.indigo, width: 2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Icon(Icons.upload_file, color: Colors.indigo, size: 16),
              const SizedBox(width: 8),
              Text(
                context.tr('provider_documents_upload'),
                style: TextStyle(color: AppColors.gray600, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 8),
          GestureDetector(
            onTap: _contactSupportForDocuments,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 24),
              decoration: BoxDecoration(
                border: Border.all(
                  color: AppColors.gray300,
                  width: 2,
                  style: BorderStyle.solid,
                ),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                children: [
                  Icon(Icons.cloud_upload, color: AppColors.gray400, size: 32),
                  const SizedBox(height: 8),
                  Text(
                    context.tr('provider_documents_upload_hint'),
                    style: TextStyle(color: AppColors.gray600, fontSize: 13),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    context.tr('provider_documents_formats'),
                    style: TextStyle(color: AppColors.gray400, fontSize: 11),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTermsCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.indigo.shade50, Colors.purple.shade50],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 20,
            height: 20,
            child: Checkbox(
              value: _agreedToTerms,
              onChanged: (val) => setState(() => _agreedToTerms = val ?? false),
              activeColor: Colors.indigo,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _agreedToTerms = !_agreedToTerms),
              child: RichText(
                text: TextSpan(
                  style: TextStyle(
                    color: AppColors.gray700,
                    fontSize: 13,
                    height: 1.4,
                  ),
                  children: [
                    TextSpan(text: '${context.tr('i_agree_to')} '),
                    TextSpan(
                      text: context.tr('terms_and_conditions'),
                      style: TextStyle(
                        color: Colors.indigo,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    TextSpan(text: ' ${context.tr('provider_terms_suffix')}'),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return Container(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        bottom: MediaQuery.of(context).padding.bottom + 16,
        top: 16,
      ),
      decoration: BoxDecoration(
        color: AppColors.gray50.withValues(alpha: 0.9),
        border: Border(
          top: BorderSide(color: Colors.purple.withValues(alpha: 0.1)),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black12,
            blurRadius: 10,
            offset: Offset(0, -4),
          ),
        ],
      ),
      child: SizedBox(
        width: double.infinity,
        height: 56,
        child: ElevatedButton.icon(
          onPressed: _isLoading ? null : _submitRegistration,
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.transparent,
            shadowColor: Colors.transparent,
            padding: EdgeInsets.zero,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          icon: Container(),
          label: Ink(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [Colors.indigo, Colors.purple, Colors.pink],
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Container(
              width: double.infinity,
              height: 56,
              alignment: Alignment.center,
              child: _isLoading
                  ? const SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.check, color: Colors.white, size: 20),
                        const SizedBox(width: 8),
                        Text(
                          context.tr('submit_request'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
