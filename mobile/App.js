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
    registerForPushNotificationsAsync().then(token => setExpoPushToken(token));

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
            const tokenData = {
              type: 'expo-push-token',
              token: '${expoPushToken}'
            };
            window.postMessage(JSON.stringify(tokenData), '*');
            // Also try direct injection into localStorage as backup
            localStorage.setItem('expo_push_token', '${expoPushToken}');
          })();
          true;
        `;
        webViewRef.current.injectJavaScript(script);
      };

      injectToken();
      
      // Retry injection a few times after load to ensure the web app is ready
      const interval = setInterval(injectToken, 5000);
      const timeout = setTimeout(() => clearInterval(interval), 30000);

      return () => {
        clearInterval(interval);
        clearTimeout(timeout);
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
    const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;
    
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
