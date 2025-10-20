# Token-Based Authentication Setup

## Overview
This backend uses **Laravel Sanctum in TOKEN mode** - pure token-based authentication.

**NO CSRF tokens, NO cookies, NO complications.**

---

## How It Works

### 1. **User Login**
- Frontend sends: `POST /api/v1/auth/admin/login` with `{ email, password }`
- Backend returns: `{ token: "1|xxxxx...", admin: {...} }`
- Frontend stores token in `localStorage`

### 2. **Authenticated Requests**
- Frontend sends token in header: `Authorization: Bearer 1|xxxxx...`
- Backend validates token via `auth:sanctum` middleware
- Works for ALL requests (GET, POST, PUT, DELETE)

### 3. **User Logout**
- Frontend sends: `POST /api/v1/auth/logout` with Authorization header
- Backend deletes the token
- Frontend removes token from `localStorage`

---

## Backend Configuration

### ✅ What's Configured

**1. Sanctum Token Authentication (`config/sanctum.php`)**
- Token expiration: null (never expires automatically)
- Tokens are database-stored and validated on each request

**2. CORS (`config/cors.php`)**
- Allows all origins: `'allowed_origins' => ['*']`
- No credentials needed: `'supports_credentials' => false`
- **Reason:** Token in header doesn't need CORS credentials

**3. Middleware (`bootstrap/app.php`)**
- Removed: `EnsureFrontendRequestsAreStateful` (no cookie auth)
- API routes use: `auth:sanctum` middleware
- Clean, simple token validation

**4. Routes (`routes/api.php`)**
- Public: `/auth/admin/login`, `/auth/employee/login`, etc.
- Protected: All routes with `auth:sanctum` middleware

---

## Environment Setup

### Local Development (`.env`)
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# or mysql for production-like setup

SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### Production (`.env`)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.woodfire.food

DB_CONNECTION=mysql
DB_HOST=your-production-db-host
DB_PORT=3306
DB_DATABASE=woodfire_production
DB_USERNAME=your-db-username
DB_PASSWORD=your-secure-db-password

SESSION_DRIVER=database
SESSION_LIFETIME=120
```

**Note:** Session config exists but NOT used for API auth (only for web routes if needed)

---

## API Response Format

### Success Response
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "admin": { ... },
    "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz1234567890"
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

## Security

### Token Storage
- Tokens stored in `personal_access_tokens` table
- Each token has: `tokenable_type`, `tokenable_id`, `name`, `token` (hashed)
- Validated on every request via Sanctum middleware

### Token Abilities (Optional)
Currently not using abilities - all tokens have full access to their user's routes.
Can be added later if needed:
```php
$token = $user->createToken('token-name', ['ability1', 'ability2']);
```

### Rate Limiting
- API throttle: `throttle:api` (default: 60 requests per minute)
- Can be customized in `app/Providers/RouteServiceProvider.php`

---

## Frontend Integration

### Login Flow
```javascript
// 1. Send login request
const response = await fetch('http://localhost:8000/api/v1/auth/admin/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    body: JSON.stringify({ email, password })
});

const data = await response.json();

// 2. Store token
if (data.success) {
    localStorage.setItem('admin_token', data.data.token);
}
```

### Authenticated Requests
```javascript
const token = localStorage.getItem('admin_token');

const response = await fetch('http://localhost:8000/api/v1/admin/dashboard', {
    method: 'GET',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`  // ← THIS IS ALL YOU NEED
    }
});
```

### Logout Flow
```javascript
const token = localStorage.getItem('admin_token');

await fetch('http://localhost:8000/api/v1/auth/logout', {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`
    }
});

// Remove token
localStorage.removeItem('admin_token');
```

---

## Testing

### Test Login (cURL)
```bash
curl -X POST http://localhost:8000/api/v1/auth/admin/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### Test Authenticated Request
```bash
curl -X GET http://localhost:8000/api/v1/admin/dashboard \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|YOUR_TOKEN_HERE"
```

---

## Troubleshooting

### Issue: 401 Unauthorized
**Cause:** Token missing, invalid, or expired (deleted)
**Solution:** 
- Check token exists in `localStorage`
- Check `Authorization` header is sent correctly
- Verify token exists in `personal_access_tokens` table

### Issue: 419 CSRF Token Mismatch
**Cause:** You're still using old CSRF/cookie code
**Solution:** This should NOT happen with token auth. If it does:
1. Clear browser cache/cookies
2. Verify `EnsureFrontendRequestsAreStateful` is removed from middleware
3. Don't send `credentials: 'include'` in fetch

### Issue: CORS Error
**Cause:** CORS misconfiguration or browser blocking
**Solution:**
- Verify backend is running
- Check `config/cors.php` has `'allowed_origins' => ['*']`
- Don't use `credentials: 'include'` in frontend

---

## Advantages of Token Auth

✅ **Works everywhere:** localhost, production, mobile apps  
✅ **No cookies:** no SameSite, Secure, Domain issues  
✅ **No CSRF:** tokens in headers, not cookies  
✅ **Simple:** just Authorization header  
✅ **Scalable:** stateless, works with load balancers  
✅ **Debuggable:** easy to test with cURL/Postman  

---

## Migration Notes

**From Cookie/CSRF Auth to Token Auth:**
- ✅ Backend: Removed `EnsureFrontendRequestsAreStateful` middleware
- ✅ Backend: Simplified CORS config
- ✅ Backend: Token creation already implemented correctly
- 🔄 Frontend: Need to remove CSRF cookie fetching
- 🔄 Frontend: Need to remove `credentials: 'include'`
- 🔄 Frontend: Keep Authorization header (already working)

---

## Support

For issues or questions, check:
1. Laravel Sanctum docs: https://laravel.com/docs/sanctum
2. This README
3. Backend logs: `storage/logs/laravel.log`

