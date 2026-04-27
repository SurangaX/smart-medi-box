package com.example.smartmedibox;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
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
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;

import com.google.android.material.floatingactionbutton.FloatingActionButton;

import org.json.JSONObject;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.WebSocket;
import okhttp3.WebSocketListener;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private static final String TAG = "MediBoxNtfy";
    private static final String CHANNEL_ID = "medibox_notifications";
    private WebSocket webSocket;
    private OkHttpClient client;
    private String currentTopic = null;

    private final ActivityResultLauncher<String> requestPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                Log.d(TAG, "Permission result: " + isGranted);
            });

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        createNotificationChannel();
        
        webView = findViewById(R.id.webview);
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);

        // Add JavascriptInterface to receive the user_id from the web app
        webView.addJavascriptInterface(new WebAppInterface(this), "AndroidInterface");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                // Inject script to detect login and send user_id back to Android
                injectLoginDetector();
            }
        });

        webView.loadUrl("https://smart-medi-box.vercel.app/");

        client = new OkHttpClient();

        FloatingActionButton fab = findViewById(R.id.fab_test);
        fab.setOnClickListener(view -> {
            if (currentTopic != null) {
                Toast.makeText(this, "Listening to: " + currentTopic, Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Not logged in. Waiting for user ID...", Toast.LENGTH_SHORT).show();
            }
            showLocalNotification("Connection Check", "The notification system is active.");
        });

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS);
            }
        }
    }

    private void injectLoginDetector() {
        // This script checks localStorage for user info and calls the Android interface
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
                "  setInterval(checkLogin, 5000);" + // Check every 5 seconds
                "})()";
        webView.evaluateJavascript(script, null);
    }

    public void startNtfyListener(String userId) {
        String newTopic = "smart_medibox_user_" + userId;
        
        if (newTopic.equals(currentTopic)) return; // Already listening to this topic
        
        if (webSocket != null) {
            webSocket.close(1000, "Switching user");
        }

        currentTopic = newTopic;
        Log.d(TAG, "Starting ntfy listener for topic: " + currentTopic);

        Request request = new Request.Builder()
                .url("wss://ntfy.sh/" + currentTopic + "/ws")
                .build();

        webSocket = client.newWebSocket(request, new WebSocketListener() {
            @Override
            public void onMessage(WebSocket webSocket, String text) {
                try {
                    JSONObject json = new JSONObject(text);
                    if (json.has("message")) {
                        String title = json.optString("title", "Smart Medi Box");
                        String message = json.getString("message");
                        runOnUiThread(() -> showLocalNotification(title, message));
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Error parsing ntfy message", e);
                }
            }

            @Override
            public void onFailure(WebSocket webSocket, Throwable t, Response response) {
                Log.e(TAG, "Ntfy Connection Failed, retrying...", t);
                // Retry after 5 seconds if still logged in
                if (currentTopic != null) {
                    runOnUiThread(() -> {
                        webView.postDelayed(() -> {
                            if (currentTopic != null) startNtfyListener(userId);
                        }, 5000);
                    });
                }
            }
        });
    }

    private void showLocalNotification(String title, String message) {
        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setAutoCancel(true);

        NotificationManager notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        notificationManager.notify((int) System.currentTimeMillis(), builder.build());
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "MediBox Alerts", NotificationManager.IMPORTANCE_HIGH);
            NotificationManager manager = getSystemService(NotificationManager.class);
            manager.createNotificationChannel(channel);
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        currentTopic = null;
        if (webSocket != null) webSocket.close(1000, "App closed");
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    // Javascript Interface class
    public class WebAppInterface {
        Context mContext;
        WebAppInterface(Context c) { mContext = c; }

        @JavascriptInterface
        public void onUserLogin(String userId) {
            Log.d(TAG, "User logged in with ID: " + userId);
            runOnUiThread(() -> startNtfyListener(userId));
        }
    }
}
