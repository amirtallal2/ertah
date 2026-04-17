// Settings Screen
// شاشة الإعدادات

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../config/app_config.dart';
import '../providers/auth_provider.dart';
import 'wallet_screen.dart';
import 'addresses_screen.dart';
import 'rewards_screen.dart';
import 'complaints_list_screen.dart';
import 'share_screen.dart';
import 'about_screen.dart';
import '../services/app_localizations.dart';
import 'app_settings_screen.dart';
import 'help_center_screen.dart';
import 'orders_screen.dart';
import 'profile_screen.dart';

class SettingsScreen extends StatelessWidget {
  final VoidCallback? onProfileClick;
  final VoidCallback? onLogout;
  final VoidCallback? onProviderRegClick;

  const SettingsScreen({
    super.key,
    this.onProfileClick,
    this.onLogout,
    this.onProviderRegClick,
  });

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;

    final topCards = [
      {
        'id': 1,
        'title': context.tr('my_profile'),
        'icon': Icons.person,
        'color': AppColors.primary,
        'screen': const ProfileScreen(),
      },
      {
        'id': 2,
        'title': context.tr('my_orders'),
        'icon': Icons.history,
        'color': AppColors.secondary,
        'screen': const OrdersScreen(),
      },
      {
        'id': 3,
        'title': context.tr('help_center'),
        'icon': Icons.help_outline,
        'color': AppColors.primary,
        'screen': const HelpCenterScreen(),
      },
    ];

