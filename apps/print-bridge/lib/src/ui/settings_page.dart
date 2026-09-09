import 'package:flutter/material.dart';

import '../bridge_controller.dart';
import '../settings.dart';

class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key, required this.controller});

  final BridgeController controller;

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  late final TextEditingController _baseUrl;
  late final TextEditingController _token;
  late final TextEditingController _poll;
  final Map<String, TextEditingController> _hosts =
      <String, TextEditingController>{};
  final Map<String, TextEditingController> _ports =
      <String, TextEditingController>{};
  final Map<String, bool> _enabled = <String, bool>{};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final BridgeSettings s = widget.controller.settings;
    _baseUrl = TextEditingController(text: s.baseUrl);
    _token = TextEditingController(text: s.token);
    _poll = TextEditingController(text: '${s.pollSeconds}');
    for (final String kod in kKnownStations) {
      final PrinterConfig cfg = s.printers[kod] ?? PrinterConfig();
      _hosts[kod] = TextEditingController(text: cfg.host);
      _ports[kod] = TextEditingController(text: '${cfg.port}');
      _enabled[kod] = cfg.enabled;
    }
  }

  @override
  void dispose() {
    _baseUrl.dispose();
    _token.dispose();
    _poll.dispose();
    for (final TextEditingController c in _hosts.values) {
      c.dispose();
    }
    for (final TextEditingController c in _ports.values) {
      c.dispose();
    }
    super.dispose();
  }

  BridgeSettings _collect() {
    final Map<String, PrinterConfig> printers = <String, PrinterConfig>{};
    for (final String kod in kKnownStations) {
      printers[kod] = PrinterConfig(
        host: _hosts[kod]!.text.trim(),
        port: int.tryParse(_ports[kod]!.text.trim()) ?? kDefaultPrinterPort,
        enabled: _enabled[kod] ?? false,
      );
    }
    return BridgeSettings(
      baseUrl: _baseUrl.text.trim().isEmpty
          ? kDefaultBaseUrl
          : _baseUrl.text.trim(),
      token: _token.text.trim(),
      pollSeconds: int.tryParse(_poll.text.trim()) ?? 3,
      printers: printers,
    );
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    await widget.controller.saveSettings(_collect());
    if (!mounted) {
      return;
    }
    setState(() => _saving = false);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Disimpan / Saved')),
    );
  }

  Future<void> _checkServer() async {
    await widget.controller.saveSettings(_collect());
    final bool ok = await widget.controller.testConnection();
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Token OK — ${widget.controller.shopName}'
              : 'Gagal / Failed: ${widget.controller.lastError}',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tetapan / Settings')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          const Text(
            'Pelayan / Server',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _baseUrl,
            keyboardType: TextInputType.url,
            decoration: const InputDecoration(
              labelText: 'Alamat pelayan / Server URL',
              hintText: kDefaultBaseUrl,
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _token,
            decoration: const InputDecoration(
              labelText: 'Token Print Bridge',
              helperText:
                  'Salin dari Tetapan Owner → Print Bridge / Copy from Owner Settings',
              helperMaxLines: 2,
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _poll,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Selang semak (saat) / Poll interval (seconds)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _checkServer,
            icon: const Icon(Icons.wifi_tethering),
            label: const Text('Semak token / Check token'),
          ),
          const Divider(height: 32),
          const Text(
            'Printer (ESC/POS, port 9100)',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          const Text(
            'Dapur dan minuman boleh guna printer sahaja — tiada telefon perlu di stesen itu.\n'
            'Kitchen and drinks can be printer-only — no phone needed there.',
            style: TextStyle(fontSize: 12),
          ),
          const SizedBox(height: 12),
          ...kKnownStations.map(_printerCard),
          const SizedBox(height: 24),
          SizedBox(
            height: 52,
            child: FilledButton(
              onPressed: _saving ? null : _save,
              child: const Text('Simpan / Save'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _printerCard(String kod) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _enabled[kod] ?? false,
              onChanged: (bool v) => setState(() => _enabled[kod] = v),
              title: Text(
                _title(kod),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
            Row(
              children: <Widget>[
                Expanded(
                  flex: 3,
                  child: TextField(
                    controller: _hosts[kod],
                    keyboardType: TextInputType.text,
                    decoration: const InputDecoration(
                      labelText: 'IP printer',
                      hintText: '192.168.1.50',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: _ports[kod],
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Port',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  static String _title(String kod) {
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
