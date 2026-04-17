import 'dart:async';
import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:image_picker/image_picker.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../models/complaint_model.dart';
import '../repositories/complaint_repository.dart';
import '../services/app_localizations.dart';

class ComplaintDetailsScreen extends StatefulWidget {
  final int complaintId;

  const ComplaintDetailsScreen({super.key, required this.complaintId});

  @override
  State<ComplaintDetailsScreen> createState() => _ComplaintDetailsScreenState();
}

class _ComplaintDetailsScreenState extends State<ComplaintDetailsScreen> {
  final ComplaintRepository _repository = ComplaintRepository();
  final TextEditingController _replyController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final ImagePicker _picker = ImagePicker();

  ComplaintModel? _complaint;
  bool _isLoading = true;
  bool _isSending = false;
  Timer? _pollTimer;
  List<String> _pendingAttachments = [];

  @override
  void initState() {
    super.initState();
    _loadDetails();
    _startPolling();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _replyController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      _loadDetails(silent: true);
    });
  }

  bool _isNearBottom() {
    if (!_scrollController.hasClients) return true;
    final distanceToBottom =
        _scrollController.position.maxScrollExtent - _scrollController.offset;
    return distanceToBottom < 120;
  }

  void _scrollToBottom({bool animated = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      final target = _scrollController.position.maxScrollExtent;
      if (animated) {
        _scrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      } else {
        _scrollController.jumpTo(target);
      }
    });
  }

  Future<void> _loadDetails({bool silent = false}) async {
    final oldCount = _complaint?.replies.length ?? 0;
    final shouldKeepScroll = _isNearBottom();

    try {
      final complaint = await _repository.getComplaintDetails(
        widget.complaintId,
      );
      if (!mounted) return;

      final hasNewReplies = (complaint.replies.length > oldCount);
      setState(() {
        _complaint = complaint;
        if (!silent) {
          _isLoading = false;
        }
      });

      if (oldCount == 0 || (hasNewReplies && shouldKeepScroll)) {
        _scrollToBottom(animated: oldCount > 0);
      }
    } catch (e) {
      if (!mounted) return;
      if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${context.tr('error_loading_data')}: $e')),
        );
      }
      setState(() => _isLoading = false);
    }
  }

  Future<void> _pickFromGallery() async {
    final files = await _picker.pickMultiImage(
      imageQuality: 85,
      maxWidth: 1800,
    );
    if (!mounted || files.isEmpty) return;

    setState(() {
      for (final file in files) {
        if (!_pendingAttachments.contains(file.path)) {
          _pendingAttachments.add(file.path);
        }
      }
    });
  }

  Future<void> _takePhoto() async {
    final file = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 85,
      maxWidth: 1800,
    );
    if (!mounted || file == null) return;

    setState(() {
      if (!_pendingAttachments.contains(file.path)) {
        _pendingAttachments.add(file.path);
      }
    });
  }

  Future<void> _showAttachmentSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      builder: (sheetContext) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: Text(context.tr('gallery_photo')),
                onTap: () async {
                  Navigator.pop(sheetContext);
                  await _pickFromGallery();
                },
              ),
              ListTile(
                leading: const Icon(Icons.camera_alt_outlined),
                title: Text(context.tr('camera_photo')),
                onTap: () async {
                  Navigator.pop(sheetContext);
                  await _takePhoto();
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _removePendingAttachment(String path) {
    setState(() {
      _pendingAttachments.remove(path);
    });
  }

  Future<void> _sendReply() async {
    final message = _replyController.text.trim();
    if (message.isEmpty && _pendingAttachments.isEmpty) return;

    setState(() => _isSending = true);
    try {
      final success = await _repository.replyComplaint(
        widget.complaintId,
        message: message,
        attachmentPaths: _pendingAttachments,
      );

      if (success) {
        _replyController.clear();
        setState(() => _pendingAttachments = []);
        await _loadDetails(silent: true);
        _scrollToBottom(animated: true);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(context.tr('request_send_failed'))),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${context.tr('connection_error')}: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  void _openImageViewer(String imageUrl) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: Colors.black,
          insetPadding: const EdgeInsets.all(8),
          child: GestureDetector(
            onTap: () => Navigator.pop(dialogContext),
            child: InteractiveViewer(
              minScale: 0.7,
              maxScale: 4,
              child: CachedNetworkImage(
                imageUrl: AppConfig.fixMediaUrl(imageUrl),
                fit: BoxFit.contain,
                errorWidget: (_, __, ___) => const SizedBox(
                  height: 220,
                  child: Center(
                    child: Icon(
                      Icons.broken_image_outlined,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildNetworkAttachments(
    List<String> attachments, {
    bool isMine = false,
  }) {
    if (attachments.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Wrap(
        spacing: 6,
        runSpacing: 6,
        children: attachments.map((url) {
          return GestureDetector(
            onTap: () => _openImageViewer(url),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Container(
                width: 78,
                height: 78,
                color: isMine
                    ? Colors.white.withValues(alpha: 0.2)
                    : AppColors.gray100,
                child: CachedNetworkImage(
                  imageUrl: AppConfig.fixMediaUrl(url),
                  fit: BoxFit.cover,
                  errorWidget: (_, __, ___) =>
                      const Icon(Icons.broken_image_outlined),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildPendingAttachments() {
    if (_pendingAttachments.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 72,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _pendingAttachments.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final path = _pendingAttachments[index];
          return Stack(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.file(
                  File(path),
                  width: 72,
                  height: 72,
                  fit: BoxFit.cover,
                ),
              ),
              Positioned(
                top: 2,
                right: 2,
                child: GestureDetector(
                  onTap: () => _removePendingAttachment(path),
                  child: Container(
                    width: 20,
                    height: 20,
                    decoration: const BoxDecoration(
                      color: Colors.black54,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.close,
                      size: 14,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildHeaderCard() {
    final complaint = _complaint!;

    return Container(
      padding: const EdgeInsets.all(16),
      color: Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  complaint.subject,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _getStatusColor(
                    complaint.status,
                  ).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  complaint.status.toUpperCase(),
                  style: TextStyle(
                    color: _getStatusColor(complaint.status),
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          if ((complaint.description ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              complaint.description!,
              style: const TextStyle(color: AppColors.gray600),
            ),
          ],
          if (complaint.attachments.isNotEmpty)
            _buildNetworkAttachments(complaint.attachments),
          const SizedBox(height: 8),
          Text(
            complaint.createdAt.toString().split('.')[0],
            style: const TextStyle(fontSize: 12, color: AppColors.gray400),
          ),
        ],
      ),
    );
  }

  Widget _buildChatList() {
    final replies = _complaint!.replies;
    if (replies.isEmpty) {
      return Center(
        child: Text(
          context.tr('no_messages'),
          style: const TextStyle(color: AppColors.gray400),
        ),
      );
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.all(16),
      itemCount: replies.length,
      itemBuilder: (context, index) {
        final reply = replies[index];
        final isMe = reply.senderType == 'user';

        return Align(
          alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(12),
            constraints: BoxConstraints(
              maxWidth: MediaQuery.of(context).size.width * 0.78,
            ),
            decoration: BoxDecoration(
              color: isMe ? AppColors.primary : Colors.white,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(12),
                topRight: const Radius.circular(12),
                bottomLeft: isMe
                    ? const Radius.circular(12)
                    : const Radius.circular(0),
                bottomRight: isMe
                    ? const Radius.circular(0)
                    : const Radius.circular(12),
              ),
              boxShadow: AppShadows.sm,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (!isMe && reply.senderName.trim().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Text(
                      reply.senderName,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                if (reply.message.trim().isNotEmpty)
                  Text(
                    reply.message,
                    style: TextStyle(
                      color: isMe ? Colors.white : AppColors.gray800,
                    ),
                  ),
                _buildNetworkAttachments(reply.attachments, isMine: isMe),
                const SizedBox(height: 4),
                Text(
                  reply.createdAt.toString().split(' ')[1].substring(0, 5),
                  style: TextStyle(
                    fontSize: 10,
                    color: isMe
                        ? Colors.white.withValues(alpha: 0.7)
                        : AppColors.gray400,
                  ),
                ),
              ],
            ),
          ).animate().fadeIn().slideY(begin: 0.1, end: 0),
        );
      },
    );
  }

  Widget _buildInputArea() {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
      color: Colors.white,
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_pendingAttachments.isNotEmpty) ...[
              _buildPendingAttachments(),
              const SizedBox(height: 8),
            ],
            Row(
              children: [
                IconButton(
                  onPressed: _isSending ? null : _showAttachmentSheet,
                  icon: const Icon(Icons.attach_file),
                  color: AppColors.primary,
                ),
                Expanded(
                  child: TextField(
                    controller: _replyController,
                    minLines: 1,
                    maxLines: 4,
                    decoration: InputDecoration(
                      hintText: context.tr('type_message'),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide.none,
                      ),
                      filled: true,
                      fillColor: AppColors.gray50,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 10,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                _isSending
                    ? const SizedBox(
                        width: 48,
                        height: 48,
                        child: Center(
                          child: Padding(
                            padding: EdgeInsets.all(12),
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        ),
                      )
                    : Container(
                        decoration: const BoxDecoration(
                          color: AppColors.primary,
                          shape: BoxShape.circle,
                        ),
                        child: IconButton(
                          icon: const Icon(Icons.send, color: Colors.white),
                          onPressed: _sendReply,
                        ),
                      ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_complaint == null) {
      return Scaffold(body: Center(child: Text(context.tr('not_found'))));
    }

    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text('#${_complaint!.ticketNumber}'),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 1,
        leading: const BackButton(color: Colors.black),
      ),
      body: Column(
        children: [
          _buildHeaderCard(),
          Expanded(child: _buildChatList()),
          if (_complaint!.status != 'closed') _buildInputArea(),
        ],
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'open':
        return Colors.orange;
      case 'in_progress':
        return Colors.blue;
      case 'resolved':
        return Colors.green;
      case 'closed':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }
}
