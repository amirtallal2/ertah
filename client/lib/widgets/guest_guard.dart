// Guest Mode Helper Widgets
// أدوات مساعدة لوضع الزائر

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../config/app_theme.dart';
import '../services/app_localizations.dart';

/// Shows a dialog prompting guest users to login/register
/// Returns true if user is logged in and can proceed, false if guest
Future<bool> checkGuestAndShowDialog(BuildContext context) async {
  final authProvider = context.read<AuthProvider>();

  if (authProvider.isGuest) {
    await showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFFBCC26).withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.person_outline,
                color: Color(0xFFFBCC26),
                size: 24,
              ),
            ),
            const SizedBox(width: 12),
            Text(
              context.tr('guest_login_required'),
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        content: Text(
          context.tr('guest_login_required_message'),
          style: const TextStyle(
            fontSize: 14,
            color: AppColors.gray600,
            height: 1.5,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(
              context.tr('later'),
              style: TextStyle(color: AppColors.gray500),
            ),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(context).pop();
              // Logout guest mode to trigger navigation to login
              authProvider.logout();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFBCC26),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: Text(context.tr('login')),
          ),
        ],
      ),
    );
    return false;
  }

  return true;
}

/// A wrapper widget that shows login prompt for guests when they try to access protected features
class GuestGuard extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;

  const GuestGuard({super.key, required this.child, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () async {
        final canProceed = await checkGuestAndShowDialog(context);
        if (canProceed && onTap != null) {
          onTap!();
        }
      },
      child: child,
    );
  }
}
