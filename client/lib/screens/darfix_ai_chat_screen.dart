import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/darfix_ai_service.dart';

class DarfixAiChatScreen extends StatefulWidget {
  const DarfixAiChatScreen({super.key});

  @override
  State<DarfixAiChatScreen> createState() => _DarfixAiChatScreenState();
}

class _DarfixAiChatScreenState extends State<DarfixAiChatScreen> {
  final DarfixAiService _darfixAiService = DarfixAiService();
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final List<_ChatMessage> _messages = <_ChatMessage>[];

  bool _isSending = false;

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    if (text.isEmpty || _isSending) {
      return;
    }

    final authProvider = context.read<AuthProvider>();
    final locationProvider = context.read<LocationProvider>();
    final localeCode = Localizations.localeOf(context).languageCode;

    final history = _messages
        .where((message) => message.role != _ChatRole.system)
        .map(
          (message) => DarfixAiConversationMessage(
            role: message.role == _ChatRole.user ? 'user' : 'assistant',
            content: message.text,
          ),
        )
        .toList();

    setState(() {
      _messages.add(
        _ChatMessage(
          role: _ChatRole.user,
          text: text,
          timestamp: DateTime.now(),
        ),
      );
      _isSending = true;
      _messageController.clear();
    });
    _scrollToBottom();

    try {
      final reply = await _darfixAiService.sendMessage(
        userMessage: text,
        history: history,
        localeCode: localeCode,
        authProvider: authProvider,
        locationProvider: locationProvider,
      );

      if (!mounted) return;
      setState(() {
        _messages.add(
          _ChatMessage(
            role: _ChatRole.assistant,
            text: reply.content,
            timestamp: DateTime.now(),
          ),
        );
        _isSending = false;
      });
      _scrollToBottom();
    } catch (error) {
      if (!mounted) return;
      setState(() => _isSending = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final canSend = _messageController.text.trim().isNotEmpty && !_isSending;

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.18),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              child: Image.asset(
                'assets/icons/ai.gif',
                fit: BoxFit.cover,
                gaplessPlayback: true,
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Darfix AI',
                  style: GoogleFonts.cairo(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.gray900,
                  ),
                ),
                Text(
                  context.tr('darfix_ai_live_data'),
                  style: GoogleFonts.cairo(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.success,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
            child: _buildIntroCard(context),
          ),
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              itemCount: _messages.length + (_isSending ? 1 : 0),
              itemBuilder: (context, index) {
                if (_isSending && index == _messages.length) {
                  return _TypingBubble(text: context.tr('darfix_ai_typing'));
                }

                final message = _messages[index];
                return _MessageBubble(message: message);
              },
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(22),
                        border: Border.all(
                          color: AppColors.gray200,
                          width: 1,
                        ),
                      ),
                      child: TextField(
                        controller: _messageController,
                        minLines: 1,
                        maxLines: 5,
                        textInputAction: TextInputAction.send,
                        onChanged: (_) => setState(() {}),
                        onSubmitted: (_) => _sendMessage(),
                        decoration: InputDecoration(
                          hintText: context.tr('darfix_ai_input_hint'),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 14,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  GestureDetector(
                    onTap: canSend ? _sendMessage : null,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        gradient: canSend
                            ? const LinearGradient(
                                colors: [
                                  AppColors.primary,
                                  AppColors.secondary,
                                ],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              )
                            : LinearGradient(
                                colors: [
                                  AppColors.gray300,
                                  AppColors.gray200,
                                ],
                              ),
                        borderRadius: BorderRadius.circular(18),
                        boxShadow: canSend
                            ? [
                                BoxShadow(
                                  color: AppColors.secondary.withValues(
                                    alpha: 0.18,
                                  ),
                                  blurRadius: 18,
                                  offset: const Offset(0, 10),
                                ),
                              ]
                            : null,
                      ),
                      child: const Icon(
                        Icons.arrow_upward_rounded,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildIntroCard(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Colors.white,
            AppColors.primaryLight.withValues(alpha: 0.55),
          ],
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.22)),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            clipBehavior: Clip.antiAlias,
            child: Image.asset(
              'assets/icons/ai.gif',
              fit: BoxFit.cover,
              gaplessPlayback: true,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              context.tr('darfix_ai_intro'),
              style: GoogleFonts.cairo(
                fontSize: 14,
                height: 1.5,
                fontWeight: FontWeight.w600,
                color: AppColors.gray800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

enum _ChatRole { user, assistant, system }

class _ChatMessage {
  final _ChatRole role;
  final String text;
  final DateTime timestamp;

  const _ChatMessage({
    required this.role,
    required this.text,
    required this.timestamp,
  });
}

class _MessageBubble extends StatelessWidget {
  final _ChatMessage message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    final isUser = message.role == _ChatRole.user;
    final alignment = isUser ? Alignment.centerRight : Alignment.centerLeft;
    final radius = BorderRadius.only(
      topLeft: const Radius.circular(22),
      topRight: const Radius.circular(22),
      bottomLeft: Radius.circular(isUser ? 22 : 8),
      bottomRight: Radius.circular(isUser ? 8 : 22),
    );

    return Align(
      alignment: alignment,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        constraints: const BoxConstraints(maxWidth: 320),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          gradient: isUser
              ? const LinearGradient(
                  colors: [AppColors.primary, AppColors.secondary],
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                )
              : null,
          color: isUser ? null : Colors.white,
          borderRadius: radius,
          border: isUser ? null : Border.all(color: AppColors.gray200),
          boxShadow: [
            BoxShadow(
              color: (isUser ? AppColors.secondary : AppColors.gray300)
                  .withValues(alpha: isUser ? 0.15 : 0.18),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Text(
          message.text,
          style: GoogleFonts.cairo(
            fontSize: 14,
            height: 1.6,
            fontWeight: FontWeight.w600,
            color: isUser ? Colors.white : AppColors.gray800,
          ),
        ),
      ),
    );
  }
}

class _TypingBubble extends StatelessWidget {
  final String text;

  const _TypingBubble({required this.text});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.gray200),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2.2,
                color: AppColors.secondary,
              ),
            ),
            const SizedBox(width: 10),
            Text(
              text,
              style: GoogleFonts.cairo(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.gray700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
