# Smart Medi Box - Mobile App (Android APK) Setup Guide

This guide explains how to build the Android APK for the Smart Medi Box dashboard and how push notifications work.

## Prerequisites
1. [Node.js](https://nodejs.org/) installed.
2. An Expo account (create one at [expo.dev](https://expo.dev/)).
3. [EAS CLI](https://docs.expo.dev/build/setup/) installed: `npm install -g eas-cli`

## Step 1: Project Configuration
1. Login to your Expo account: `eas login`
2. Initialize the project: `eas build:configure`
3. This will generate an `eas.json` file. Update the `preview` profile to generate an APK instead of an AAB (for testing):

```json
{
  "build": {
    "preview": {
      "android": {
        "buildType": "apk"
      }
    },
    "production": {}
  }
}
```

## Step 2: Build the APK
Run the following command in the `mobile/` directory:
```bash
eas build -p android --profile preview
```
This will build the APK in the cloud. Once finished, Expo will provide a link/QR code to download the `.apk` file to your Android device.

## Step 3: Local Development (Optional)
To run the app on your phone during development:
1. Install the "Expo Go" app from the Play Store.
2. Run `npx expo start` in the `mobile/` directory.
3. Scan the QR code with Expo Go.
*Note: Push notifications require a physical device and often a custom build (APK) to work reliably outside of Expo Go.*

## How Push Notifications Work
1. **Token Generation**: When the mobile app starts, it requests notification permissions and gets an **Expo Push Token**.
2. **Token Transmission**: The app wraps the web dashboard in a WebView. It "injects" the token into the web app using `window.postMessage`.
3. **Backend Registration**: The web dashboard receives the token and sends it to your PHP backend (`api/user/update-push-token`) when you log in.
4. **Pushing**: When the backend triggers a notification (e.g., medicine reminder), it checks if you have a token and sends a request to Expo's Push API, which delivers the native notification to your phone.

## Troubleshooting
- **No Notifications**: Ensure you granted notification permissions on the device.
- **Token not updating**: Check the browser console in the dashboard for "Received Expo Push Token".
- **Backend Error**: Check `robot_api/db_output_logs.txt` for any CURL or Database errors related to Expo Push.
