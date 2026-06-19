# Add project specific ProGuard rules here.
# By default, the flags in this file are appended to flags specified
# in sdk_root/tools/proguard/proguard-android.txt

# ============================================================================
# Modelos de API e entidades locais — desserializados por reflexão (Gson/Room).
# Sem isto, o R8 obfusca/remove campos e a leitura das respostas da API quebra.
# ============================================================================
-keep class com.sistemafrotas.motorista.data.api.** { *; }
-keep class com.sistemafrotas.motorista.data.local.** { *; }

# ---------------------------------------------------------------------------
# Gson
# ---------------------------------------------------------------------------
-keepattributes Signature
-keepattributes *Annotation*
-keepattributes EnclosingMethod
-keepattributes InnerClasses
-keep class com.google.gson.** { *; }
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keepclassmembers,allowobfuscation class * {
    @com.google.gson.annotations.SerializedName <fields>;
}
# Mantém os campos das classes de modelo intactos para o Gson
-keepclassmembers class com.sistemafrotas.motorista.data.api.** {
    <fields>;
}

# ---------------------------------------------------------------------------
# Retrofit / OkHttp
# ---------------------------------------------------------------------------
-keepattributes RuntimeVisibleAnnotations,RuntimeVisibleParameterAnnotations
-keepclassmembers,allowshrinking,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}
-keep,allowobfuscation,allowshrinking interface retrofit2.Call
-keep,allowobfuscation,allowshrinking class retrofit2.Response
-keep,allowobfuscation,allowshrinking class kotlin.coroutines.Continuation
-dontwarn retrofit2.**
-dontwarn okhttp3.**
-dontwarn okio.**

# ---------------------------------------------------------------------------
# Enums (Gson usa values()/valueOf())
# ---------------------------------------------------------------------------
-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

# ---------------------------------------------------------------------------
# ML Kit (barcode scanning) — usa modelos nativos por reflexão
# ---------------------------------------------------------------------------
-keep class com.google.mlkit.** { *; }
-dontwarn com.google.mlkit.**

# ---------------------------------------------------------------------------
# Coil / Kotlin coroutines
# ---------------------------------------------------------------------------
-dontwarn coil.**
-dontwarn kotlinx.coroutines.**
