import { test, expect } from '@playwright/test';

/**
 * Mobile API Orders Integration Tests
 * 
 * Tests for order listing, order details, and order sync.
 */

const API_BASE = process.env.API_BASE_URL || 'http://localhost:8000';

test.describe('Mobile API - Orders', () => {
  let authToken: string;

  test.beforeAll(async ({ request }) => {
    // Login to get auth token
    const loginResponse = await request.post(`${API_BASE}/api/mobile/auth/login`, {
      data: {
        email: 'admin@example.com',
        password: 'password',
        device_name: 'Test Device'
      }
    });
    
    const loginData = await loginResponse.json();
    authToken = loginData.data.token;
  });

  test('GET /api/mobile/orders - should list orders', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(Array.isArray(data.data)).toBe(true);
  });

  test('GET /api/mobile/orders - should support pagination', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders?page=1&per_page=10`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(Array.isArray(data.data)).toBe(true);
  });

  test('GET /api/mobile/orders/{order} - should return order details', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders/1`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(data.data.id).toBe(1);
    expect(data.data.products).toBeDefined();
    expect(data.data.total).toBeDefined();
  });

  test('GET /api/mobile/orders/{order} - should handle non-existent order', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders/999999`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.status()).toBe(404);
  });

  test('GET /api/mobile/orders/sync - should sync orders', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders/sync`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
  });

  test('GET /api/mobile/orders - should require authentication', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders`);

    expect(response.status()).toBe(401);
  });

  test('GET /api/mobile/orders/{order} - should require authentication', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/orders/1`);

    expect(response.status()).toBe(401);
  });
});
