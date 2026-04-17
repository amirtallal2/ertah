// Splash Screen
// شاشة البداية

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';

import 'dart:math' as math;
import '../config/app_theme.dart';
import '../services/app_localizations.dart';
import '../providers/app_settings_provider.dart';
import '../widgets/app_logo.dart';

class SplashScreen extends StatefulWidget {
  final VoidCallback onComplete;

  const SplashScreen({super.key, required this.onComplete});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  late AnimationController _circleController;

  @override
  void initState() {
    super.initState();

    _circleController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 15),
    )..repeat();

    // Navigate after 3 seconds
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) {
        widget.onComplete();
      }
    });
  }

  @override
  void dispose() {
    _circleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final appName = context.watch<AppSettingsProvider>().appName;
    final displayName =
        appName.trim().isNotEmpty ? appName : context.tr('app_name');
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.primary,
              AppColors.primaryDark,
              AppColors.primary,
            ],
          ),
        ),
        child: Stack(
          children: [
            // Animated Background Circles
            _buildAnimatedCircle(
              top: 80,
              left: 80,
              size: 256,
              color: Colors.white.withValues(alpha: 0.1),
              delay: 0,
            ),
            _buildAnimatedCircle(
              bottom: 80,
              right: 80,
              size: 384,
              color: AppColors.secondary.withValues(alpha: 0.2),
              delay: 2,
            ),

            // Rotating circle in center
            Positioned.fill(
              child: AnimatedBuilder(
                animation: _circleController,
                builder: (context, child) {
                  return Transform.rotate(
                    angle: _circleController.value * 2 * math.pi,
                    child: Container(
                      margin: const EdgeInsets.all(100),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.05),
                          width: 2,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),

            // Floating Particles
            ...List.generate(6, (i) => _buildFloatingParticle(i)),

            // Main Logo Container
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Logo with Animation
                  Animate(
                    onPlay: (controller) => controller.repeat(reverse: true),
                    effects: [
                      MoveEffect(
                        begin: const Offset(0, 0),
                        end: const Offset(0, -15),
                        duration: 1500.ms,
                        curve: Curves.easeInOut,
                        delay: 2400.ms, // Wait for entrance
                      ),
                    ],
                    child:
                        Container(
                              width: 192,
                              height: 192,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.2),
                                    blurRadius: 40,
                                    offset: const Offset(0, 20),
                                  ),
                                ],
                              ),
                              child: Container(
                                decoration: const BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: Colors.white,
                                ),
                                padding: const EdgeInsets.all(24),
                                child: const AppLogo(
                                  width: 192,
                                  height: 192,
                                  fit: BoxFit.contain,
                                ),
                              ),
                            )
                            .animate()
                            .scale(
                              begin: const Offset(0, 0),
                              end: const Offset(1, 1),
                              duration: 1200.ms,
                              curve: Curves.elasticOut,
                            )
                            .rotate(
                              begin: -0.5,
                              end: 0,
                              duration: 1200.ms,
                              curve: Curves.elasticOut,
                            ),
                  ),

                  const SizedBox(height: 32),

                  // Welcome Text
                  // Welcome Text
                  Text(
                        displayName,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                          color: Colors.white.withValues(alpha: 0.9),
                          letterSpacing: 1,
                        ),
                      )
                      .animate()
                      .fadeIn(delay: 800.ms, duration: 800.ms)
                      .slideY(begin: 0.3, end: 0),

                  const SizedBox(height: 32),

                  // Loading Dots
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(3, (i) {
                      return Container(
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            width: 10,
                            height: 10,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          )
                          .animate(onPlay: (c) => c.repeat())
                          .fadeIn(delay: (1200 + i * 150).ms)
                          .then()
                          .moveY(
                            begin: 0,
                            end: -12,
                            duration: 1.seconds,
                            curve: Curves.easeInOut,
                            delay: (i * 150).ms,
                          )
                          .then()
                          .moveY(
                            begin: -12,
                            end: 0,
                            duration: 1.seconds,
                            curve: Curves.easeInOut,
                          );
                    }),
                  ),
                ],
              ),
            ),

            // Bottom Gradient Overlay
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                height: 128,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [
                      AppColors.secondary.withValues(alpha: 0.3),
                      Colors.transparent,
                    ],
                  ),
                ),
              ).animate().fadeIn(delay: 1.seconds, duration: 1.seconds),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAnimatedCircle({
    double? top,
    double? bottom,
    double? left,
    double? right,
    required double size,
    required Color color,
    required int delay,
  }) {
    return Positioned(
      top: top,
      bottom: bottom,
      left: left,
      right: right,
      child:
          Container(
                width: size,
                height: size,
                decoration: BoxDecoration(shape: BoxShape.circle, color: color),
              )
              .animate(onPlay: (c) => c.repeat())
              .scale(
                begin: const Offset(1, 1),
                end: Offset(1.2 + delay * 0.05, 1.2 + delay * 0.05),
                duration: (8 + delay * 2).seconds,
                curve: Curves.easeInOut,
              )
              .then()
              .scale(
                begin: Offset(1.2 + delay * 0.05, 1.2 + delay * 0.05),
                end: const Offset(1, 1),
                duration: (8 + delay * 2).seconds,
                curve: Curves.easeInOut,
              )
              .move(
                begin: Offset.zero,
                end: Offset(
                  (delay == 0 ? 30 : -40).toDouble(),
                  (delay == 0 ? -30 : 40).toDouble(),
                ),
                duration: (8 + delay * 2).seconds,
                curve: Curves.easeInOut,
              ),
    );
  }

  Widget _buildFloatingParticle(int index) {
    final positions = [
      [0.2, 0.3],
      [0.35, 0.4],
      [0.5, 0.35],
      [0.65, 0.45],
      [0.8, 0.3],
      [0.9, 0.5],
    ];

    return Positioned(
      left: MediaQuery.of(context).size.width * positions[index][0],
      top: MediaQuery.of(context).size.height * positions[index][1],
      child:
          Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.3),
                  shape: BoxShape.circle,
                ),
              )
              .animate(onPlay: (c) => c.repeat())
              .fadeIn(delay: (index * 300).ms)
              .moveY(
                begin: 0,
                end: -100,
                duration: (4 + index * 0.5).seconds,
                curve: Curves.easeInOut,
              )
              .then()
              .moveY(
                begin: -100,
                end: 0,
                duration: (4 + index * 0.5).seconds,
                curve: Curves.easeInOut,
              )
              .fadeOut(duration: (4 + index * 0.5).seconds),
    );
  }
}
