// Waiting For Provider Screen
// شاشة انتظار مقدم الخدمة

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../models/models.dart';
import '../services/app_localizations.dart';

class WaitingForProviderScreen extends StatefulWidget {
  final ServiceCategoryModel service;
  final VoidCallback onBack;

  const WaitingForProviderScreen({
    super.key,
    required this.service,
    required this.onBack,
  });

  @override
  State<WaitingForProviderScreen> createState() =>
      _WaitingForProviderScreenState();
}

class _WaitingForProviderScreenState extends State<WaitingForProviderScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      body: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.fromLTRB(16, 48, 16, 20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
              ),
              boxShadow: AppShadows.md,
            ),
            child: Row(
              children: [
                InkWell(
                  onTap: widget.onBack,
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
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('order_status'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        context
                            .tr('request_service_title')
                            .replaceAll('{}', widget.service.nameAr),
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.8),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Main Status Card
                  Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFFBCC26), Color(0xFFE5B41F)],
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: AppShadows.xl,
                    ),
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      children: [
                        // Animated Icon
                        Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.2),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.access_time,
                                color: Colors.white,
                                size: 48,
                              ),
                            )
                            .animate(onPlay: (c) => c.repeat())
                            .rotate(duration: 4.seconds),

                        const SizedBox(height: 16),

                        Text(
                          context.tr('searching_for_provider'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          context.tr('sending_request_to_specialists'),
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.8),
                            fontSize: 12,
                          ),
                          textAlign: TextAlign.center,
                        ),

                        const SizedBox(height: 24),

                        // Loading Bar
                        Container(
                          height: 6,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(3),
                          ),
                          child: Stack(
                            children: [
                              Container(
                                    width: 100, // Dynamic width simulation
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(3),
                                    ),
                                  )
                                  .animate(onPlay: (c) => c.repeat())
                                  .slideX(
                                    begin: -1,
                                    end: 3,
                                    duration: 2.seconds,
                                  ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 16),

                        // Time Estimate
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 12,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.bolt,
                                    color: Colors.white,
                                    size: 16,
                                  ),
                                  SizedBox(width: 4),
                                  Text(
                                    context.tr('expected_response_time'),
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                context.tr('response_time_duration'),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ).animate().scale(curve: Curves.elasticOut, duration: 800.ms),

                  const SizedBox(height: 24),

                  // Process Steps
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: AppShadows.md,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.timeline, color: Color(0xFFFBCC26)),
                            SizedBox(width: 8),
                            Text(
                              context.tr('next_steps'),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),
                        _buildStepItem(
                          isActive: true,
                          icon: Icons.person_search,
                          title: context.tr('step_search_providers'),
                          subtitle: context.tr('step_sending_request'),
                        ),
                        _buildStepItem(
                          isActive: false,
                          icon: Icons.chat_bubble_outline,
                          title: context.tr('step_receive_offers'),
                          subtitle: context.tr('step_receive_offers_desc'),
                        ),
                        _buildStepItem(
                          isActive: false,
                          icon: Icons.check_circle_outline,
                          title: context.tr('step_choose_provider'),
                          subtitle: context.tr('step_choose_provider_desc'),
                        ),
                      ],
                    ),
                  ).animate().slideY(begin: 0.2, end: 0, delay: 200.ms),

                  const SizedBox(height: 24),

                  // Floating Info
                  Column(
                    children: [
                      Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: const Color(
                                0xFFFBCC26,
                              ).withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.star,
                              color: Color(0xFFFBCC26),
                              size: 32,
                            ),
                          )
                          .animate(onPlay: (c) => c.repeat(reverse: true))
                          .scale(
                            begin: const Offset(1, 1),
                            end: const Offset(1.1, 1.1),
                          ),
                      const SizedBox(height: 8),
                      Text(
                        context.tr('notify_when_offers_received'),
                        style: const TextStyle(
                          color: AppColors.gray500,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepItem({
    required bool isActive,
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  gradient:
                      isActive
                          ? const LinearGradient(
                            colors: [Color(0xFFFBCC26), Color(0xFFF5C01F)],
                          )
                          : null,
                  color: isActive ? null : AppColors.gray200,
                  shape: BoxShape.circle,
                  boxShadow: isActive ? AppShadows.md : null,
                ),
                child: Icon(
                  icon,
                  color: isActive ? Colors.white : AppColors.gray400,
                  size: 20,
                ),
              ),
              Container(
                width: 2,
                height: 20,
                color: AppColors.gray200,
                margin: const EdgeInsets.only(top: 8),
              ),
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: isActive ? AppColors.gray800 : AppColors.gray500,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.gray400,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
