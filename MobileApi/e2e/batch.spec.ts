import { test, expect } from '@playwright/test';

/**
 * Mobile API Batch Submit Integration Tests
 * 
 * Tests for batch order submission.
 */

const API_BASE = process.env.API_BASE_URL || 'http://localhost:8000';

test.describe('Mobile API - Batch Submit', () => {
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

  test('POST /api/mobile/orders/batch - should submit batch of orders', async ({ request }) => {
    const batchData = {
      orders: [
        {
          customer_id: 1,
          products: [
            {
              product_id: 1,
              quantity: 2,
              unit_price: 10.00
            }
          ],
          payment_status: 'paid',
          total: 20.00
        },
        {
          customer_id: 1,
          products: [
            {
              product_id: 2,
              quantity: 1,
              unit_price: 15.00
            }
          ],
          payment_status: 'paid',
          total: 15.00
        }
      ]
    };

    const response = await request.post(`${API_BASE}/api/mobile/orders/batch`, {
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Content-Type': 'application/json'
      },
      data: batchData
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(data.data.processed).toBeDefined();
    expect(data.data.failed).toBeDefined();
  });

  test('POST /api/mobile/orders/batch - should handle empty batch', async ({ request }) => {
    const response = await request.post(`${API_BASE}/api/mobile/orders/batch`, {
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Content-Type': 'application/json'
      },
      data: {
        orders: []
      }
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    expect(data.data.processed).toBe(0);
  });

  test('POST /api/mobile/orders/batch - should validate order data', async ({ request }) => {
    const invalidBatchData = {
      orders: [
        {
          // Missing required fields
          products: []
        }
      ]
    };

    const response = await request.post(`${API_BASE}/api/mobile/orders/batch`, {
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Content-Type': 'application/json'
      },
      data: invalidBatchData
    });

    expect(response.status()).toBe(422);
    
    const data = await response.json();
    expect(data.success).toBe(false);
    expect(data.errors).toBeDefined();
  });

  test('POST /api/mobile/orders/batch - should handle partial failures', async ({ request }) => {
    const mixedBatchData = {
      orders: [
        {
          customer_id: 1,
          products: [
            {
              product_id: 1,
              quantity: 2,
              unit_price: 10.00
            }
          ],
          payment_status: 'paid',
          total: 20.00
        },
        {
          customer_id: 999999, // Non-existent customer
          products: [
            {
              product_id: 2,
              quantity: 1,
              unit_price: 15.00
            }
          ],
          payment_status: 'paid',
          total: 15.00
        }
      ]
    };

    const response = await request.post(`${API_BASE}/api/mobile/orders/batch`, {
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Content-Type': 'application/json'
      },
      data: mixedBatchData
    });

    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    // Should have some processed and some failed
    expect(data.data.processed + data.data.failed).toBe(2);
  });

  test('POST /api/mobile/orders/batch - should require authentication', async ({ request }) => {
    const batchData = {
      orders: [
        {
          customer_id: 1,
          products: [
            {
              product_id: 1,
              quantity: 2,
              unit_price: 10.00
            }
          ],
          payment_status: 'paid',
          total: 20.00
        }
      ]
    };

    const response = await request.post(`${API_BASE}/api/mobile/orders/batch`, {
      headers: {
        'Content-Type': 'application/json'
      },
      data: batchData
    });

    expect(response.status()).toBe(401);
  });

  test('POST /api/mobile/orders/batch - should respect rate limiting', async ({ request }) => {
    // Make multiple rapid requests to test rate limiting
    const batchData = {
      orders: [
        {
          customer_id: 1,
          products: [
            {
              product_id: 1,
              quantity: 1,
              unit_price: 10.00
            }
          ],
          payment_status: 'paid',
          total: 10.00
        }
      ]
    };

    const requests = [];
    for (let i = 0; i < 25; i++) {
      requests.push(
        request.post(`${API_BASE}/api/mobile/orders/batch`, {
          headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json'
          },
          data: batchData
        })
      );
    }

    const responses = await Promise.all(requests);
    const rateLimitedResponses = responses.filter(r => r.status() === 429);
    
    // At least some requests should be rate limited
    expect(rateLimitedResponses.length).toBeGreaterThan(0);
  });
});
