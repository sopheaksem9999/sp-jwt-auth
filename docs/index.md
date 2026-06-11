---
layout: home

hero:
  name: "SP JWT Auth Package"
  text: "JWT Authentication Package for Laravel"
  tagline: "A modular Laravel package for first-party JWT auth, account security, API keys, external identity, and optional OAuth server mode."
  actions:
    - theme: brand
      text: "Get Started"
      link: "/getting-started/overview"
    - theme: alt
      text: "Quick Start"
      link: "/getting-started/quick-start"

features:
  - title: "Core JWT"
    details: "Laravel sp-jwt guard, signed access tokens, persisted jti rows, opaque rotating refresh tokens, scopes, claims, and revocation."
    link: "/guide/core-jwt"
  - title: "Account Security"
    details: "MFA challenge broker, hashed OTP codes, email verification tokens, and password reset tokens with app-owned delivery."
    link: "/guide/mfa-otp"
  - title: "SaaS Integrations"
    details: "Scoped API keys with public-id lookup, hashed secret validation, rotation, revocation, and middleware."
    link: "/guide/api-keys"
  - title: "External Identity"
    details: "Socialite/OIDC-style identity normalization and storage while apps own account linking policy."
    link: "/guide/external-identity"
  - title: "OAuth Server"
    details: "Optional third-party OAuth clients, authorization-code + PKCE, client credentials, refresh tokens, introspection, and resource middleware."
    link: "/guide/oauth-server"
  - title: "Events and Hooks"
    details: "Lifecycle events and hook points for app audit logging, policy checks, and custom token context rules."
    link: "/guide/events-hooks"
---
