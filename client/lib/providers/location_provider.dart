// Location Provider
// مزود خدمة الموقع والعناوين

import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocationProvider extends ChangeNotifier {
  static const LatLng _fallbackLocation = LatLng(24.7136, 46.6753);

  String _currentAddress = '';
  String _currentAddressTitle = 'المنزل';
  LatLng _currentLocation = _fallbackLocation;
  String _currentCountryCode = '';
  String _currentCityName = '';
  String _currentVillageName = '';
  bool _hasSelectedLocation = false;
  final bool _isLoading = false;

  String get currentAddress => _currentAddress;
  String get currentAddressTitle => _currentAddressTitle;
  LatLng get currentLocation => _currentLocation;
  String get currentCountryCode => _currentCountryCode;
  String get currentCityName => _currentCityName;
  String get currentVillageName => _currentVillageName;
  bool get hasSelectedLocation => _hasSelectedLocation;
  double? get requestLat =>
      _hasSelectedLocation ? _currentLocation.latitude : null;
  double? get requestLng =>
      _hasSelectedLocation ? _currentLocation.longitude : null;
  String? get requestCountryCode {
    if (!_hasSelectedLocation) {
      return null;
    }
    final normalized = _currentCountryCode.trim().toUpperCase();
    return normalized.isEmpty ? null : normalized;
  }

  bool get isLoading => _isLoading;

  LocationProvider() {
    _loadSavedLocation();
  }

  Future<void> _loadSavedLocation() async {
    final prefs = await SharedPreferences.getInstance();
    final address = prefs.getString('selected_address');
    final lat = prefs.getDouble('selected_lat');
    final lng = prefs.getDouble('selected_lng');
    final countryCode = prefs.getString('selected_country_code');
    final cityName = prefs.getString('selected_city_name');
    final villageName = prefs.getString('selected_village_name');

    if (address != null &&
        address.trim().isNotEmpty &&
        lat != null &&
        lng != null) {
      _currentAddress = address.trim();
      _currentLocation = LatLng(lat, lng);
      if (countryCode != null && countryCode.trim().isNotEmpty) {
        _currentCountryCode = countryCode.trim().toUpperCase();
      }
      if (cityName != null && cityName.trim().isNotEmpty) {
        _currentCityName = cityName.trim();
      }
      _currentVillageName = (villageName ?? '').trim();
      _hasSelectedLocation = true;
      notifyListeners();
      await _normalizeAddressIfCoordinates();
    }
  }

  bool _looksLikeCoordinates(String value) {
    final text = value.trim();
    if (text.isEmpty) return false;
    final coordinatePattern = RegExp(
      r'^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$',
    );
    return coordinatePattern.hasMatch(text);
  }

  Future<String?> _reverseGeocodeAddress(LatLng position) async {
    try {
      final placemarks = await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      );
      if (placemarks.isEmpty) return null;

      final place = placemarks.first;
      final parts = <String>[];

      void addPart(String? value) {
        final trimmed = (value ?? '').trim();
        if (trimmed.isNotEmpty && !parts.contains(trimmed)) {
          parts.add(trimmed);
        }
      }

      addPart(place.street);
      addPart(place.subLocality);
      addPart(place.locality);
      addPart(place.administrativeArea);
      addPart(place.country);

      if (parts.isEmpty) {
        return null;
      }
      return parts.join('، ');
    } catch (_) {
      return null;
    }
  }

  Future<void> _normalizeAddressIfCoordinates() async {
    if (!_looksLikeCoordinates(_currentAddress)) {
      return;
    }

    final resolved = await _reverseGeocodeAddress(_currentLocation);
    if (resolved == null || resolved.trim().isEmpty) {
      return;
    }

    _currentAddress = resolved.trim();
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('selected_address', _currentAddress);
  }

  Future<void> updateLocation({
    required String address,
    required LatLng position,
    String title = 'موقع محدد',
    String? countryCode,
    String? cityName,
    String? villageName,
  }) async {
    var normalizedAddress = address.trim();
    if (_looksLikeCoordinates(normalizedAddress)) {
      final resolvedAddress = await _reverseGeocodeAddress(position);
      if (resolvedAddress != null && resolvedAddress.trim().isNotEmpty) {
        normalizedAddress = resolvedAddress.trim();
      }
    }

    _currentAddress = normalizedAddress;
    _currentAddressTitle = title;
    _currentLocation = position;
    final normalizedCountry = (countryCode ?? '').trim().toUpperCase();
    if (normalizedCountry.isNotEmpty) {
      _currentCountryCode = normalizedCountry;
    }
    final normalizedCity = (cityName ?? '').trim();
    if (normalizedCity.isNotEmpty) {
      _currentCityName = normalizedCity;
    }
    _currentVillageName = (villageName ?? '').trim();
    _hasSelectedLocation = true;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('selected_address', _currentAddress);
    await prefs.setDouble('selected_lat', position.latitude);
    await prefs.setDouble('selected_lng', position.longitude);
    await prefs.setString('selected_country_code', _currentCountryCode);
    await prefs.setString('selected_city_name', _currentCityName);
    await prefs.setString('selected_village_name', _currentVillageName);
  }
}
