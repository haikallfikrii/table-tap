import 'package:flutter/material.dart';

import '../bridge_controller.dart';
import '../settings.dart';
import 'settings_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.controller});

  final BridgeController controller;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  BridgeController get c => widget.controller;

  @override
  void initState() {
    super.initState();
    c.addListener(_refresh);
  }

  @override
  void dispose() {
    c.removeListener(_refresh);
    super.dispose();
  }

  void _refresh() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _openSettings() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SettingsPage(controller: c),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (c.loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final ThemeData theme = Theme.of(context);
    final List<String> active = c.settings.activeStations;

    return Scaffold(
      appBar: AppBar(
        title: const Text('TableTap Print Bridge'),
        actions: <Widget>[
          IconButton(
            onPressed: _openSettings,
            icon: const Icon(Icons.settings),
            tooltip: 'Tetapan / Settings',
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Card(
            color: c.isRunning
                ? Colors.green.shade50
                : (c.state == BridgeState.error
                    ? Colors.red.shade50
                    : theme.colorScheme.surfaceVariant),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    c.isRunning
                        ? 'AKTIF — jangan tutup app ini'
                        : (c.state == BridgeState.error
                            ? 'RALAT / ERROR'
                            : 'BERHENTI / STOPPED'),
                    style: theme.textTheme.titleLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    c.isRunning
                        ? 'Keep this app open. Biarkan tablet berhubung Wi-Fi yang sama dengan printer.'
                        : 'Tekan Mula untuk terima tiket / Tap Start to receive tickets.',
                  ),
                  if (c.shopName.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 8),
                    Text('Kedai / Shop: ${c.shopName}'),
                  ],
                  if (c.lastError.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 8),
                    Text(c.lastError, style: TextStyle(color: Colors.red.shade700)),
                  ],
                  const SizedBox(height: 8),
                  Text('Tiket dicetak / Printed: ${c.printedCount}'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 56,
            child: FilledButton.icon(
              onPressed: () => c.isRunning ? c.stop() : c.start(),
              icon: Icon(c.isRunning ? Icons.stop : Icons.play_arrow),
              label: Text(
                c.isRunning ? 'Berhenti / Stop' : 'Mula / Start',
                style: const TextStyle(fontSize: 18),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text('Printer', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          ...kKnownStations.map((String kod) {
            final PrinterConfig cfg =
                c.settings.printers[kod] ?? PrinterConfig();
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                cfg.isUsable ? Icons.print : Icons.print_disabled,
                color: cfg.isUsable ? Colors.green : Colors.grey,
              ),
              title: Text(_stationName(kod)),
              subtitle: Text(
                cfg.host.trim().isEmpty
                    ? 'Tiada IP / not set'
                    : '${cfg.host}:${cfg.port}${cfg.enabled ? '' : ' (OFF)'}',
              ),
              trailing: TextButton(
                onPressed: cfg.host.trim().isEmpty
                    ? null
                    : () => c.testPrinter(kod),
                child: const Text('Test'),
              ),
            );
          }),
          if (active.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Belum ada printer aktif. Buka Tetapan dan masukkan IP printer.\n'
                'No active printer yet. Open Settings and enter printer IPs.',
                style: TextStyle(color: Colors.orange),
              ),
            ),
          const SizedBox(height: 20),
          Text('Log', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          if (c.logs.isEmpty)
            const Text('—')
          else
            ...c.logs.take(40).map(
                  (BridgeLogEntry entry) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 2),
                    child: Text(
                      '${entry.time}  ${entry.message}',
                      style: TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 12,
                        color: entry.isError ? Colors.red.shade700 : null,
                      ),
                    ),
                  ),
                ),
        ],
      ),
    );
  }

  static String _stationName(String kod) {
    switch (kod) {
      case 'kasir':
        return 'Kasir / Cashier';
      case 'dapur':
        return 'Dapur / Kitchen';
      case 'minuman':
        return 'Minuman / Drinks';
      default:
        return kod;
    }
  }
}
