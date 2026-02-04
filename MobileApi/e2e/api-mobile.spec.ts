import { test, expect, request } from '@playwright/test';

const baseURL = process.env.BASE_URL || 'http://jazidet.ddev.site';
const token = process.env.E2E_TOKEN as string | undefined;

async function authed(baseURL: string, token?: string) {
  return await request.newContext({
    baseURL,
    extraHTTPHeaders: token
      ? {
          Authorization: `Bearer ${token}`,
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        }
      : { Accept: 'application/json' },
  });
}

test.describe('MobileApi endpoints', () => {
  test('GET /api/mobile/sync/bootstrap responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/mobile/sync/bootstrap');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/mobile/sync/status responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/mobile/sync/status');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/mobile/sync/delta responds or requires since', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/mobile/sync/delta');
    expect([200, 400, 401, 403, 404]).toContain(res.status());
  });

  test('POST /api/mobile/products/search responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.post('/api/mobile/products/search', {
      data: { search: 'pr', limit: 5 },
    });
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/mobile/products/0 responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/mobile/products/0');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/mobile/orders responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/mobile/orders?limit=1');
    expect([200, 401, 403, 404]).toContain(res.status());
  });
});
