import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Stations the bridge can print to. Extra (Pro) station codes fall back to
/// the kitchen printer so nothing is silently dropped.
const List<String> kKnownStations = <String>['kasir', 'dapur', 'minuman'];

const String kDefaultBaseUrl = 'https://tabletap.my';
const int kDefaultPrinterPort = 9100;

class PrinterConfig {
  PrinterConfig({
    this.host = '',
    this.port = kDefaultPrinterPort,
    this.enabled = false,
  });

  String host;
  int port;
  bool enabled;

  bool get isUsable => enabled && host.trim().isNotEmpty;

  Map<String, dynamic> toJson() => <String, dynamic>{
        'host': host,
        'port': port,
        'enabled': enabled,
      };

  static PrinterConfig fromJson(Map<String, dynamic> json) => PrinterConfig(
        host: (json['host'] ?? '') as String,
        port: (json['port'] ?? kDefaultPrinterPort) as int,
        enabled: (json['enabled'] ?? false) as bool,
      );
}

class BridgeSettings {
  BridgeSettings({
    this.baseUrl = kDefaultBaseUrl,
    this.token = '',
    this.pollSeconds = 3,
    Map<String, PrinterConfig>? printers,
  }) : printers = printers ??
            <String, PrinterConfig>{
              for (final String kod in kKnownStations) kod: PrinterConfig(),
            };

  String baseUrl;
  String token;
  int pollSeconds;
  Map<String, PrinterConfig> printers;

  bool get isConfigured => token.trim().isNotEmpty && baseUrl.trim().isNotEmpty;

  List<String> get activeStations => printers.entries
      .where((MapEntry<String, PrinterConfig> e) => e.value.isUsable)
      .map((MapEntry<String, PrinterConfig> e) => e.key)
      .toList();

  /// Unknown station codes (Pro stations like "western") print on the kitchen
  /// printer, and the cashier printer is the last resort.
  PrinterConfig? printerFor(String station) {
    final PrinterConfig? exact = printers[station];
    if (exact != null && exact.isUsable) {
      return exact;
    }
    for (final String fallback in <String>['dapur', 'kasir']) {
      final PrinterConfig? candidate = printers[fallback];
      if (candidate != null && candidate.isUsable) {
        return candidate;
      }
    }
    return null;
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
        'base_url': baseUrl,
        'token': token,
        'poll_seconds': pollSeconds,
        'printers': printers.map(
          (String k, PrinterConfig v) => MapEntry<String, dynamic>(k, v.toJson()),
        ),
      };

  static BridgeSettings fromJson(Map<String, dynamic> json) {
    final Map<String, PrinterConfig> printers = <String, PrinterConfig>{
      for (final String kod in kKnownStations) kod: PrinterConfig(),
    };
    final dynamic raw = json['printers'];
    if (raw is Map) {
      raw.forEach((dynamic key, dynamic value) {
        if (value is Map) {
          printers[key.toString()] =
              PrinterConfig.fromJson(Map<String, dynamic>.from(value));
        }
      });
    }
    return BridgeSettings(
      baseUrl: (json['base_url'] ?? kDefaultBaseUrl) as String,
      token: (json['token'] ?? '') as String,
      pollSeconds: (json['poll_seconds'] ?? 3) as int,
      printers: printers,
    );
  }
}

class SettingsStore {
  static const String _key = 'tabletap_print_bridge_settings';

  Future<BridgeSettings> load() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) {
      return BridgeSettings();
    }
    try {
      return BridgeSettings.fromJson(
        Map<String, dynamic>.from(jsonDecode(raw) as Map),
      );
    } catch (_) {
      return BridgeSettings();
    }
  }

  Future<void> save(BridgeSettings settings) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(settings.toJson()));
  }
}
