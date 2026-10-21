# Security Policy

## Reporting Vulnerabilities

Report security vulnerabilities privately via [GitHub Security Advisories](https://github.com/jeffpaul/rsl-wp/security/advisories).

## Data Storage

The plugin stores the following data types:
- **License configurations** - Content licensing terms and metadata
- **OAuth client credentials** - Hashed client secrets (using wp_hash_password)
- **JWT tokens** - JTI tracking for token revocation (no secrets stored)
- **Session data** - Temporary payment flow state

**Note:** Client secrets are properly hashed and never stored in plain text.

## Security Measures

- **Output Sanitization** - All XML output is properly escaped for attributes and text nodes
- **Authentication** - OAuth 2.0 client credentials flow with proper secret validation
- **Authorization** - JWT-based token validation with expiration and revocation support
- **Input Validation** - All user inputs are sanitized and validated before storage
- **Capability Checks** - Admin functions require proper WordPress capabilities

## Authentication Behavior

- 401 challenges are issued to crawler user-agents for protected content
- OAuth tokens are validated against stored client credentials
- JWT tokens include proper expiration and revocation checking