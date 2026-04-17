// Onboarding Screen
// شاشة التعريف بالتطبيق

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../utils/saudi_riyal_icon.dart';
import '../widgets/app_logo.dart';

class OnboardingScreen extends StatefulWidget {
  final VoidCallback onComplete;

  const OnboardingScreen({super.key, required this.onComplete});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  int _currentScreen = 0;
  final PageController _pageController = PageController();

  List<Map<String, dynamic>> _screens = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _screens = [
      {
        'title': context.tr('onboarding_title_1'),
        'description': context.tr('onboarding_desc_1'),
        'bgColor': [
          const Color(0xFFFBCC26).withValues(alpha: 0.1),
          const Color(0xFFFBCC26).withValues(alpha: 0.05),
          Colors.white,
        ],
        'activeDotColor': const Color(0xFF7466ED),
      },
      {
        'title': context.tr('onboarding_title_2'),
        'description': context.tr('onboarding_desc_2'),
        'bgColor': [
          const Color(0xFF7466ED).withValues(alpha: 0.1),
          const Color(0xFF7466ED).withValues(alpha: 0.05),
          Colors.white,
        ],
        'activeDotColor': const Color(0xFFFBCC26),
      },
      {
        'title': context.tr('onboarding_title_3'),
        'description': context.tr('onboarding_desc_3'),
        'bgColor': [
          const Color(0xFFFBCC26).withValues(alpha: 0.1),
          const Color(0xFFFBCC26).withValues(alpha: 0.05),
          Colors.white,
        ],
        'activeDotColor': const Color(0xFF7466ED),
      },
    ];
  }

  void _handleNext() {
    if (_currentScreen < _screens.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOut,
      );
    } else {
      widget.onComplete();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AnimatedContainer(
        duration: const Duration(milliseconds: 500),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: _screens[_currentScreen]['bgColor'] as List<Color>,
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              // Main Content
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  onPageChanged: (index) =>
                      setState(() => _currentScreen = index),
                  itemCount: _screens.length,
                  itemBuilder: (context, index) {
                    return _buildScreenContent(index);
                  },
                ),
              ),

              // Bottom Section
              Container(
                padding: const EdgeInsets.only(bottom: 24, left: 24, right: 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Pagination Dots
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(_screens.length, (index) {
                        final isActive = index == _currentScreen;
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 300),
                          margin: const EdgeInsets.symmetric(horizontal: 4),
                          width: isActive ? 32 : 6,
                          height: 6,
                          decoration: BoxDecoration(
                            color: isActive
                                ? (_screens[_currentScreen]['activeDotColor']
                                      as Color)
                                : AppColors.gray300,
                            borderRadius: BorderRadius.circular(3),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 24),

                    // Next Button
                    SizedBox(
                      width: double.infinity,
                      height: 56,
                      child: ElevatedButton(
                        onPressed: _handleNext,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFBCC26),
                          foregroundColor: Colors.white,
                          elevation: 4,
                          shadowColor: const Color(
                            0xFFFBCC26,
                          ).withValues(alpha: 0.3),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: Text(
                          _currentScreen < _screens.length - 1
                              ? context.tr('next')
                              : context.tr('start_now'),
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildScreenContent(int index) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Animation Component
          SizedBox(
            height: 320,
            width: double.infinity,
            child: index == 0
                ? const Screen1Animation()
                : index == 1
                ? const Screen2Animation()
                : const Screen3Animation(),
          ),

          const SizedBox(height: 32),

          // Title
          Text(
            _screens[index]['title'] as String,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.gray800,
            ),
            textAlign: TextAlign.center,
          ).animate().fadeIn(delay: 200.ms).slideY(begin: 0.2, end: 0),

          const SizedBox(height: 12),

          // Description
          Text(
            _screens[index]['description'] as String,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.gray600,
              height: 1.5,
            ),
            textAlign: TextAlign.center,
          ).animate().fadeIn(delay: 300.ms).slideY(begin: 0.2, end: 0),
        ],
      ),
    );
  }
}

class Screen1Animation extends StatelessWidget {
  const Screen1Animation({super.key});

  @override
  Widget build(BuildContext context) {
    final services = [
      {
        'icon': Icons.cleaning_services_outlined,
        'label': context.tr('cleaning'),
        'pos': const Alignment(0, -1),
        'color': const Color(0xFFFBCC26),
      },
      {
        'icon': Icons.plumbing_outlined,
        'label': context.tr('plumbing'),
        'pos': const Alignment(-0.8, -0.4),
        'color': const Color(0xFF7466ED),
      },
      {
        'icon': Icons.ac_unit_outlined,
        'label': context.tr('ac_repair'),
        'pos': const Alignment(0.8, -0.4),
        'color': const Color(0xFFFBCC26),
      },
      {
        'icon': Icons.electric_bolt_outlined,
        'label': context.tr('electricity'),
        'pos': const Alignment(-0.6, 0.5),
        'color': const Color(0xFFFBCC26),
      },
      {
        'icon': Icons.carpenter_outlined,
        'label': context.tr('carpentry'),
        'pos': const Alignment(0.6, 0.5),
        'color': const Color(0xFFFBCC26),
      },
      {
        'icon': Icons.handyman_outlined,
        'label': context.tr('maintenance'),
        'pos': const Alignment(0, 0.9),
        'color': const Color(0xFF7466ED),
      },
    ];

    return Stack(
      alignment: Alignment.center,
      children: [
        // Concentric Circles
        ...List.generate(4, (i) {
          return Container(
                width: 280 - (i * 50.0),
                height: 280 - (i * 50.0),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: const Color(0xFF7466ED).withValues(alpha: 0.15),
                    width: 2,
                  ),
                ),
              )
              .animate(onPlay: (controller) => controller.repeat(reverse: true))
              .scale(
                begin: const Offset(1, 1),
                end: const Offset(1.05, 1.05),
                duration: 3.seconds,
                delay: (i * 200).ms,
              );
        }),

        // Center Logo
        Container(
              width: 140,
              height: 140,
              decoration: BoxDecoration(
                color: const Color(0xFFFBCC26),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFFFBCC26).withValues(alpha: 0.4),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Center(
                child: const AppLogo(
                  width: 70,
                  height: 70,
                  fit: BoxFit.contain,
                ),
              ),
            )
            .animate(onPlay: (c) => c.repeat(reverse: true))
            .rotate(begin: -0.02, end: 0.02, duration: 2.seconds),

        // Service Cards
        ...services.map((service) {
          return Align(
            alignment: service['pos'] as Alignment,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: (service['color'] as Color).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      service['icon'] as IconData,
                      size: 16,
                      color: service['color'] as Color,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    service['label'] as String,
                    style: const TextStyle(
                      fontSize: 10,
                      color: AppColors.gray700,
                    ),
                  ),
                ],
              ),
            ).animate().scale(duration: 400.ms, curve: Curves.elasticOut),
          );
        }),
      ],
    );
  }
}

