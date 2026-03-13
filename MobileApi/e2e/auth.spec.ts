import { test, expect } from '@playwright/test';

/**
 * Mobile API Authentication Integration Tests
 * 
 * Tests for login, logout, and permissions endpoints.
 */

const API_BASE = process.env.API_BASE_URL || 'http://localhost:8000';

test.describe('Mobile API - Authentication', () => {
  let authToken: string;

  test('POST /api/mobile/auth/login - should authenticate user and return token', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'password',
        device_name: 'Test Device'
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.token).toBeDefined();
    expect(data.data.user).toBeDefined();
    expect(data.data.user.email).toBe('admin@example.com');
    
    authToken = data.data.token;
  });

  test('POST /api/mobile/auth/login - should reject invalid credentials', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'wrongpassword',
        device_name: 'Test Device'
      }
    });

    expect(response.status()).toBe(422);
    
    const data = await response.json();
    expect(data.success).toBe(false);
  });

  test('POST /api/mobile/auth/login - should validate required fields', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com'
        // Missing password and device_name
      }
    });

    expect(response.status()).toBe(422);
    
    const data = await response.json();
    expect(data.success).toBe(false);
    expect(data.errors).toBeDefined();
  });

  test('GET /api/mobile/auth/permissions - should return user permissions', async ({ request }) => {
    // First login to get token
    const loginResponse = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'password',
        device_name: 'Test Device'
      }
    });
    
    const loginData = await loginResponse.json();
    const token = loginData.data.token;

    // Get permissions
    const response = await request.get(`${API_BASE}/api/mobile/auth/permissions`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.permissions).toBeDefined();
    expect(Array.isArray(data.data.permissions)).toBe(true);
    expect(data.data.roles).toBeDefined();
    expect(Array.isArray(data.data.roles)).toBe(true);
  });

  test('GET /api/mobile/auth/permissions - should require authentication', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/auth/permissions`);

    expect(response.status()).toBe(401);
  });

  test('GET /api/mobile/auth/me - should return current user info', async ({ request }) => {
    // First login to get token
    const loginResponse = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'password',
        device_name: 'Test Device'
      }
    });
    
    const loginData = await loginResponse.json();
    const token = loginData.data.token;

    // Get user info
    const response = await request.get(`${API_BASE}/api/mobile/auth/me`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data.id).toBeDefined();
    expect(data.data.name).toBeDefined();
    expect(data.data.email).toBeDefined();
    expect(data.data.roles).toBeDefined();
  });

  test('POST /api/mobile/auth/logout - should revoke token', async ({ request }) => {
    // First login to get token
    const loginResponse = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'password',
        device_name: 'Test Device'
      }
    });
    
    const loginData = await loginResponse.json();
    const token = loginData.data.token;

    // Logout
    const logoutResponse = await request.post(`${API_BASE}/api/mobile/auth/logout`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    expect(logoutResponse.ok()).toBeTruthy();
    
    const logoutData = await logoutResponse.json();
    expect(logoutData.success).toBe(true);
    expect(logoutData.message).toBe('Successfully logged out');

    // Try to use the token after logout
    const protectedResponse = await request.get(`${API_BASE}/api/mobile/auth/me`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    expect(protectedResponse.status()).toBe(401);
  });

  test('POST /api/mobile/auth/logout - should require authentication', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/auth/logout`);

    expect(response.status()).toBe(401);
  });
});
