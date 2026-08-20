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
    chunks.push(new Uint8Array([0x1b, 0x40])); // init
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
      device = dev;
      attachDisconnect();
      return device.gatt.connect();
    }).then(function (server) {
      return findWritable(server).catch(function () {
        return findWritableFallback(server);
      });
    }).then(function (char) {
      characteristic = char;
      connecting = false;
      notify();
      return { name: device.name || 'Printer' };
    }).catch(function (err) {
      connecting = false;
      characteristic = null;
      notify();
      throw err;
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

  function printTest(labels) {
    labels = labels || {};
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

  global.TableTapPrint = {
    supported: supported,
    isConnected: isConnected,
    connecting: function () { return connecting; },
    connect: connect,
    disconnect: disconnect,
    onChange: onChange,
    printKitchenTicket: printKitchenTicket,
    printTest: printTest,
    buildKitchenTicket: buildKitchenTicket,
  };
})(window);
