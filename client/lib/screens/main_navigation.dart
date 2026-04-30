// Main Navigation Shell
// الإطار الرئيسي للتنقل

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/app_settings_provider.dart';
import '../config/app_theme.dart';
import 'home_screen.dart';
import 'orders_screen.dart';
import 'offers_screen.dart';
import 'settings_screen.dart';
import 'all_services_screen.dart';
import 'all_stores_screen.dart';
import 'most_requested_services_screen.dart';
import 'best_offers_screen.dart';
import 'profile_screen.dart';
import 'all_spares_screen.dart';
import 'service_selection_screen.dart';
import 'darfix_ai_chat_screen.dart';

import '../services/app_localizations.dart';

class MainNavigation extends StatefulWidget {
  const MainNavigation({super.key});

  @override
  State<MainNavigation> createState() => _MainNavigationState();
}

class _MainNavigationState extends State<MainNavigation> {
  int _currentIndex = 0;

  _BottomNavLayout _bottomNavLayout(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);
    final size = mediaQuery.size;
    final shortestSide = size.shortestSide;
    final isSmallScreen = size.height < 700 || shortestSide < 360;
    final hasBottomInset = mediaQuery.padding.bottom > 0;

    return _BottomNavLayout(
      iconBoxSize: isSmallScreen ? 40 : 44,
      iconSize: isSmallScreen ? 20 : 22,
      labelFontSize: isSmallScreen ? 8.5 : 9,
      labelTopSpacing: isSmallScreen ? 5 : 6,
      topPadding: isSmallScreen ? 8 : 10,
      bottomPadding: hasBottomInset ? 4 : (isSmallScreen ? 8 : 10),
      safeBottomGap: hasBottomInset ? 0 : 2,
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDarfixAiEnabled = context.select<AppSettingsProvider, bool>(
      (provider) => provider.isInitialized && provider.isDarfixAiEnabled,
    );

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: [
          HomeScreen(
            onProfileClick: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (context) => const ProfileScreen()),
              );
            },
            onServiceClick: (service) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) =>
                      ServiceSelectionScreen(service: service),
                ),
              );
            },
            onViewAllServices: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const AllServicesScreen(),
                ),
              );
            },
            onViewAllStores: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const AllStoresScreen(),
                ),
              );
            },
            onViewMostRequested: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const MostRequestedServicesScreen(),
                ),
              );
            },
            onBannerClick: () {
              // Open Best Offers on banner click for now
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const BestOffersScreen(),
                ),
              );
            },
            onViewAllSpares: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) =>
                      AllSparesScreen(onBack: () => Navigator.pop(context)),
                ),
              );
            },
          ),
          OrdersScreen(
            isActiveTab: _currentIndex == 1,
            onGoHome: () => setState(() => _currentIndex = 0),
          ),
          const OffersScreen(), // Assuming this points to a tab, not the full screen
          SettingsScreen(
            onProfileClick: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (context) => const ProfileScreen()),
              );
            },
            onLogout: () {
              context.read<AuthProvider>().logout();
            },
          ),
        ],
      ),
      floatingActionButton: _currentIndex == 0 && isDarfixAiEnabled
          ? Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: _DarfixAiFab(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const DarfixAiChatScreen(),
                    ),
                  );
                },
              ),
            )
          : null,
      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
      bottomNavigationBar: _buildBottomNavBar(),
    );
  }

  Widget _buildBottomNavBar() {
    final navLayout = _bottomNavLayout(context);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.1),
            blurRadius: 30,
            offset: const Offset(0, -4),
          ),
        ],
        border: Border(
          top: BorderSide(
            color: AppColors.gray200.withValues(alpha: 0.5),
            width: 1,
          ),
        ),
      ),
      child: SafeArea(
        top: false,
        minimum: EdgeInsets.only(bottom: navLayout.safeBottomGap),
        child: Container(
          padding: EdgeInsets.fromLTRB(
            8,
            navLayout.topPadding,
            8,
            navLayout.bottomPadding,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _buildNavItem(
                0,
                Icons.home_rounded,
                context.tr('home'),
                navLayout,
              ),
              _buildNavItem(
                1,
                Icons.receipt_long_rounded,
                context.tr('orders'),
                navLayout,
              ),
              _buildNavItem(
                2,
                Icons.local_offer_rounded,
                context.tr('offers'),
                navLayout,
              ),
              _buildNavItem(
                3,
                Icons.settings_rounded,
                context.tr('settings'),
                navLayout,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(
    int index,
    IconData icon,
    String label,
    _BottomNavLayout navLayout,
  ) {
    final isActive = _currentIndex == index;

    return GestureDetector(
      onTap: () => setState(() => _currentIndex = index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              width: navLayout.iconBoxSize,
              height: navLayout.iconBoxSize,
              decoration: BoxDecoration(
                gradient: isActive
                    ? LinearGradient(
                        colors: [
                          AppColors.primary,
                          AppColors.primary.withValues(alpha: 0.85),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : LinearGradient(
                        colors: [AppColors.gray100, AppColors.gray50],
                      ),
                borderRadius: BorderRadius.circular(14),
                boxShadow: isActive
                    ? [
                        BoxShadow(
                          color: AppColors.primary.withValues(alpha: 0.4),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ]
                    : null,
              ),
              child: Icon(
                icon,
                size: navLayout.iconSize,
                color: isActive ? Colors.white : AppColors.gray400,
              ),
            ),
            SizedBox(height: navLayout.labelTopSpacing),
            AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              style: TextStyle(
                fontSize: navLayout.labelFontSize,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.w500,
                color: isActive ? AppColors.primary : AppColors.gray500,
                fontFamily: 'Cairo',
              ),
              child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
      ),
    );
  }
}

class _DarfixAiFab extends StatelessWidget {
  final VoidCallback onTap;

  const _DarfixAiFab({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Ink(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppColors.primary, AppColors.secondary],
              begin: Alignment.topRight,
              end: Alignment.bottomLeft,
            ),
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: AppColors.secondary.withValues(alpha: 0.22),
                blurRadius: 18,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.94),
                  borderRadius: BorderRadius.circular(14),
                ),
                clipBehavior: Clip.antiAlias,
                child: Image.asset(
                  'assets/icons/ai.gif',
                  fit: BoxFit.cover,
                  gaplessPlayback: true,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                'Darfix AI',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  fontFamily: AppTheme.cairoKey,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomNavLayout {
  const _BottomNavLayout({
    required this.iconBoxSize,
    required this.iconSize,
    required this.labelFontSize,
    required this.labelTopSpacing,
    required this.topPadding,
    required this.bottomPadding,
    required this.safeBottomGap,
  });

  final double iconBoxSize;
  final double iconSize;
  final double labelFontSize;
  final double labelTopSpacing;
  final double topPadding;
  final double bottomPadding;
  final double safeBottomGap;
}
