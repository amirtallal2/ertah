import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../services/providers_service.dart';
import '../services/app_localizations.dart';

class ProviderProfileSetupScreen extends StatefulWidget {
  final VoidCallback onComplete;

  const ProviderProfileSetupScreen({super.key, required this.onComplete});

  @override
  State<ProviderProfileSetupScreen> createState() =>
      _ProviderProfileSetupScreenState();
}

class _ProviderProfileSetupScreenState
    extends State<ProviderProfileSetupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _fullNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _whatsappController = TextEditingController();
  final _countryController = TextEditingController();
  final _cityController = TextEditingController();
  final _districtController = TextEditingController();
  final _experienceController = TextEditingController();
  final _bioController = TextEditingController();
  final ProvidersService _providersService = ProvidersService();
  final ImagePicker _imagePicker = ImagePicker();

  List<Map<String, dynamic>> _categories = [];
  final Set<int> _selectedCategoryIds = <int>{};
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _pickedAvatarPath;
  String? _pickedResidencyPath;
  String? _pickedAjeerPath;
  String? _existingAvatarRaw;
  String? _existingAvatarUrl;
  String? _existingResidencyRaw;
  String? _existingResidencyUrl;
  String? _existingAjeerRaw;
  String? _existingAjeerUrl;
  bool _categoriesLocked = false;

  bool get _hasExistingAvatar {
    final raw = (_existingAvatarRaw ?? '').trim();
    if (raw.isEmpty) return false;

    final lowered = raw.toLowerCase();
    if (lowered == 'null' || lowered == 'undefined') return false;
    final normalized = lowered
        .replaceAll('\\', '/')
        .replaceAll(RegExp(r'^/+|/+$'), '');
    if (normalized == 'default-provider.png' ||
        normalized.endsWith('/default-provider.png')) {
      return false;
    }

    return true;
  }

  bool get _hasExistingResidency {
    final raw = (_existingResidencyRaw ?? '').trim();
    if (raw.isEmpty) return false;

    final lowered = raw.toLowerCase();
    if (lowered == 'null' || lowered == 'undefined') return false;
    return true;
  }

  bool get _hasExistingAjeer {
    final raw = (_existingAjeerRaw ?? '').trim();
    if (raw.isEmpty) return false;

    final lowered = raw.toLowerCase();
    if (lowered == 'null' || lowered == 'undefined') return false;
    return true;
  }

  bool _isNoChangesMessage(String? message) {
    final normalized = (message ?? '').trim().toLowerCase();
    if (normalized.isEmpty) {
      return false;
    }

    return normalized.contains('no data to update') ||
        normalized.contains('nothing to update') ||
        normalized.contains('لا توجد تغييرات للحفظ');
  }

  List<int> _extractCategoryIds(dynamic raw) {
    if (raw is List) {
      return raw
          .map((item) => int.tryParse(item.toString()) ?? 0)
          .where((id) => id != 0)
          .toList();
    }

    final normalized = raw?.toString().trim() ?? '';
    if (normalized.isEmpty) {
      return const <int>[];
    }

    return normalized
        .split(',')
        .map((item) => int.tryParse(item.trim()) ?? 0)
        .where((id) => id != 0)
        .toList();
  }

  @override
  void initState() {
    super.initState();
    _loadInitialData();
  }

  @override
  void dispose() {
    _fullNameController.dispose();
    _emailController.dispose();
    _whatsappController.dispose();
    _countryController.dispose();
    _cityController.dispose();
    _districtController.dispose();
    _experienceController.dispose();
    _bioController.dispose();
    super.dispose();
  }

  Future<void> _loadInitialData() async {
    setState(() => _isLoading = true);

    final authProvider = context.read<AuthProvider>();
    await authProvider.refreshUser();

    final profile = authProvider.providerProfile ?? <String, dynamic>{};
    _fullNameController.text = (profile['full_name'] ?? '').toString();
    _emailController.text = (profile['email'] ?? '').toString();
    _whatsappController.text =
        (profile['whatsapp_number'] ?? profile['phone'] ?? '').toString();
    _countryController.text = (profile['country'] ?? '').toString();
    _cityController.text = (profile['city'] ?? '').toString();
    _districtController.text = (profile['district'] ?? '').toString();
    final exp = (profile['experience_years'] ?? 0).toString();
    _experienceController.text = exp == '0' ? '' : exp;
    _bioController.text = (profile['bio'] ?? '').toString();
    _existingAvatarRaw = (profile['avatar'] ?? '').toString().trim();
    _existingAvatarUrl = _hasExistingAvatar
        ? AppConfig.fixMediaUrl(_existingAvatarRaw)
        : null;
    _existingResidencyRaw =
        (profile['residency_document_path'] ??
                profile['residency_document'] ??
                '')
            .toString()
            .trim();
    _existingResidencyUrl = _hasExistingResidency
        ? AppConfig.fixMediaUrl(_existingResidencyRaw)
        : null;
    _existingAjeerRaw =
        (profile['ajeer_certificate_path'] ??
                profile['ajeer_certificate'] ??
                '')
            .toString()
            .trim();
    _existingAjeerUrl = _hasExistingAjeer
        ? AppConfig.fixMediaUrl(_existingAjeerRaw)
        : null;

    _selectedCategoryIds
      ..clear()
      ..addAll(_extractCategoryIds(profile['category_ids']));
    _categoriesLocked =
        profile['categories_locked'] == true ||
        profile['categories_locked'] == 1;
    if (!_categoriesLocked) {
      final profileCompleted =
          profile['profile_completed'] == true ||
          profile['profile_completed'] == 1;
      if (profileCompleted && _selectedCategoryIds.isNotEmpty) {
        _categoriesLocked = true;
      }
    }

    final categoriesResponse = await _providersService.getCategories(
      rootOnly: true,
    );
    if (categoriesResponse.success && categoriesResponse.data is List) {
      _categories = (categoriesResponse.data as List)
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where((item) => (item['id'] ?? 0) is int || item['id'] != null)
          .toList();

      if (!_categoriesLocked) {
        final visibleIds = _categories
            .map((item) => int.tryParse('${item['id']}') ?? 0)
            .where((id) => id > 0)
            .toSet();
        _selectedCategoryIds.removeWhere((id) => !visibleIds.contains(id));
      }
    }

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _pickAvatar() async {
    final XFile? file = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 80,
      maxWidth: 1280,
    );
    if (file == null) return;
    if (!mounted) return;
    setState(() => _pickedAvatarPath = file.path);
  }

  Future<void> _pickResidencyDocument() async {
    final XFile? file = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 1600,
    );
    if (file == null) return;
    if (!mounted) return;
    setState(() => _pickedResidencyPath = file.path);
  }

  Future<void> _pickAjeerCertificate() async {
    final XFile? file = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 1600,
    );
    if (file == null) return;
    if (!mounted) return;
    setState(() => _pickedAjeerPath = file.path);
  }

  Future<void> _submit() async {
    if (_isSubmitting) return;
    if (!_formKey.currentState!.validate()) return;

    if (_pickedAvatarPath == null && !_hasExistingAvatar) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('provider_avatar_hint'))),
      );
      return;
    }
    if (_pickedResidencyPath == null && !_hasExistingResidency) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('provider_residency_required'))),
      );
      return;
    }
    if (!_categoriesLocked && _selectedCategoryIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('provider_select_category_required')),
        ),
      );
      return;
    }

    final experienceYearsRaw = int.tryParse(_experienceController.text.trim());
    final experienceYears = experienceYearsRaw != null && experienceYearsRaw > 0
        ? experienceYearsRaw
        : 0;

    setState(() => _isSubmitting = true);
    final authProvider = context.read<AuthProvider>();

    final city = _cityController.text.trim();
    final country = _countryController.text.trim();
    final district = _districtController.text.trim();
    final bio = _bioController.text.trim();
    final categories = _selectedCategoryIds.toList()..sort();

    final response = await authProvider.completeProviderProfile(
      fullName: _fullNameController.text.trim(),
      email: _emailController.text.trim(),
      whatsappNumber: _whatsappController.text.trim(),
      city: city,
      country: country,
      district: district,
      bio: bio,
      experienceYears: experienceYears,
      categoryIds: _categoriesLocked ? null : categories,
      avatarPath: _pickedAvatarPath,
      residencyDocumentPath: _pickedResidencyPath,
      ajeerCertificatePath: _pickedAjeerPath,
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    final noChanges = _isNoChangesMessage(response.message);

    if (!response.success && !noChanges) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(response.message ?? context.tr('save_changes_failed')),
        ),
      );
      return;
    }

    if (noChanges) {
      await authProvider.refreshUser();
      if (!mounted) return;
    }

    if (authProvider.needsProfileCompletion) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('provider_profile_incomplete'))),
      );
      return;
    }

    widget.onComplete();
  }

  Widget _buildAvatarCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.gray50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: AppColors.gray200,
            backgroundImage:
                _pickedAvatarPath == null &&
                    _existingAvatarUrl != null &&
                    _existingAvatarUrl!.trim().isNotEmpty
                ? NetworkImage(_existingAvatarUrl!)
                : null,
            child: _pickedAvatarPath != null
                ? const Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 28,
                  )
                : (_existingAvatarUrl == null || _existingAvatarUrl!.isEmpty)
                ? const Icon(Icons.person, color: AppColors.gray500)
                : null,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              _pickedAvatarPath != null
                  ? context.tr('provider_avatar_selected')
                  : context.tr('provider_avatar_hint'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
          OutlinedButton(
            onPressed: _isSubmitting ? null : _pickAvatar,
            child: Text(context.tr('choose')),
          ),
        ],
      ),
    );
  }

  Widget _buildResidencyCard() {
    final hasExisting =
        _existingResidencyUrl != null &&
        _existingResidencyUrl!.trim().isNotEmpty;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.gray50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.gray200),
            ),
            child: _pickedResidencyPath != null
                ? const Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 28,
                  )
                : hasExisting
                ? const Icon(
                    Icons.description_outlined,
                    color: AppColors.primaryDark,
                    size: 28,
                  )
                : const Icon(
                    Icons.badge_outlined,
                    color: AppColors.gray500,
                    size: 28,
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              _pickedResidencyPath != null
                  ? context.tr('provider_residency_selected')
                  : hasExisting
                  ? context.tr('provider_residency_uploaded')
                  : context.tr('provider_residency_hint'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
          OutlinedButton(
            onPressed: _isSubmitting ? null : _pickResidencyDocument,
            child: Text(context.tr('choose')),
          ),
        ],
      ),
    );
  }

  Widget _buildAjeerCard() {
    final hasExisting =
        _existingAjeerUrl != null && _existingAjeerUrl!.trim().isNotEmpty;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.gray50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.gray200),
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.gray200),
            ),
            child: _pickedAjeerPath != null
                ? const Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 28,
                  )
                : hasExisting
                ? const Icon(
                    Icons.verified_outlined,
                    color: AppColors.primaryDark,
                    size: 28,
                  )
                : const Icon(
                    Icons.verified_outlined,
                    color: AppColors.gray500,
                    size: 28,
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              _pickedAjeerPath != null
                  ? context.tr('provider_ajeer_selected')
                  : hasExisting
                  ? context.tr('provider_ajeer_uploaded')
                  : context.tr('provider_ajeer_hint'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
          OutlinedButton(
            onPressed: _isSubmitting ? null : _pickAjeerCertificate,
            child: Text(context.tr('choose')),
          ),
        ],
      ),
    );
  }

  Widget _buildCategories() {
    if (_categories.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.warningLight,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(context.tr('provider_no_categories')),
      );
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: _categories.map((category) {
        final id = int.tryParse('${category['id']}') ?? 0;
        final selected = _selectedCategoryIds.contains(id);
        return FilterChip(
          label: Text(
            (category['name_ar'] ?? context.tr('provider_category')).toString(),
          ),
          labelStyle: TextStyle(
            color: selected ? AppColors.gray900 : AppColors.gray800,
            fontWeight: FontWeight.w700,
          ),
          selectedColor: AppColors.primary,
          backgroundColor: AppColors.white,
          checkmarkColor: AppColors.gray900,
          side: BorderSide(
            color: selected ? AppColors.primaryDark : AppColors.gray800,
            width: 1.2,
          ),
          selected: selected,
          onSelected: _isSubmitting || _categoriesLocked
              ? null
              : (value) {
                  setState(() {
                    if (value) {
                      _selectedCategoryIds.add(id);
                    } else {
                      _selectedCategoryIds.remove(id);
                    }
                  });
                },
        );
      }).toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('provider_profile_setup_title'))),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SafeArea(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 640),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            context.tr('provider_profile_setup_intro'),
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 16),
                          _buildAvatarCard(),
                          const SizedBox(height: 16),
                          _buildResidencyCard(),
                          const SizedBox(height: 16),
                          _buildAjeerCard(),
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _fullNameController,
                            enabled: !_isSubmitting,
                            decoration: InputDecoration(
                              labelText: context.tr('full_name'),
                            ),
                            validator: (value) {
                              if ((value ?? '').trim().isEmpty) {
                                return context.tr('full_name_required');
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _emailController,
                            enabled: !_isSubmitting,
                            decoration: InputDecoration(
                              labelText: context.tr('provider_email_optional'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _whatsappController,
                            enabled: !_isSubmitting,
                            keyboardType: TextInputType.phone,
                            decoration: InputDecoration(
                              labelText: context.tr('provider_whatsapp_label'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _countryController,
                            enabled: !_isSubmitting,
                            decoration: InputDecoration(
                              labelText: context.tr('country'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _cityController,
                            enabled: !_isSubmitting,
                            decoration: InputDecoration(
                              labelText: context.tr('city'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _districtController,
                            enabled: !_isSubmitting,
                            decoration: InputDecoration(
                              labelText: context.tr(
                                'provider_district_optional',
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _experienceController,
                            enabled: !_isSubmitting,
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(
                              labelText: context.tr(
                                'provider_experience_years',
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _bioController,
                            enabled: !_isSubmitting,
                            minLines: 3,
                            maxLines: 5,
                            decoration: InputDecoration(
                              labelText: context.tr('provider_about_you'),
                              alignLabelWithHint: true,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            context.tr('provider_categories_prompt'),
                            style: Theme.of(context).textTheme.labelLarge,
                          ),
                          const SizedBox(height: 8),
                          _buildCategories(),
                          if (_categoriesLocked) ...[
                            const SizedBox(height: 8),
                            Text(
                              context.tr('provider_categories_locked'),
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(color: AppColors.warning),
                            ),
                          ],
                          const SizedBox(height: 24),
                          SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: AppColors.gray900,
                              ),
                              onPressed: _isSubmitting ? null : _submit,
                              child: _isSubmitting
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: AppColors.gray900,
                                      ),
                                    )
                                  : Text(context.tr('provider_save_continue')),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
    );
  }
}
