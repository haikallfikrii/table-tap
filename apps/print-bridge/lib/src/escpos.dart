import 'dart:typed_data';

/// ESC/POS ticket builder for 58 mm thermal printers.
///
/// Mirrors the layout of the Web Bluetooth printer (assets/js/thermal-print.js)
/// so a shop gets the same ticket whichever path it uses.
class EscPos {
  EscPos({this.width = 32});

  static const int doubleWidth = 16;

  final int width;
  final BytesBuilder _out = BytesBuilder();

  Uint8List bytes() => _out.toBytes();

  void raw(List<int> data) => _out.add(data);

  void init() => raw(<int>[0x1b, 0x40]);

  void feed(int lines) {
    for (int i = 0; i < lines; i++) {
      raw(<int>[0x0a]);
    }
  }

  void cut() => raw(<int>[0x1d, 0x56, 0x00]);

  /// ESC B n t — buzzer on the common Chinese ESC/POS boards, with a BEL fallback.
  void beep(int times, {int duration = 2}) {
    final int n = times.clamp(0, 9).toInt();
    if (n <= 0) {
      return;
    }
    raw(<int>[0x1b, 0x42, n, duration.clamp(1, 9).toInt(), 0x07]);
  }

  void line(
    String text, {
    String align = 'left',
    bool bold = false,
    bool big = false,
    bool wide = false,
    bool tall = false,
  }) {
    switch (align) {
      case 'center':
        raw(<int>[0x1b, 0x61, 0x01]);
        break;
      case 'right':
        raw(<int>[0x1b, 0x61, 0x02]);
        break;
      default:
        raw(<int>[0x1b, 0x61, 0x00]);
    }
    if (bold) {
      raw(<int>[0x1b, 0x45, 0x01]);
    }
    if (big) {
      raw(<int>[0x1d, 0x21, 0x11]);
    } else if (wide) {
      raw(<int>[0x1d, 0x21, 0x10]);
    } else if (tall) {
      raw(<int>[0x1d, 0x21, 0x01]);
    }

    raw(encode(text));
    raw(<int>[0x0a]);

    if (big || wide || tall) {
      raw(<int>[0x1d, 0x21, 0x00]);
    }
    if (bold) {
      raw(<int>[0x1b, 0x45, 0x00]);
    }
  }

  void separator() => line('-' * width);

  /// Most cheap thermal heads speak a single-byte code page, so send Latin-1
  /// and degrade anything outside it rather than printing mojibake.
  static Uint8List encode(String text) {
    final List<int> out = <int>[];
    for (final int rune in text.runes) {
      if (rune < 0x100) {
        out.add(rune);
      } else {
        out.add(0x3f); // '?'
      }
    }
    return Uint8List.fromList(out);
  }

  static List<String> wrap(String text, int max) {
    final String value = text.trim();
    if (max <= 0) {
      return <String>[value];
    }
    if (value.length <= max) {
      return <String>[value];
    }
    final List<String> rows = <String>[];
    String rest = value;
    while (rest.length > max) {
      rows.add(rest.substring(0, max));
      rest = rest.substring(max);
    }
    if (rest.isNotEmpty) {
      rows.add(rest);
    }
    return rows;
  }

  String padAmount(String label, String amount) {
    final int space = width - label.length - amount.length;
    if (space < 1) {
      return '$label $amount';
    }
    return label + ' ' * space + amount;
  }
}

String _str(Map<String, dynamic> job, String key, [String fallback = '']) {
  final dynamic value = job[key];
  if (value == null) {
    return fallback;
  }
  final String text = value.toString().trim();
  return text.isEmpty ? fallback : text;
}

String _label(Map<String, dynamic> job, String key, String fallback) {
  final dynamic labels = job['labels'];
  if (labels is Map && labels[key] != null) {
    final String text = labels[key].toString().trim();
    if (text.isNotEmpty) {
      return text;
    }
  }
  return fallback;
}

int _int(Map<String, dynamic> job, String key, [int fallback = 0]) {
  final dynamic value = job[key];
  if (value is int) {
    return value;
  }
  return int.tryParse('${value ?? ''}') ?? fallback;
}

List<Map<String, dynamic>> _items(Map<String, dynamic> job) {
  final dynamic raw = job['items'];
  if (raw is! List) {
    return <Map<String, dynamic>>[];
  }
  return raw
      .whereType<Map<dynamic, dynamic>>()
      .map((Map<dynamic, dynamic> e) => Map<String, dynamic>.from(e))
      .toList();
}

