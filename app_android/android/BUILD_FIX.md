# Corrigir erro "Unsupported class file major version 68" / cache 8.2

O erro acontece porque o **Gradle está rodando com Java 24** (class version 68), e o cache ou a versão do Gradle que o Android Studio está usando não suporta isso.

## Solução mais rápida: usar Java 17 no Gradle

1. No Android Studio: **File** → **Settings** (**Ctrl+Alt+S**).
2. **Build, Execution, Deployment** → **Build Tools** → **Gradle**.
3. Em **Gradle JDK**, selecione **Java 17**:
   - **Embedded JDK** (se for 17), ou  
   - **jbr-17** / **JDK 17** (se tiver instalado).
4. **Apply** → **OK**.
5. **File** → **Invalidate Caches / Restart** → **Invalidate and Restart**.
6. Depois de reabrir: **File** → **Sync Project with Gradle Files**.

Assim o Gradle passa a rodar com Java 17 e o erro “Unsupported class file major version 68” tende a sumir.

---

## Se ainda aparecer cache do Gradle 8.2

Confirme que o projeto usa o **Gradle Wrapper** (e não uma instalação local antiga):

1. **Settings** → **Build, Execution, Deployment** → **Gradle**.
2. Em **Use Gradle from**, marque **Gradle Wrapper** (e não “Local installation”).
3. **Gradle JDK** → **Java 17** (como acima).

Depois **pare os daemons** e **apague o cache antigo**:

- Feche o Android Studio.
- Apague a pasta:  
  `C:\Users\victo\.gradle\caches\8.2`  
  (ou, se quiser limpar tudo: `C:\Users\victo\.gradle\caches`).
- Abra de novo o projeto e faça **Sync Project with Gradle Files**.

Na primeira vez o Gradle 8.14.3 (do `gradle-wrapper.properties`) será baixado e o sync deve concluir.

---

## Conferir o wrapper

O arquivo **gradle/wrapper/gradle-wrapper.properties** deve ter:

```properties
distributionUrl=https\://services.gradle.org/distributions/gradle-8.14.3-bin.zip
```

Se tiver isso e o **Gradle JDK** estiver em **Java 17**, o build deve funcionar.