    final settingsOptions = [
      {
        'id': 1,
        'title': context.tr('wallet'),
        'icon': Icons.account_balance_wallet,
        'color': AppColors.primary,
        'bgColor': AppColors.primary.withValues(alpha: 0.1),
        'screen': const WalletScreen(),
      },
      {
        'id': 2,
        'title': context.tr('address_book'),
        'icon': Icons.location_on,
        'color': Colors.red,
        'bgColor': Colors.red.withValues(alpha: 0.1),
        'screen': const AddressesScreen(),
      },
      {
        'id': 3,
        'title': context.tr('rewards'),
        'icon': Icons.emoji_events,
        'color': Colors.amber,
        'bgColor': Colors.amber.withValues(alpha: 0.1),
        'screen': const RewardsScreen(),
      },
      {
        'id': 4,
        'title': context.tr('complaints'),
        'icon': Icons.chat_bubble,
        'color': Colors.pink,
        'bgColor': Colors.pink.withValues(alpha: 0.1),
        'screen': const ComplaintsListScreen(),
      },
      {
        'id': 5,
        'title': context.tr('share_app'),
        'icon': Icons.share,
        'color': Colors.cyan,
        'bgColor': Colors.cyan.withValues(alpha: 0.1),
        'screen': const ShareScreen(),
      },
      {
        'id': 6,
        'title': context.tr('settings'),
        'icon': Icons.settings,
        'color': AppColors.gray500,
        'bgColor': AppColors.gray100,
        'screen': const AppSettingsScreen(),
      },
      {
        'id': 7,
        'title': context.tr('about_app'),
        'icon': Icons.info,
        'color': Colors.teal,
        'bgColor': Colors.teal.withValues(alpha: 0.1),
        'screen': const AboutScreen(),
      },
      {
        'id': 8,
        'title': context.tr('logout'),
        'icon': Icons.logout,
        'color': Colors.red,
        'bgColor': Colors.red.withValues(alpha: 0.1),
        'showChevron': false,
      },
    ];

    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          // Profile Section with Gradient
          Container(
            decoration: BoxDecoration(gradient: AppColors.primaryGradient),
            child: SafeArea(
              bottom: false,
              child: Stack(
                children: [
                  // Decorative Circles
                  Positioned(
                    top: -20,
                    left: -20,
                    child: Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: -20,
                    right: -10,
                    child: Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),

                  // Profile Content
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: authProvider.isGuest
                        ? _buildGuestHeader(context, authProvider)
                        : _buildUserHeader(context, user, authProvider),
                  ),
                ],
              ),
            ),
          ),

          // Top Cards
          Transform.translate(
            offset: const Offset(0, -16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: topCards.asMap().entries.map((entry) {
                  final card = entry.value;
                  final index = entry.key;
                  return Expanded(
                    child: GestureDetector(
                      onTap: () {
                        if (card['screen'] != null) {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => card['screen'] as Widget,
                            ),
                          );
                        }
                      },
                      child:
                          Container(
                                margin: EdgeInsets.only(
                                  left: index < 2 ? 8 : 0,
                                ),
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  boxShadow: AppShadows.md,
                                ),
                                child: Column(
                                  children: [
                                    Container(
                                      width: 36,
                                      height: 36,
                                      decoration: BoxDecoration(
                                        color: (card['color'] as Color)
                                            .withValues(alpha: 0.1),
                                        shape: BoxShape.circle,
                                      ),
                                      child: Icon(
                                        card['icon'] as IconData,
                                        size: 20,
                                        color: card['color'] as Color,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      card['title'] as String,
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelSmall
                                          ?.copyWith(
                                            color: AppColors.gray700,
                                            fontWeight: FontWeight.w500,
                                          ),
                                    ),
                                  ],
                                ),
                              )
                              .animate()
                              .fadeIn(delay: (300 + index * 100).ms)
                              .slideY(begin: 0.2, end: 0),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),

          // Settings Options
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: settingsOptions.length,
              itemBuilder: (context, index) {
                final option = settingsOptions[index];

                return GestureDetector(
                  onTap: () {
                    if (option['id'] == 8) {
                      onLogout?.call();
                    } else if (option['screen'] != null) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => option['screen'] as Widget,
                        ),
                      );
                    }
                  },
                  child:
                      Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: AppShadows.sm,
                            ),
                            child: Row(
                              children: [
                                // Icon
                                Container(
                                  width: 44,
                                  height: 44,
                                  decoration: BoxDecoration(
                                    color: option['bgColor'] as Color,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Icon(
                                    option['icon'] as IconData,
                                    size: 22,
                                    color: option['color'] as Color,
                                  ),
                                ),

                                const SizedBox(width: 16),

                                // Title
                                Expanded(
                                  child: Text(
                                    option['title'] as String,
                                    style: Theme.of(context).textTheme.bodyLarge
                                        ?.copyWith(color: AppColors.gray800),
                                  ),
                                ),
                              ],
                            ),
                          )
                          .animate()
                          .fadeIn(delay: (400 + index * 50).ms)
                          .slideX(begin: 0.05, end: 0),
                );
              },
            ),
          ),

          // Version Info
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              '${context.tr('version_label')} 1.0.0',
              style: Theme.of(
                context,
              ).textTheme.labelSmall?.copyWith(color: AppColors.gray400),
              textAlign: TextAlign.center,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGuestHeader(BuildContext context, AuthProvider authProvider) {
    return Row(
      children: [
        // Guest Icon
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
            color: Colors.white.withValues(alpha: 0.2),
          ),
          child: const Icon(
            Icons.person_outline,
            color: Colors.white,
            size: 28,
          ),
        ).animate().fadeIn().scale(
          begin: const Offset(0.8, 0.8),
          end: const Offset(1, 1),
        ),

        const SizedBox(width: 12),

        // Guest Info
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    context.tr('guest_user'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      context.tr('guest_label'),
                      style: const TextStyle(
                        fontSize: 10,
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                context.tr('login_to_benefit'),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.9),
                ),
              ),
            ],
          ),
        ).animate().fadeIn(delay: 100.ms).slideX(begin: -0.1, end: 0),

        // Login Button
        ElevatedButton(
          onPressed: () => authProvider.logout(),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.white,
            foregroundColor: AppColors.primary,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
          ),
          child: Text(
            context.tr('login'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
          ),
        ).animate().fadeIn(delay: 200.ms),
      ],
    );
  }

  Widget _buildUserHeader(
    BuildContext context,
    user,
    AuthProvider authProvider,
  ) {
    return Row(
      children: [
        // Profile Image
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
            boxShadow: AppShadows.md,
          ),
          child: ClipOval(
            child: user?.avatar != null
                ? CachedNetworkImage(
                    imageUrl: AppConfig.fixMediaUrl(user!.avatar!),
                    fit: BoxFit.cover,
                    errorWidget: (context, url, error) => Container(
                      color: Colors.white,
                      child: const Icon(Icons.person, color: AppColors.gray400),
                    ),
                  )
                : Container(
                    color: Colors.white,
                    child: CachedNetworkImage(
                      imageUrl:
                          'https://images.pexels.com/photos/614810/pexels-photo-614810.jpeg?auto=compress&cs=tinysrgb?w=200',
                      fit: BoxFit.cover,
                      errorWidget: (context, url, error) => const Icon(
                        Icons.person,
                        color: AppColors.gray400,
                        size: 30,
                      ),
                    ),
                  ),
          ),
        ).animate().fadeIn().scale(
          begin: const Offset(0.8, 0.8),
          end: const Offset(1, 1),
        ),

        const SizedBox(width: 12),

        // User Info
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                user?.fullName ?? context.tr('user'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                user?.phone ?? '',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.9),
                ),
              ),
            ],
          ),
        ).animate().fadeIn(delay: 100.ms).slideX(begin: -0.1, end: 0),

        // Edit Button
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.2),
            shape: BoxShape.circle,
          ),
          child: IconButton(
            onPressed: onProfileClick,
            icon: const Icon(
              Icons.edit_outlined,
              size: 14,
              color: Colors.white,
            ),
            padding: EdgeInsets.zero,
          ),
        ).animate().fadeIn(delay: 200.ms),
      ],
    );
  }
}
