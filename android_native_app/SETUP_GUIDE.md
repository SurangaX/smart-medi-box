# A-to-Z Guide: Android WebView App with Firebase Notifications

This guide will walk you through creating a native Android app that displays your website in a WebView and receives push notifications from Firebase.

### Step 1: Create a New Project in Android Studio

1.  Open Android Studio.
2.  Click **"New Project"**.
3.  Select **"Empty Views Activity"** and click **"Next"**.
4.  Configure your project:
    *   **Name:** `Smart Medi Box`
    *   **Package name:** `com.example.smartmedibox` (You can change this, but you must update it in the code provided).
    *   **Save location:** Point this to the `android_native_app` folder that has been created.
    *   **Language:** `Java`
    *   **Minimum SDK:** `API 24: Android 7.0 (Nougat)`
5.  Click **"Finish"**. Android Studio will generate many files. You will replace the content of some of them in the next steps.

### Step 2: Connect to Firebase

1.  Go to the [Firebase Console](https://console.firebase.google.com/).
2.  Click **"Add project"** and follow the steps to create a new project.
3.  Once your project is ready, click the **Android icon** (</>) to add an Android app.
4.  **Register app:**
    *   **Android package name:** `com.example.smartmedibox` (must match what you set in Android Studio).
    *   **App nickname (Optional):** `Smart Medi Box Android`
    *   **Debug signing certificate SHA-1 (Optional but recommended):** Follow the instructions in the Firebase console to get this from your computer.
5.  Click **"Register app"**.
6.  **Download config file:**
    *   Click **"Download google-services.json"**.
    *   In Android Studio, switch to the **Project** view on the left.
    *   Move the downloaded `google-services.json` file into the `android_native_app/app/` directory.

### Step 3: Add Dependencies

Replace the entire contents of your `app/build.gradle.kts` file with the code provided in `app/build.gradle.kts`. This adds the necessary libraries for Firebase, WebView, and the UI.

### Step 4: Create the Layout

Replace the entire contents of `app/src/main/res/layout/activity_main.xml` with the code provided in `app/src/main/res/layout/activity_main.xml`. This creates the WebView and the floating test button.

### Step 5: Update the Main Activity

Replace the entire contents of `app/src/main/java/com/example/smartmedibox/MainActivity.java` with the code provided in that file. This contains the logic to load your website and handle the test button.

### Step 6: Create the Notification Service

1.  In Android Studio's project view, navigate to `app/src/main/java/com/example/smartmedibox`.
2.  Right-click the `smartmedibox` folder and select **New > Java Class**.
3.  Name the class `MyFirebaseMessagingService` and click **"OK"**.
4.  Replace the contents of this new file with the code provided in `app/src/main/java/com/example/smartmedibox/MyFirebaseMessagingService.java`.

### Step 7: Update the Android Manifest

Replace the entire contents of `app/src/main/AndroidManifest.xml` with the provided code. This registers the required internet permission and the new notification service.

### Step 8: Build and Run

1.  Click the **"Sync Now"** button that appears in Android Studio after you update the Gradle file.
2.  Connect your Android device or start an emulator.
3.  Click the **"Run"** button (green play icon) in the toolbar.

### Step 9: Manual Testing

1.  With the app running, open **Logcat** in Android Studio (`View > Tool Windows > Logcat`).
2.  In the Logcat search bar, enter `FCM_TOKEN`.
3.  Tap the floating mail icon in the app. The FCM token will be printed in the logs.
4.  Copy this long token string.
5.  In the Firebase Console, go to your project, then navigate to **Engage > Messaging > "Create your first campaign"**.
6.  Select **"Firebase Notification messages"**.
7.  Compose a notification (e.g., Title: "Test", Body: "Hello").
8.  On the right side, click **"Send test message"**.
9.  Paste the copied FCM token into the text field and click **"Test"**. You should receive the notification on your device.
