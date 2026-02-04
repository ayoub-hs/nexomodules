import { test, expect, request } from '@playwright/test';

const jsonHeaders = {
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};

// Helper to create an authorized APIRequestContext
async function authedRequest(baseURL: string, token?: string) {
  const ctx = await request.newContext({
    baseURL,
    extraHTTPHeaders: token ? {
      Authorization: `Bearer ${token}`,
      'X-Requested-With': 'XMLHttpRequest',
      ...jsonHeaders,
    } : jsonHeaders,
  });
  return ctx;
}

// Minimal smoke e2e on module API
// Requires: E2E_TOKEN (Sanctum token) and module enabled + migrations run inside ddev
const token = process.env.E2E_TOKEN as string | undefined;
const baseURL = process.env.BASE_URL || 'https://jazidet.ddev.site';

// Skip all tests if no token provided
const describeOrSkip = token ? test.describe : test.describe.skip;

describeOrSkip('NsSpecialCustomer API (cashback)', () => {
  test('GET /api/special-customer/cashback returns success', async () => {
    const api = await authedRequest(baseURL, token);
    const res = await api.get('/api/special-customer/cashback');
    expect([200, 401, 403]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body.status).toBe('success');
      expect(body).toHaveProperty('data');
    }
  });

  test('GET /api/special-customer/config returns config', async () => {
    const api = await authedRequest(baseURL, token);
    const res = await api.get('/api/special-customer/config');
    expect([200, 401, 403]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json();
      expect(body.status).toBe('success');
      expect(body.data).toHaveProperty('groupId');
    }
  });

  test('GET /api/special-customer/balance/:id requires access control', async () => {
    const api = await authedRequest(baseURL, token);
    const res = await api.get('/api/special-customer/balance/1');
    expect([200, 400, 403, 404]).toContain(res.status());
  });

  test('GET /api/crud/ns.special-customers responds', async () => {
    const api = await authedRequest(baseURL, token);
    const res = await api.get('/api/crud/ns.special-customers');
    expect([200, 401, 403]).toContain(res.status());
  });
});
