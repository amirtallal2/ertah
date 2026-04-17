// Addresses Screen
// شاشة العناوين

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../services/services.dart';
import 'package:provider/provider.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../providers/location_provider.dart';
import 'location_picker_screen.dart';

import '../services/app_localizations.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  final AddressesService _addressesService = AddressesService();
  bool _isLoading = true;
  List<dynamic> _addresses = [];

  @override
  void initState() {
    super.initState();
    _fetchAddresses();
  }

  Future<void> _fetchAddresses() async {
    try {
      final response = await _addressesService.getAddresses();
      if (response.success && response.data != null) {
        final list = response.data is List
            ? response.data as List
            : <dynamic>[];
        setState(() {
          _addresses = list;
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _deleteAddress(int id) async {
    // Confirm dialog
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('delete_address')),
        content: Text(context.tr('confirm_delete_address')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.tr('cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: Text(context.tr('delete')),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      final response = await _addressesService.deleteAddress(id);
      if (response.success) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.tr('address_deleted')),
              backgroundColor: Colors.green,
            ),
          );
        }
        _fetchAddresses();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.message ?? context.tr('delete_failed')),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      // Error
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.gray50,
      appBar: AppBar(
        title: Text(context.tr('my_addresses')),
        centerTitle: true,
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  // Add New Address Button
                  InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const LocationPickerScreen(),
                        ),
                      ).then((value) {
                        if (value != null) {
                          // Assume returns true if added
                          _fetchAddresses();
                        }
                      });
                    },
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        border: Border.all(
                          color: AppColors.primary,
                          style: BorderStyle.solid,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        color: AppColors.primary.withValues(alpha: 0.05),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.add, color: AppColors.primary),
                          const SizedBox(width: 8),
                          Text(
                            context.tr('add_new_address'),
                            style: const TextStyle(
                              color: AppColors.primary,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 24),

                  // Addresses List
                  _addresses.isEmpty
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Text(context.tr('no_addresses')),
                          ),
                        )
                      : ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _addresses.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 16),
                          itemBuilder: (context, index) {
                            final rawItem = _addresses[index];
                            final item = rawItem is Map
                                ? Map<String, dynamic>.from(
                                    rawItem.map(
                                      (key, value) =>
                                          MapEntry(key.toString(), value),
                                    ),
                                  )
                                : <String, dynamic>{};
                            final isDefault =
                                item['is_default'] == true ||
                                item['is_default'] == 1 ||
                                item['is_default'] == '1';
                            final itemAddress = (item['address'] ?? '')
                                .toString();
                            final itemTitle = (item['title'] ?? '').toString();
                            final itemTitleNormalized = itemTitle.toLowerCase();
                            final isHomeTitle =
                                itemTitleNormalized.contains('منزل') ||
                                itemTitleNormalized.contains('home') ||
                                itemTitleNormalized.contains('گھر');
                            final itemId =
                                int.tryParse((item['id'] ?? 0).toString()) ?? 0;

                            final isSelected =
                                context
                                    .watch<LocationProvider>()
                                    .currentAddress ==
                                itemAddress;

                            return GestureDetector(
                              onTap: () {
                                context.read<LocationProvider>().updateLocation(
                                  address: itemAddress,
                                  title: itemTitle.isNotEmpty
                                      ? itemTitle
                                      : item['title'] ??
                                            context.tr('defined_location'),
                                  position: LatLng(
                                    double.tryParse(item['lat'].toString()) ??
                                        24.7136,
                                    double.tryParse(item['lng'].toString()) ??
                                        46.6753,
                                  ),
                                  countryCode: (item['country_code'] ?? '')
                                      .toString()
                                      .trim()
                                      .toUpperCase(),
                                  cityName: (item['city_name'] ?? '')
                                      .toString()
                                      .trim(),
                                  villageName: (item['village_name'] ?? '')
                                      .toString()
                                      .trim(),
                                );
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(context.tr('set_as_current')),
                                  ),
                                );
                              },
                              child:
                                  Container(
                                        padding: const EdgeInsets.all(16),
                                        decoration: BoxDecoration(
                                          color: isSelected
                                              ? AppColors.primary.withValues(
                                                  alpha: 0.05,
                                                )
                                              : Colors.white,
                                          borderRadius: BorderRadius.circular(
                                            16,
                                          ),
                                          boxShadow: AppShadows.sm,
                                          border: isSelected || isDefault
                                              ? Border.all(
                                                  color: isSelected
                                                      ? AppColors.primary
                                                      : AppColors.secondary,
                                                  width: 2,
                                                )
                                              : null,
                                        ),
                                        child: Column(
                                          children: [
                                            Row(
                                              children: [
                                                Icon(
                                                  isSelected
                                                      ? Icons.check_circle
                                                      : (isHomeTitle
                                                            ? Icons.home
                                                            : Icons.work),
                                                  color: isSelected
                                                      ? AppColors.primary
                                                      : AppColors.secondary,
                                                ),
                                                const SizedBox(width: 12),
                                                Expanded(
                                                  child: Column(
                                                    crossAxisAlignment:
                                                        CrossAxisAlignment
                                                            .start,
                                                    children: [
                                                      Text(
                                                        itemTitle.isNotEmpty
                                                            ? itemTitle
                                                            : context.tr(
                                                                'untitled_address',
                                                              ),
                                                        style: const TextStyle(
                                                          fontWeight:
                                                              FontWeight.bold,
                                                        ),
                                                      ),
                                                      Text(
                                                        itemAddress,
                                                        style: const TextStyle(
                                                          fontSize: 12,
                                                          color:
                                                              AppColors.gray500,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                                if (isSelected)
                                                  Container(
                                                    padding:
                                                        const EdgeInsets.symmetric(
                                                          horizontal: 8,
                                                          vertical: 4,
                                                        ),
                                                    decoration: BoxDecoration(
                                                      color: AppColors.primary
                                                          .withValues(
                                                            alpha: 0.1,
                                                          ),
                                                      borderRadius:
                                                          BorderRadius.circular(
                                                            8,
                                                          ),
                                                    ),
                                                    child: Text(
                                                      context.tr(
                                                        'currently_selected',
                                                      ),
                                                      style: const TextStyle(
                                                        fontSize: 10,
                                                        color:
                                                            AppColors.primary,
                                                        fontWeight:
                                                            FontWeight.bold,
                                                      ),
                                                    ),
                                                  )
                                                else if (isDefault)
                                                  Container(
                                                    padding:
                                                        const EdgeInsets.symmetric(
                                                          horizontal: 8,
                                                          vertical: 4,
                                                        ),
                                                    decoration: BoxDecoration(
                                                      color: AppColors.secondary
                                                          .withValues(
                                                            alpha: 0.1,
                                                          ),
                                                      borderRadius:
                                                          BorderRadius.circular(
                                                            8,
                                                          ),
                                                    ),
                                                    child: Text(
                                                      context.tr(
                                                        'default_address',
                                                      ),
                                                      style: const TextStyle(
                                                        fontSize: 10,
                                                        color:
                                                            AppColors.secondary,
                                                        fontWeight:
                                                            FontWeight.bold,
                                                      ),
                                                    ),
                                                  ),
                                              ],
                                            ),
                                            const Divider(height: 24),
                                            Row(
                                              mainAxisAlignment:
                                                  MainAxisAlignment.end,
                                              children: [
                                                // IconButton(
                                                //   icon: const Icon(
                                                //     Icons.edit,
                                                //     size: 20,
                                                //     color: AppColors.gray500,
                                                //   ),
                                                //   onPressed: () {},
                                                // ),
                                                IconButton(
                                                  icon: const Icon(
                                                    Icons.delete,
                                                    size: 20,
                                                    color: Colors.red,
                                                  ),
                                                  onPressed: itemId > 0
                                                      ? () => _deleteAddress(
                                                          itemId,
                                                        )
                                                      : null,
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                      )
                                      .animate()
                                      .fadeIn(delay: (100 * index).ms)
                                      .slideY(begin: 0.1, end: 0),
                            );
                          },
                        ),
                ],
              ),
            ),
    );
  }
}
