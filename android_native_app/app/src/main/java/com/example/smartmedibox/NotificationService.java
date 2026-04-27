package com.example.smartmedibox;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.IBinder;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import org.json.JSONObject;

import java.util.concurrent.TimeUnit;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.WebSocket;
import okhttp3.WebSocketListener;

public class NotificationService extends Service {
    private static final String TAG = "MediBoxService";
    private static final String CHANNEL_ID = "medibox_notifications";
    private static final int SERVICE_ID = 101;
    
    private OkHttpClient client;
    private WebSocket webSocket;
    private String currentTopic = null;

    @Override
    public void onCreate() {
        super.onCreate();
        client = new OkHttpClient.Builder()
                .readTimeout(0, TimeUnit.MILLISECONDS) // Keep connection alive
                .build();
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        String userId = intent.getStringExtra("user_id");
        if (userId != null) {
            startForeground(SERVICE_ID, createForegroundNotification());
            startNtfyListener(userId);
        }
        return START_STICKY; // Tell system to recreate service if killed
    }

    private void startNtfyListener(String userId) {
        String newTopic = "smart_medibox_user_" + userId;
        if (newTopic.equals(currentTopic)) return;

        if (webSocket != null) {
            webSocket.close(1000, "Switching topic");
        }

        currentTopic = newTopic;
        Log.d(TAG, "Background listening to topic: " + currentTopic);

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
                        showLocalNotification(title, message);
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Parse error", e);
                }
            }

            @Override
            public void onFailure(WebSocket webSocket, Throwable t, Response response) {
                Log.e(TAG, "WebSocket failure, retrying...", t);
                // Auto-retry after 10 seconds
                new android.os.Handler().postDelayed(() -> {
                    if (currentTopic != null) startNtfyListener(userId);
                }, 10000);
            }
        });
    }

    private void showLocalNotification(String title, String message) {
        Intent intent = new Intent(this, MainActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, intent, 
                PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);

        Notification n = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(pendingIntent)
                .setAutoCancel(true)
                .build();

        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        nm.notify((int) System.currentTimeMillis(), n);
    }

    private Notification createForegroundNotification() {
        return new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle("Smart Medi Box Active")
                .setContentText("Monitoring for medication reminders...")
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .build();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "MediBox Alerts", NotificationManager.IMPORTANCE_HIGH);
            NotificationManager manager = getSystemService(NotificationManager.class);
            manager.createNotificationChannel(channel);
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) { return null; }

    @Override
    public void onDestroy() {
        super.onDestroy();
        currentTopic = null;
        if (webSocket != null) webSocket.close(1000, "Service destroyed");
    }
}