/// Kitchen / drinks / station ticket — big text, staff read it across the pass.
Uint8List buildKitchenTicket(Map<String, dynamic> job) {
  final EscPos p = EscPos();
  final int beeps = _int(job, 'beep', 4);

  p.init();
  p.beep(beeps);
  p.line(_str(job, 'shop_name', 'TableTap'), align: 'center', bold: true, wide: true);

  final String station = _str(job, 'ticket_label', _str(job, 'station_name'));
  if (station.isNotEmpty) {
    p.line(station, align: 'center', bold: true, big: true);
  }
  p.separator();
  p.line(
    '${_label(job, 'table', 'Meja')} ${_str(job, 'table', '-')}',
    align: 'center',
    bold: true,
    big: true,
  );
  p.line(
    '${_label(job, 'order', 'Order')} #${_str(job, 'order_id', '-')}',
    align: 'center',
    bold: true,
    tall: true,
  );
  final String serve = _str(job, 'serve');
  if (serve.isNotEmpty) {
    p.line(serve, align: 'center', bold: true, tall: true);
  }
  final String guest = _str(job, 'guest');
  if (guest.isNotEmpty) {
    p.line('${_label(job, 'guest', 'Pelanggan')}: $guest',
        align: 'center', bold: true, tall: true);
  }
  final String time = _str(job, 'time');
  if (time.isNotEmpty) {
    p.line(time, align: 'center', tall: true);
  }
  p.separator();

  for (final Map<String, dynamic> item in _items(job)) {
    final String qty = 'x${_int(item, 'qty', 1)} ';
    final List<String> rows =
        EscPos.wrap('${item['name'] ?? ''}', EscPos.doubleWidth - qty.length);
    p.line('$qty${rows.first}', bold: true, big: true);
    for (final String extra in rows.skip(1)) {
      p.line(extra, bold: true, tall: true);
    }
    final String note = '${item['note'] ?? ''}'.trim();
    if (note.isNotEmpty) {
      p.line('* $note', bold: true, tall: true);
    }
    p.feed(1);
  }

  p.separator();
  p.line(_label(job, 'kitchen_ticket', 'TIKET DAPUR'),
      align: 'center', bold: true, wide: true);
  p.feed(3);
  if (beeps > 0) {
    p.beep(beeps > 3 ? 3 : beeps);
  }
  p.cut();
  return p.bytes();
}

/// Paid receipt for the cashier printer.
Uint8List buildReceiptTicket(Map<String, dynamic> job) {
  final EscPos p = EscPos();

  p.init();
  p.beep(_int(job, 'beep', 0));
  p.line(_str(job, 'shop_name', 'TableTap'), align: 'center', bold: true);
  p.line('${_label(job, 'receipt', 'RESIT')} #${_str(job, 'order_id', '-')}',
      align: 'center', bold: true);
  p.separator();
  p.line('${_label(job, 'table', 'Meja')} ${_str(job, 'table', '-')}');

  final String paidAt = _str(job, 'paid_at');
  if (paidAt.isNotEmpty) {
    p.line('${_label(job, 'paid', 'Dibayar')}: $paidAt');
  }
  final String guest = _str(job, 'guest');
  if (guest.isNotEmpty) {
    p.line('${_label(job, 'guest', 'Pelanggan')}: $guest');
  }
  final String serve = _str(job, 'serve');
  if (serve.isNotEmpty) {
    p.line(serve);
  }
  final String splitFrom = _str(job, 'split_from');
  if (splitFrom.isNotEmpty) {
    p.line('${_label(job, 'split_from', 'Bahagi dari')} #$splitFrom');
  }
  p.separator();

  for (final Map<String, dynamic> item in _items(job)) {
    final String qty = '${_int(item, 'qty', 1)}x ';
    final String amount = '${item['amount'] ?? ''}';
    final List<String> rows = EscPos.wrap(
      '${item['name'] ?? ''}',
      p.width - qty.length - amount.length - 1,
    );
    p.line(p.padAmount('$qty${rows.first}', amount));
    for (final String extra in rows.skip(1)) {
      p.line('   $extra');
    }
    final String note = '${item['note'] ?? ''}'.trim();
    if (note.isNotEmpty) {
      p.line('* $note');
    }
  }

  p.separator();
  p.line(p.padAmount(_label(job, 'subtotal', 'Subjumlah'), _str(job, 'subtotal')));
  final String sst = _str(job, 'sst');
  if (sst.isNotEmpty) {
    p.line(p.padAmount(_str(job, 'sst_label', 'SST'), sst));
  }
  p.line(p.padAmount(_label(job, 'total', 'Jumlah'), _str(job, 'total')), bold: true);
  p.separator();
  p.line(_label(job, 'thank_you', 'Terima kasih!'), align: 'center');
  p.line('TableTap', align: 'center');
  p.feed(3);
  p.cut();
  return p.bytes();
}

/// Turn one queued job payload into printer bytes.
Uint8List buildTicket(Map<String, dynamic> payload) {
  final String type = _str(payload, 'type', 'kitchen');
  if (type == 'receipt') {
    return buildReceiptTicket(payload);
  }
  return buildKitchenTicket(payload);
}
