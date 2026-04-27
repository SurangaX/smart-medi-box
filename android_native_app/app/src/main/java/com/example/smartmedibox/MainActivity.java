package com.example.smartmedibox;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.firebase.installations.FirebaseInstallations;
import com.google.firebase.messaging.FirebaseMessaging;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private static final String TAG = "MainActivity";

    // Launcher for the notification permission request
    private final ActivityResultLauncher<String> requestPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                if (isGranted) {
                    // Permission is granted. Get the token.
                    Log.d(TAG, "Notification permission granted. Fetching token...");
                    getFCMToken();
                } else {
                    // Explain to the user that they will not receive notifications
                    Toast.makeText(this, "Notifications will not be sent.", Toast.LENGTH_SHORT).show();
                }
            });

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        
        // Basic WebView settings
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);

        // Set a WebViewClient to handle page loads
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                // When the page is fully loaded, you could re-inject the token if needed,
                // but we will primarily rely on the initial injection.
                super.onPageFinished(view, url);
            }
        });

        // Load the website
        webView.loadUrl("https://smart-medi-box.vercel.app/");

        // Setup the manual test button
        FloatingActionButton fab = findViewById(R.id.fab_test);
        fab.setOnClickListener(view -> {
            Log.d(TAG, "Manual token fetch requested by user.");
            Toast.makeText(MainActivity.this, "Fetching token, check Logcat.", Toast.LENGTH_SHORT).show();
            getFCMToken();
        });

        // Ask for notification permission, which then triggers the token fetch
        if (savedInstanceState == null) {
            askNotificationPermission();
        }
    }

    private void askNotificationPermission() {
        // This is only necessary for API level >= 33 (Tiramisu)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) ==
                    PackageManager.PERMISSION_GRANTED) {
                // Permission is already granted, get the token
                getFCMToken();
            } else {
                // Directly ask for the permission
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS);
            }
        } else {
            // For older versions, permission is granted by default, so get the token
            getFCMToken();
        }
    }
    
    private void getFCMToken() {
        FirebaseMessaging.getInstance().getToken()
                .addOnCompleteListener(task -> {
                    if (!task.isSuccessful()) {
                        Exception exception = task.getException();
                        Log.w(TAG, "Fetching FCM registration token failed", exception);

                        // Handle TOO_MANY_REGISTRATIONS error specifically
                        if (exception != null && exception.getMessage() != null &&
                                exception.getMessage().contains("TOO_MANY_REGISTRATIONS")) {
                            Log.e(TAG, "Limit reached: TOO_MANY_REGISTRATIONS. Attempting to reset Firebase Installation ID...");
                            resetFirebaseInstallation();
                        }
                        return;
                    }

                    // Get new FCM registration token
                    String token = task.getResult();
                    Log.d("FCM_TOKEN", "Token successfully fetched: " + token);

                    // Inject the token into the WebView
                    injectTokenIntoWebView(token);
                });
    }

    private void resetFirebaseInstallation() {
        FirebaseInstallations.getInstance().delete()
                .addOnCompleteListener(task -> {
                    if (task.isSuccessful()) {
                        Log.d(TAG, "Firebase Installation deleted. This clears the local FCM state.");
                        Toast.makeText(this, "FCM state reset. Please tap the test button to retry.", Toast.LENGTH_LONG).show();
                    } else {
                        Log.e(TAG, "Failed to delete Firebase Installation", task.getException());
                    }
                });
    }

    private void injectTokenIntoWebView(String token) {
        if (token != null && webView != null) {
            String script = "javascript:localStorage.setItem('fcm_token', '" + token + "');";
            webView.evaluateJavascript(script, value -> Log.d(TAG, "Token injected into WebView."));
        }
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
