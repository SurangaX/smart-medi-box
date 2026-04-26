import React, { useState, useEffect, useRef } from 'react';
import { StyleSheet, View, SafeAreaView, Platform, Alert, BackHandler } from 'react-native';
import { WebView } from 'react-native-webview';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { StatusBar } from 'expo-status-bar';

// Notification configuration
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

export default function App() {
  const [expoPushToken, setExpoPushToken] = useState('');
  const [notification, setNotification] = useState(false);
  const notificationListener = useRef();
  const responseListener = useRef();
  const webViewRef = useRef(null);

  const DASHBOARD_URL = 'https://smart-medi-box.vercel.app/';

  useEffect(() => {
    registerForPushNotificationsAsync().then(token => {
      if (token) {
        setExpoPushToken(token);
        Alert.alert('Mobile Sync', 'Notification system initialized!');
      }
    });

    notificationListener.current = Notifications.addNotificationReceivedListener(notification => {
      setNotification(notification);
    });

    responseListener.current = Notifications.addNotificationResponseReceivedListener(response => {
      const data = response.notification.request.content.data;
      console.log('Notification clicked:', data);

      if (data && webViewRef.current) {
        let page = null;
        let tab = null;

        switch (data.type) {
          case 'alarm':
            tab = 'schedules';
            break;
          case 'chat':
            tab = 'chat';
            break;
          case 'article':
            tab = 'articles';
            break;
          case 'report':
            tab = 'reports';
            break;
        }

        if (tab) {
          const script = `
            if (window.appNavigate) {
              window.appNavigate(null, '${tab}');
            }
            true;
          `;
          webViewRef.current.injectJavaScript(script);
        }
      }
    });

    // Handle back button on Android to navigate back in WebView
    const onBackPress = () => {
      if (webViewRef.current) {
        webViewRef.current.goBack();
        return true;
      }
      return false;
    };

    BackHandler.addEventListener('hardwareBackPress', onBackPress);

    return () => {
      Notifications.removeNotificationSubscription(notificationListener.current);
      Notifications.removeNotificationSubscription(responseListener.current);
      BackHandler.removeEventListener('hardwareBackPress', onBackPress);
    };
  }, []);

  // When token is received, inject it into the WebView
  useEffect(() => {
    if (expoPushToken && webViewRef.current) {
      const injectToken = () => {
        const script = `
          (function() {
            try {
              const token = '${expoPushToken}';
              localStorage.setItem('expo_push_token', token);
              window.postMessage(JSON.stringify({type: 'expo-push-token', token: token}), '*');
              window.dispatchEvent(new CustomEvent('expo-token-ready', { detail: token }));

              // Visible debug banner
              const banner = document.createElement('div');
              banner.innerHTML = '📱 Notification Link Active';
              banner.style.cssText = 'position:fixed;top:0;left:0;width:100%;background:#4CAF50;color:white;text-align:center;padding:5px;z-index:999999;font-weight:bold;font-size:12px;';
              document.body.appendChild(banner);
              setTimeout(() => banner.remove(), 4000);

              console.log('Push token injected successfully');
            } catch (e) {
              console.error('Injection error:', e);
            }
          })();
          true;
        `;
        webViewRef.current.injectJavaScript(script);
      };

      // Inject multiple times to handle slow loads
      injectToken();
      const i1 = setTimeout(injectToken, 2000);
      const i2 = setTimeout(injectToken, 5000);
      const i3 = setTimeout(injectToken, 10000);

      return () => {
        clearTimeout(i1);
        clearTimeout(i2);
        clearTimeout(i3);
      };
    }
  }, [expoPushToken]);

  const onWebViewMessage = (event) => {
    console.log('Message from WebView:', event.nativeEvent.data);
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="auto" />
      <View style={styles.webviewContainer}>
        <WebView
          ref={webViewRef}
          source={{ uri: DASHBOARD_URL }}
          style={styles.webview}
          onMessage={onWebViewMessage}
          javaScriptEnabled={true}
          domStorageEnabled={true}
          startInLoadingState={true}
          scalesPageToFit={true}
          onLoadEnd={() => {
            // Inject token again on load end to ensure it's received
            if (expoPushToken) {
              const script = `
                window.postMessage(JSON.stringify({
                  type: 'expo-push-token',
                  token: '${expoPushToken}'
                }), '*');
                true;
              `;
              webViewRef.current.injectJavaScript(script);
            }
          }}
        />
      </View>
    </SafeAreaView>
  );
}

async function registerForPushNotificationsAsync() {
  let token;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'default',
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#FF231F7C',
    });
  }

  if (Device.isDevice) {
    const { status: existingStatus } = await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;
    if (existingStatus !== 'granted') {
      const { status } = await Notifications.requestPermissionsAsync();
      finalStatus = status;
    }
    if (finalStatus !== 'granted') {
      Alert.alert('Failed to get push token for push notification!');
      return;
    }
    
    // Get project ID from constants if using EAS
    const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? 
                      Constants?.easConfig?.projectId ?? 
                      '41eed6f7-ee0b-4659-8299-8c3f8e5ea585';
    
    console.log('Using Project ID:', projectId);
    
    token = (await Notifications.getExpoPushTokenAsync({
      projectId: projectId
    })).data;
    console.log('Expo Push Token:', token);
  } else {
    // Alert.alert('Must use physical device for Push Notifications');
  }

  return token;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    paddingTop: Platform.OS === 'android' ? 30 : 0,
  },
  webviewContainer: {
    flex: 1,
  },
  webview: {
    flex: 1,
  },
});
