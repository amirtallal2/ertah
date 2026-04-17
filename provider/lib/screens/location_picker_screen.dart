import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../providers/location_provider.dart';
import '../services/app_localizations.dart';
import '../services/providers_service.dart';

class LocationPickerScreen extends StatefulWidget {
  final Function(Map<String, dynamic>)? onComplete;

  const LocationPickerScreen({super.key, this.onComplete});

  @override
  State<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends State<LocationPickerScreen> {
  GoogleMapController? _mapController;
  final ProvidersService _providersService = ProvidersService();
  LatLng _currentPosition = const LatLng(24.7136, 46.6753);
  String _currentAddress = '';
  bool _isLoading = true;
  bool _isSaving = false;
  bool _hasLocationPermission = false;

  bool _isMandatoryFlow(BuildContext context) {
    return widget.onComplete != null && !Navigator.of(context).canPop();
  }

  void _showSaveToContinueHint() {
    final messenger = ScaffoldMessenger.maybeOf(context);
    messenger?.showSnackBar(
      SnackBar(content: Text(context.tr('location_save_to_continue'))),
    );
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _determinePosition();
    });
  }

  Future<void> _determinePosition() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) {
          setState(() {
            _hasLocationPermission = false;
            _isLoading = false;
          });
        }
        await _getAddressFromLatLng();
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (mounted) {
          setState(() {
            _hasLocationPermission = false;
            _isLoading = false;
          });
        }
        await _getAddressFromLatLng();
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.best,
          timeLimit: Duration(seconds: 12),
        ),
      );

      if (!mounted) return;

      setState(() {
        _hasLocationPermission = true;
        _currentPosition = LatLng(position.latitude, position.longitude);
        _isLoading = false;
      });

      _mapController?.animateCamera(
        CameraUpdate.newLatLngZoom(_currentPosition, 16),
      );

      await _getAddressFromLatLng();
    } catch (_) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _hasLocationPermission = false;
        });
      }
      await _getAddressFromLatLng();
    }
  }

  Future<void> _getAddressFromLatLng() async {
    try {
      final placemarks = await placemarkFromCoordinates(
        _currentPosition.latitude,
        _currentPosition.longitude,
      );
      if (!mounted) return;
      if (placemarks.isEmpty) {
        setState(() {
          if (_currentAddress.trim().isEmpty) {
            _currentAddress = context.tr('address_not_specified');
          }
        });
        return;
      }

      final place = placemarks.first;
      final parts = <String>[];
      if ((place.street ?? '').trim().isNotEmpty) {
        parts.add(place.street!.trim());
      }
      if ((place.subLocality ?? '').trim().isNotEmpty) {
        parts.add(place.subLocality!.trim());
      }
      if ((place.locality ?? '').trim().isNotEmpty) {
        parts.add(place.locality!.trim());
      }
      if ((place.country ?? '').trim().isNotEmpty) {
        parts.add(place.country!.trim());
      }

      setState(() {
        _currentAddress = parts.toSet().join('، ').trim();
        if (_currentAddress.isEmpty) {
          _currentAddress = context.tr('address_not_specified');
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        if (_currentAddress.trim().isEmpty) {
          _currentAddress = context.tr('address_not_specified');
        }
      });
    }
  }

  String _resolvedAddressOrFallback() {
    final address = _currentAddress.trim();
    if (address.isNotEmpty) {
      return address;
    }
    return context.tr('address_not_specified');
  }

  Future<void> _saveLocation() async {
    if (_isSaving) return;
    setState(() => _isSaving = true);

    final messenger = ScaffoldMessenger.maybeOf(context);
    final locationSavedMessage = context.tr('location_saved_successfully');
    final saveFailedMessage = context.tr('save_changes_failed');
    final definedLocationTitle = context.tr('defined_location');
    final locationProvider = context.read<LocationProvider>();
    final authProvider = context.read<AuthProvider>();
    final address = _resolvedAddressOrFallback();

    try {
      final response = await _providersService.saveProfileLocation(
        address: address,
        lat: _currentPosition.latitude,
        lng: _currentPosition.longitude,
      );
      if (!response.success) {
        if (!mounted) return;
        setState(() => _isSaving = false);
        messenger?.showSnackBar(
          SnackBar(content: Text(response.message ?? saveFailedMessage)),
        );
        return;
      }

      await locationProvider.updateLocation(
        address: address,
        position: _currentPosition,
        title: definedLocationTitle,
      );
      await authProvider.refreshUser();

      if (!mounted) return;

      setState(() => _isSaving = false);

      final data = {
        'address': address,
        'lat': _currentPosition.latitude,
        'lng': _currentPosition.longitude,
      };

      if (widget.onComplete != null) {
        widget.onComplete!(data);
      } else {
        Navigator.pop(context, data);
      }

      messenger?.showSnackBar(SnackBar(content: Text(locationSavedMessage)));
    } catch (_) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      messenger?.showSnackBar(SnackBar(content: Text(saveFailedMessage)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isMandatoryFlow = _isMandatoryFlow(context);
    return PopScope(
      canPop: !isMandatoryFlow,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop || !isMandatoryFlow) {
          return;
        }
        _showSaveToContinueHint();
      },
      child: Scaffold(
        body: Stack(
          children: [
            GoogleMap(
              initialCameraPosition: CameraPosition(
                target: _currentPosition,
                zoom: 15,
              ),
              myLocationEnabled: _hasLocationPermission,
              myLocationButtonEnabled: false,
              zoomControlsEnabled: false,
              onMapCreated: (controller) {
                _mapController = controller;
              },
              onCameraMove: (position) {
                _currentPosition = position.target;
              },
              onCameraIdle: _getAddressFromLatLng,
            ),
            const Center(
              child: Icon(
                Icons.location_on,
                size: 44,
                color: Color(0xFFFBCC26),
              ),
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: Container(
                padding: EdgeInsets.only(
                  top: MediaQuery.of(context).padding.top + 10,
                  bottom: 14,
                  left: 16,
                  right: 16,
                ),
                color: Colors.white.withValues(alpha: 0.96),
                child: Row(
                  children: [
                    IconButton(
                      onPressed: isMandatoryFlow
                          ? _showSaveToContinueHint
                          : () => Navigator.pop(context),
                      icon: const Icon(Icons.arrow_back),
                    ),
                    Expanded(
                      child: Text(
                        context.tr('location_picker'),
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 40),
                  ],
                ),
              ),
            ),
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.12),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      context.tr('move_map'),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 12,
                        color: Colors.black54,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _currentAddress.trim().isEmpty
                          ? context.tr('detecting_location')
                          : _currentAddress,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 13),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 48,
                      child: ElevatedButton(
                        onPressed: (_isSaving || _isLoading)
                            ? null
                            : _saveLocation,
                        child: _isSaving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : Text(context.tr('save')),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
