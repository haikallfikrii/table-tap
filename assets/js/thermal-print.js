/**
 * TableTap — Bluetooth thermal kitchen tickets (Web Bluetooth + ESC/POS)
 * Silent print from the kitchen/station device (no browser print dialog).
 */
(function (global) {
  'use strict';

  var SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb',
    '0000ff00-0000-1000-8000-00805f9b34fb',
    '0000ffe0-0000-1000-8000-00805f9b34fb',
    '0000ae30-0000-1000-8000-00805f9b34fb',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    'e7810a71-73ae-43a5-8f0c-2a5e0c4d5e0c',
  ];

  var WRITE_HINTS = [
    '00002af1-0000-1000-8000-00805f9b34fb',
    '0000ff02-0000-1000-8000-00805f9b34fb',
    '0000ffe1-0000-1000-8000-00805f9b34fb',
    '0000ae01-0000-1000-8000-00805f9b34fb',
    '49535343-8841-43f4-a8d4-ecbe34729bb3',
    'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
  ];

  var device = null;
  var characteristic = null;
  var connecting = false;
  var listeners = [];

  function supported() {
    return !!(navigator.bluetooth && typeof navigator.bluetooth.requestDevice === 'function');
  }

  function isConnected() {
    return !!(device && device.gatt && device.gatt.connected && characteristic);
  }

  function notify() {
    var state = {
      supported: supported(),
      connected: isConnected(),
      name: device ? (device.name || 'Printer') : '',
    };
    listeners.forEach(function (fn) {
      try { fn(state); } catch (e) { /* ignore */ }
    });
  }

  function onChange(fn) {
    if (typeof fn === 'function') listeners.push(fn);
    notify();
    return function () {
      listeners = listeners.filter(function (x) { return x !== fn; });
    };
  }

  function encodeText(str) {
    // Prefer TextEncoder (UTF-8). Many BLE printers accept Latin-1-ish bytes;
    // non-ASCII Malay/EN names usually still print readable enough.
    if (typeof TextEncoder !== 'undefined') {
      return new TextEncoder().encode(String(str == null ? '' : str));
    }
    var s = String(str == null ? '' : str);
    var out = new Uint8Array(s.length);
    for (var i = 0; i < s.length; i++) out[i] = s.charCodeAt(i) & 0xff;
    return out;
  }

  function concatBytes(chunks) {
    var total = 0;
    chunks.forEach(function (c) { total += c.length; });
    var out = new Uint8Array(total);
    var off = 0;
    chunks.forEach(function (c) {
      out.set(c, off);
      off += c.length;
    });
    return out;
  }

  function line(text, opts) {
    opts = opts || {};
    var parts = [];
    if (opts.align === 'center') parts.push(new Uint8Array([0x1b, 0x61, 0x01]));
    else if (opts.align === 'right') parts.push(new Uint8Array([0x1b, 0x61, 0x02]));
    else parts.push(new Uint8Array([0x1b, 0x61, 0x00]));

    if (opts.bold) parts.push(new Uint8Array([0x1b, 0x45, 0x01]));
    if (opts.double) parts.push(new Uint8Array([0x1d, 0x21, 0x11]));
    else if (opts.wide) parts.push(new Uint8Array([0x1d, 0x21, 0x10]));
    else if (opts.tall) parts.push(new Uint8Array([0x1d, 0x21, 0x01]));

    parts.push(encodeText(text));
    parts.push(new Uint8Array([0x0a]));

    if (opts.double || opts.wide || opts.tall) parts.push(new Uint8Array([0x1d, 0x21, 0x00]));
    if (opts.bold) parts.push(new Uint8Array([0x1b, 0x45, 0x00]));
    return concatBytes(parts);
  }

  function separator(width) {
    width = width || 32;
    return line(new Array(width + 1).join('-'));
  }

  /**
   * ESC B n t — common Chinese ESC/POS buzzer (MTP-II / PT200 class).
   * n = times (1–9), t = duration/interval unit (1–9).
   * times=0 → empty (no beep).
   */
  function beep(times, duration) {
    var n = Math.max(0, Math.min(9, Number(times) || 0));
    if (n <= 0) return new Uint8Array(0);
    var t = Math.max(1, Math.min(9, Number(duration) || 2));
    return new Uint8Array([
      0x1b, 0x42, n, t, // ESC B n t
      0x07,             // BEL fallback
    ]);
  }

  function resolveBeepCount(labels, fallback) {
    if (labels && labels.beep_count != null) {
      return Math.max(0, Math.min(9, Number(labels.beep_count) || 0));
    }
    return Math.max(0, Math.min(9, Number(fallback) || 0));
  }

  function wrapName(name, width) {
    width = width || 28;
    var s = String(name || '').trim();
    if (s.length <= width) return [s];
    var rows = [];
    while (s.length > width) {
      rows.push(s.slice(0, width));
      s = s.slice(width);
    }
    if (s) rows.push(s);
    return rows;
  }

  /**
   * Build one kitchen ticket (ESC/POS bytes) for a group of items (same order).
   */
  function buildKitchenTicket(ticket, labels) {
    labels = labels || {};
    var width = 32;
    var chunks = [];
    var beepCount = resolveBeepCount(labels, 4);
    chunks.push(new Uint8Array([0x1b, 0x40])); // init
    // Alert kitchen: tit-tit before ticket body (MTP-II has hardware buzzer)
    if (beepCount > 0) {
      chunks.push(beep(beepCount, 2));
    }
    chunks.push(line(ticket.shopName || 'TableTap', { align: 'center', bold: true }));
    if (ticket.stationName) {
      chunks.push(line(ticket.stationName, { align: 'center' }));
    }
    chunks.push(separator(width));
    chunks.push(line((labels.table || 'Meja') + ' ' + (ticket.table || '-'), { bold: true, double: true, align: 'center' }));
    chunks.push(line((labels.order || 'Order') + ' #' + ticket.orderId, { align: 'center', bold: true }));
    chunks.push(line(ticket.serveLabel || '', { align: 'center' }));
    if (ticket.guest) {
      chunks.push(line((labels.guest || 'Guest') + ': ' + ticket.guest, { align: 'center' }));
    }
    if (ticket.time) {
      chunks.push(line(ticket.time, { align: 'center' }));
    }
    chunks.push(separator(width));

    (ticket.items || []).forEach(function (it) {
      var qty = 'x' + (it.qty || 1) + ' ';
      var nameRows = wrapName(it.nama || '', width - qty.length);
      chunks.push(line(qty + nameRows[0], { bold: true }));
      for (var i = 1; i < nameRows.length; i++) {
        chunks.push(line('   ' + nameRows[i]));
      }
      if (it.catatan) {
        chunks.push(line('* ' + it.catatan));
      }
      chunks.push(new Uint8Array([0x0a]));
    });

    chunks.push(separator(width));
    chunks.push(line(labels.kitchen_ticket || 'KITCHEN TICKET', { align: 'center' }));
    chunks.push(new Uint8Array([0x0a, 0x0a, 0x0a]));
    if (beepCount > 0) {
      var endBeeps = Math.max(1, Math.min(beepCount, 3));
      chunks.push(beep(endBeeps, 2)); // second alert after print so staff notices paper
    }
    chunks.push(new Uint8Array([0x1d, 0x56, 0x00])); // full cut
    return concatBytes(chunks);
  }

  function findWritable(server) {
    return SERVICES.reduce(function (chain, uuid) {
      return chain.catch(function () {
        return server.getPrimaryService(uuid).then(function (service) {
          return service.getCharacteristics().then(function (chars) {
            var preferred = null;
            for (var i = 0; i < chars.length; i++) {
              var c = chars[i];
              var props = c.properties || {};
              var id = (c.uuid || '').toLowerCase();
              if (!(props.write || props.writeWithoutResponse)) continue;
              if (WRITE_HINTS.indexOf(id) !== -1) return c;
              if (!preferred) preferred = c;
            }
            if (preferred) return preferred;
            throw new Error('No writable characteristic');
          });
        });
      });
    }, Promise.reject());
  }

  function findWritableFallback(server) {
    return server.getPrimaryServices().then(function (services) {
      var queue = Promise.reject();
      services.forEach(function (service) {
        queue = queue.catch(function () {
          return service.getCharacteristics().then(function (chars) {
            for (var i = 0; i < chars.length; i++) {
              var c = chars[i];
              var props = c.properties || {};
              if (props.write || props.writeWithoutResponse) return c;
            }
            throw new Error('next');
          });
        });
      });
      return queue;
    });
  }

  function attachDisconnect() {
    if (!device) return;
    device.addEventListener('gattserverdisconnected', function () {
      characteristic = null;
      notify();
    });
  }

  function bindDevice(dev) {
    device = dev;
    attachDisconnect();
    return device.gatt.connect().then(function (server) {
      return findWritable(server).catch(function () {
        return findWritableFallback(server);
      });
    }).then(function (char) {
      characteristic = char;
      notify();
      return { name: device.name || 'Printer' };
    });
  }

  /**
   * Reconnect to a previously permitted Bluetooth printer (no picker).
   * Chrome: navigator.bluetooth.getDevices() after first grant.
   */
  function reconnect() {
    if (!supported()) {
      return Promise.reject(new Error('unsupported'));
    }
    if (isConnected()) {
      return Promise.resolve({ name: device.name || 'Printer' });
    }
    if (connecting) return Promise.reject(new Error('busy'));
    if (typeof navigator.bluetooth.getDevices !== 'function') {
      return Promise.reject(new Error('no_saved'));
    }

    connecting = true;
    notify();

    return navigator.bluetooth.getDevices().then(function (devices) {
      if (!devices || !devices.length) throw new Error('no_saved');
      // Prefer a device that still looks reachable / last used
      var queue = Promise.reject(new Error('no_saved'));
      devices.forEach(function (dev) {
        queue = queue.catch(function () {
          return bindDevice(dev);
        });
      });
      return queue;
    }).then(function (result) {
      connecting = false;
      notify();
      return result;
    }).catch(function (err) {
      connecting = false;
      if (!isConnected()) {
        characteristic = null;
      }
      notify();
      throw err;
    });
  }

  function connect() {
    if (!supported()) {
      return Promise.reject(new Error('unsupported'));
    }
    if (connecting) return Promise.reject(new Error('busy'));
    connecting = true;
    notify();

    return navigator.bluetooth.requestDevice({
      acceptAllDevices: true,
      optionalServices: SERVICES,
    }).then(function (dev) {
      return bindDevice(dev);
    }).then(function (result) {
      connecting = false;
      notify();
      return result;
    }).catch(function (err) {
      connecting = false;
      if (!isConnected()) {
        characteristic = null;
      }
      notify();
      throw err;
    });
  }

  /**
   * Ensure a live GATT connection before silent print.
   * @param {{interactive?: boolean}} opts
   *   interactive=true may open the Bluetooth device picker (needs user gesture).
   */
  function ensureConnected(opts) {
    opts = opts || {};
    if (isConnected()) {
      return Promise.resolve({ name: device.name || 'Printer' });
    }
    return reconnect().catch(function () {
      if (opts.interactive) return connect();
      throw new Error('not_connected');
    });
  }

  function disconnect() {
    try {
      if (device && device.gatt && device.gatt.connected) device.gatt.disconnect();
    } catch (e) { /* ignore */ }
    device = null;
    characteristic = null;
    notify();
  }

  function writeChunk(bytes) {
    if (!characteristic) return Promise.reject(new Error('not_connected'));
    var props = characteristic.properties || {};
    if (props.writeWithoutResponse && characteristic.writeValueWithoutResponse) {
      return characteristic.writeValueWithoutResponse(bytes);
    }
    return characteristic.writeValue(bytes);
  }

  function printRaw(bytes) {
    if (!isConnected()) return Promise.reject(new Error('not_connected'));
    var chunkSize = 100;
    var chain = Promise.resolve();
    for (var i = 0; i < bytes.length; i += chunkSize) {
      (function (slice) {
        chain = chain.then(function () {
          return writeChunk(slice);
        }).then(function () {
          return new Promise(function (r) { setTimeout(r, 20); });
        });
      })(bytes.slice(i, i + chunkSize));
    }
    return chain;
  }

  function printKitchenTicket(ticket, labels) {
    return printRaw(buildKitchenTicket(ticket, labels));
  }

  function padMoneyLine(label, amount, width) {
    width = width || 32;
    var left = String(label || '');
    var right = String(amount || '');
    var space = width - left.length - right.length;
    if (space < 1) return left + ' ' + right;
    return left + new Array(space + 1).join(' ') + right;
  }

  function formatRm(n) {
    return 'RM ' + (Number(n) || 0).toFixed(2);
  }

  /**
   * Paid receipt (kasir) — silent ESC/POS, no browser print dialog.
   */
  function buildReceiptTicket(receipt, labels) {
    labels = labels || {};
    var width = 32;
    var lang = (receipt && receipt.lang) === 'en' ? 'en' : 'my';
    var chunks = [];
    var beepCount = resolveBeepCount(labels, 0);
    chunks.push(new Uint8Array([0x1b, 0x40]));
    if (beepCount > 0) {
      chunks.push(beep(beepCount, 2));
    }
    chunks.push(line((receipt && receipt.shop_name) || 'TableTap', { align: 'center', bold: true }));
    chunks.push(line((labels.receipt || (lang === 'en' ? 'RECEIPT' : 'RESIT')) + ' #' + ((receipt && receipt.order_id) || ''), { align: 'center', bold: true }));
    chunks.push(separator(width));
    chunks.push(line((labels.table || 'Meja') + ' ' + ((receipt && receipt.nomor_meja) || '-')));
    var paidAt = (receipt && (receipt.waktu_lunas || receipt.waktu_order)) || '';
    if (paidAt) chunks.push(line((labels.paid || 'Paid') + ': ' + paidAt));
    if (receipt && receipt.nama_pelanggan) {
      chunks.push(line((labels.guest || 'Guest') + ': ' + receipt.nama_pelanggan));
    }
    var serve = (receipt && receipt.jenis_hidang) === 'takeaway'
      ? (labels.takeaway || 'Takeaway')
      : (labels.dine_in || 'Dine in');
    chunks.push(line(serve));
    if (receipt && receipt.split_from_order_id) {
      chunks.push(line((labels.split_from || 'Split from') + ' #' + receipt.split_from_order_id));
    }
    chunks.push(separator(width));

    (receipt && receipt.items ? receipt.items : []).forEach(function (it) {
      var qty = (it.qty || 1) + 'x ';
      var nameRows = wrapName(it.nama || '', width - qty.length - 8);
      var amt = formatRm(it.line_total != null ? it.line_total : ((it.unit || 0) * (it.qty || 1)));
      chunks.push(line(padMoneyLine(qty + nameRows[0], amt, width)));
      for (var i = 1; i < nameRows.length; i++) {
        chunks.push(line('   ' + nameRows[i]));
      }
      if (it.catatan) chunks.push(line('* ' + it.catatan));
    });

    chunks.push(separator(width));
    chunks.push(line(padMoneyLine(labels.subtotal || 'Subtotal', formatRm(receipt && receipt.subtotal), width)));
    if (receipt && Number(receipt.sst_jumlah) > 0) {
      chunks.push(line(padMoneyLine(
        'SST (' + Number(receipt.sst_rate || 0).toFixed(2) + '%)',
        formatRm(receipt.sst_jumlah),
        width
      )));
    }
    chunks.push(line(padMoneyLine(labels.total || 'Total', formatRm(receipt && receipt.total_harga), width), { bold: true }));
    chunks.push(separator(width));
    chunks.push(line(labels.thank_you || (lang === 'en' ? 'Thank you!' : 'Terima kasih!'), { align: 'center' }));
    chunks.push(line('TableTap', { align: 'center' }));
    chunks.push(new Uint8Array([0x0a, 0x0a, 0x0a]));
    chunks.push(new Uint8Array([0x1d, 0x56, 0x00]));
    return concatBytes(chunks);
  }

  function printReceipt(receipt, labels) {
    return printRaw(buildReceiptTicket(receipt, labels));
  }

  function printTest(labels) {
    labels = labels || {};
    if (labels.mode === 'receipt') {
      return printReceipt({
        shop_name: 'TableTap',
        order_id: 'TEST',
        nomor_meja: '0',
        waktu_lunas: new Date().toLocaleString(),
        jenis_hidang: 'dine_in',
        subtotal: 1,
        sst_rate: 0,
        sst_jumlah: 0,
        total_harga: 1,
        items: [{ qty: 1, nama: labels.test_item || 'Test print OK', line_total: 1, catatan: '' }],
        lang: 'my',
      }, labels);
    }
    return printKitchenTicket({
      shopName: 'TableTap',
      stationName: labels.test_station || 'Test',
      table: '0',
      orderId: 'TEST',
      serveLabel: labels.dine_in || 'Dine in',
      time: new Date().toLocaleString(),
      items: [{ qty: 1, nama: labels.test_item || 'Test print OK', catatan: '' }],
    }, labels);
  }

  function printBeep(times, duration) {
    return printRaw(concatBytes([
      new Uint8Array([0x1b, 0x40]),
      beep(times, duration),
    ]));
  }

  global.TableTapPrint = {
    supported: supported,
    isConnected: isConnected,
    connecting: function () { return connecting; },
    connect: connect,
    reconnect: reconnect,
    ensureConnected: ensureConnected,
    disconnect: disconnect,
    onChange: onChange,
    printKitchenTicket: printKitchenTicket,
    printReceipt: printReceipt,
    printTest: printTest,
    printBeep: printBeep,
    buildKitchenTicket: buildKitchenTicket,
    buildReceiptTicket: buildReceiptTicket,
  };
})(window);
