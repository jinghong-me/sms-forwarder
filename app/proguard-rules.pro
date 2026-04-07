# ProGuard 规则文件

# 保留数据模型类
-keep class com.lanbing.smsforwarder.** { *; }

# 保留 OkHttp
-dontwarn okhttp3.**
-keep class okhttp3.** { *; }
-dontwarn okio.**
-keep class okio.** { *; }

# 保留 Compose
-keep class androidx.compose.** { *; }
-dontwarn androidx.compose.**

# 保留 BroadcastReceiver
-keep public class * extends android.content.BroadcastReceiver
