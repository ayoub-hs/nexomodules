import { test, expect } from '@playwright/test';

/**
 * Mobile API Products Integration Tests
 * 
 * Tests for product search, barcode lookup, and product details.
 */

const API_BASE = process.env.API_BASE_URL || 'http://localhost:8000';

test.describe('Mobile API - Products', () => {
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

  test('POST /api/mobile/products/search - should search products', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/products/search`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      },
      data: {
        search: 'test'
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(Array.isArray(data.data)).toBe(true);
  });

  test('POST /api/mobile/products/search - should handle empty results', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/products/search`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      },
      data: {
        search: 'nonexistentproductxyz123'
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(Array.isArray(data.data)).toBe(true);
    expect(data.data.length).toBe(0);
  });

  test('GET /api/mobile/products/{id} - should return product details', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/products/1`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(data.data.id).toBe(1);
    expect(data.data.name).toBeDefined();
    expect(data.data.price).toBeDefined();
  });

  test('GET /api/mobile/products/{id} - should handle non-existent product', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/products/999999`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.status()).toBe(404);
  });

  test('GET /api/mobile/products/barcode/{barcode} - should find product by barcode', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/products/barcode/123456789`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(data.data.barcode).toBe('123456789');
  });

  test('GET /api/mobile/products/barcode/{barcode} - should handle non-existent barcode', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/products/barcode/000000000`, {
      headers: {
        'Authorization': `Bearer ${authToken}`
      }
    });

    expect(response.status()).toBe(404);
  });

  test('POST /api/mobile/products/search - should require authentication', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/products/search`, {
      data: {
        search: 'test'
      }
    });

    expect(response.status()).toBe(401);
  });

  test('GET /api/mobile/products/{id} - should require authentication', async ({ request }) => {
    const response = await request.get(`${API_BASE}/api/mobile/products/1`);

    expect(response.status()).toBe(401);
  });
});
