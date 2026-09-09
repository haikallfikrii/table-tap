# TableTap Print Bridge (Android)

Small Flutter app that lets a shop keep using the **web kasir app on HTTPS** while
printing to **Wi-Fi / LAN ESC-POS thermal printers**.

Chrome blocks `http://127.0.0.1` calls from an HTTPS page (mixed content), so the
web app never talks to the printer directly. Instead:

1. TableTap (PHP) queues a print job in MySQL when a kitchen ticket or paid receipt happens.
2. This app polls `https://<server>/public/api/print_bridge/claim.php` every few seconds.
3. It renders ESC/POS bytes and opens a raw TCP socket to the printer (usually port 9100).
4. It acks the job back to the server (`ack.php`), so a failed print is retried.

Bluetooth printing in the web app is untouched — a shop can run both.

## Requirements

- Flutter SDK 3.16 or newer (`flutter --version`)
- Android SDK with platform 34 + build-tools
- Java 17
- One Android phone/tablet on the **same Wi-Fi** as the printers
- ESC/POS network printers with a **static IP** (set a DHCP reservation on the router)

## Build

```bash
cd apps/print-bridge

# One-time: fill in any platform files this repo does not track (gradle wrapper jar, icons)
flutter create --platforms=android --org my.tabletap --project-name print_bridge .

flutter pub get

# Debug APK — fastest way to test on site
flutter build apk --debug
# -> build/app/outputs/flutter-apk/app-debug.apk

# Release APK (unsigned key falls back to the debug key unless key.properties exists)
flutter build apk --release
# -> build/app/outputs/flutter-apk/app-release.apk
```

`flutter create` will not overwrite `lib/`, `pubspec.yaml`, or the Android files
already committed here; it only adds what is missing.

### Signed release build

Create a keystore once and keep it **outside git**:

```bash
keytool -genkey -v -keystore ~/tabletap-print-bridge.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

Then create `android/key.properties` (already git-ignored):

```properties
storeFile=/absolute/path/to/tabletap-print-bridge.jks
storePassword=<password>
keyAlias=upload
keyPassword=<password>
```

and run `flutter build apk --release` again.

## Install on the tablet (sideload)

1. Copy the APK to the tablet (USB, Google Drive, or WhatsApp to yourself).
2. Settings → Security → allow "Install unknown apps" for the file manager / browser.
3. Tap the APK, install, open.
4. `adb install -r build/app/outputs/flutter-apk/app-release.apk` also works over USB.

## Setup in the app

1. **Settings** → paste the **Print Bridge token** from
   TableTap → Owner → Settings → Print Bridge.
2. Server URL: `https://tabletap.my` (default).
3. Tap **Check token** — it should show the shop name.
4. Enter the printer IP + port `9100` for kasir / dapur / minuman and switch on the
   stations this shop uses. Unmapped stations (extra Pro stations) fall back to the
   kitchen printer, then the cashier printer.
5. Tap **Test** next to a printer to confirm cabling.
6. Back on the home screen tap **Mula / Start** and leave the app open. The screen
   stays awake via wakelock while it is running.

## Notes / limits (MVP)

- Polling runs while the app is open and in the foreground. There is no foreground
  service yet, so do not swipe the app away.
- Jobs left in `printing` for more than 2 minutes go back to `pending` server-side,
  so a crash or a dropped Wi-Fi connection does not lose a ticket.
- After 5 failed attempts a job is marked `failed` and stops retrying.
- Text is sent as single-byte (Latin-1); characters outside it print as `?`.
