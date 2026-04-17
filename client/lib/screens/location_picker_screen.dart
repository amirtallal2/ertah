// Location Picker Screen
// شاشة تحديد الموقع - تصميم جديد بوضع ليلي

import 'package:flutter/material.dart';

import 'package:flutter_animate/flutter_animate.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:provider/provider.dart';

import '../providers/location_provider.dart';
import '../services/services.dart';
import 'dart:async';
import '../services/app_localizations.dart';

class LocationPickerScreen extends StatefulWidget {
  final Function(Map<String, dynamic>)? onComplete;
  final bool returnDataOnly;

  const LocationPickerScreen({
    super.key,
    this.onComplete,
    this.returnDataOnly = false,
  });

  @override
  State<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends State<LocationPickerScreen> {
  final AddressesService _addressesService = AddressesService();

  GoogleMapController? _mapController;
  LatLng _currentPosition = const LatLng(24.7136, 46.6753); // Riyadh
  String _currentAddress = '';
  String _currentCountryCode = '';
  String _currentCityName = '';
  String _currentVillageName = '';
  bool _isLoading = true;
  bool _isSaving = false;
  bool _hasLocationPermission = false;

  // Dark Map Style JSON
  final String _darkMapStyle = '''
[
  {
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#212121"
      }
    ]
  },
  {
    "elementType": "labels.icon",
    "stylers": [
      {
        "visibility": "off"
      }
    ]
  },
  {
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "elementType": "labels.text.stroke",
    "stylers": [
      {
        "color": "#212121"
      }
    ]
  },
  {
    "featureType": "administrative",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "featureType": "administrative.country",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#9e9e9e"
      }
    ]
  },
  {
    "featureType": "administrative.land_parcel",
    "stylers": [
      {
        "visibility": "off"
      }
    ]
  },
  {
    "featureType": "administrative.locality",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#bdbdbd"
      }
    ]
  },
  {
    "featureType": "poi",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "featureType": "poi.park",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#181818"
      }
    ]
  },
  {
    "featureType": "poi.park",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#616161"
      }
    ]
  },
  {
    "featureType": "poi.park",
    "elementType": "labels.text.stroke",
    "stylers": [
      {
        "color": "#1b1b1b"
      }
    ]
  },
  {
    "featureType": "road",
    "elementType": "geometry.fill",
    "stylers": [
      {
        "color": "#2c2c2c"
      }
    ]
  },
  {
    "featureType": "road",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#8a8a8a"
      }
    ]
  },
  {
    "featureType": "road.arterial",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#373737"
      }
    ]
  },
  {
    "featureType": "road.highway",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#3c3c3c"
      }
    ]
  },
  {
    "featureType": "road.highway.controlled_access",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#4e4e4e"
      }
    ]
  },
  {
    "featureType": "road.local",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#616161"
      }
    ]
  },
  {
    "featureType": "transit",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "featureType": "water",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#000000"
      }
    ]
  },
  {
    "featureType": "water",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#3d3d3d"
      }
    ]
  }
]
''';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _determinePosition();
    });
  }

  StreamSubscription<Position>? _positionStreamSubscription;

  @override
  void dispose() {
    _positionStreamSubscription?.cancel();
    super.dispose();
  }

  Future<void> _determinePosition() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) {
          setState(() => _hasLocationPermission = false);
        }
        _useDefaultLocation();
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          if (mounted) {
            setState(() => _hasLocationPermission = false);
          }
          _useDefaultLocation();
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        if (mounted) {
          setState(() => _hasLocationPermission = false);
        }
        _useDefaultLocation();
        return;
      }

      if (mounted) {
        setState(() => _hasLocationPermission = true);
      }

      // 1. Get initial position quickly
      Position? lastKnown = await Geolocator.getLastKnownPosition();
      if (lastKnown != null && mounted) {
        setState(() {
          _currentPosition = LatLng(lastKnown.latitude, lastKnown.longitude);
        });
        _mapController?.animateCamera(
          CameraUpdate.newLatLngZoom(_currentPosition, 16),
        );
        _getAddressFromLatLng();
      }

      // 2. Start high-precision stream
      const LocationSettings locationSettings = LocationSettings(
        accuracy:
            LocationAccuracy.bestForNavigation, // Highest possible accuracy
        distanceFilter: 10, // Update if moved 10 meters
      );

      await _positionStreamSubscription?.cancel();
      _positionStreamSubscription =
          Geolocator.getPositionStream(
            locationSettings: locationSettings,
          ).listen(
            (Position position) {
              if (!mounted) return;

              // Update marker and camera to this precise location
              setState(() {
                _currentPosition = LatLng(
                  position.latitude,
                  position.longitude,
                );
                _isLoading = false;
              });

              _mapController?.animateCamera(
                CameraUpdate.newCameraPosition(
                  CameraPosition(
                    target: _currentPosition,
                    zoom: 19.0, // Maximum zoom for street-level detail
                    tilt: 0,
                  ),
                ),
              );

              // Fetch address for this precise location
              _getAddressFromLatLng();
            },
            onError: (_) {
              if (!mounted) return;
              setState(() {
                _isLoading = false;
              });
            },
          );
    } catch (e) {
      if (mounted) {
        setState(() => _hasLocationPermission = false);
      }
      _useDefaultLocation();
    }
  }

  void _useDefaultLocation() {
    if (mounted) {
      setState(() => _isLoading = false);
      _getAddressFromLatLng();
    }
  }

  Future<void> _getAddressFromLatLng() async {
    try {
      List<Placemark> placemarks = await placemarkFromCoordinates(
        _currentPosition.latitude,
        _currentPosition.longitude,
      );

      if (placemarks.isNotEmpty && mounted) {
        Placemark place = placemarks[0];
        List<String> addressParts = [];

        // Add street name (most important)
        if (place.street != null && place.street!.isNotEmpty) {
          addressParts.add(place.street!);
        }

        // Add building name/number if available and different from street
        if (place.name != null &&
            place.name != place.street &&
            place.name!.isNotEmpty) {
          // check if name is just numbers (building number)
          addressParts.add(place.name!);
        }

        // Add District / SubLocality
        if (place.subLocality != null && place.subLocality!.isNotEmpty) {
          addressParts.add(place.subLocality!);
        }

        // Add City
        if (place.locality != null && place.locality!.isNotEmpty) {
          addressParts.add(place.locality!);
        }

        // Add Administrative Area (Region)
        if (place.administrativeArea != null &&
            place.administrativeArea!.isNotEmpty) {
          addressParts.add(place.administrativeArea!);
        }

        setState(() {
          // Remove duplicates effectively
          _currentAddress = addressParts.toSet().join('، ');
          _currentCountryCode = (place.isoCountryCode ?? '')
              .trim()
              .toUpperCase();
          _currentCityName = (place.locality ?? place.administrativeArea ?? '')
              .trim();
          _currentVillageName = (place.subLocality ?? '').trim();
        });
      }
    } catch (e) {
      debugPrint('Error getting address: $e');
    }
  }

  String _resolvedAddressOrFallback() {
    final directAddress = _currentAddress.trim();
    if (directAddress.isNotEmpty) {
      return directAddress;
    }

    final fallbackParts = <String>{
      _currentVillageName.trim(),
      _currentCityName.trim(),
    }.where((part) => part.isNotEmpty).toList();

    if (fallbackParts.isNotEmpty) {
      return fallbackParts.join('، ');
    }

    return context.tr('address_not_specified');
  }

  Future<void> _saveLocation() async {
    if (_isSaving) return;
    setState(() => _isSaving = true);

    final messenger = ScaffoldMessenger.maybeOf(context);
    final definedLocationLabel = context.tr('defined_location');
    final locationSavedMessage = context.tr('location_saved_successfully');
    final saveFailedMessage = context.tr('save_changes_failed');
    final safeAddress = _resolvedAddressOrFallback();

    try {
      // Update Global State
      await context.read<LocationProvider>().updateLocation(
        address: safeAddress,
        position: _currentPosition,
        countryCode: _currentCountryCode,
        cityName: _currentCityName,
        villageName: _currentVillageName,
      );

      // Attempt to save to backend (if logged in)
      try {
        await _addressesService.addAddress(
          title: definedLocationLabel,
          address: safeAddress,
          lat: _currentPosition.latitude,
          lng: _currentPosition.longitude,
          notes: '',
          countryCode: _currentCountryCode,
          cityName: _currentCityName,
          villageName: _currentVillageName,
        );
      } catch (_) {
        // Ignore backend errors for now, main goal is flow
      }

      if (!mounted) return;

      setState(() => _isSaving = false);

      final data = {
        'address': safeAddress,
        'lat': _currentPosition.latitude,
        'lng': _currentPosition.longitude,
        'country_code': _currentCountryCode,
        'city_name': _currentCityName,
        'village_name': _currentVillageName,
      };

      if (widget.onComplete != null) {
        widget.onComplete!(data);
      } else {
        Navigator.pop(context, data);
      }

      messenger?.showSnackBar(
        SnackBar(
          content: Text(locationSavedMessage),
          backgroundColor: const Color(0xFFFBCC26),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      messenger?.showSnackBar(
        SnackBar(
          content: Text(saveFailedMessage),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // 1. Full Screen Map
          GoogleMap(
            initialCameraPosition: CameraPosition(
              target: _currentPosition,
              zoom: 16,
            ),
            style: _darkMapStyle,
            onMapCreated: (controller) {
              _mapController = controller;
            },
            myLocationEnabled: _hasLocationPermission,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onCameraMove: (position) {
              _currentPosition = position.target;
            },
            onCameraIdle: () {
              _getAddressFromLatLng();
            },
          ),

          // 2. Center Pin
          Center(
            child:
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFBCC26), // Yellow pin
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black38,
                            blurRadius: 10,
                            offset: Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Icon(Icons.location_on, color: Colors.white),
                    ),
                    Container(
                      width: 4,
                      height: 30,
                      color: const Color(0xFFFBCC26),
                    ),
                    Container(
                      width: 10,
                      height: 10,
                      decoration: const BoxDecoration(
                        color: Colors.blue,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.blueAccent,
                            blurRadius: 5,
                            spreadRadius: 2,
                          ),
                        ],
                      ),
                    ),
                  ],
                ).animate().slideY(
                  begin: -0.2,
                  end: 0,
                  duration: 600.ms,
                  curve: Curves.elasticOut,
                ),
          ),

          // 3. Header (Yellow)
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: EdgeInsets.only(
                top: MediaQuery.of(context).padding.top + 10,
                bottom: 20,
                left: 20,
                right: 20,
              ),
              decoration: const BoxDecoration(
                color: Color(0xFFFBCC26),
                borderRadius: BorderRadius.vertical(
                  bottom: Radius.circular(30),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black26,
                    blurRadius: 10,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: const BoxDecoration(
                        color: Colors.black12,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.arrow_back, color: Colors.white),
                    ),
                  ),
                  Text(
                    context.tr('location_picker'),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF333333), // Dark text on yellow
                    ),
                  ),
                  const SizedBox(width: 40), // Balance
                ],
              ),
            ),
          ),

          // 4. My Location Button (Bottom Left)
          Positioned(
            bottom: 200,
            left: 20,
            child: FloatingActionButton(
              onPressed: _determinePosition,
              backgroundColor: const Color(0xFFFBCC26),
              foregroundColor: Colors.white,
              child: _isLoading
                  ? const Padding(
                      padding: EdgeInsets.all(12.0),
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 3,
                      ),
                    )
                  : const Icon(Icons.navigation),
            ),
          ),

          // 5. Address Card (Bottom Sheet style)
          Positioned(
            bottom: 20,
            left: 20,
            right: 20,
            child:
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: const Color(
                      0xFF424242,
                    ).withValues(alpha: 0.95), // Dark Gray
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: const [
                      BoxShadow(
                        color: Colors.black45,
                        blurRadius: 15,
                        offset: Offset(0, 5),
                      ),
                    ],
                    border: Border.all(color: Colors.white10),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          const Icon(
                            Icons.location_on,
                            color: Color(0xFFFBCC26),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              _currentAddress.isEmpty
                                  ? (_isLoading
                                        ? context.tr('detecting_location')
                                        : _resolvedAddressOrFallback())
                                  : _currentAddress,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 14,
                                height: 1.4,
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton(
                          onPressed: _isSaving ? null : _saveLocation,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFFBCC26),
                            foregroundColor: const Color(0xFF333333),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(15),
                            ),
                            elevation: 0,
                          ),
                          child: _isSaving
                              ? const CircularProgressIndicator(
                                  color: Colors.black,
                                )
                              : Text(
                                  context.tr('save'),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                        ),
                      ),
                    ],
                  ),
                ).animate().slideY(
                  begin: 0.5,
                  end: 0,
                  duration: 500.ms,
                  curve: Curves.easeOutBack,
                ),
          ),
        ],
      ),
    );
  }
}
