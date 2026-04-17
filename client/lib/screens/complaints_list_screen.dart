import 'package:flutter/material.dart';
import '../repositories/complaint_repository.dart';
import '../models/complaint_model.dart';
import '../services/app_localizations.dart';
import '../config/app_theme.dart';
import 'create_complaint_screen.dart';
import 'complaint_details_screen.dart';

class ComplaintsListScreen extends StatefulWidget {
  const ComplaintsListScreen({super.key});

  @override
  State<ComplaintsListScreen> createState() => _ComplaintsListScreenState();
}

class _ComplaintsListScreenState extends State<ComplaintsListScreen> {
  final ComplaintRepository _repository = ComplaintRepository();
  List<ComplaintModel> _complaints = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadComplaints();
  }

  Future<void> _loadComplaints() async {
    try {
      final complaints = await _repository.getComplaints();
      if (mounted) {
        setState(() {
          _complaints = complaints;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${context.tr('error_loading_data')}: $e')),
        );
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('complaints')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _complaints.isEmpty
          ? _buildEmptyState()
          : RefreshIndicator(
              onRefresh: _loadComplaints,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _complaints.length,
                itemBuilder: (context, index) {
                  final complaint = _complaints[index];
                  return _buildComplaintItem(complaint);
                },
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateComplaintScreen()),
          );
          if (result == true) _loadComplaints();
        },
        label: Text(context.tr('new_complaint')),
        icon: const Icon(Icons.add),
        backgroundColor: AppColors.primary,
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.support_agent, size: 64, color: AppColors.gray400),
          const SizedBox(height: 16),
          Text(
            context.tr('no_complaints_yet'),
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Text(
            context.tr('start_new_complaint'),
            style: const TextStyle(color: AppColors.gray500),
          ),
        ],
      ),
    );
  }

  Widget _buildComplaintItem(ComplaintModel complaint) {
    Color statusColor;
    switch (complaint.status) {
      case 'open':
        statusColor = Colors.orange;
        break;
      case 'in_progress':
        statusColor = Colors.blue;
        break;
      case 'resolved':
        statusColor = Colors.green;
        break;
      case 'closed':
        statusColor = Colors.grey;
        break;
      default:
        statusColor = Colors.grey;
    }

    return Card(
      elevation: 2,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ComplaintDetailsScreen(complaintId: complaint.id),
            ),
          );
          if (mounted) {
            _loadComplaints();
          }
        },
        title: Row(
          children: [
            Expanded(
              child: Text(
                complaint.subject,
                style: const TextStyle(fontWeight: FontWeight.bold),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: statusColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                complaint.status.toUpperCase(),
                style: TextStyle(
                  color: statusColor,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '#${complaint.ticketNumber}',
                style: const TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
              const SizedBox(height: 4),
              Text(
                complaint.createdAt.toString().split(' ')[0],
                style: const TextStyle(fontSize: 12, color: AppColors.gray400),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