class Screen2Animation extends StatelessWidget {
  const Screen2Animation({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        // Floating Icons
        Positioned(
          top: 0,
          left: 20,
          child: _buildFloatingIcon(
            Icons.access_time_filled,
            const Color(0xFF7466ED),
            3,
          ),
        ),
        Positioned(
          top: 20,
          right: 30,
          child: _buildFloatingIcon(Icons.emoji_events, Colors.amber, 2),
        ),

        // Main Card
        Center(
          child: Container(
            width: 280,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: AppShadows.xl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Checkmark
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: const BoxDecoration(
                    color: Color(0xFF7466ED),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.check, color: Colors.white, size: 24),
                ).animate().scale(duration: 400.ms, curve: Curves.elasticOut),

                const SizedBox(height: 12),

                Text(
                  context.tr('order_completed_professionally'),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 16),

                // Details Card
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.gray50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(
                                colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
                              ),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Icon(
                              Icons.auto_awesome,
                              color: Colors.white,
                              size: 12,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            context.tr('integrated_home_services'),
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _buildServiceItem(
                        '1',
                        context.tr('ac_maintenance'),
                        Icons.wind_power,
                      ),
                      const SizedBox(height: 8),
                      _buildServiceItem(
                        '2',
                        context.tr('comprehensive_cleaning'),
                        Icons.cleaning_services,
                      ),
                      const SizedBox(height: 12),
                      const Divider(height: 1),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(
                                0xFFFBCC26,
                              ).withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              context.tr('instant_service'),
                              style: const TextStyle(
                                fontSize: 9,
                                color: Color(0xFF7466ED),
                              ),
                            ),
                          ),
                          const SaudiRiyalText(
                            text: '56',
                            style: TextStyle(fontWeight: FontWeight.bold),
                            iconSize: 13,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ).animate().slideY(begin: 0.1, end: 0, duration: 500.ms),
        ),
      ],
    );
  }

  Widget _buildFloatingIcon(IconData icon, Color color, int duration) {
    return Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: color, size: 24),
        )
        .animate(onPlay: (c) => c.repeat(reverse: true))
        .moveY(begin: 0, end: -10, duration: duration.seconds);
  }

  Widget _buildServiceItem(String num, String text, IconData icon) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Row(
          children: [
            Icon(icon, size: 14, color: Colors.blue),
            const SizedBox(width: 8),
            Text(text, style: const TextStyle(fontSize: 10)),
          ],
        ),
        Text(
          num,
          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
        ),
      ],
    );
  }
}

class Screen3Animation extends StatelessWidget {
  const Screen3Animation({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [
        // Floating Icons
        Positioned(
          top: 20,
          left: 30,
          child: _buildIcon(Icons.verified, Colors.orange, 0),
        ),
        Positioned(
          top: 30,
          right: 40,
          child: _buildIcon(Icons.access_time, Colors.amber, 1),
        ),
        Positioned(
          bottom: 40,
          right: 50,
          child: _buildIcon(
            Icons.inventory_2_outlined,
            const Color(0xFFFBCC26),
            2,
          ),
        ),
        Positioned(
          bottom: 30,
          left: 40,
          child: _buildIcon(
            Icons.emoji_events_outlined,
            const Color(0xFF7466ED),
            3,
          ),
        ),

        // Main Shield
        Container(
          width: 160,
          height: 160,
          decoration: const BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(color: Colors.black12, blurRadius: 20, spreadRadius: 5),
            ],
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Icon(
                Icons.shield,
                size: 100,
                color: const Color(0xFF7466ED).withValues(alpha: 0.9),
              ),
              const Icon(Icons.check, size: 40, color: Color(0xFFFBCC26)),

              // Rotating Badge
              Positioned(
                bottom: 20,
                child:
                    Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFBCC26),
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: const Icon(
                            Icons.star,
                            color: Colors.white,
                            size: 16,
                          ),
                        )
                        .animate(onPlay: (c) => c.repeat())
                        .rotate(duration: 5.seconds),
              ),
            ],
          ),
        ).animate().scale(duration: 600.ms, curve: Curves.elasticOut),
      ],
    );
  }

  Widget _buildIcon(IconData icon, Color color, int index) {
    return Container(
          padding: const EdgeInsets.all(8),
          decoration: const BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 8)],
          ),
          child: Icon(icon, color: color, size: 20),
        )
        .animate(onPlay: (c) => c.repeat(reverse: true))
        .moveY(
          begin: 0,
          end: index.isEven ? -10 : 10,
          duration: 3.seconds,
          delay: (index * 200).ms,
        );
  }
}
