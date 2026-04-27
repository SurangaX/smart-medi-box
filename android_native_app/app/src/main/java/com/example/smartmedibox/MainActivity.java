package com.example.smartmedibox;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.webkit.JavascriptInterface;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import com.google.android.material.floatingactionbutton.FloatingActionButton;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private static final String TAG = "MediBoxMain";
    private String currentUserId = null;

    private final ActivityResultLauncher<String> requestPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                Log.d(TAG, "Notification permission result: " + isGranted);
            });

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);

        webView.addJavascriptInterface(new WebAppInterface(this), "AndroidInterface");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                injectLoginDetector();
            }
        });

        webView.loadUrl("https://smart-medi-box.vercel.app/");

        FloatingActionButton fab = findViewById(R.id.fab_test);
        fab.setOnClickListener(view -> {
            if (currentUserId != null) {
                Toast.makeText(this, "Background service active for ID: " + currentUserId, Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Waiting for login...", Toast.LENGTH_SHORT).show();
            }
        });

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS);
            }
        }
    }

    private void injectLoginDetector() {
        String script = "javascript:(function() {" +
                "  const checkLogin = () => {" +
                "    const userId = localStorage.getItem('user_id');" +
                "    if (userId) {" +
                "      try {" +
                "        AndroidInterface.onUserLogin(userId.toString());" +
                "      } catch (e) { console.error(e); }" +
                "    }" +
                "  };" +
                "  checkLogin();" +
                "  setInterval(checkLogin, 5000);" +
                "})()";
        webView.evaluateJavascript(script, null);
    }

    public void startBackgroundService(String userId) {
        if (userId.equals(currentUserId)) return;
        currentUserId = userId;
        
        Log.d(TAG, "Starting background notification service for user: " + userId);
        Intent intent = new Intent(this, NotificationService.class);
        intent.putExtra("user_id", userId);
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(intent);
        } else {
            startService(intent);
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

    public class WebAppInterface {
        Context mContext;
        WebAppInterface(Context c) { mContext = c; }

        @JavascriptInterface
        public void onUserLogin(String userId) {
            runOnUiThread(() -> startBackgroundService(userId));
        }
    }
}
