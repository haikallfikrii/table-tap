import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import 'api_client.dart';
import 'escpos.dart';
import 'printer_client.dart';
import 'settings.dart';

enum BridgeState { idle, running, error }

class BridgeLogEntry {
  BridgeLogEntry(this.message, {this.isError = false})
      : at = DateTime.now();

  final String message;
  final bool isError;
  final DateTime at;

  String get time =>
      '${at.hour.toString().padLeft(2, '0')}:${at.minute.toString().padLeft(2, '0')}:${at.second.toString().padLeft(2, '0')}';
}

/// Polls the TableTap server and pushes each claimed ticket to its printer.
class BridgeController extends ChangeNotifier {
  BridgeController({SettingsStore? store, PrinterClient? printer})
      : _store = store ?? SettingsStore(),
        _printer = printer ?? PrinterClient();

  final SettingsStore _store;
  final PrinterClient _printer;

  BridgeSettings settings = BridgeSettings();
  BridgeState state = BridgeState.idle;
  String shopName = '';
  String lastError = '';
  int printedCount = 0;
  bool loading = true;

  final List<BridgeLogEntry> logs = <BridgeLogEntry>[];
  Timer? _timer;
  bool _busy = false;

  bool get isRunning => state == BridgeState.running;

  Future<void> init() async {
    settings = await _store.load();
    loading = false;
    notifyListeners();
  }

  Future<void> saveSettings(BridgeSettings next) async {
    settings = next;
    await _store.save(next);
    notifyListeners();
    if (isRunning) {
      await stop();
      await start();
    }
  }

  void _log(String message, {bool isError = false}) {
    logs.insert(0, BridgeLogEntry(message, isError: isError));
    if (logs.length > 100) {
      logs.removeRange(100, logs.length);
    }
    notifyListeners();
  }

  Future<bool> testConnection() async {
    final BridgeApi api =
        BridgeApi(baseUrl: settings.baseUrl, token: settings.token);
    try {
      final Map<String, dynamic> body = await api.heartbeat();
      shopName = '${body['shop_name'] ?? ''}';
      lastError = '';
      _log('Bersambung / Connected: $shopName');
      return true;
    } catch (e) {
      lastError = '$e';
      _log('Gagal sambung server / Server error: $e', isError: true);
      return false;
    } finally {
      api.close();
      notifyListeners();
    }
  }

  Future<bool> testPrinter(String station) async {
    final PrinterConfig? cfg = settings.printers[station];
    if (cfg == null || cfg.host.trim().isEmpty) {
      _log('$station: tiada IP printer / no printer IP', isError: true);
      return false;
    }
    try {
      await _printer.send(
        cfg.host.trim(),
        cfg.port,
        buildKitchenTicket(<String, dynamic>{
          'shop_name': shopName.isEmpty ? 'TableTap' : shopName,
          'ticket_label': station.toUpperCase(),
          'table': '0',
          'order_id': 'TEST',
          'serve': 'Test print',
          'time': DateTime.now().toString().split('.').first,
          'beep': 1,
          'items': <Map<String, dynamic>>[
            <String, dynamic>{'qty': 1, 'name': 'Test print OK', 'note': ''},
          ],
        }),
      );
      _log('$station: test print OK');
      return true;
    } catch (e) {
      _log('$station: test print gagal / failed — $e', isError: true);
      return false;
    }
  }

  Future<void> start() async {
    if (!settings.isConfigured) {
      lastError = 'Masukkan token dahulu / Enter the token first';
      state = BridgeState.error;
      notifyListeners();
      return;
    }
    if (!await testConnection()) {
      state = BridgeState.error;
      notifyListeners();
      return;
    }

    state = BridgeState.running;
    lastError = '';
    notifyListeners();

    try {
      await WakelockPlus.enable();
    } catch (_) {
      // wakelock is a nice-to-have; polling still works while the app is open
    }

    final int seconds = settings.pollSeconds.clamp(2, 30).toInt();
    _timer?.cancel();
    _timer = Timer.periodic(Duration(seconds: seconds), (_) => _tick());
    unawaited(_tick());
  }

  Future<void> stop() async {
    _timer?.cancel();
    _timer = null;
    state = BridgeState.idle;
    try {
      await WakelockPlus.disable();
    } catch (_) {
      // ignore
    }
    notifyListeners();
  }

  Future<void> _tick() async {
    if (_busy || state != BridgeState.running) {
      return;
    }
    _busy = true;
    final BridgeApi api =
        BridgeApi(baseUrl: settings.baseUrl, token: settings.token);
    try {
      final List<String> stations = settings.activeStations;
      final List<PrintJob> jobs = await api.claim(stations);
      if (jobs.isEmpty) {
        return;
      }

      final List<Map<String, dynamic>> acks = <Map<String, dynamic>>[];
      for (final PrintJob job in jobs) {
        final PrinterConfig? cfg = settings.printerFor(job.station);
        if (cfg == null) {
          acks.add(<String, dynamic>{
            'id': job.id,
            'ok': false,
            'error': 'no printer mapped for ${job.station}',
          });
          _log('#${job.id} ${job.station}: tiada printer / no printer',
              isError: true);
          continue;
        }
        try {
          final Uint8List bytes = buildTicket(job.payload);
          await _printer.send(cfg.host.trim(), cfg.port, bytes);
          acks.add(<String, dynamic>{'id': job.id, 'ok': true});
          printedCount++;
          _log('#${job.id} ${job.station} → ${cfg.host}:${cfg.port} OK');
        } catch (e) {
          acks.add(<String, dynamic>{
            'id': job.id,
            'ok': false,
            'error': '$e',
          });
          _log('#${job.id} ${job.station}: gagal cetak / print failed — $e',
              isError: true);
        }
      }
      await api.ack(acks);
      lastError = '';
    } catch (e) {
      lastError = '$e';
      _log('Poll gagal / poll failed: $e', isError: true);
    } finally {
      api.close();
      _busy = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
