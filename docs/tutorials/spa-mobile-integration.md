---
title: "SPA and Mobile Integration"
description: "Token storage, refresh strategy, and logout flows for first-party clients."
---

# SPA and Mobile Integration

First-party clients (SPA, mobile app, desktop app) use the package's first-party JWT auth flow.

## Token Format

The `TokenResponse::passportCompatible()` helper returns:

```json
{
    "token_type": "Bearer",
    "expires_in": 900,
    "access_token": "eyJ...",
    "refresh_token": "abc123.def456"
}
```

- `access_token`: short-lived JWT (default 15 minutes)
- `refresh_token`: opaque `id.secret` pair for rotation
- `expires_in`: seconds until access token expiry

## Client-Side Token Storage

### SPA (Browser)

| Storage | Pros | Cons |
|---|---|---|
| httpOnly cookie | XSS-safe, automatic send | CSRF protection needed, harder to read expiry |
| memory (variable) | Not persisted to disk | Lost on page refresh |
| localStorage | Survives refresh | XSS-vulnerable |

**Recommended SPA approach:** Store tokens in memory, use httpOnly cookie for the refresh token, or use a short-lived access token with silent refresh via an iframe/service worker.

```javascript
// In-memory storage example
let accessToken = null;
let refreshToken = null;

async function login(email, password) {
    const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    const data = await res.json();
    accessToken = data.token.access_token;
    refreshToken = data.token.refresh_token;
}

async function refresh() {
    const res = await fetch('/api/auth/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refreshToken }),
    });
    const data = await res.json();
    accessToken = data.token.access_token;
    refreshToken = data.token.refresh_token;
}
```

### Mobile (iOS, Android)

Use the OS secure keystore (iOS Keychain, Android EncryptedSharedPreferences). Never store tokens in plaintext SharedPreferences or UserDefaults.

```swift
// iOS — store in Keychain
let tokenData = Data(accessToken.utf8)
let query = [
    kSecClass: kSecClassGenericPassword,
    kSecAttrAccount: "access_token",
    kSecValueData: tokenData,
] as CFDictionary
SecItemAdd(query, nil)
```

## Refresh Strategy

Access tokens expire quickly (default 15 minutes). Use the refresh token to get a new pair before expiry.

**Recommended approach:** Intercept 401 responses and attempt a refresh before failing.

```javascript
let isRefreshing = false;
let refreshQueue = [];

async function fetchWithAuth(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: { ...options.headers, Authorization: `Bearer ${accessToken}` },
    });

    if (res.status === 401 && refreshToken) {
        if (!isRefreshing) {
            isRefreshing = true;
            try {
                await refresh();
                isRefreshing = false;
                refreshQueue.forEach(cb => cb());
                refreshQueue = [];
            } catch {
                isRefreshing = false;
                refreshQueue = [];
                redirectToLogin();
                return;
            }
        }

        // Queue requests while refresh is in progress
        return new Promise((resolve) => {
            refreshQueue.push(() => resolve(fetchWithAuth(url, options)));
        });
    }

    return res;
}
```

## Logout

```javascript
async function logout() {
    await fetch('/api/auth/logout', {
        method: 'POST',
        headers: { Authorization: `Bearer ${accessToken}` },
    });
    accessToken = null;
    refreshToken = null;
    redirectToLogin();
}
```

## Handling Token Expiry on Mobile

On mobile, schedule a token refresh before the access token expires:

```kotlin
// Android — schedule refresh before expiry
val expiresInSeconds = data.token.expires_in
Handler(Looper.getMainLooper()).postDelayed({
    refreshToken()
}, (expiresInSeconds - 60) * 1000L) // refresh 1 minute before expiry
```

## Security Notes

- Use HTTPS in production. Tokens are bearer credentials.
- Never log tokens or store them in plaintext.
- Short access token TTL reduces the impact of token leakage.
- httpOnly cookies prevent XSS from reading tokens but require CSRF protection.
- Refresh tokens rotate on each use. Old refresh tokens are rejected.
