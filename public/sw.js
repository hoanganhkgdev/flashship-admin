importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');

firebase.initializeApp({
  apiKey: "AIzaSyDga3drPiu7YcxWoK68a1hZV0sfqcxF6sg",
  authDomain: "local-flashship.firebaseapp.com",
  databaseURL: "https://local-flashship-default-rtdb.firebaseio.com",
  projectId: "local-flashship",
  storageBucket: "local-flashship.firebasestorage.app",
  messagingSenderId: "652824641372",
  appId: "1:652824641372:web:ae11696409d9e1fa10cb99",
  measurementId: "G-87F0P2CZQP"
});

const messaging = firebase.messaging();

// 🔔 Lắng nghe thông báo khi trình duyệt đang đóng (Background)
messaging.onBackgroundMessage((payload) => {
  console.log('[sw.js] Received background message ', payload);
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/logo.png',
    data: payload.data,
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
