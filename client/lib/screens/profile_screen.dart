// Profile Screen
// شاشة الملف الشخصي

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'dart:io';
import 'package:image_picker/image_picker.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../services/services.dart';
import '../config/app_config.dart';
import '../services/app_localizations.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final UserService _userService = UserService();

  late TextEditingController _nameController;
  late TextEditingController _emailController;
  late TextEditingController _phoneController;
  late TextEditingController _cityController;
  late TextEditingController _countryController;

  bool _isSaving = false;
  String? _avatarUrl;
  Map<String, dynamic>? _stats;
  File? _pickedImage;
  String _membershipLevel = 'silver';

  String? _sanitizeAvatarUrl(dynamic rawAvatar) {
    final avatar = (rawAvatar ?? '').toString().trim();
    if (avatar.isEmpty) {
      return null;
    }

    final lowered = avatar.toLowerCase();
    final normalized = lowered
        .replaceAll('\\', '/')
        .replaceAll(RegExp(r'^/+|/+$'), '');
    if (lowered == 'null' ||
        lowered == 'undefined' ||
        normalized == 'default-user.png' ||
        normalized.endsWith('/default-user.png')) {
      return null;
    }

    return avatar;
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

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: ImageSource.gallery);

    if (pickedFile != null && mounted) {
      setState(() {
        _pickedImage = File(pickedFile.path);
      });
    }
  }

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameController = TextEditingController(text: user?.fullName ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
    _phoneController = TextEditingController(text: user?.phone ?? '');
    _cityController = TextEditingController();
    _countryController = TextEditingController();
    _avatarUrl = _sanitizeAvatarUrl(user?.avatar);
    _membershipLevel = user?.membershipLevel ?? 'silver';

    _fetchProfile();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _cityController.dispose();
    _countryController.dispose();
    super.dispose();
  }

  Future<void> _fetchProfile() async {
    try {
      final response = await _userService.getProfile();
      if (response.success && response.data is Map<String, dynamic>) {
        final data = response.data as Map<String, dynamic>;
        if (!mounted) return;

        _applyProfileData(data);

        await context.read<AuthProvider>().refreshUser();
      } else if (response.isUnauthorized) {
        if (!mounted) return;
        await context.read<AuthProvider>().logout();
      }
    } catch (e) {
      // No-op: keep existing profile data in UI on transient failures.
    }
  }

  void _applyProfileData(Map<String, dynamic> data) {
    if (!mounted) return;

    setState(() {
      _nameController.text = (data['full_name'] ?? '').toString();
      _emailController.text = (data['email'] ?? '').toString();
      _phoneController.text = (data['phone'] ?? '').toString();
      _cityController.text = (data['city'] ?? '').toString();
      _countryController.text = (data['country'] ?? '').toString();
      _avatarUrl = _sanitizeAvatarUrl(data['avatar']);
      _membershipLevel = (data['membership_level'] ?? _membershipLevel)
          .toString();
      _stats = {
        'completed_orders': data['completed_orders_count'] ?? 0,
        'rating': data['rating'] ?? 0.0,
        'points': data['points'] ?? 0,
      };
      _pickedImage = null;
    });
  }

  Future<void> _handleSave() async {
    final fullName = _nameController.text.trim();
    final email = _emailController.text.trim();
    final phone = _phoneController.text.trim();
    final city = _cityController.text.trim();
    final country = _countryController.text.trim();

    if (fullName.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.tr('full_name_required')),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    if (email.isNotEmpty &&
        !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('صيغة البريد الإلكتروني غير صحيحة'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    setState(() => _isSaving = true);

    try {
      final response = await _userService.updateProfile(
        fullName: fullName,
        email: email,
        phone: phone,
        city: city,
        country: country,
        avatarPath: _pickedImage?.path,
      );

      final noChanges = _isNoChangesMessage(response.message);

      if (response.success || noChanges) {
        if (response.data is Map<String, dynamic>) {
          _applyProfileData(response.data as Map<String, dynamic>);
        } else if (noChanges) {
          await _fetchProfile();
        }

        // Update AuthProvider to reflect changes everywhere
        if (mounted) {
          final authProvider = context.read<AuthProvider>();
          await authProvider.refreshUser();

          if (!mounted) return;

          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                noChanges
                    ? 'لا توجد تغييرات للحفظ'
                    : context.tr('changes_saved_successfully'),
              ),
              backgroundColor: noChanges ? Colors.orange : Colors.green,
            ),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                response.message ?? context.tr('save_changes_failed'),
              ),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.tr('connection_error')),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);
    final keyboardInset = mediaQuery.viewInsets.bottom;

    // Fallback stats if not loaded yet
    final completedOrders = _stats?['completed_orders'] ?? 0;
    final rating = _stats?['rating'] ?? 5.0; // Default reasonable rating
    final points = _stats?['points'] ?? 0;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: AppColors.gray50,
      body: Stack(
        children: [
          // Content
          SingleChildScrollView(
            padding: EdgeInsets.only(
              bottom: 100 + (keyboardInset > 0 ? keyboardInset : 0),
            ),
            child: Column(
              children: [
                _buildHeader(),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      // Form
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: AppShadows.sm,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.tr('personal_information'),
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 16),
                            _buildTextField(
                              context.tr('full_name'),
                              Icons.person_outline,
                              _nameController,
                            ),
                            const SizedBox(height: 16),
                            _buildTextField(
                              context.tr('email'),
                              Icons.email_outlined,
                              _emailController,
                            ),
                            const SizedBox(height: 16),
                            _buildTextField(
                              context.tr('phone_number'),
                              Icons.phone_outlined,
                              _phoneController,
                            ),
                            const SizedBox(height: 16),
                            _buildTextField(
                              context.tr('city'),
                              Icons.location_city_outlined,
                              _cityController,
                            ),
                            const SizedBox(height: 16),
                            _buildTextField(
                              context.tr('country'),
                              Icons.flag_outlined,
                              _countryController,
                            ),
                          ],
                        ),
                      ).animate().fadeIn().slideY(begin: 0.1, end: 0),

                      const SizedBox(height: 16),

                      // Stats
                      Row(
                        children: [
                          Expanded(
                            child: _buildStatCard(
                              '$completedOrders',
                              context.tr('completed_order'),
                              Colors.orange,
                              Icons.check_circle_outline,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildStatCard(
                              '$rating',
                              context.tr('rating'),
                              Colors.green,
                              Icons.star_outline,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildStatCard(
                              '$points',
                              context.tr('points'),
                              const Color(0xFF7466ED),
                              Icons.card_giftcard,
                            ),
                          ),
                        ],
                      ).animate().fadeIn(delay: 200.ms),

                      const SizedBox(height: 16),

                      // Premium Badge
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: _isPremiumMember
                                ? const [Color(0xFFFBCC26), Color(0xFFF5C01F)]
                                : const [Color(0xFF94A3B8), Color(0xFF64748B)],
                          ),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: AppShadows.md,
                        ),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.2),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.verified,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _isPremiumMember
                                        ? context.tr('premium_membership')
                                        : context.tr('standard_membership'),
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  Text(
                                    _isPremiumMember
                                        ? context.tr('enjoy_exclusive_benefits')
                                        : context.tr(
                                            'upgrade_from_admin_panel',
                                          ),
                                    style: const TextStyle(
                                      color: Colors.white70,
                                      fontSize: 11,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ).animate().fadeIn(delay: 400.ms),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Bottom Button
          AnimatedPositioned(
            duration: const Duration(milliseconds: 180),
            curve: Curves.easeOut,
            bottom: keyboardInset > 0 ? keyboardInset : 0,
            left: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.9),
                border: Border(
                  top: BorderSide(color: Colors.grey.withValues(alpha: 0.1)),
                ),
              ),
              child: SafeArea(
                // Check for notches
                top: false,
                child: SizedBox(
                  height: 56,
                  child: ElevatedButton(
                    onPressed: _isSaving ? null : _handleSave,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFBCC26),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: _isSaving
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 2,
                            ),
                          )
                        : Text(
                            context.tr('save_changes'),
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.only(top: 60, bottom: 20, left: 16, right: 16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
        ),
        borderRadius: const BorderRadius.only(
          bottomLeft: Radius.circular(32),
          bottomRight: Radius.circular(32),
        ),
        boxShadow: AppShadows.md,
      ),
      child: Column(
        children: [
          Row(
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
              Expanded(
                child: Text(
                  context.tr('my_profile'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
              const SizedBox(width: 40), // Spacer
            ],
          ),
          const SizedBox(height: 20),
          Stack(
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 3),
                  boxShadow: AppShadows.lg,
                ),
                child: ClipOval(
                  child: _pickedImage != null
                      ? Image.file(_pickedImage!, fit: BoxFit.cover)
                      : _avatarUrl != null && _avatarUrl!.trim().isNotEmpty
                      ? CachedNetworkImage(
                          imageUrl: AppConfig.fixMediaUrl(_avatarUrl!),
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => Image.network(
                            "https://images.pexels.com/photos/614810/pexels-photo-614810.jpeg?auto=compress&cs=tinysrgb?w=400",
                            fit: BoxFit.cover,
                          ),
                        )
                      : Image.network(
                          "https://images.pexels.com/photos/614810/pexels-photo-614810.jpeg?auto=compress&cs=tinysrgb?w=400",
                          fit: BoxFit.cover,
                        ),
                ),
              ),
              Positioned(
                bottom: 0,
                right: 0,
                child: InkWell(
                  onTap: _pickImage,
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: AppShadows.sm,
                    ),
                    child: const Icon(
                      Icons.camera_alt,
                      color: Color(0xFFFBCC26),
                      size: 16,
                    ),
                  ),
                ),
              ),
            ],
          ).animate().scale(),
          const SizedBox(height: 12),
          Text(
            _nameController.text.isNotEmpty
                ? _nameController.text
                : context.tr('user'),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
          Text(
            _membershipLabel,
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
          Text(
            '${context.tr('user_id')}: ${context.read<AuthProvider>().user?.id ?? "-"}',
            style: const TextStyle(color: Colors.white70, fontSize: 10),
          ),
        ],
      ),
    );
  }

  Widget _buildTextField(
    String label,
    IconData icon,
    TextEditingController controller, {
    bool readOnly = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 16, color: const Color(0xFF7466ED)),
            const SizedBox(width: 8),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: AppColors.gray600),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: readOnly ? AppColors.gray50 : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.gray200),
          ),
          child: TextField(
            controller: controller,
            readOnly: readOnly,
            style: TextStyle(
              color: readOnly ? AppColors.gray500 : AppColors.gray800,
            ),
            decoration: const InputDecoration(
              border: InputBorder.none,
              contentPadding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 12,
              ),
            ),
          ),
        ),
      ],
    );
  }

  bool get _isPremiumMember {
    final normalized = _membershipLevel.trim().toLowerCase();
    return normalized == 'gold' ||
        normalized == 'platinum' ||
        normalized == 'premium' ||
        normalized == 'vip';
  }

  String get _membershipLabel {
    final normalized = _membershipLevel.trim().toLowerCase();
    switch (normalized) {
      case 'gold':
        return context.tr('membership_level_gold');
      case 'platinum':
        return context.tr('membership_level_platinum');
      case 'premium':
        return context.tr('membership_level_premium');
      case 'vip':
        return context.tr('membership_level_vip');
      default:
        return context.tr('membership_level_standard');
    }
  }

  Widget _buildStatCard(
    String value,
    String label,
    Color color,
    IconData icon,
  ) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
          Text(
            label,
            style: const TextStyle(color: AppColors.gray500, fontSize: 10),
          ),
        ],
      ),
    );
  }
}
