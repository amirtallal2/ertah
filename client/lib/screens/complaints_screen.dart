// Complaints Screen
// شاشة الشكاوى

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../services/services.dart';

import '../services/app_localizations.dart';
import 'complaint_details_screen.dart';

class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key});

  @override
  State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  final ComplaintsService _complaintsService = ComplaintsService();
  final TextEditingController _complaintController = TextEditingController();

  bool _isLoading = true;
  bool _isSubmitting = false;
  List<dynamic> _complaints = [];

  @override
  void initState() {
    super.initState();
    _fetchComplaints();
  }

  @override
  void dispose() {
    _complaintController.dispose();
    super.dispose();
  }

  Future<void> _fetchComplaints() async {
    try {
      final response = await _complaintsService.getComplaints();
      if (response.success) {
        final list = response.data is List
            ? response.data as List
            : <dynamic>[];
        if (mounted) {
          setState(() {
            _complaints = list;
            _isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _submitComplaint() async {
    final text = _complaintController.text.trim();
    if (text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.tr('please_enter_complaint'))),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      // Mock orderId for now, in real app might select from list
      final response = await _complaintsService.submitComplaint(
        subject: context.tr('new_complaint_title'),
        description: text,
      );

      if (response.success) {
        _complaintController.clear();
        await _fetchComplaints();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('complaint_sent_success')),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                response.message ?? context.tr('complaint_send_failed'),
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
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('complaints_and_suggestions')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // New Complaint Form
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: AppShadows.md,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.tr('submit_new_complaint'),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Text Area
                  TextField(
                    controller: _complaintController,
                    maxLines: 5,
                    decoration: InputDecoration(
                      hintText: context.tr('complaint_details_hint'),
                      filled: true,
                      fillColor: AppColors.gray50,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: BorderSide.none,
                      ),
                      contentPadding: const EdgeInsets.all(16),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Submit Button
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: ElevatedButton(
                      onPressed: _isSubmitting ? null : _submitComplaint,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: _isSubmitting
                          ? const CircularProgressIndicator(color: Colors.white)
                          : Text(context.tr('submit_complaint')),
                    ),
                  ),
                ],
              ),
            ).animate().fadeIn().slideY(begin: 0.1, end: 0),

            const SizedBox(height: 32),

            // Previous Complaints
            Align(
              alignment: Alignment.centerRight,
              child: Text(
                context.tr('previous_complaints'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(height: 16),

            _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _complaints.isEmpty
                ? Center(child: Text(context.tr('no_previous_complaints')))
                : ListView.separated(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: _complaints.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final complaint = _complaints[index];
                      final status = (complaint['status'] ?? 'open').toString();
                      final isResolved = status == 'resolved';
                      final subject = (complaint['subject'] ?? '').toString();
                      final ticket = (complaint['ticket_number'] ?? '')
                          .toString();
                      final createdAt = (complaint['created_at'] ?? '')
                          .toString();

                      return InkWell(
                            borderRadius: BorderRadius.circular(16),
                            onTap: () async {
                              final complaintId = int.tryParse(
                                '${complaint['id'] ?? 0}',
                              );
                              if (complaintId == null || complaintId <= 0) {
                                return;
                              }
                              await Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => ComplaintDetailsScreen(
                                    complaintId: complaintId,
                                  ),
                                ),
                              );
                              if (mounted) {
                                _fetchComplaints();
                              }
                            },
                            child: Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                boxShadow: AppShadows.sm,
                              ),
                              child: Column(
                                children: [
                                  Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        ticket.isNotEmpty
                                            ? '#$ticket'
                                            : '${context.tr('complaint_number')}${complaint['id']}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 4,
                                        ),
                                        decoration: BoxDecoration(
                                          color: isResolved
                                              ? Colors.green.withValues(
                                                  alpha: 0.1,
                                                )
                                              : Colors.orange.withValues(
                                                  alpha: 0.1,
                                                ),
                                          borderRadius: BorderRadius.circular(
                                            8,
                                          ),
                                        ),
                                        child: Text(
                                          isResolved
                                              ? context.tr('resolved')
                                              : _getStatusText(context, status),
                                          style: TextStyle(
                                            fontSize: 10,
                                            color: isResolved
                                                ? Colors.green
                                                : Colors.orange,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    subject.isNotEmpty
                                        ? subject
                                        : context.tr('new_complaint_title'),
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: AppColors.gray600,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (createdAt.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      createdAt,
                                      style: const TextStyle(
                                        fontSize: 11,
                                        color: AppColors.gray400,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          )
                          .animate()
                          .fadeIn(delay: (100 * index).ms)
                          .slideX(begin: 0.1, end: 0);
                    },
                  ),
          ],
        ),
      ),
    );
  }

  String _getStatusText(BuildContext context, String status) {
    switch (status) {
      case 'open':
        return context.tr('under_review');
      case 'in_progress':
        return context.tr('processing');
      case 'resolved':
        return context.tr('resolved');
      case 'closed':
        return context.tr('closed');
      default:
        return status;
    }
  }
}
